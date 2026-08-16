<?php
/**
 * Shared branded shell for the "BusinessDay Theme" and "AeroPaywall"
 * wp-admin settings screens (reader-requested: match the polish of a
 * well-designed commercial plugin's settings UI — Leaky Paywall was the
 * reference — rather than the default WordPress Settings API look every
 * other add-on's admin page had until now).
 *
 * Deliberately a thin wrapper around the *existing* markup every
 * settings page already renders (form-table rows, buttons, etc.) rather
 * than a rewrite of each one: admin-ui.css reskins .form-table/inputs/
 * buttons wholesale, and this class only supplies the parts that didn't
 * exist before at all — the branded top bar, the tab strip, the two-
 * column shell, and the right-rail "what is this" panel. Every existing
 * render_*() callback across every addon needed zero markup changes to
 * pick this up; they just call open()/close() around what they already
 * print.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Admin_UI {

	public static function init(): void {
		add_filter( 'admin_body_class', array( self::class, 'body_class' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	private static function is_bday_admin_page(): bool {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}
		$page = (string) $_GET['page'];
		return 0 === strpos( $page, 'bday-theme-settings' ) || 0 === strpos( $page, 'aero-paywall' );
	}

	public static function body_class( string $classes ): string {
		if ( self::is_bday_admin_page() ) {
			$classes .= ' bday-admin-ui';
		}
		return $classes;
	}

	public static function enqueue(): void {
		if ( ! self::is_bday_admin_page() ) {
			return;
		}
		wp_enqueue_style(
			'bday-admin-ui-fonts',
			'https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap',
			array(),
			null
		);
		wp_enqueue_style(
			'bday-admin-ui',
			BDAY_THEME_URI . 'core/options/admin-ui.css',
			array( 'bday-admin-ui-fonts' ),
			(string) filemtime( BDAY_THEME_DIR . 'core/options/admin-ui.css' )
		);
		wp_enqueue_media();
	}

	/**
	 * Opens the branded shell: top bar, tab strip, and the two-column
	 * body (main content column starts open — caller prints its form
	 * markup as normal, then either close_main_open_aside() + one or
	 * more sidebar()/status() calls, or close() directly for a page with
	 * no sidebar content).
	 *
	 * @param string $product   e.g. "BusinessDay Theme" | "AeroPaywall"
	 * @param string $page_title e.g. "Masthead" | "Restrictions"
	 * @param array<int, array{label: string, url: string, active: bool}> $tabs
	 * @param string $intro     Optional lead paragraph explaining what this
	 *                          page/tab governs — printed inside the main
	 *                          card, above the form.
	 */
	public static function open( string $product, string $page_title, array $tabs, string $intro = '' ): void {
		?>
		<div class="bday-admin__topbar">
			<span class="bday-admin__mark">B</span>
			<div class="bday-admin__title-group">
				<span class="bday-admin__product"><?php echo esc_html( $product ); ?></span>
				<h1 class="bday-admin__page-title"><?php echo esc_html( $page_title ); ?></h1>
			</div>
		</div>
		<?php if ( ! empty( $tabs ) ) : ?>
			<nav class="bday-admin__tabs">
				<?php foreach ( $tabs as $tab ) : ?>
					<a href="<?php echo esc_url( $tab['url'] ); ?>" class="<?php echo ! empty( $tab['active'] ) ? 'is-active' : ''; ?>"><?php echo esc_html( $tab['label'] ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
		<div class="bday-admin__body">
			<main class="bday-admin__main">
				<?php if ( '' !== $intro ) : ?>
					<p class="bday-admin__intro"><?php echo wp_kses_post( $intro ); ?></p>
				<?php endif; ?>
		<?php
	}

	/** Closes the main column and opens the right-rail aside — call sidebar_card()/status_card() one or more times after this, then close(). */
	public static function start_aside(): void {
		?>
			</main>
			<aside class="bday-admin__aside">
		<?php
	}

	/** A single right-rail card: a heading plus arbitrary HTML body (paragraphs, a <ul>, whatever the caller already builds). */
	public static function sidebar_card( string $heading, string $body_html, bool $accent = false ): void {
		?>
		<div class="bday-admin__card<?php echo $accent ? ' bday-admin__card--accent' : ''; ?>">
			<h3><?php echo esc_html( $heading ); ?></h3>
			<?php echo wp_kses_post( $body_html ); ?>
		</div>
		<?php
	}

	/** Closes whichever column is currently open (main-only pages never called start_aside(), so this closes </main> instead of </aside>). */
	public static function close( bool $has_aside = true ): void {
		if ( $has_aside ) {
			?>
			</aside>
		<?php } else { ?>
			</main>
		<?php }
		?>
		</div>
		<?php
	}

	/** A small pill — "Enabled"/"Disabled"/"Not configured" — for a sidebar card's status line. */
	public static function status_pill( string $label, string $state ): string {
		$class = 'bday-admin__status--' . ( in_array( $state, array( 'on', 'off', 'warn' ), true ) ? $state : 'off' );
		return '<span class="bday-admin__status ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}
}

Bday_Admin_UI::init();
