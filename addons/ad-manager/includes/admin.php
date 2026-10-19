<?php
/**
 * Ad Manager admin — three settings tabs under BDay Aero Options:
 *
 *   Ad Manager   global switches, tuning, ad categories, default reader policies
 *   Ad Units     the partner registry (GAM / AdSense / pasted tags / local banners)
 *   Ad Placements which units fill each location in the theme, and the rules
 *
 * Adding a new ad partner means adding an Ad Unit here — no template or
 * theme code changes, which is the point.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ── Schema ─────────────────────────────────────────────────────── */

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['ad-manager']    = array(
			'tab_label' => 'Ad Manager',
			'group'     => 'monetization',
			'option'    => Bday_Ads_Store::SETTINGS,
			'render'    => 'bday_ads_render_settings_tab',
			'intro'     => 'Site-wide advertising controls: the master switch, refresh and lazy-loading behaviour, the ad categories used for subscriber gating, and what each kind of reader sees by default.',
			'about'     => '<p>Ads are decided <strong>in the reader\'s browser</strong>, so cached pages stay fast and identical for everyone. A subscriber\'s plan can carry its own ad policy (set in the subscription platform\'s plan editor); the defaults here apply when a plan sets none.</p><p>Ads are always off on staging hostnames.</p>',
		);
		$schema['ad-units']      = array(
			'tab_label' => 'Ad Units',
			'group'     => 'monetization',
			'option'    => Bday_Ads_Store::UNITS,
			'render'    => 'bday_ads_render_units_tab',
			'intro'     => 'Every ad source the site can show. Add a Google Ad Manager slot, an AdSense unit, a third-party ad tag, or a local banner you manage yourself — then assign it to a location on the Ad Placements tab.',
			'about'     => '<p>Nothing here touches the theme code: a new ad partner is just a new unit. Each unit belongs to an <em>ad category</em>; subscription plans hide or allow ads by category.</p>',
		);
		$schema['ad-placements'] = array(
			'tab_label' => 'Ad Placements',
			'group'     => 'monetization',
			'option'    => Bday_Ads_Store::PLACEMENTS,
			'render'    => 'bday_ads_render_placements_tab',
			'intro'     => 'Every location in the theme where an ad can appear, with the creative sizes it is designed for. Choose which units fill each one and where it should (not) show.',
			'about'     => '<p>Recommended sizes are what the location\'s layout was built around. Stick to them for banners you upload; GAM units can offer several sizes and the best-paying one that fits is served.</p>',
		);
		return $schema;
	}
);

add_action(
	'admin_init',
	static function (): void {
		register_setting( Bday_Ads_Store::SETTINGS, Bday_Ads_Store::SETTINGS, array( 'sanitize_callback' => 'bday_ads_sanitize_settings' ) );
		register_setting( Bday_Ads_Store::UNITS, Bday_Ads_Store::UNITS, array( 'sanitize_callback' => 'bday_ads_sanitize_units' ) );
		register_setting( Bday_Ads_Store::PLACEMENTS, Bday_Ads_Store::PLACEMENTS, array( 'sanitize_callback' => 'bday_ads_sanitize_placements' ) );
	}
);

add_action(
	'admin_enqueue_scripts',
	static function (): void {
		$page = isset( $_GET['page'] ) ? (string) $_GET['page'] : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( 0 !== strpos( $page, 'bday-theme-settings' ) || ! preg_match( '/ad-(manager|units|placements)$/', $page ) ) {
			return;
		}
		$base = get_template_directory_uri() . '/addons/ad-manager/assets/';
		$dir  = get_template_directory() . '/addons/ad-manager/assets/';
		wp_enqueue_style( 'bday-ad-manager-admin', $base . 'ad-manager-admin.css', array(), (string) filemtime( $dir . 'ad-manager-admin.css' ) );
		wp_enqueue_script( 'bday-ad-manager-admin', $base . 'ad-manager-admin.js', array(), (string) filemtime( $dir . 'ad-manager-admin.js' ), true );
	}
);

// Saving any tab changes what the front end should render — drop the in-request caches.
foreach ( array( Bday_Ads_Store::SETTINGS, Bday_Ads_Store::UNITS, Bday_Ads_Store::PLACEMENTS ) as $bday_ads_opt ) {
	add_action( "update_option_{$bday_ads_opt}", array( 'Bday_Ads_Store', 'flush' ) );
	add_action( "add_option_{$bday_ads_opt}", array( 'Bday_Ads_Store', 'flush' ) );
}
unset( $bday_ads_opt );

/* ── Helpers ────────────────────────────────────────────────────── */

/** @param array<string,string> $options */
function bday_ads_select( string $name, array $options, string $value ): string {
	$html = '<select name="' . esc_attr( $name ) . '">';
	foreach ( $options as $k => $label ) {
		$html .= '<option value="' . esc_attr( (string) $k ) . '"' . selected( $value, (string) $k, false ) . '>' . esc_html( $label ) . '</option>';
	}
	return $html . '</select>';
}

