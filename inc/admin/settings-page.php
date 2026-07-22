<?php
/**
 * Theme settings screen (Appearance > BusinessDay Theme). Currently just
 * the homepage-variant override control — the one setting the "switch the
 * homepage layout without touching Settings > Reading" feature needs. More
 * theme-level settings can land on this same screen later instead of each
 * spawning its own top-level admin menu page (see functions/features.php's
 * bd_settings_panel() for the older pattern this is meant to replace over
 * time — Premium Leaderboard / BDay Live still live there for now).
 */

add_action( 'admin_menu', 'bd_register_theme_settings_page' );
function bd_register_theme_settings_page(): void {
	add_theme_page(
		'BusinessDay Theme Settings',
		'BusinessDay Theme',
		'manage_options',
		'bd-theme-settings',
		'bd_render_theme_settings_page'
	);
}

add_action( 'admin_init', 'bd_register_theme_settings_fields' );
function bd_register_theme_settings_fields(): void {
	register_setting( 'bd_theme_settings', 'bd_homepage_variant_override' );
}

function bd_render_theme_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$variants = bd_get_homepage_variants();
	$current  = get_option( 'bd_homepage_variant_override', 'auto' );
	?>
	<div class="wrap">
		<h1>BusinessDay Theme Settings</h1>
		<form action="options.php" method="post">
			<?php settings_fields( 'bd_theme_settings' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="bd_homepage_variant_override">Homepage layout</label></th>
					<td>
						<select name="bd_homepage_variant_override" id="bd_homepage_variant_override">
							<option value="auto" <?php selected( $current, 'auto' ); ?>>Automatic (weekday/weekend by day)</option>
							<?php foreach ( $variants as $key => $variant ) : ?>
								<option value="<?= esc_attr( $key ) ?>" <?php selected( $current, $key ); ?>>Force: <?= esc_html( $variant['label'] ) ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							"Automatic" switches between the default and weekend layouts by day of week.
							Forcing a layout (e.g. "Breaking News" during a major story) overrides that
							until switched back to Automatic — the homepage stays the same WordPress
							Page either way, only its internal layout changes.
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
