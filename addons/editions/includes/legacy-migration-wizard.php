<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Step-by-step wp-admin wizard for moving legacy e-paper posts onto the
 * secure bday_edition system, for production environments that don't have
 * WP-CLI/SSH access the way staging does — same underlying logic as
 * `wp bday migrate-legacy-editions` (legacy-migration-core.php), driven from
 * the browser instead of a terminal. Batches over AJAX (a few posts per
 * request) so a large legacy library doesn't hit PHP's request timeout.
 *
 * Deliberately does NOT delete any theme files as part of the wizard — the
 * final step is a checklist for a developer to action as a normal, git-
 * tracked deploy, not something this page does to the code it's currently
 * running from. Everything else (migrating content, toggling the legacy
 * add-on off) is safe, reversible, and left to run from here.
 */

const BDAY_EDITION_LEGACY_WIZARD_NONCE = 'bday_edition_legacy_wizard';

add_action(
	'admin_menu',
	static function (): void {
		add_submenu_page(
			'edit.php?post_type=bday_edition',
			'Legacy Migration Wizard',
			'Legacy Migration',
			'edit_others_posts',
			'bday-edition-legacy-wizard',
			'bday_edition_render_legacy_wizard_page'
		);
	}
);

function bday_edition_render_legacy_wizard_page(): void {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		wp_die( esc_html__( 'You do not have permission to do this.' ) );
	}

	$nonce           = wp_create_nonce( BDAY_EDITION_LEGACY_WIZARD_NONCE );
	$addon_enabled   = class_exists( 'Bday_Addon_Loader' ) && Bday_Addon_Loader::is_enabled( 'e-edition' );
	$can_toggle_addon = current_user_can( 'manage_options' );
	?>
	<div class="wrap bday-legacy-wizard">
		<h1>Legacy E-Paper Migration Wizard</h1>
		<p>Moves legacy e-paper posts (category <code>e-paper</code>/<code>e-edition</code>, PDF pasted as a public URL — no login or subscription check on that path today) onto the same secure, signed-download system already used by E-Editions. Safe to re-run at any point; already-migrated posts are skipped automatically.</p>

		<ol class="bday-legacy-wizard__steps">
			<li id="bday-wizard-step-0" class="bday-legacy-wizard__step">
				<h2>0. Check connections</h2>
				<p>Confirms the full upload path works before migrating anything real: WordPress reaching subscription-service (base URL + API key), and subscription-service reaching your S3 bucket (an actual write-then-read test, not just checking the credentials are present). Run this first if editions aren't showing their PDF.</p>
				<button type="button" class="button button-primary" id="bday-wizard-storage-test">Test AWS/S3 connection</button>
				<div id="bday-wizard-storage-result" class="bday-legacy-wizard__result" aria-live="polite"></div>
			</li>

			<li id="bday-wizard-step-1" class="bday-legacy-wizard__step">
				<h2>1. Scan</h2>
				<p>Counts legacy posts and how many still need migrating. Makes no changes.</p>
				<button type="button" class="button button-primary" id="bday-wizard-scan">Scan legacy content</button>
				<div id="bday-wizard-scan-result" class="bday-legacy-wizard__result" aria-live="polite"></div>
			</li>

			<li id="bday-wizard-step-2" class="bday-legacy-wizard__step">
				<h2>2. Migrate</h2>
				<p>Fetches each legacy post's PDF and uploads it through the same secure path E-Editions already uses, then creates and publishes a matching Edition. Run a dry run first to preview without making changes.</p>
				<button type="button" class="button" id="bday-wizard-dry-run">Dry run</button>
				<button type="button" class="button button-primary" id="bday-wizard-migrate">Run migration</button>
				<div class="bday-legacy-wizard__progress" id="bday-wizard-progress" style="display:none;">
					<div class="bday-legacy-wizard__progress-bar"><div class="bday-legacy-wizard__progress-fill" id="bday-wizard-progress-fill"></div></div>
					<p id="bday-wizard-progress-label"></p>
				</div>
				<div id="bday-wizard-summary" class="bday-legacy-wizard__result" aria-live="polite"></div>
				<table class="widefat striped" id="bday-wizard-log-table" style="display:none;margin-top:1em;">
					<thead><tr><th style="width:6em;">Post</th><th>Title</th><th style="width:10em;">Status</th><th>Detail</th></tr></thead>
					<tbody id="bday-wizard-log-body"></tbody>
				</table>
			</li>

			<li id="bday-wizard-step-3" class="bday-legacy-wizard__step">
				<h2>3. Verify</h2>
				<p>Before disabling the legacy viewer, confirm:</p>
				<ul style="list-style:disc;margin-left:2em;">
					<li>Step 2's summary shows no unexplained failures — every legacy post is either migrated or genuinely had no PDF link.</li>
					<li>Old legacy URLs already redirect to their new Edition permalinks (this is automatic — the redirect ships as part of the E-Edition add-on and needs no action here).</li>
					<li>A migrated edition's "Read Edition" button resolves a signed download for a logged-in, entitled subscriber, and is blocked for a guest. Check a couple from <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=bday_edition' ) ); ?>">All Editions</a>.</li>
				</ul>
			</li>

			<li id="bday-wizard-step-4" class="bday-legacy-wizard__step">
				<h2>4. Retire the legacy viewer</h2>
				<p>Only do this once step 3 is confirmed. Disabling stops the insecure PDF embed from rendering and stops editors from creating new legacy entries' render path — the legacy post data itself is untouched and nothing is deleted.</p>
				<p>Current status: <strong id="bday-wizard-addon-status"><?php echo $addon_enabled ? 'Enabled (insecure viewer is still live)' : 'Disabled'; ?></strong></p>
				<?php if ( $can_toggle_addon ) : ?>
					<button type="button" class="button button-primary" id="bday-wizard-disable-addon" <?php disabled( ! $addon_enabled ); ?>>Disable legacy PDF viewer</button>
					<button type="button" class="button" id="bday-wizard-enable-addon" <?php disabled( $addon_enabled ); ?>>Re-enable (rollback)</button>
				<?php else : ?>
					<p><em>Requires an administrator (manage_options) to toggle this from here.</em></p>
				<?php endif; ?>
			</li>

			<li id="bday-wizard-step-5" class="bday-legacy-wizard__step">
				<h2>5. Code cleanup (developer, deploy-time — not automated here)</h2>
				<p>This page deliberately does not delete theme files — a page shouldn't self-modify the code it's currently executing, and this kind of change belongs in a normal, reviewed, git-tracked deploy rather than a runtime action. Once step 4 has been live for a bake period with no issues, a developer should:</p>
				<ul style="list-style:disc;margin-left:2em;">
					<li>Delete <code>addons/e-edition/</code> (whole add-on folder).</li>
					<li>Delete <code>template-parts/single-edition.php</code>, <code>category-e-edition.php</code>, <code>templates/todays-epaper.php</code>.</li>
					<li>Simplify <code>single.php</code> back to unconditionally using <code>template-parts/single-default.php</code>.</li>
					<li>Remove the "PDF Meta" box and its two fields from <code>core/editorial-meta.php</code> — this is what stops editors from creating new insecure entries.</li>
					<li>Leave the historical <code>_bday_pdf_preview_link</code>/<code>_bday_pdf_link</code> post meta and the redirect (<code>addons/e-edition/includes/legacy-redirect.php</code>) in place permanently, so old bookmarked/indexed links keep resolving.</li>
				</ul>
			</li>
		</ol>
	</div>

	<style>
		.bday-legacy-wizard__steps { list-style: none; margin: 0; padding: 0; counter-reset: none; }
		.bday-legacy-wizard__step { background: #fff; border: 1px solid #dcdcde; border-radius: 4px; padding: 1.25em 1.5em; margin-bottom: 1em; }
		.bday-legacy-wizard__step h2 { margin-top: 0; }
		.bday-legacy-wizard__result { margin-top: 1em; }
		.bday-legacy-wizard__progress { margin-top: 1em; max-width: 40em; }
		.bday-legacy-wizard__progress-bar { background: #dcdcde; border-radius: 3px; height: 10px; overflow: hidden; }
		.bday-legacy-wizard__progress-fill { background: #2271b1; height: 100%; width: 0%; transition: width .2s ease; }
		#bday-wizard-log-table td, #bday-wizard-log-table th { vertical-align: top; }
		.bday-legacy-wizard__status-migrated, .bday-legacy-wizard__status-would_migrate { color: #007017; }
		.bday-legacy-wizard__status-failed { color: #d63638; font-weight: 600; }
		.bday-legacy-wizard__status-no_link, .bday-legacy-wizard__status-already_migrated { color: #646970; }
	</style>

	<script>
	( function () {
		var ajaxUrl   = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
		var nonce     = <?php echo wp_json_encode( $nonce ); ?>;
		var batchSize = 3;

		function post( action, data ) {
			var body = new URLSearchParams( Object.assign( { action: action, _ajax_nonce: nonce }, data || {} ) );
			return fetch( ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) { return r.json(); } );
		}

		var storageTestBtn = document.getElementById( 'bday-wizard-storage-test' );
		if ( storageTestBtn ) {
			storageTestBtn.addEventListener( 'click', function () {
				storageTestBtn.disabled = true;
				var out = document.getElementById( 'bday-wizard-storage-result' );
				out.textContent = 'Testing…';
				post( 'bday_edition_legacy_storage_test' ).then( function ( res ) {
					storageTestBtn.disabled = false;
					var ok = res.success && res.data && res.data.ok;
					out.innerHTML = '<span class="bday-legacy-wizard__status-' + ( ok ? 'migrated' : 'failed' ) + '">'
						+ ( ok ? '✓ ' : '✗ ' )
						+ escapeHtml( ( res.data && res.data.message ) || 'Unknown error.' )
						+ '</span>';
				} );
			} );
		}

		var scanBtn = document.getElementById( 'bday-wizard-scan' );
		if ( scanBtn ) {
			scanBtn.addEventListener( 'click', function () {
				scanBtn.disabled = true;
				var out = document.getElementById( 'bday-wizard-scan-result' );
				out.textContent = 'Scanning…';
				post( 'bday_edition_legacy_scan' ).then( function ( res ) {
					scanBtn.disabled = false;
					if ( ! res.success ) {
						out.textContent = 'Scan failed: ' + ( res.data && res.data.message ? res.data.message : 'unknown error' );
						return;
					}
					var d = res.data;
					out.innerHTML = '<strong>' + d.total + '</strong> legacy post(s) found — '
						+ '<strong>' + d.pending + '</strong> ready to migrate, '
						+ '<strong>' + d.already + '</strong> already migrated, '
						+ '<strong>' + d.no_link + '</strong> have no PDF link set (need manual editorial follow-up).';
				} );
			} );
		}

		function runMigration( dryRun ) {
			var progress   = document.getElementById( 'bday-wizard-progress' );
			var fill       = document.getElementById( 'bday-wizard-progress-fill' );
			var label      = document.getElementById( 'bday-wizard-progress-label' );
			var summary    = document.getElementById( 'bday-wizard-summary' );
			var table      = document.getElementById( 'bday-wizard-log-table' );
			var tbody      = document.getElementById( 'bday-wizard-log-body' );
			var dryBtn     = document.getElementById( 'bday-wizard-dry-run' );
			var migrateBtn = document.getElementById( 'bday-wizard-migrate' );

			dryBtn.disabled = true;
			migrateBtn.disabled = true;
			progress.style.display = '';
			table.style.display = '';
			tbody.innerHTML = '';
			summary.textContent = '';
			fill.style.width = '0%';
			label.textContent = 'Starting…';

			var counts = { migrated: 0, would_migrate: 0, no_link: 0, failed: 0, already_migrated: 0 };
			var offset = 0;

			function step() {
				post( 'bday_edition_legacy_migrate_batch', { offset: offset, batch_size: batchSize, dry_run: dryRun ? '1' : '' } )
					.then( function ( res ) {
						if ( ! res.success ) {
							label.textContent = 'Failed: ' + ( res.data && res.data.message ? res.data.message : 'unknown error' );
							dryBtn.disabled = false;
							migrateBtn.disabled = false;
							return;
						}
						var d = res.data;
						d.results.forEach( function ( r ) {
							counts[ r.status ] = ( counts[ r.status ] || 0 ) + 1;
							var tr = document.createElement( 'tr' );
							tr.innerHTML = '<td>#' + r.id + '</td><td>' + escapeHtml( r.title ) + '</td>'
								+ '<td class="bday-legacy-wizard__status-' + r.status + '">' + r.status.replace( /_/g, ' ' ) + '</td>'
								+ '<td>' + escapeHtml( r.message ) + '</td>';
							tbody.appendChild( tr );
						} );
						offset = d.next_offset;
						label.textContent = offset + ' post(s) processed…';
						// found_total tracks the scan's total when known; otherwise the
						// bar just grows toward "done" without a fixed denominator.
						fill.style.width = d.done ? '100%' : Math.min( 95, offset * 5 ) + '%';

						if ( d.done ) {
							label.textContent = ( dryRun ? 'Dry run complete — ' : 'Migration complete — ' ) + offset + ' post(s) processed.';
							summary.innerHTML = ( dryRun ? 'Would migrate: ' : 'Migrated: ' ) + '<strong>' + ( counts.migrated + counts.would_migrate ) + '</strong>, '
								+ 'no link: <strong>' + counts.no_link + '</strong>, '
								+ 'failed: <strong>' + counts.failed + '</strong>, '
								+ 'already migrated: <strong>' + counts.already_migrated + '</strong>.';
							dryBtn.disabled = false;
							migrateBtn.disabled = false;
							return;
						}
						step();
					} );
			}
			step();
		}

		function escapeHtml( s ) {
			var div = document.createElement( 'div' );
			div.textContent = s == null ? '' : String( s );
			return div.innerHTML;
		}

		var dryRunBtn = document.getElementById( 'bday-wizard-dry-run' );
		if ( dryRunBtn ) {
			dryRunBtn.addEventListener( 'click', function () { runMigration( true ); } );
		}
		var migrateBtn = document.getElementById( 'bday-wizard-migrate' );
		if ( migrateBtn ) {
			migrateBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( 'This will fetch and migrate legacy PDFs for real. Continue?' ) ) {
					return;
				}
				runMigration( false );
			} );
		}

		function toggleAddon( enable, btn, otherBtn ) {
			btn.disabled = true;
			post( 'bday_edition_legacy_toggle_addon', { enable: enable ? '1' : '' } ).then( function ( res ) {
				if ( ! res.success ) {
					window.alert( 'Failed: ' + ( res.data && res.data.message ? res.data.message : 'unknown error' ) );
					btn.disabled = false;
					return;
				}
				document.getElementById( 'bday-wizard-addon-status' ).textContent = res.data.enabled ? 'Enabled (insecure viewer is still live)' : 'Disabled';
				btn.disabled = res.data.enabled === enable ? true : false;
				if ( otherBtn ) { otherBtn.disabled = ! btn.disabled; }
			} );
		}

		var disableBtn = document.getElementById( 'bday-wizard-disable-addon' );
		var enableBtn  = document.getElementById( 'bday-wizard-enable-addon' );
		if ( disableBtn ) {
			disableBtn.addEventListener( 'click', function () {
				if ( ! window.confirm( 'Disable the legacy PDF viewer now? Make sure step 3 has been verified first.' ) ) {
					return;
				}
				toggleAddon( false, disableBtn, enableBtn );
			} );
		}
		if ( enableBtn ) {
			enableBtn.addEventListener( 'click', function () {
				toggleAddon( true, enableBtn, disableBtn );
			} );
		}
	} )();
	</script>
	<?php
}

add_action(
	'wp_ajax_bday_edition_legacy_storage_test',
	static function (): void {
		check_ajax_referer( BDAY_EDITION_LEGACY_WIZARD_NONCE );
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$base_url = (string) get_option( 'aero_paywall_api_base_url', '' );
		$api_key  = (string) get_option( 'aero_paywall_api_key', '' );
		if ( '' === $base_url || '' === $api_key ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'message' => 'The Aero Paywall API base URL and/or API key aren\'t set yet — configure those first (Settings → Connection tab), then re-run this test.',
				)
			);
		}

		// Reaching subscription-service at all (any 2xx/4xx response, not a
		// connection failure) already confirms the WordPress-to-backend leg
		// works; only the response body (or a non-2xx/4xx status) tells us
		// about the backend-to-S3 leg, which is what the request actually
		// tests server-side via PdfStorageService::testConnection().
		$response = wp_remote_get(
			rtrim( $base_url, '/' ) . '/connector/storage-status',
			array(
				'timeout' => 15,
				'headers' => array( 'X-Api-Key' => $api_key ),
			)
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'message' => 'Could not reach subscription-service at "' . $base_url . '": ' . $response->get_error_message() . ' — check the base URL is correct and reachable from this server.',
				)
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( 401 === $status || 403 === $status ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'message' => 'subscription-service rejected the request (HTTP ' . $status . ') — the API key doesn\'t match CONNECTOR_API_KEY on that service.',
				)
			);
		}
		if ( 200 !== $status ) {
			wp_send_json_success(
				array(
					'ok'      => false,
					'message' => 'subscription-service returned an unexpected HTTP ' . $status . ' — check its logs for what /connector/storage-status hit.',
				)
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$body = is_array( $data['data'] ?? null ) ? $data['data'] : array();

		wp_send_json_success(
			array(
				'ok'      => ! empty( $body['ok'] ),
				'message' => (string) ( $body['message'] ?? 'subscription-service returned no message.' ),
			)
		);
	}
);