function bday_ads_policy_editor( string $name, array $policy ): void {
	$policy = bday_ads_clean_policy( $policy );
	echo bday_ads_select( $name . '[mode]', array( 'all' => 'Show all ads', 'limited' => 'Limited — hide some categories / cap the count', 'none' => 'Ad-free (no ads at all)' ), $policy['mode'] ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<div class="bday-ads-policy__detail" style="margin-top:8px;"><strong>Hide these ad categories:</strong><br>';
	foreach ( Bday_Ads_Store::categories() as $key => $label ) {
		printf(
			'<label style="display:inline-block;margin:2px 14px 2px 0;"><input type="checkbox" name="%s[hide][]" value="%s" %s> %s</label>',
			esc_attr( $name ),
			esc_attr( (string) $key ),
			checked( in_array( $key, $policy['hide'], true ), true, false ),
			esc_html( $label )
		);
	}
	printf(
		'<br><label>Max ads per page <input type="number" min="0" class="small-text" name="%s[max]" value="%d"> <span class="description">0 = no cap. Counts in-page ads in page order.</span></label></div>',
		esc_attr( $name ),
		(int) $policy['max']
	);
}

/**
 * Ad code pasted into the theme's Custom Code boxes (Header / Body open / Footer) that talks to
 * Google Publisher Tag directly. It runs outside Ad Manager, so it can fight with it: a second
 * interstitial/anchor of the same format is refused by GPT, and one early enableServices() call
 * stops Ad Manager's lazy loading and single-request settings from applying.
 *
 * @return array<int, array{slot:string, paths:string[], enables_services:bool, loads_gpt:bool}>
 */
function bday_ads_custom_code_conflicts(): array {
	if ( ! function_exists( 'bday_custom_code' ) ) {
		return array();
	}
	$found = array();
	foreach ( array( 'header' => 'Header', 'body_open' => 'Body open', 'footer' => 'Footer' ) as $key => $label ) {
		$code = bday_custom_code( $key );
		if ( '' === $code || ! preg_match( '/googletag|gpt\.js|adsbygoogle/i', $code ) ) {
			continue;
		}
		preg_match_all( '#["\'](/\d[\w,.\-/]*)["\']#', $code, $m );
		$found[] = array(
			'slot'             => $label,
			'paths'            => array_values( array_unique( $m[1] ) ),
			'enables_services' => (bool) preg_match( '/enableServices\s*\(/', $code ),
			'loads_gpt'        => (bool) preg_match( '/gpt\.js/', $code ),
		);
	}
	return $found;
}

function bday_ads_render_conflict_notice(): void {
	$conflicts = bday_ads_custom_code_conflicts();
	if ( ! $conflicts ) {
		return;
	}
	echo '<div class="notice notice-warning inline" style="margin:12px 0;padding:10px 14px;"><p><strong>Ad code found in Custom Code.</strong> This runs outside Ad Manager and can conflict with it:</p><ul style="list-style:disc;margin-left:20px;">';
	foreach ( $conflicts as $c ) {
		echo '<li><strong>' . esc_html( $c['slot'] ) . '</strong>: ';
		$bits = array();
		if ( $c['paths'] ) {
			$bits[] = 'defines ad units ' . implode( ', ', array_map( static fn( $p ) => '<code>' . esc_html( $p ) . '</code>', $c['paths'] ) );
		}
		if ( $c['loads_gpt'] ) {
			$bits[] = 'loads the Google ad script itself';
		}
		if ( $c['enables_services'] ) {
			$bits[] = 'calls <code>enableServices()</code> early, which stops Ad Manager\'s lazy loading and single-request settings from applying';
		}
		echo $bits ? implode( '; ', $bits ) : 'contains ad-network code'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
		echo '</li>';
	}
	echo '</ul><p>Best fix: add each unit under <strong>Ad Units</strong> (use the Format setting for interstitial / anchor units), assign it on <strong>Ad Placements</strong>, then delete the snippet from Custom Code. Google allows only one interstitial and one anchor per page, so two definitions mean one is silently dropped.</p></div>';
}

/* ── Tab 1: settings ────────────────────────────────────────────── */

function bday_ads_render_settings_tab(): void {
	bday_ads_render_conflict_notice();
	$s    = Bday_Ads_Store::settings();
	$name = Bday_Ads_Store::SETTINGS;
	$cats = '';
	foreach ( (array) $s['categories'] as $k => $label ) {
		$cats .= $k . ' | ' . $label . "\n";
	}
	$rows = array(
		array( 'Ads enabled site-wide', sprintf( '<label><input type="checkbox" name="%s[master]" value="1" %s> On</label><p class="description">Master switch. Always off on staging hostnames regardless.</p>', $name, checked( ! empty( $s['master'] ), true, false ) ) ),
		array( 'AdSense publisher ID', sprintf( '<input type="text" class="regular-text" name="%s[adsense_client]" value="%s" placeholder="ca-pub-0000000000000000"><p class="description">Only needed if you add AdSense units.</p>', $name, esc_attr( (string) $s['adsense_client'] ) ) ),
		array( 'Mobile / desktop breakpoint (px)', sprintf( '<input type="number" class="small-text" min="320" max="1600" name="%s[breakpoint]" value="%d"><p class="description">Below this width a reader gets the mobile sizes and mobile-only units. Keep at 768 unless the theme\'s layout breakpoint changes.</p>', $name, (int) $s['breakpoint'] ) ),
		array( 'Auto-refresh', sprintf( 'Every <input type="number" class="small-text" min="0" name="%1$s[refresh_seconds]" value="%2$d"> seconds after an ad has been viewed, at most <input type="number" class="small-text" min="0" name="%1$s[refresh_max]" value="%3$d"> times per slot.<p class="description">0 seconds turns refresh off. Only refreshes while the tab is visible and the reader is active. Applies to GAM units that have "Auto-refresh" ticked.</p>', $name, (int) $s['refresh_seconds'], (int) $s['refresh_max'] ) ),
		array( 'Lazy loading', sprintf( 'Fetch within <input type="number" class="small-text" min="0" name="%1$s[lazy_fetch_pct]" value="%2$d">%% of a screen, render within <input type="number" class="small-text" min="0" name="%1$s[lazy_render_pct]" value="%3$d">%%.<p class="description">Fetch-ahead 200%% / render 100%% is a good balance of viewability and speed. 0 fetch margin turns lazy loading off.</p>', $name, (int) $s['lazy_fetch_pct'], (int) $s['lazy_render_pct'] ) ),
		array( 'Collapse unfilled slots', sprintf( '<label><input type="checkbox" name="%s[collapse_empty]" value="1" %s> On</label><p class="description">When an ad network returns nothing (and no fallback banner is set), remove the reserved space.</p>', $name, checked( ! empty( $s['collapse_empty'] ), true, false ) ) ),
		array( 'Page targeting for GAM', sprintf( '<label><input type="checkbox" name="%s[targeting]" value="1" %s> Send page_type, category, sections and reader state</label><p class="description">Key-values line items can target. Not personal data.</p>', $name, checked( ! empty( $s['targeting'] ), true, false ) ) ),
		array( 'Debug logging', sprintf( '<label><input type="checkbox" name="%s[debug]" value="1" %s> Log ad decisions to the browser console</label>', $name, checked( ! empty( $s['debug'] ), true, false ) ) ),
	);
	echo '<h2>General</h2><table class="form-table" role="presentation"><tbody>';
	foreach ( $rows as $r ) {
		echo '<tr><th scope="row">' . esc_html( $r[0] ) . '</th><td>' . $r[1] . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped when built.
	}
	echo '</tbody></table>';

	echo '<h2>Ad categories</h2><p class="description">One per line as <code>key | Label</code>. Every ad unit belongs to one category, and a subscription plan hides ads by category — so add a category here first, then tick it in the plan editor. Don\'t delete a key a plan already uses.</p>';
	printf( '<textarea name="%s[categories]" rows="6" class="large-text code">%s</textarea>', esc_attr( $name ), esc_textarea( trim( $cats ) ) );

	echo '<h2>Default reader policies</h2><p class="description">What each kind of reader sees. A subscriber whose <em>plan</em> carries its own ad policy gets that instead.</p><table class="form-table" role="presentation"><tbody>';
	foreach ( array( 'anonymous' => 'Not signed in', 'registered' => 'Signed in, no subscription', 'subscriber' => 'Subscriber (plan has no ad policy)' ) as $state => $label ) {
		echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
		bday_ads_policy_editor( $name . '[policy_' . $state . ']', (array) ( $s[ 'policy_' . $state ] ?? array() ) );
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

/** @param mixed $input */
function bday_ads_sanitize_settings( $input ): array {
	$in  = is_array( $input ) ? $input : array(); // options.php has already unslashed it.
	$def = Bday_Ads_Store::settings_defaults();

	$cats = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $in['categories'] ?? '' ) ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		$key   = sanitize_key( $parts[0] );
		if ( '' !== $key ) {
			$cats[ $key ] = sanitize_text_field( $parts[1] ?? $parts[0] ) ?: $key;
		}
	}
	// A category already in use must not vanish silently from under a unit or plan.
	foreach ( Bday_Ads_Store::units() as $unit ) {
		$cats[ $unit['category'] ] = $cats[ $unit['category'] ] ?? ( Bday_Ads_Store::categories()[ $unit['category'] ] ?? $unit['category'] );
	}
	if ( ! $cats ) {
		$cats = bday_ads_default_categories();
	}

	$out = array(
		'master'          => ! empty( $in['master'] ),
		'adsense_client'  => preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) ( $in['adsense_client'] ?? '' ) ),
		'breakpoint'      => max( 320, min( 1600, (int) ( $in['breakpoint'] ?? 768 ) ) ),
		'refresh_seconds' => max( 0, (int) ( $in['refresh_seconds'] ?? $def['refresh_seconds'] ) ),
		'refresh_max'     => max( 0, (int) ( $in['refresh_max'] ?? $def['refresh_max'] ) ),
		'lazy_fetch_pct'  => max( 0, (int) ( $in['lazy_fetch_pct'] ?? $def['lazy_fetch_pct'] ) ),
		'lazy_render_pct' => max( 0, (int) ( $in['lazy_render_pct'] ?? $def['lazy_render_pct'] ) ),
		'collapse_empty'  => ! empty( $in['collapse_empty'] ),
		'targeting'       => ! empty( $in['targeting'] ),
		'debug'           => ! empty( $in['debug'] ),
		'categories'      => $cats,
	);
	foreach ( array( 'anonymous', 'registered', 'subscriber' ) as $state ) {
		$p                          = bday_ads_clean_policy( $in[ 'policy_' . $state ] ?? array() );
		$p['hide']                  = array_values( array_intersect( $p['hide'], array_keys( $cats ) ) );
		$out[ 'policy_' . $state ]  = $p;
	}
	return $out;
}

