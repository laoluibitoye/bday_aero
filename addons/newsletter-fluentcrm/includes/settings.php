<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['newsletter'] = array(
			'tab_label' => 'Newsletter',
				'group'     => 'editorial', // category-to-list mapping is the day-to-day interaction; the one-time remote credential setup is the exception, not the common case
			'option'    => 'bday_addon_newsletter',
			'render'    => 'bday_newsletter_settings_tab',
			'intro'     => 'Connects the site\'s newsletter signup forms to a remote FluentCRM install — a separate WordPress site running the FluentCRM plugin, not something hosted here. Once connected, readers who sign up for a specific category\'s updates are subscribed to that category\'s mapped list automatically.',
			'about'     => '<p>The connection uses a WordPress application password (Users → Profile → Application Passwords on the <em>remote</em> CRM site, not this one) rather than the CRM admin\'s real login password. "Sync lists" pulls the current list names from FluentCRM so they can be mapped to categories below.</p>',
		);
		return $schema;
	}
);

function bday_newsletter_settings_tab(): void {
	$settings = get_option( 'bday_addon_newsletter', array() );
	if ( isset( $_GET['bday_refresh_lists'] ) ) { // phpcs:ignore -- read-only, not the settings form submit
		wp_cache_delete( 'lists', 'bday_newsletter' );
	}
	$lists   = bday_newsletter_get_lists();
	$visible = (array) ( $settings['visible_lists'] ?? array() );
	?>
	<table class="form-table" role="presentation"><tbody>
		<tr><th scope="row">Remote server URL</th><td><input type="text" name="bday_addon_newsletter[remote_url]" value="<?php echo esc_attr( $settings['remote_url'] ?? '' ); ?>" class="regular-text" placeholder="https://crm.example.com" /><p class="description">The base URL of the WordPress site running FluentCRM — not this site.</p></td></tr>
		<tr><th scope="row">Admin email</th><td><input type="email" name="bday_addon_newsletter[api_username]" value="<?php echo esc_attr( $settings['api_username'] ?? '' ); ?>" class="regular-text" /><p class="description">The username (usually an email) of a user on the remote CRM site who has an application password issued.</p></td></tr>
		<tr><th scope="row">Application password</th><td><input type="password" name="bday_addon_newsletter[api_password]" value="<?php echo esc_attr( $settings['api_password'] ?? '' ); ?>" class="regular-text" /><p class="description">Generated on the remote CRM site under Users → Profile → Application Passwords — not that user's real login password.</p></td></tr>
	</tbody></table>

	<?php if ( ! empty( $lists ) ) : ?>
		<h3>Visible lists</h3>
		<p class="description">Which FluentCRM lists readers are allowed to see/choose when signing up on this site. The description below each is what readers see on the <code>/newsletter-opt-in/</code> page (Section 6.5) — FluentCRM doesn't expose its own list descriptions over this bridge, so it's entered here instead.</p>
		<?php $descriptions = (array) ( $settings['list_descriptions'] ?? array() ); ?>
		<?php foreach ( $lists as $list ) : ?>
			<div style="margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #e5e7eb;">
				<label style="display:block;margin-bottom:6px;font-weight:600;">
					<input type="checkbox" name="bday_addon_newsletter[visible_lists][]" value="<?php echo esc_attr( $list['id'] ); ?>" <?php checked( in_array( (int) $list['id'], array_map( 'intval', $visible ), true ) ); ?> />
					<?php echo esc_html( $list['title'] ); ?>
				</label>
				<input type="text" name="bday_addon_newsletter[list_descriptions][<?php echo esc_attr( $list['id'] ); ?>]" value="<?php echo esc_attr( $descriptions[ $list['id'] ] ?? '' ); ?>" class="regular-text" placeholder="One line describing what this newsletter covers and how often it sends" />
			</div>
		<?php endforeach; ?>
		<p><a href="<?php echo esc_url( add_query_arg( 'bday_refresh_lists', '1' ) ); ?>" class="button button-secondary">Sync lists from remote CRM</a></p>

		<h3>Category → list mapping</h3>
		<p class="description">When a reader opts into updates from a specific category (e.g. an in-article "Follow Economy" prompt), they're subscribed to whichever list is mapped to that category here.</p>
		<?php $mappings = (array) ( $settings['category_mappings'] ?? array() ); ?>
		<table class="widefat striped" style="max-width:650px;"><thead><tr><th>Category</th><th>Newsletter list</th></tr></thead><tbody>
			<?php foreach ( get_categories( array( 'hide_empty' => false ) ) as $cat ) : ?>
				<tr>
					<td><?php echo esc_html( $cat->name ); ?></td>
					<td>
						<select name="bday_addon_newsletter[category_mappings][<?php echo esc_attr( $cat->term_id ); ?>]">
							<option value="0">— Do not map —</option>
							<?php foreach ( $lists as $list ) : ?>
								<option value="<?php echo esc_attr( $list['id'] ); ?>" <?php selected( (int) ( $mappings[ $cat->term_id ] ?? 0 ), (int) $list['id'] ); ?>><?php echo esc_html( $list['title'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody></table>
	<?php else : ?>
		<p class="description">Save valid remote credentials, then reload this page to sync lists.</p>
	<?php endif; ?>
	<?php
}

add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'bday_addon_newsletter',
			'bday_addon_newsletter',
			array(
				'sanitize_callback' => static function ( $input ) {
					$input = is_array( $input ) ? $input : array();
					$mappings = array();
					foreach ( (array) ( $input['category_mappings'] ?? array() ) as $cat_id => $list_id ) {
						$mappings[ (int) $cat_id ] = (int) $list_id;
					}
					$descriptions = array();
					foreach ( (array) ( $input['list_descriptions'] ?? array() ) as $list_id => $desc ) {
						$desc = sanitize_text_field( $desc );
						if ( '' !== $desc ) {
							$descriptions[ (int) $list_id ] = $desc;
						}
					}
					return array(
						'remote_url'        => esc_url_raw( $input['remote_url'] ?? '' ),
						'api_username'      => sanitize_email( $input['api_username'] ?? '' ),
						'api_password'      => sanitize_text_field( $input['api_password'] ?? '' ),
						'visible_lists'     => array_map( 'intval', (array) ( $input['visible_lists'] ?? array() ) ),
						'category_mappings' => $mappings,
						'list_descriptions' => $descriptions,
					);
				},
			)
		);
	}
);
