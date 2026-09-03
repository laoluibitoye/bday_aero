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
				'group'     => 'technical', // whether a whole feature runs at all is a site-operations call, not a day-to-day editorial one
			'option'    => 'bday_addon_states_ui', // placeholder option; actual toggle state lives in bday_addon_states via a bespoke render
			'render'    => 'bday_render_general_tab',
			'intro'     => 'The master feature list for this theme. Every row below is an independent add-on — <strong>disabling one costs nothing</strong>: its PHP file is never even <code>require()</code>\'d, so a disabled feature can\'t slow the site down or run a stray query. Most features also have their own settings tab in the sidebar (only shown once the feature is enabled here) for the day-to-day content/config changes — this page is only for turning the feature itself on or off.',
		);

		$schema['homepage'] = array(
			'tab_label' => 'Homepage Variants',
				'group'     => 'editorial',
			'option'    => 'bday_homepage_variant_settings',
			'intro'     => 'The homepage automatically switches between a weekday news layout and a lighter weekend/magazine layout — this page only exists to override that automatic behavior, for example to keep the weekday layout live during a Saturday breaking-news event, or to preview the weekend layout early.',
			'about'     => '<p>Each layout is its own template file under <code>homepage-variants/</code> — "Automatic" picks between them by the server\'s day of week, with no reader-visible difference otherwise (same header, footer, ads, and add-ons on either one).</p>',
			'fields'    => array(
				array(
					'key'         => 'override',
					'type'        => 'select',
					'label'       => 'Homepage layout',
					'description' => '"Automatic" switches between the default and weekend layouts by day of week. Forcing a layout overrides that until switched back to Automatic — remember to switch it back; a forced layout does not expire on its own.',
					'default'     => 'auto',
					'options'     => bday_homepage_variant_options(),
				),
			),
		);

		$schema['masthead'] = array(
			'tab_label' => 'Masthead',
				'group'     => 'editorial',
			'option'    => 'bday_masthead',
			'intro'     => 'Everything on this page is content, not code — the wording, links, and cities shown in the header on every page of the site. Changes here are live immediately for every reader (no cache purge needed beyond the usual object-cache TTL).',
			'about'     => '<p>The masthead is the logo/tagline block plus the row of controls beside it (the CTA button, sign-in) and the dark utility bar above it (date, world clocks, translate, theme toggle). This tab covers the parts an editor is expected to change; the rest of the utility bar\'s look is a design decision, not a setting.</p>',
			'use_cases' => array(
				'Running a subscription campaign: change the CTA label to "Subscribe — 20% off" and point the URL at a promo page.',
				'A new market opens: add its financial-centre city/timezone to the world clock strip.',
				'A seasonal rebrand: swap the tagline for a limited time (e.g. an anniversary message).',
			),
			'fields'    => array(
				array(
					'key'         => 'tagline',
					'type'        => 'text',
					'label'       => 'Tagline',
					'description' => 'Shown under the logo in the masthead. Leave blank to hide it.',
					'default'     => "Tracking Trends | Informing Decisions",
				),
				array(
					'key'         => 'cta_label',
					'type'        => 'text',
					'label'       => 'CTA button label',
					'description' => 'Shown in the site header next to the account menu. Leave blank to hide the button entirely.',
					'default'     => 'Subscribe',
				),
				array(
					'key'         => 'cta_url',
					'type'        => 'url',
					'label'       => 'CTA button URL',
					'description' => 'Where the header CTA button links to.',
					'default'     => '',
				),
				array(
					'key'         => 'world_clocks',
					'type'        => 'text',
					'label'       => 'World clock cities',
					'description' => 'Comma-separated "City:Timezone" pairs shown in the utility bar (timezone must be a valid IANA name, e.g. Africa/Lagos). Default: Lagos:Africa/Lagos,London:Europe/London,New York:America/New_York,Dubai:Asia/Dubai',
					'default'     => 'Lagos:Africa/Lagos,London:Europe/London,New York:America/New_York,Dubai:Asia/Dubai',
				),
			),
		);

		$schema['custom-code'] = array(
			'tab_label' => 'Custom Code',
				'group'     => 'technical', // raw script injection with no sandboxing — a mistake here can break the whole site
			'option'    => 'bday_custom_code',
			'intro'     => 'A verbatim injection point for third-party snippets (a verification meta tag, a tracking pixel, an analytics script) that don\'t warrant a whole add-on of their own. <strong>Whatever is pasted here runs unmodified on every single page</strong> — there is no sandboxing, no validation, and a mistake here (a stray <code>&lt;script&gt;</code> tag, broken HTML) can break the entire site\'s rendering for every visitor. Only paste code from a source you trust.',
			'about'     => '<p>Only use this for something that genuinely has to run on every page and has no other legitimate home. A tracking pixel from your ad network or a site-verification tag are the intended use — a whole feature belongs in its own add-on instead, where it can be turned off independently and reviewed like real code.</p>',
			'fields'    => array(
				array(
					'key'         => 'header',
					'type'        => 'code-editor',
					'label'       => 'Header code',
					'description' => 'Injected just before </head> on every page. The usual home for a verification meta tag or a script that must load before the page body (most analytics snippets ask for this spot).',
				),
				array(
					'key'         => 'body_open',
					'type'        => 'code-editor',
					'label'       => 'Body (top) code',
					'description' => 'Injected right after the opening <body> tag — Google Tag Manager\'s own install instructions specifically ask for this exact position, ahead of any other body content.',
				),
				array(
					'key'         => 'footer',
					'type'        => 'code-editor',
					'label'       => 'Footer code',
					'description' => 'Injected just before </body> on every page. The right spot for anything that doesn\'t need to block initial rendering — most tracking/chat-widget scripts prefer this position so they don\'t slow down the page a reader is actually trying to read.',
				),
			),
		);

		$schema['access-control'] = array(
			'tab_label' => 'Access Control',
				'group'     => 'technical',
			'option'    => Bday_Settings_Visibility::OPTION,
			'render'    => 'bday_render_access_control_tab',
			'intro'     => 'Choose which roles, besides Administrator, can see and use each settings tab below. This tab itself always stays Administrator-only, so no role can grant itself broader access.',
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

/**
 * Two separate tables — Editorial & Content, then Technical &
 * Infrastructure — using the same 'group' each tab already declares on
 * itself (core-tabs.php's own entries, and every add-on's) rather than a
 * second classification maintained here. The split exists specifically
 * so an admin handing out access can tell at a glance which half of this
 * list is "day-to-day desk work" versus "can affect the whole site" —
 * grouping the checkboxes visually is the actual point of this tab
 * existing at all, not just a cosmetic reorder of the same flat table.
 *
 * @param array<string, string[]> $values slug => role slugs currently granted
 */
function bday_render_access_control_tab( array $values ): void {
	$roles = wp_roles()->get_names();
	unset( $roles['administrator'] );

	$schema = Bday_Options_Framework::schema();
	$slugs  = array_diff( array_keys( $schema ), array( Bday_Settings_Visibility::ADMIN_ONLY_SLUG ) );

	$groups = array(
		'editorial' => array( 'label' => 'Editorial & Content', 'rows' => array() ),
		'technical' => array( 'label' => 'Technical & Infrastructure', 'rows' => array() ),
	);

	foreach ( $slugs as $slug ) {
		$tab   = $schema[ $slug ];
		$group = ( $tab['group'] ?? 'technical' ) === 'editorial' ? 'editorial' : 'technical';
		$groups[ $group ]['rows'][ $slug ] = $tab['tab_label'] ?? $slug;
	}
	// AeroPaywall's own bespoke admin screen isn't part of the shared
	// schema (it registers its wp-admin menu directly, not through
	// bday_settings_schema), but its visibility is gated through this
	// same option — it belongs with the rest of the paywall/revenue
	// infrastructure in Technical, not among the schema-driven tabs above.
	$groups['technical']['rows']['aero-paywall'] = 'AeroPaywall';

	echo '<style>.bday-access-control-group{margin:28px 0 8px;font-size:15px}.bday-access-control-group:first-of-type{margin-top:16px}</style>';
	echo '<p class="description">Administrator always has full access to every tab, regardless of what is selected below.</p>';

	foreach ( $groups as $group ) {
		if ( empty( $group['rows'] ) ) {
			continue;
		}

		echo '<h3 class="bday-access-control-group">' . esc_html( $group['label'] ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>Settings tab</th>';
		foreach ( $roles as $role_name ) {
			echo '<th>' . esc_html( translate_user_role( $role_name ) ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $group['rows'] as $slug => $label ) {
			$granted = $values[ $slug ] ?? array();
			echo '<tr><td>' . esc_html( $label ) . '</td>';
			foreach ( $roles as $role_slug => $role_name ) {
				printf(
					'<td><input type="checkbox" name="%1$s[%2$s][]" value="%3$s" %4$s /></td>',
					esc_attr( Bday_Settings_Visibility::OPTION ),
					esc_attr( $slug ),
					esc_attr( $role_slug ),
					checked( in_array( $role_slug, $granted, true ), true, false )
				);
			}
			echo '</tr>';
		}

		echo '</tbody></table>';
	}
}

function bday_render_general_tab(): void {
	$states = Bday_Addon_Loader::states();
	echo '<table class="form-table" role="presentation"><tbody>';
	foreach ( Bday_Addon_Loader::discover() as $slug => $meta ) {
		printf(
			'<tr><th scope="row">%1$s</th><td><label><input type="checkbox" name="bday_addon_states[%2$s]" value="1" %3$s /> Enabled</label>%4$s</td></tr>',
			esc_html( $meta['name'] ?: $slug ),
			esc_attr( $slug ),
			checked( ! empty( $states[ $slug ] ), true, false ),
			! empty( $meta['description'] ) ? '<p class="description">' . esc_html( $meta['description'] ) . '</p>' : ''
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
