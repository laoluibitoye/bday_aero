<?php
/**
 * The core-owned settings tabs: General (master add-on checklist),
 * Homepage Variants (override + schedule), and Custom Code (header/
 * footer/body-open script injection). Every add-on contributes its own
 * tab the same way, from its own settings.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['general'] = array(
			'tab_label' => 'General',
			'option'    => 'bday_addon_states_ui', // placeholder option; actual toggle state lives in bday_addon_states via a bespoke render
			'render'    => 'bday_render_general_tab',
		);

		$schema['homepage'] = array(
			'tab_label' => 'Homepage Variants',
			'option'    => 'bday_homepage_variant_settings',
			'fields'    => array(
				array(
					'key'         => 'override',
					'type'        => 'select',
					'label'       => 'Homepage layout',
					'description' => '"Automatic" switches between the default and weekend layouts by day of week. Forcing a layout overrides that until switched back to Automatic.',
					'default'     => 'auto',
					'options'     => bday_homepage_variant_options(),
				),
			),
		);

		$schema['custom-code'] = array(
			'tab_label' => 'Custom Code',
			'option'    => 'bday_custom_code',
			'fields'    => array(
				array(
					'key'         => 'header',
					'type'        => 'code-editor',
					'label'       => 'Header code',
					'description' => 'Injected just before </head> on every page.',
				),
				array(
					'key'         => 'body_open',
					'type'        => 'code-editor',
					'label'       => 'Body (top) code',
					'description' => 'Injected right after the opening <body> tag.',
				),
				array(
					'key'         => 'footer',
					'type'        => 'code-editor',
					'label'       => 'Footer code',
					'description' => 'Injected just before </body> on every page.',
				),
			),
		);

		return $schema;
	}
);

/** @return array<string, string> */
function bday_homepage_variant_options(): array {
	$options = array( 'auto' => 'Automatic (weekday/weekend by day)' );
	foreach ( Bday_Variant_Registry::discover() as $slug => $meta ) {
		$options[ $slug ] = 'Force: ' . $meta['label'];
	}
	return $options;
}

function bday_render_general_tab(): void {
	$states = Bday_Addon_Loader::states();
	echo '<p>Turn features on or off. Disabled features cost nothing — their code is never loaded.</p>';
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( Bday_Addon_Loader::discover() as $slug => $meta ) {
		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="bday_addon_states[%2$s]" value="1" %3$s /> Enabled</label></td></tr>',
			esc_html( $meta['name'] ?: $slug ),
			esc_attr( $slug ),
			checked( ! empty( $states[ $slug ] ), true, false )
		);
	}
	echo '</tbody></table>';
}

// The General tab posts to its own dedicated option (bday_addon_states)
// rather than the placeholder used above purely to satisfy the framework's
// one-option-per-tab convention — registered and saved separately here.
add_action(
	'admin_init',
	static function (): void {
		register_setting(
			'bday_addon_states_ui',
			'bday_addon_states',
			array(
				'sanitize_callback' => static function ( $input ) {
					$input  = is_array( $input ) ? $input : array();
					$output = array();
					foreach ( array_keys( Bday_Addon_Loader::discover() ) as $slug ) {
						$output[ $slug ] = ! empty( $input[ $slug ] );
					}
					return $output;
				},
			)
		);
	}
);
