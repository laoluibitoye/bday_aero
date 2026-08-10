<?php
/**
 * Unified theme options screen: its own top-level "BusinessDay Theme"
 * wp-admin menu, one real submenu page per section — core sections
 * (General, Homepage Variants, Ads & Sharing Matrix, Custom Code,
 * Integrations) and every add-on's own section — all contributed through
 * one `bday_settings_schema` filter and rendered by one shared code path,
 * replacing the previous theme's six separate top-level admin screens
 * (each with its own option name and no shared framework).
 *
 * Each schema entry is its own wp-admin page/URL (not an in-page tab): the
 * WordPress admin sidebar itself is the section switcher, which is also
 * why no nav-tab markup is rendered here anymore.
 *
 * Schema shape:
 *   $schema['slug'] = [
 *     'tab_label' => 'Live Match',
 *     'option'    => 'bday_addon_live_match',   // one option per section, array-shaped
 *     'fields'    => [ ['key','type','label','description','default','options','min'], ... ],
 *     'render'    => callable(array $values): void,  // optional escape hatch, replaces the generic field loop
 *   ]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Options_Framework {

	private const PAGE_SLUG = 'bday-theme-settings';

	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'register_menu' ) );
		add_action( 'admin_init', array( self::class, 'register_settings' ) );
	}

	/** @return array<string, array<string, mixed>> */
	public static function schema(): array {
		static $schema = null;
		if ( null === $schema ) {
			$schema = apply_filters( 'bday_settings_schema', array() );
		}
		return $schema;
	}

	/**
	 * Registers the top-level menu plus one submenu page per schema entry.
	 * The first entry's submenu is deliberately given the *same* slug as
	 * the top-level menu (self::PAGE_SLUG) — the standard WP idiom for
	 * "clicking the top-level item itself opens the first section" without
	 * WordPress also generating a redundant duplicate submenu row for it.
	 */
	public static function register_menu(): void {
		$schema = self::schema();
		$slugs  = array_keys( $schema );

		add_menu_page(
			'BusinessDay Theme',
			'BusinessDay Theme',
			'manage_options',
			self::PAGE_SLUG,
			empty( $slugs ) ? array( self::class, 'render_empty' ) : null,
			'dashicons-admin-customizer',
			61
		);

		foreach ( $slugs as $index => $slug ) {
			$tab        = $schema[ $slug ];
			$label      = $tab['tab_label'] ?? $slug;
			$page_slug  = 0 === $index ? self::PAGE_SLUG : self::PAGE_SLUG . '-' . $slug;

			add_submenu_page(
				self::PAGE_SLUG,
				$label,
				$label,
				'manage_options',
				$page_slug,
				static function () use ( $slug ): void {
					self::render_tab( $slug );
				}
			);
		}
	}

	public static function register_settings(): void {
		foreach ( self::schema() as $slug => $tab ) {
			if ( empty( $tab['option'] ) ) {
				continue;
			}
			register_setting(
				$tab['option'],
				$tab['option'],
				array(
					'sanitize_callback' => static function ( $input ) use ( $tab ) {
						if ( ! empty( $tab['fields'] ) ) {
							return bday_sanitize_fields( $tab['fields'], $input );
						}
						return is_array( $input ) ? $input : array();
					},
				)
			);
		}
	}

	/** Shown only if no schema entries were ever registered (no core-tabs.php, no add-ons at all). */
	public static function render_empty(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>BusinessDay Theme</h1><p>No settings sections registered.</p></div>';
	}

	/** Renders one schema entry's own submenu page — the section switcher is the wp-admin sidebar itself, not in-page tabs. */
	public static function render_tab( string $slug ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$schema = self::schema();
		if ( ! isset( $schema[ $slug ] ) ) {
			return;
		}
		$tab = $schema[ $slug ];
		?>
		<div class="wrap bday-theme-settings">
			<h1><?php echo esc_html( 'BusinessDay Theme — ' . ( $tab['tab_label'] ?? $slug ) ); ?></h1>

			<form action="options.php" method="post">
				<?php settings_fields( $tab['option'] ); ?>
				<?php
				$values = get_option( $tab['option'], array() );
				$values = is_array( $values ) ? $values : array();

				if ( ! empty( $tab['render'] ) && is_callable( $tab['render'] ) ) {
					call_user_func( $tab['render'], $values );
				} else {
					echo '<table class="form-table" role="presentation"><tbody>';
					foreach ( (array) ( $tab['fields'] ?? array() ) as $field ) {
						$field['_option'] = $tab['option'];
						echo '<tr><th scope="row">' . esc_html( $field['label'] ) . '</th><td>';
						bday_render_field( $field, $values );
						echo '</td></tr>';
					}
					echo '</tbody></table>';
				}
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}

Bday_Options_Framework::init();
