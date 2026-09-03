<?php
/**
 * Unified theme options screen: its own top-level "BDay Aero Options"
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
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue_media' ) );
		add_action( 'admin_head', array( self::class, 'print_menu_group_css' ) );
	}

	/**
	 * Styles the group-label rows add_group_divider() splices into the
	 * sidebar submenu — a small uppercase heading with a hairline above it,
	 * rather than looking like just another clickable tab. Printed
	 * unconditionally on every wp-admin screen (a few bytes of inline CSS
	 * that only ever matches an element on this menu) rather than gated to
	 * this screen only, so the rule doesn't need to duplicate
	 * enqueue_media()'s page-slug check for something this cheap.
	 */
	public static function print_menu_group_css(): void {
		?>
		<style>
			#adminmenu .bday-menu-group-label {
				display: block;
				margin-top: 6px;
				padding-top: 8px;
				border-top: 1px solid rgba(240, 246, 252, .15);
				font-size: 11px;
				font-weight: 600;
				letter-spacing: .06em;
				text-transform: uppercase;
				color: #b4b9be;
				cursor: default;
			}
			#adminmenu .wp-submenu li:first-child .bday-menu-group-label {
				margin-top: 0;
				padding-top: 0;
				border-top: none;
			}
		</style>
		<?php
	}

	/**
	 * The media library picker ('image' field type, field-types/render.php)
	 * needs wp.media, which core only enqueues automatically on screens
	 * that ask for it — every settings page under this framework's own
	 * menu slug gets it unconditionally rather than each schema entry
	 * needing to remember to request it itself.
	 */
	public static function enqueue_media(): void {
		if ( isset( $_GET['page'] ) && 0 === strpos( (string) $_GET['page'], self::PAGE_SLUG ) ) {
			wp_enqueue_media();
		}
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
	/** @var array<string, string> group key => sidebar/section heading */
	private const GROUP_LABELS = array(
		'editorial' => 'Editorial & Content',
		'technical' => 'Technical & Infrastructure',
	);

	public static function register_menu(): void {
		$schema = self::schema();

		if ( empty( $schema ) ) {
			add_menu_page(
				'BDay Aero Options',
				'BDay Aero Options',
				'manage_options',
				self::PAGE_SLUG,
				array( self::class, 'render_empty' ),
				'dashicons-admin-customizer',
				61
			);
			return;
		}

		$slugs = self::visible_slugs( $schema );
		if ( empty( $slugs ) ) {
			return;
		}

		add_menu_page(
			'BDay Aero Options',
			'BDay Aero Options',
			Bday_Settings_Visibility::capability_for( $slugs[0] ),
			self::PAGE_SLUG,
			null,
			'dashicons-admin-customizer',
			61
		);

		// $slugs is already Editorial-then-Technical (visible_slugs() sorts
		// by group) — a group-label divider is spliced into the wp-admin
		// sidebar right before each group's first item, so the same split
		// asked for in Access Control is visible one level up too: an
		// editor scanning the sidebar sees at a glance which tabs are
		// theirs to touch day-to-day versus the technical/ops half below.
		$last_group = null;
		foreach ( $slugs as $index => $slug ) {
			$tab        = $schema[ $slug ];
			$group      = self::group_of( $tab );
			$label      = $tab['tab_label'] ?? $slug;
			$page_slug  = 0 === $index ? self::PAGE_SLUG : self::PAGE_SLUG . '-' . $slug;

			if ( $group !== $last_group ) {
				self::add_group_divider( $group );
				$last_group = $group;
			}

			add_submenu_page(
				self::PAGE_SLUG,
				$label,
				$label,
				Bday_Settings_Visibility::capability_for( $slug ),
				$page_slug,
				static function () use ( $slug ): void {
					self::render_tab( $slug );
				}
			);
		}
	}

	/** @param array<string, mixed> $tab */
	private static function group_of( array $tab ): string {
		return ( $tab['group'] ?? 'technical' ) === 'editorial' ? 'editorial' : 'technical';
	}

	/**
	 * Injects a non-clickable section-header row directly into the wp-admin
	 * submenu list — add_submenu_page() has no concept of a plain label, so
	 * this manipulates the $submenu global the same way core itself
	 * populates it (a long-standing, widely-used extensibility pattern,
	 * not a documented API). Only ever called right before a real page in
	 * that group is about to be added, so it can never appear as an
	 * orphan heading with nothing underneath for a role that can't see
	 * any tab in that group. Capability 'read' is safe here specifically
	 * *because* that visibility decision already happened by the time
	 * this is called — every role that reaches this line can already see
	 * at least one page in the group, so gating the label itself on
	 * anything stricter would only ever hide it for someone who's about
	 * to see the exact same words as a real submenu label two lines down.
	 */
	private static function add_group_divider( string $group ): void {
		global $submenu;
		$label = self::GROUP_LABELS[ $group ] ?? ucfirst( $group );
		$submenu[ self::PAGE_SLUG ][] = array(
			'<span class="bday-menu-group-label">' . esc_html( $label ) . '</span>',
			'read',
			self::PAGE_SLUG,
		);
	}

	/**
	 * @param array<string, array<string, mixed>> $schema
	 * @return string[] schema slugs the current user can view, Editorial
	 *                   group before Technical, each group's own relative
	 *                   order otherwise unchanged (usort is stable as of
	 *                   PHP 8, which this theme already requires).
	 */
	private static function visible_slugs( array $schema ): array {
		$visible = array_values(
			array_filter(
				array_keys( $schema ),
				static function ( string $slug ): bool {
					return current_user_can( Bday_Settings_Visibility::capability_for( $slug ) );
				}
			)
		);

		usort(
			$visible,
			static function ( string $a, string $b ) use ( $schema ): int {
				$rank = static function ( string $slug ) use ( $schema ): int {
					return 'editorial' === self::group_of( $schema[ $slug ] ) ? 0 : 1;
				};
				return $rank( $a ) <=> $rank( $b );
			}
		);

		return $visible;
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
						if ( Bday_Settings_Visibility::OPTION === $tab['option'] ) {
							return Bday_Settings_Visibility::sanitize( $input );
						}
						if ( ! empty( $tab['fields'] ) ) {
							return bday_sanitize_fields( $tab['fields'], $input );
						}
						return is_array( $input ) ? $input : array();
					},
				)
			);

			add_filter(
				"option_page_capability_{$tab['option']}",
				static function () use ( $slug ): string {
					return Bday_Settings_Visibility::capability_for( $slug );
				}
			);
		}
	}

	/** Shown only if no schema entries were ever registered (no core-tabs.php, no add-ons at all). */
	public static function render_empty(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="wrap"><h1>BDay Aero Options</h1><p>No settings sections registered.</p></div>';
	}

	/** @return array<int, array{label: string, url: string, active: bool}> */
	private static function build_tabs( string $current_slug ): array {
		$schema = self::schema();
		$tabs   = array();
		foreach ( self::visible_slugs( $schema ) as $index => $slug ) {
			$page_slug = 0 === $index ? self::PAGE_SLUG : self::PAGE_SLUG . '-' . $slug;
			$tabs[]    = array(
				'label'  => $schema[ $slug ]['tab_label'] ?? $slug,
				'url'    => admin_url( 'admin.php?page=' . $page_slug ),
				'active' => $slug === $current_slug,
			);
		}
		return $tabs;
	}

	/** Renders one schema entry's own submenu page. */
	public static function render_tab( string $slug ): void {
		if ( ! current_user_can( Bday_Settings_Visibility::capability_for( $slug ) ) ) {
			return;
		}

		$schema = self::schema();
		if ( ! isset( $schema[ $slug ] ) ) {
			return;
		}
		$tab = $schema[ $slug ];

		Bday_Admin_UI::open( 'BDay Aero Options', $tab['tab_label'] ?? $slug, self::build_tabs( $slug ), $tab['intro'] ?? '' );
		?>
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
		<?php
		if ( ! empty( $tab['about'] ) ) {
			Bday_Admin_UI::start_aside();
			Bday_Admin_UI::sidebar_card( 'About this feature', $tab['about'], true );
			if ( ! empty( $tab['use_cases'] ) ) {
				$items = '<ul>';
				foreach ( (array) $tab['use_cases'] as $use_case ) {
					$items .= '<li>' . wp_kses_post( $use_case ) . '</li>';
				}
				$items .= '</ul>';
				Bday_Admin_UI::sidebar_card( 'Common use cases', $items );
			}
			Bday_Admin_UI::close( true );
		} else {
			Bday_Admin_UI::close( false );
		}
		self::render_image_field_script();
	}

	/**
	 * One shared wp.media wiring for every 'image' field on the page
	 * (field-types/render.php's markup) instead of a per-field inline
	 * script — a tab can declare any number of image fields (sidebar-
	 * promo's two slots, for instance) without each needing its own copy.
	 */
	private static function render_image_field_script(): void {
		?>
		<script>
		(function () {
			document.querySelectorAll('[data-bday-image-field]').forEach(function (field) {
				var input = field.querySelector('[data-bday-image-input]');
				var selectBtn = field.querySelector('[data-bday-image-select]');
				var removeBtn = field.querySelector('[data-bday-image-remove]');
				var preview = field.querySelector('.bday-image-field__preview');
				var frame;

				selectBtn.addEventListener('click', function (e) {
					e.preventDefault();
					if (frame) { frame.open(); return; }
					frame = wp.media({ title: 'Select image', multiple: false, library: { type: 'image' } });
					frame.on('select', function () {
						var attachment = frame.state().get('selection').first().toJSON();
						input.value = attachment.id;
						var src = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
						preview.innerHTML = '<img src="' + src + '" alt="" style="max-width:220px;height:auto;display:block;border:1px solid #ddd;">';
						removeBtn.style.display = '';
					});
					frame.open();
				});

				removeBtn.addEventListener('click', function (e) {
					e.preventDefault();
					input.value = '';
					preview.innerHTML = '';
					removeBtn.style.display = 'none';
				});
			});
		})();
		</script>
		<?php
	}
}

Bday_Options_Framework::init();