add_action(
	'wp_ajax_bday_edition_legacy_scan',
	static function (): void {
		check_ajax_referer( BDAY_EDITION_LEGACY_WIZARD_NONCE );
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$posts   = bday_edition_get_legacy_posts();
		$total   = count( $posts );
		$no_link = 0;
		$already = 0;
		foreach ( $posts as $legacy_post ) {
			$existing_id = (int) get_post_meta( $legacy_post->ID, '_bday_migrated_to_edition_id', true );
			if ( $existing_id && get_post_status( $existing_id ) ) {
				++$already;
				continue;
			}
			$link = trim( (string) get_post_meta( $legacy_post->ID, '_bday_pdf_preview_link', true ) );
			if ( '' === $link ) {
				++$no_link;
			}
		}

		wp_send_json_success(
			array(
				'total'   => $total,
				'no_link' => $no_link,
				'already' => $already,
				'pending' => $total - $no_link - $already,
			)
		);
	}
);

add_action(
	'wp_ajax_bday_edition_legacy_migrate_batch',
	static function (): void {
		check_ajax_referer( BDAY_EDITION_LEGACY_WIZARD_NONCE );
		if ( ! current_user_can( 'edit_others_posts' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied.' ), 403 );
		}

		$offset     = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
		$batch_size = isset( $_POST['batch_size'] ) ? max( 1, min( 10, (int) $_POST['batch_size'] ) ) : 3;
		$dry_run    = ! empty( $_POST['dry_run'] );

		$posts   = bday_edition_get_legacy_posts( $batch_size, $offset );
		$results = array();
		foreach ( $posts as $legacy_post ) {
			$result    = bday_edition_migrate_one_legacy_post( $legacy_post, $dry_run );
			$results[] = array(
				'id'      => $legacy_post->ID,
				'title'   => $legacy_post->post_title,
				'status'  => $result['status'],
				'message' => $result['message'],
			);
		}

		wp_send_json_success(
			array(
				'results'     => $results,
				'done'        => count( $posts ) < $batch_size,
				'next_offset' => $offset + count( $posts ),
			)
		);
	}
);

add_action(
	'wp_ajax_bday_edition_legacy_toggle_addon',
	static function (): void {
		check_ajax_referer( BDAY_EDITION_LEGACY_WIZARD_NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permission denied — an administrator must do this.' ), 403 );
		}
		if ( ! class_exists( 'Bday_Addon_Loader' ) ) {
			wp_send_json_error( array( 'message' => 'Add-on loader unavailable.' ), 500 );
		}

		$enable = ! empty( $_POST['enable'] );
		$states = Bday_Addon_Loader::states();
		$states['e-edition'] = $enable;
		Bday_Addon_Loader::save_states( $states );

		wp_send_json_success( array( 'enabled' => $enable ) );
	}
);