/* ── Tab 2: units ───────────────────────────────────────────────── */

function bday_ads_partner_labels(): array {
	return array(
		'gam'     => 'Google Ad Manager (GAM)',
		'adsense' => 'Google AdSense',
		'html'    => 'Third-party ad tag (paste code)',
		'banner'  => 'Local banner (upload image)',
	);
}

/** Placements that use a unit, as "label (recommended sizes)" strings. */
function bday_ads_unit_usage( string $unit_id ): array {
	$out = array();
	$cat = bday_ads_placement_catalog();
	foreach ( Bday_Ads_Store::placements() as $pid => $conf ) {
		if ( isset( $cat[ $pid ] ) && in_array( $unit_id, (array) $conf['units'], true ) ) {
			$out[ $pid ] = $cat[ $pid ];
		}
	}
	return $out;
}

/** "970×90 or 728×90" → [[970,90],[728,90]] */
function bday_ads_recommended_dims( string $text ): array {
	preg_match_all( '/(\d{2,4})\s*[×x]\s*(\d{2,4})/u', $text, $m, PREG_SET_ORDER );
	return array_map( static fn( $x ) => array( (int) $x[1], (int) $x[2] ), $m );
}

function bday_ads_render_unit_card( $i, array $u, bool $is_template = false ): void {
	$n        = Bday_Ads_Store::UNITS . '[' . $i . ']';
	$cats     = Bday_Ads_Store::categories();
	$banners  = array();
	foreach ( Bday_Ads_Store::units() as $other ) {
		if ( 'banner' === $other['partner'] && $other['id'] !== $u['id'] ) {
			$banners[ $other['id'] ] = $other['label'] ?: $other['id'];
		}
	}
	$usage = $u['id'] ? bday_ads_unit_usage( $u['id'] ) : array();
	$title = $u['label'] ?: ( $u['id'] ?: 'New ad unit' );
	?>
	<details class="bday-ad-unit" data-bday-unit <?php echo $is_template || ! $u['id'] ? 'open' : ''; ?>>
		<summary>
			<strong><?php echo esc_html( $title ); ?></strong>
			<span class="bday-ad-chip"><?php echo esc_html( bday_ads_partner_labels()[ $u['partner'] ] ?? $u['partner'] ); ?></span>
			<span class="bday-ad-chip bday-ad-chip--cat"><?php echo esc_html( $cats[ $u['category'] ] ?? $u['category'] ); ?></span>
			<?php if ( 'active' !== $u['status'] ) : ?><span class="bday-ad-chip bday-ad-chip--off">paused</span><?php endif; ?>
			<?php if ( $usage ) : ?><span class="bday-ad-chip bday-ad-chip--use">used in <?php echo (int) count( $usage ); ?></span><?php endif; ?>
		</summary>
		<div class="bday-ad-unit__body">
			<input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo esc_attr( $u['id'] ); ?>">
			<table class="form-table" role="presentation"><tbody>
				<tr><th>Name</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[label]" value="<?php echo esc_attr( $u['label'] ); ?>" placeholder="e.g. Sidebar 300×600"></td></tr>
				<tr><th>Partner</th><td><?php echo bday_ads_select( $n . '[partner]', bday_ads_partner_labels(), $u['partner'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td></tr>
				<tr><th>Status</th><td><?php echo bday_ads_select( $n . '[status]', array( 'active' => 'Active', 'paused' => 'Paused (never shown)' ), $u['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td></tr>
				<tr><th>Show on</th><td><?php echo bday_ads_select( $n . '[device]', array( 'all' => 'All devices', 'desktop' => 'Desktop / tablet only', 'mobile' => 'Mobile only' ), $u['device'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td></tr>
				<tr><th>Ad category</th><td><?php echo bday_ads_select( $n . '[category]', $cats, $u['category'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?><p class="description">What a subscription plan switches on or off for ad-network units. <strong>Local banners ignore this</strong> — they always show to every reader, subscribers included.</p></td></tr>
				<tr><th>Sizes</th><td>
					Desktop <input type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[sizes_desktop]" value="<?php echo esc_attr( $u['sizes_desktop'] ); ?>" placeholder="970x90, 728x90, fluid"><br>
					Mobile <input type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[sizes_mobile]" value="<?php echo esc_attr( $u['sizes_mobile'] ); ?>" placeholder="320x100, 300x250">
					<p class="description">Comma-separated <code>WIDTHxHEIGHT</code> (or <code>fluid</code>). The shortest height listed is reserved on the page so ads don't push content down. Leave one blank to reuse the other.</p>
				</td></tr>
			</tbody></table>

			<div data-partner-section="gam">
				<table class="form-table" role="presentation"><tbody>
					<tr><th>GAM ad unit path</th><td><input type="text" class="large-text code" name="<?php echo esc_attr( $n ); ?>[path]" value="<?php echo esc_attr( $u['path'] ); ?>" placeholder="/21781351181/bd_sidebar_1"><p class="description">Copy it exactly from Ad Manager (<code>/networkCode/adUnitName</code>).</p></td></tr>
					<tr><th>Format</th><td><?php echo bday_ads_select( $n . '[format]', array( 'standard' => 'Standard banner (needs a placement spot)', 'interstitial' => 'Interstitial (full screen)', 'top_anchor' => 'Anchor — top', 'bottom_anchor' => 'Anchor — bottom', 'left_rail' => 'Side rail — left', 'right_rail' => 'Side rail — right' ), $u['format'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?><p class="description">Anything other than "standard" has no container; assign it to <em>Sticky &amp; full-screen formats</em>.</p></td></tr>
					<tr><th>Auto-refresh</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[refresh]" value="1" <?php checked( ! empty( $u['refresh'] ) ); ?>> Refresh after viewed (see Ad Manager settings)</label></td></tr>
				</tbody></table>
			</div>

			<div data-partner-section="adsense">
				<table class="form-table" role="presentation"><tbody>
					<tr><th>AdSense slot ID</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[adsense_slot]" value="<?php echo esc_attr( $u['adsense_slot'] ); ?>" placeholder="1234567890"><p class="description">The publisher ID is set once on the Ad Manager tab. With no size above it is a responsive unit.</p></td></tr>
				</tbody></table>
			</div>

			<div data-partner-section="html">
				<table class="form-table" role="presentation"><tbody>
					<?php if ( ! current_user_can( 'unfiltered_html' ) ) : ?>
						<tr><th></th><td><em>Your account can't edit raw ad code (it needs the "unfiltered_html" capability). Existing code is kept.</em></td></tr>
					<?php endif; ?>
					<tr><th>Head code</th><td><textarea class="large-text code" rows="4" name="<?php echo esc_attr( $n ); ?>[head_code]" <?php disabled( ! current_user_can( 'unfiltered_html' ) ); ?> placeholder="Library <script> the partner needs loaded once"><?php echo esc_textarea( $u['head_code'] ); ?></textarea><p class="description">Loaded once per page, only when this unit is going to be shown.</p></td></tr>
					<tr><th>Body code</th><td><textarea class="large-text code" rows="6" name="<?php echo esc_attr( $n ); ?>[body_code]" <?php disabled( ! current_user_can( 'unfiltered_html' ) ); ?> placeholder="The tag that goes where the ad shows"><?php echo esc_textarea( $u['body_code'] ); ?></textarea><p class="description">Runs when the spot nears the screen, and never for readers whose plan hides this category.</p></td></tr>
				</tbody></table>
			</div>

			<div data-partner-section="banner">
				<table class="form-table" role="presentation"><tbody>
					<tr><th>Creatives</th><td>
						<div data-creatives>
							<?php
							$creatives = $u['creatives'] ?: array( array() );
							foreach ( $creatives as $j => $c ) {
								bday_ads_render_creative_row( $n, $j, (array) $c );
							}
							?>
						</div>
						<button type="button" class="button" data-add-creative>+ Add another creative (rotates)</button>
						<template data-creative-template><?php bday_ads_render_creative_row( $n, '__j__', array() ); ?></template>
						<?php
						$warn = bday_ads_banner_warnings( $u, $usage );
						foreach ( $warn as $w ) {
							echo '<p class="bday-ad-warn">⚠ ' . esc_html( $w ) . '</p>';
						}
						if ( $usage ) {
							echo '<p class="description">Shown in: ';
							$bits = array();
							foreach ( $usage as $def ) {
								$bits[] = '<strong>' . esc_html( $def['label'] ) . '</strong> — desktop ' . esc_html( $def['desktop'] ) . ', mobile ' . esc_html( $def['mobile'] );
							}
							echo implode( '; ', $bits ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
						}
						?>
					</td></tr>
					<tr><th>Sponsor label</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[sponsor_label]" value="<?php echo esc_attr( $u['sponsor_label'] ); ?>" placeholder="Sponsored"><p class="description">Replaces the word "Advertisement" above the banner.</p></td></tr>
					<tr><th>Rotation</th><td>Every <input type="number" class="small-text" min="0" name="<?php echo esc_attr( $n ); ?>[rotate_seconds]" value="<?php echo (int) $u['rotate_seconds']; ?>"> seconds <span class="description">(only if there is more than one creative)</span></td></tr>
					<tr><th>Schedule</th><td>
						From <input type="datetime-local" name="<?php echo esc_attr( $n ); ?>[start]" value="<?php echo esc_attr( $u['start'] ); ?>">
						until <input type="datetime-local" name="<?php echo esc_attr( $n ); ?>[end]" value="<?php echo esc_attr( $u['end'] ); ?>">
						<p class="description">Site time zone. Leave blank for always on. Enforced again in the browser, so a cached page won't keep showing an expired campaign.</p>
					</td></tr>
					<tr><th>Links</th><td><label><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[new_tab]" value="1" <?php checked( ! empty( $u['new_tab'] ) ); ?>> Open in a new tab</label></td></tr>
				</tbody></table>
			</div>

			<table class="form-table" role="presentation"><tbody>
				<tr><th>If this comes back empty</th><td>
					<?php echo bday_ads_select( $n . '[fallback]', array( '' => 'Collapse the space' ) + $banners, (string) $u['fallback'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<p class="description">Show one of your local banners (e.g. a house ad) when the ad network has nothing to serve.</p>
				</td></tr>
			</tbody></table>

			<p><button type="button" class="button-link-delete" data-remove-unit>Delete this unit</button></p>
		</div>
	</details>
	<?php
}

function bday_ads_render_creative_row( string $n, $j, array $c ): void {
	$c  = array_merge( array( 'image' => 0, 'image_url' => '', 'image_mobile' => 0, 'url' => '', 'alt' => '' ), $c );
	$nn = $n . '[creatives][' . $j . ']';
	$thumb = static function ( int $id, string $url ): string {
		$src = $id ? wp_get_attachment_image_url( $id, 'medium' ) : $url;
		return $src ? '<img src="' . esc_url( $src ) . '" alt="">' : '';
	};
	?>
	<div class="bday-creative" data-creative>
		<div class="bday-creative__img" data-media-field>
			<strong>Desktop image</strong>
			<div class="bday-creative__preview"><?php echo $thumb( (int) $c['image'], (string) $c['image_url'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<input type="hidden" name="<?php echo esc_attr( $nn ); ?>[image]" value="<?php echo esc_attr( (string) (int) $c['image'] ); ?>" data-media-input>
			<input type="hidden" name="<?php echo esc_attr( $nn ); ?>[image_url]" value="<?php echo esc_attr( (string) $c['image_url'] ); ?>" data-media-url>
			<button type="button" class="button" data-media-pick>Choose</button> <button type="button" class="button-link" data-media-clear>Remove</button>
		</div>
		<div class="bday-creative__img" data-media-field>
			<strong>Mobile image <em>(optional)</em></strong>
			<div class="bday-creative__preview"><?php echo $thumb( (int) $c['image_mobile'], '' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
			<input type="hidden" name="<?php echo esc_attr( $nn ); ?>[image_mobile]" value="<?php echo esc_attr( (string) (int) $c['image_mobile'] ); ?>" data-media-input>
			<button type="button" class="button" data-media-pick>Choose</button> <button type="button" class="button-link" data-media-clear>Remove</button>
		</div>
		<div class="bday-creative__meta">
			<label>Link <input type="url" class="regular-text" name="<?php echo esc_attr( $nn ); ?>[url]" value="<?php echo esc_attr( $c['url'] ); ?>" placeholder="https://"></label>
			<label>Alt text <input type="text" class="regular-text" name="<?php echo esc_attr( $nn ); ?>[alt]" value="<?php echo esc_attr( $c['alt'] ); ?>" placeholder="Describe the ad"></label>
			<button type="button" class="button-link-delete" data-remove-creative>Remove creative</button>
		</div>
	</div>
	<?php
}

/** Flags a banner whose uploaded image doesn't match what its locations are built for. */
function bday_ads_banner_warnings( array $u, array $usage ): array {
	if ( 'banner' !== $u['partner'] || ! $usage ) {
		return array();
	}
	$warnings = array();
	foreach ( $u['creatives'] as $n => $c ) {
		foreach ( array( 'image' => 'desktop', 'image_mobile' => 'mobile' ) as $field => $which ) {
			$id = (int) ( $c[ $field ] ?? 0 );
			$m  = $id ? wp_get_attachment_metadata( $id ) : null;
			if ( empty( $m['width'] ) || empty( $m['height'] ) ) {
				continue;
			}
			$ratio  = $m['width'] / $m['height'];
			$wanted = array();
			foreach ( $usage as $def ) {
				$wanted = array_merge( $wanted, bday_ads_recommended_dims( (string) $def[ $which ] ) );
			}
			if ( ! $wanted ) {
				continue;
			}
			$fits = false;
			foreach ( $wanted as $d ) {
				if ( abs( $ratio - $d[0] / $d[1] ) / ( $d[0] / $d[1] ) < 0.12 ) {
					$fits = true;
					break;
				}
			}
			if ( ! $fits ) {
				$warnings[] = sprintf(
					'Creative %d (%s) is %d×%d, which doesn\'t match the shape its locations expect (%s). It will be scaled to fit and may look cropped or tiny.',
					$n + 1,
					$which,
					$m['width'],
					$m['height'],
					implode( ' / ', array_unique( array_map( static fn( $d ) => $d[0] . '×' . $d[1], $wanted ) ) )
				);
			}
		}
	}
	return $warnings;
}

function bday_ads_render_units_tab(): void {
	bday_ads_render_conflict_notice();
	$units = array_values( Bday_Ads_Store::units() );
	echo '<div data-bday-units>';
	foreach ( $units as $i => $u ) {
		bday_ads_render_unit_card( $i, $u );
	}
	echo '</div>';
	echo '<p><button type="button" class="button button-secondary" data-add-unit>+ Add ad unit</button></p>';
	echo '<template data-unit-template>';
	bday_ads_render_unit_card( '__i__', array_merge( Bday_Ads_Store::unit_defaults(), array( 'creatives' => array() ) ), true );
	echo '</template>';
	// Lets the sanitizer tell "tab saved with zero units" from "tab never rendered".
	echo '<input type="hidden" name="' . esc_attr( Bday_Ads_Store::UNITS ) . '[__saved]" value="1">';
}

/** @param mixed $input */
function bday_ads_sanitize_units( $input ): array {
	$in       = is_array( $input ) ? $input : array(); // options.php has already unslashed it.
	$existing = Bday_Ads_Store::units();
	$cats     = array_keys( Bday_Ads_Store::categories() );
	$can_html = current_user_can( 'unfiltered_html' );
	$out      = array();
	$seen     = array();

	foreach ( $in as $key => $row ) {
		if ( '__saved' === $key || ! is_array( $row ) ) {
			continue;
		}
		$label = sanitize_text_field( (string) ( $row['label'] ?? '' ) );
		$id    = sanitize_key( (string) ( $row['id'] ?? '' ) );
		if ( '' === $id ) {
			$id = sanitize_title( $label ?: 'unit' );
		}
		$base = $id;
		$k    = 2;
		while ( isset( $seen[ $id ] ) ) {
			$id = $base . '-' . $k++;
		}
		$seen[ $id ] = true;
		$prev        = $existing[ $id ] ?? Bday_Ads_Store::unit_defaults();

		$partner = in_array( $row['partner'] ?? '', array_keys( bday_ads_partner_labels() ), true ) ? $row['partner'] : 'gam';
		$norm    = static fn( $s ) => implode(
			',',
			array_map(
				static fn( $z ) => is_array( $z ) ? $z[0] . 'x' . $z[1] : $z,
				bday_ads_parse_sizes( (string) $s )
			)
		);

		$path = trim( (string) ( $row['path'] ?? '' ) );
		$path = preg_match( '#^/[A-Za-z0-9_\-/.,]+$#', $path ) ? $path : '';

		$creatives = array();
		foreach ( (array) ( $row['creatives'] ?? array() ) as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$creative = array(
				'image'        => absint( $c['image'] ?? 0 ),
				'image_url'    => esc_url_raw( (string) ( $c['image_url'] ?? '' ) ),
				'image_mobile' => absint( $c['image_mobile'] ?? 0 ),
				'url'          => esc_url_raw( (string) ( $c['url'] ?? '' ) ),
				'alt'          => sanitize_text_field( (string) ( $c['alt'] ?? '' ) ),
			);
			// A chosen attachment replaces any URL carried over from the old add-on.
			if ( $creative['image'] ) {
				$creative['image_url'] = '';
			}
			if ( $creative['image'] || $creative['image_url'] ) {
				$creatives[] = $creative;
			}
		}

		$date = static function ( $v ): string {
			$v = (string) $v;
			return preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v ) ? $v : '';
		};

		$category = in_array( $row['category'] ?? '', $cats, true ) ? $row['category'] : ( $cats[0] ?? 'display' );
		$unit     = array(
			'id'             => $id,
			'label'          => $label ?: $id,
			'partner'        => $partner,
			'status'         => 'paused' === ( $row['status'] ?? '' ) ? 'paused' : 'active',
			'device'         => in_array( $row['device'] ?? '', array( 'desktop', 'mobile' ), true ) ? $row['device'] : 'all',
			'category'       => $category,
			'sizes_desktop'  => $norm( $row['sizes_desktop'] ?? '' ),
			'sizes_mobile'   => $norm( $row['sizes_mobile'] ?? '' ),
			'fallback'       => sanitize_key( (string) ( $row['fallback'] ?? '' ) ),
			'path'           => $path,
			'format'         => in_array( $row['format'] ?? '', array( 'interstitial', 'top_anchor', 'bottom_anchor', 'left_rail', 'right_rail' ), true ) ? $row['format'] : 'standard',
			'refresh'        => ! empty( $row['refresh'] ),
			'adsense_slot'   => preg_replace( '/[^0-9]/', '', (string) ( $row['adsense_slot'] ?? '' ) ),
			'head_code'      => $can_html ? (string) ( $row['head_code'] ?? '' ) : (string) $prev['head_code'],
			'body_code'      => $can_html ? (string) ( $row['body_code'] ?? '' ) : (string) $prev['body_code'],
			'creatives'      => $creatives,
			'sponsor_label'  => sanitize_text_field( (string) ( $row['sponsor_label'] ?? '' ) ),
			'rotate_seconds' => max( 0, (int) ( $row['rotate_seconds'] ?? 8 ) ),
			'start'          => $date( $row['start'] ?? '' ),
			'end'            => $date( $row['end'] ?? '' ),
			'new_tab'        => ! empty( $row['new_tab'] ),
		);
		$out[]    = $unit;
	}

	return $out;
}

/* ── Tab 3: placements ──────────────────────────────────────────── */

function bday_ads_render_placements_tab(): void {
	$catalog    = bday_ads_placement_catalog();
	$placements = Bday_Ads_Store::placements();
	$units      = Bday_Ads_Store::units();
	$post_types = get_post_types( array( 'public' => true ), 'objects' );
	$cats       = Bday_Ads_Store::categories();
	$n          = Bday_Ads_Store::PLACEMENTS;

	echo '<input type="hidden" name="' . esc_attr( $n ) . '[__saved]" value="1">';
	foreach ( $catalog as $id => $def ) {
		$conf = $placements[ $id ];
		?>
		<div class="bday-ad-placement">
			<h3>
				<?php echo esc_html( $def['label'] ); ?>
				<span class="bday-ad-chip"><?php echo esc_html( 'content' === $def['kind'] ? 'in article text' : ( 'global' === $def['kind'] ? 'floating' : 'fixed spot' ) ); ?></span>
			</h3>
			<p class="description"><?php echo esc_html( $def['where'] ); ?></p>
			<p class="bday-ad-rec"><strong>Designed for:</strong> desktop <?php echo esc_html( $def['desktop'] ); ?> · mobile <?php echo esc_html( $def['mobile'] ); ?></p>
			<table class="form-table" role="presentation"><tbody>
				<tr><th>Enabled</th><td><label><input type="checkbox" name="<?php echo esc_attr( "{$n}[{$id}][enabled]" ); ?>" value="1" <?php checked( ! empty( $conf['enabled'] ) ); ?>> On</label></td></tr>
				<tr><th>Units shown here</th><td>
					<?php
					$any = false;
					// Units already assigned come first, in their saved order.
					$ordered = array();
					foreach ( (array) $conf['units'] as $uid ) {
						if ( isset( $units[ $uid ] ) ) {
							$ordered[ $uid ] = $units[ $uid ];
						}
					}
					$ordered += $units;
					echo '<div class="bday-ad-picklist">';
					foreach ( $ordered as $u ) {
						$is_oop = 'gam' === $u['partner'] && 'standard' !== $u['format'];
						if ( 'global' === $def['kind'] ? ! $is_oop : $is_oop ) {
							continue;
						}
						$any = true;
						printf(
							'<label class="bday-ad-pick"><input type="checkbox" name="%s[%s][units][]" value="%s" %s> %s <span class="bday-ad-chip">%s</span> <span class="bday-ad-chip">%s</span><span class="bday-ad-chip bday-ad-chip--cat">%s</span>%s</label>',
							esc_attr( $n ),
							esc_attr( $id ),
							esc_attr( $u['id'] ),
							checked( in_array( $u['id'], (array) $conf['units'], true ), true, false ),
							esc_html( $u['label'] ?: $u['id'] ),
							esc_html( bday_ads_partner_labels()[ $u['partner'] ] ?? $u['partner'] ),
							esc_html( 'all' === $u['device'] ? 'all devices' : $u['device'] . ' only' ),
							esc_html( $cats[ $u['category'] ] ?? $u['category'] ),
							'active' === $u['status'] ? '' : ' <span class="bday-ad-chip bday-ad-chip--off">paused</span>'
						);
					}
					echo '</div>';
					if ( ! $any ) {
						echo '<em>No suitable units yet — add one on the Ad Units tab.</em>';
					}
					?>
					<p class="description">Tick several to stack them (e.g. a desktop unit and a mobile unit). An empty placement shows nothing.</p>
				</td></tr>
				<?php if ( 'content' === $def['kind'] ) : ?>
					<tr><th>Position</th><td>After paragraph <input type="number" class="small-text" min="1" max="60" name="<?php echo esc_attr( "{$n}[{$id}][paragraph]" ); ?>" value="<?php echo (int) $conf['paragraph']; ?>">
						· only in articles with at least <input type="number" class="small-text" min="1" max="80" name="<?php echo esc_attr( "{$n}[{$id}][min_paragraphs]" ); ?>" value="<?php echo (int) $conf['min_paragraphs']; ?>"> paragraphs</td></tr>
				<?php endif; ?>
				<tr><th>Only on</th><td>
					<?php foreach ( $post_types as $pt ) : ?>
						<label style="margin-right:14px;"><input type="checkbox" name="<?php echo esc_attr( "{$n}[{$id}][post_types][]" ); ?>" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, (array) $conf['post_types'], true ) ); ?>> <?php echo esc_html( $pt->labels->name ); ?></label>
					<?php endforeach; ?>
					<p class="description">Leave all unticked for every post type.</p>
				</td></tr>
				<tr><th>Never on categories</th><td><input type="text" class="regular-text" name="<?php echo esc_attr( "{$n}[{$id}][exclude_categories]" ); ?>" value="<?php echo esc_attr( implode( ', ', (array) $conf['exclude_categories'] ) ); ?>" placeholder="opinion, sponsored-content"><p class="description">Category slugs, comma-separated (e.g. "no ads on op-eds").</p></td></tr>
			</tbody></table>
		</div>
		<?php
	}
}

/** @param mixed $input */
function bday_ads_sanitize_placements( $input ): array {
	$in      = is_array( $input ) ? $input : array(); // options.php has already unslashed it.
	$catalog = bday_ads_placement_catalog();
	$units   = Bday_Ads_Store::units();
	$types   = get_post_types( array( 'public' => true ) );
	$out     = array();

	foreach ( $catalog as $id => $def ) {
		$row = is_array( $in[ $id ] ?? null ) ? $in[ $id ] : array();
		$out[ $id ] = array(
			'enabled'            => ! empty( $row['enabled'] ),
			'units'              => array_values( array_filter( array_map( 'sanitize_key', (array) ( $row['units'] ?? array() ) ), static fn( $u ) => isset( $units[ $u ] ) ) ),
			'post_types'         => array_values( array_intersect( array_map( 'sanitize_key', (array) ( $row['post_types'] ?? array() ) ), $types ) ),
			'exclude_categories' => array_values( array_filter( array_map( 'sanitize_title', preg_split( '/[\s,]+/', (string) ( $row['exclude_categories'] ?? '' ) ) ) ) ),
			'paragraph'          => 'content' === $def['kind'] ? max( 1, min( 60, (int) ( $row['paragraph'] ?? $def['paragraph'] ?? 2 ) ) ) : 0,
			'min_paragraphs'     => 'content' === $def['kind'] ? max( 1, min( 80, (int) ( $row['min_paragraphs'] ?? 0 ) ) ) : 0,
		);
	}
	return $out;
}
