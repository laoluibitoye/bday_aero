<?php
/**
 * Unified theme options screen: Appearance > BusinessDay Theme. Every tab
 * — core tabs (General, Homepage Variants, Ads & Sharing Matrix, Custom
 * Code, Integrations) and every add-on's own tab — is contributed through
 * one `bday_settings_schema` filter and rendered by one shared code path,
 * replacing the previous theme's six separate top-level admin screens
 * (each with its own option name and no shared framework).
 *
 * Schema shape:
 *   $schema['slug'] = [
 *     'tab_label' => 'Live Match',
 *     'option'    => 'bday_addon_live_match',   // one option per tab, array-shaped
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

	public static function register_menu(): void {
		add_theme_page(
			'BusinessDay Theme',
			'BusinessDay Theme',
			'manage_options',
			self::PAGE_SLUG,
			array( self::class, 'render_page' )
		);
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

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$schema = self::schema();
		$slugs  = array_keys( $schema );
		if ( empty( $slugs ) ) {
			echo '<div class="wrap"><h1>BusinessDay Theme</h1><p>No settings sections registered.</p></div>';
			return;
		}

		$active = isset( $_GET['tab'] ) && isset( $schema[ sanitize_key( wp_unslash( $_GET['tab'] ) ) ] )
			? sanitize_key( wp_unslash( $_GET['tab'] ) )
			: $slugs[0];

		$tab = $schema[ $active ];
		?>
		<div class="wrap bday-theme-settings">
			<h1>BusinessDay Theme</h1>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $schema as $slug => $t ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $slug ) ); ?>" class="nav-tab <?php echo $slug === $active ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $t['tab_label'] ?? $slug ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

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
