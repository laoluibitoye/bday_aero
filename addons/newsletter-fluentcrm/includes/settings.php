<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['newsletter'] = array(
			'tab_label' => 'Newsletter',
			'option'    => 'bday_addon_newsletter',
			'render'    => 'bday_newsletter_settings_tab',
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
		<tr><th scope="row">Remote server URL</th><td><input type="text" name="bday_addon_newsletter[remote_url]" value="<?php echo esc_attr( $settings['remote_url'] ?? '' ); ?>" class="regular-text" placeholder="https://crm.example.com" /></td></tr>
		<tr><th scope="row">Admin email</th><td><input type="email" name="bday_addon_newsletter[api_username]" value="<?php echo esc_attr( $settings['api_username'] ?? '' ); ?>" class="regular-text" /></td></tr>
		<tr><th scope="row">Application password</th><td><input type="password" name="bday_addon_newsletter[api_password]" value="<?php echo esc_attr( $settings['api_password'] ?? '' ); ?>" class="regular-text" /></td></tr>
	</tbody></table>

	<?php if ( ! empty( $lists ) ) : ?>
		<h3>Visible lists</h3>
		<?php foreach ( $lists as $list ) : ?>
			<label style="display:block;margin-bottom:6px;">
				<input type="checkbox" name="bday_addon_newsletter[visible_lists][]" value="<?php echo esc_attr( $list['id'] ); ?>" <?php checked( in_array( (int) $list['id'], array_map( 'intval', $visible ), true ) ); ?> />
				<?php echo esc_html( $list['title'] ); ?>
			</label>
		<?php endforeach; ?>
		<p><a href="<?php echo esc_url( add_query_arg( 'bday_refresh_lists', '1' ) ); ?>" class="button button-secondary">Sync lists from remote CRM</a></p>

		<h3>Category → list mapping</h3>
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
					return array(
						'remote_url'        => esc_url_raw( $input['remote_url'] ?? '' ),
						'api_username'      => sanitize_email( $input['api_username'] ?? '' ),
						'api_password'      => sanitize_text_field( $input['api_password'] ?? '' ),
						'visible_lists'     => array_map( 'intval', (array) ( $input['visible_lists'] ?? array() ) ),
						'category_mappings' => $mappings,
					);
				},
			)
		);
	}
);
