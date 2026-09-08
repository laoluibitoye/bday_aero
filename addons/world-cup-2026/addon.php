<?php
/**
 * Addon Name: World Cup 2026
 * Addon Slug: world-cup-2026
 * Description: World Cup 2026 fixtures and scores widget.
 * Cache Namespace: world_cup
 * Settings Tab: World Cup 2026
 * Default: off
 *
 * Seasonal add-on: promo marquee shortcode, a today's-fixtures widget
 * fetching from a third-party API (client-side, so no server load), and
 * the bracket-prediction form handler (writes to a CSV in uploads/,
 * unchanged behavior). Disabled by default — costs nothing off-season
 * since this file is never loaded while the add-on is off.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode(
	'worldcup_marquee',
	static function (): string {
		ob_start();
		?>
		<a href="<?php echo esc_url( home_url( '/world-cup-2026/' ) ); ?>" class="bday-wc-marquee">
			<span class="bday-wc-marquee__content">🏆 WORLD CUP 2026 IS LIVE! CLICK HERE TO VIEW REAL-TIME FIXTURES, RESULTS, AND PLAY THE INTERACTIVE BRACKET PREDICTOR! ⚽</span>
		</a>
		<?php
		return (string) ob_get_clean();
	}
);

add_shortcode(
	'wc_todays_fixtures',
	static function (): string {
		ob_start();
		?>
		<div class="bday-wc-fixtures" data-api="https://worldcup26.ir/get/games" data-link="<?php echo esc_url( home_url( '/world-cup-2026/' ) ); ?>">
			<div class="bday-wc-fixtures__loading">Loading fixtures…</div>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function () {
			// This widget's data comes from a third-party API
			// (worldcup26.ir, see data-api above) this theme doesn't
			// control — every field from it is escaped before it ever
			// touches innerHTML, so a compromised or malicious response
			// can only ever render as inert text, never as markup/script.
			function escHtml(s) {
				return String(s).replace(/[&<>"']/g, function (c) {
					return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
				});
			}
			document.querySelectorAll('.bday-wc-fixtures').forEach(function (container) {
				fetch(container.dataset.api)
					.then(function (r) { return r.json(); })
					.then(function (data) {
						if (!data || !data.games) return;
						var today = new Date();
						var games = data.games.filter(function (g) {
							if (!g.local_date) return false;
							var p = g.local_date.split(' ')[0].split('/');
							if (p.length !== 3) return false;
							var d = new Date(p[2], p[0] - 1, p[1]);
							return d.toDateString() === today.toDateString();
						});
						if (!games.length) {
							container.innerHTML = '<p class="bday-wc-fixtures__empty">No matches scheduled for today.</p>';
							return;
						}
						var html = games.map(function (g) {
							var home = escHtml(g.home_team_name_en || g.home_team_label || 'TBD');
							var away = escHtml(g.away_team_name_en || g.away_team_label || 'TBD');
							var score = escHtml(
								g.finished === 'TRUE' || (g.time_elapsed !== 'notstarted' && g.time_elapsed !== 'finished')
									? g.home_score + ' - ' + g.away_score
									: (g.local_time || 'VS')
							);
							return '<a class="bday-wc-fixture" href="' + container.dataset.link + '"><span>' + home + '</span><strong>' + score + '</strong><span>' + away + '</span></a>';
						}).join('');
						container.innerHTML = html + '<a class="bday-wc-fixtures__cta" href="' + container.dataset.link + '">Predict Matches</a>';
					})
					.catch(function () {
						container.innerHTML = '<p class="bday-wc-fixtures__error">Failed to load fixtures.</p>';
					});
			});
		});
		</script>
		<?php
		return (string) ob_get_clean();
	}
);

add_action( 'wp_ajax_wc_submit_prediction', 'bday_wc_prediction_handler' );
add_action( 'wp_ajax_nopriv_wc_submit_prediction', 'bday_wc_prediction_handler' );

/**
 * Where prediction submissions (real PII: name/email/phone) are stored.
 * Deliberately NOT a fixed name directly under uploads/ — that's a public,
 * web-served directory, so a guessable filename there is a public PII
 * leak. Two independent layers instead: a dedicated subdirectory locked
 * down with .htaccess + index.php (works on this stack's Apache; kept as
 * defense-in-depth, not the only layer, since a different host might not
 * honor .htaccess), and a filename derived from this site's own auth salt
 * so it isn't guessable even without that lock.
 */
function bday_wc_predictions_dir(): string {
	$upload_dir = wp_upload_dir();
	$dir        = $upload_dir['basedir'] . '/private/world-cup-2026';

	if ( ! file_exists( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		file_put_contents( $dir . '/.htaccess', "Require all denied\ndeny from all\n" );
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" );
	}

	return $dir;
}

function bday_wc_predictions_file(): string {
	$token = substr( hash_hmac( 'sha256', 'world-cup-2026-predictions', wp_salt( 'auth' ) ), 0, 24 );
	return bday_wc_predictions_dir() . '/predictions-' . $token . '.csv';
}

/**
 * check_ajax_referer() requires a 'nonce' field carrying
 * wp_create_nonce('bday_wc_predict') — whichever prediction-form markup
 * ends up calling this endpoint needs to send that nonce (e.g. as a hidden
 * field, or a data attribute read into the POST body), the same way every
 * other AJAX handler in this theme is called. Until then this correctly
 * rejects every request instead of accepting unauthenticated submissions.
 */
function bday_wc_prediction_handler(): void {
	check_ajax_referer( 'bday_wc_predict', 'nonce' );

	$name  = sanitize_text_field( wp_unslash( $_POST['pred_name'] ?? '' ) );
	$email = sanitize_email( wp_unslash( $_POST['pred_email'] ?? '' ) );
	$phone = sanitize_text_field( wp_unslash( $_POST['pred_phone'] ?? '' ) );

	if ( '' === $name || '' === $email || '' === $phone ) {
		wp_send_json_error( 'Missing required fields.' );
	}

	$csv_file = bday_wc_predictions_file();
	$is_new   = ! file_exists( $csv_file );

	$fp = fopen( $csv_file, 'a' );
	if ( false === $fp ) {
		wp_send_json_error( 'Could not save prediction.' );
	}
	if ( $is_new ) {
		fputcsv( $fp, array( 'Name', 'Email', 'Phone Number', 'Semi-Finalist 1', 'Semi-Finalist 2', 'Semi-Finalist 3', 'Semi-Finalist 4', 'Submission Date' ) );
	}
	fputcsv(
		$fp,
		array(
			$name,
			$email,
			$phone,
			sanitize_text_field( wp_unslash( $_POST['sf1'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['sf2'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['sf3'] ?? '' ) ),
			sanitize_text_field( wp_unslash( $_POST['sf4'] ?? '' ) ),
			current_time( 'mysql' ),
		)
	);
	fclose( $fp );

	wp_send_json_success( 'Prediction saved successfully!' );
}
