<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php
	/*
	 * Page shell only. Every vendor/analytics/ad script that used to be
	 * hardcoded here now prints through addons/vendors/ drivers on
	 * wp_head/wp_footer; the Custom Code injection slots are
	 * core/custom-code.php.
	 */
	?>
	<link rel="preconnect" href="https://securepubads.g.doubleclick.net">
	<link rel="preconnect" href="https://pagead2.googlesyndication.com">
	<link rel="icon" href="<?php echo esc_url( BDAY_THEME_URI . 'assets/build/images/bd-icon.png' ); ?>" sizes="any">
	<?php
	// Sets --color-*/[data-theme] before first paint from the reader's
	// stored preference (one of 'light' | 'warm' | 'dark'), so there's no
	// flash-of-wrong-theme between the CSS default and script.js reading
	// localStorage after load. Inline and tiny on purpose.
	?>
	<script>
	(function () {
		try {
			var t = localStorage.getItem( 'bd-theme' );
			if ( t === 'dark' || t === 'warm' || t === 'light' ) {
				document.documentElement.setAttribute( 'data-theme', t );
			}
		} catch ( e ) {}
	})();
	</script>
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php do_action( 'bday_homepage_leaderboard_zone' ); ?>

<header class="bd-header" data-bd-header>
	<div class="bd-header__utility">
		<div class="bd-container bd-header__utility-row">
			<div class="bd-header__utility-left">
				<span class="bd-header__date"><?php echo esc_html( date_i18n( 'l, F j, Y' ) ); ?></span>
			</div>

			<?php
			/**
			 * World-clock strip — inspired by semafor.com's D.C./Brussels/
			 * Lagos/Riyadh/Beijing/Singapore row (deliberately readopted,
			 * not the old bare local-time clock this theme previously
			 * dropped as "no real reader value": the earlier version was
			 * one ticking clock with no editorial point; this is a curated
			 * set of financial-centre times that's actually meaningful for
			 * a business/markets audience trading across time zones).
			 * Ticks client-side (script.js) — server-rendered initial
			 * values would go stale between cache hits.
			 */
			$bday_masthead_opt      = get_option( 'bday_masthead', array() );
			$bday_clocks_raw        = is_array( $bday_masthead_opt ) ? ( $bday_masthead_opt['world_clocks'] ?? '' ) : '';
			$bday_clocks_default    = 'Lagos:Africa/Lagos,London:Europe/London,New York:America/New_York,Dubai:Asia/Dubai';
			$bday_world_clocks      = array();
			foreach ( explode( ',', $bday_clocks_raw ?: $bday_clocks_default ) as $bday_clock_pair ) {
				$bday_clock_parts = explode( ':', trim( $bday_clock_pair ), 2 );
				if ( 2 === count( $bday_clock_parts ) && trim( $bday_clock_parts[0] ) && trim( $bday_clock_parts[1] ) ) {
					$bday_world_clocks[ trim( $bday_clock_parts[0] ) ] = trim( $bday_clock_parts[1] );
				}
			}
			?>
			<div class="bd-header__clocks" aria-label="World markets clock">
				<?php foreach ( $bday_world_clocks as $bday_city => $bday_tz ) : ?>
					<span class="bd-header__clock" data-bd-clock data-bd-clock-tz="<?php echo esc_attr( $bday_tz ); ?>">
						<span class="bd-header__clock-city"><?php echo esc_html( $bday_city ); ?></span>
						<span class="bd-header__clock-time" data-bd-clock-time>--:--</span>
					</span>
				<?php endforeach; ?>
			</div>

			<div class="bd-header__utility-right">
				<?php
				$bday_masthead  = get_option( 'bday_masthead', array() );
				$bday_cta_label = is_array( $bday_masthead ) ? ( $bday_masthead['cta_label'] ?? '' ) : '';
				$bday_cta_url   = is_array( $bday_masthead ) ? ( $bday_masthead['cta_url'] ?? '' ) : '';
				if ( '' !== $bday_cta_label && '' !== $bday_cta_url ) :
					?>
					<a class="bd-header__cta" href="<?php echo esc_url( $bday_cta_url ); ?>"><?php echo esc_html( $bday_cta_label ); ?></a>
				<?php endif; ?>
				<?php
				/**
				 * Language-picker trigger — lives in the utility bar, not
				 * the masthead, since it's always-dark chrome regardless
				 * of site theme, same posture as the world clocks/theme
				 * toggle right next to it (reader-requested move). Addon
				 * owns the actual Google Website Translator embed (script
				 * + init); this hook point is a no-op if that add-on is
				 * disabled, same "dead air, not an empty gap" convention
				 * as the ticker zone below the header.
				 */
				do_action( 'bday_header_translate_zone' );
				?>
				<?php
				/**
				 * Three-state toggle (Deep Dive follow-up): light -> warm
				 * (sepia, reduced blue light) -> dark -> back to light.
				 * One button cycling three icons rather than three separate
				 * buttons/a dropdown — same interaction cost as the old
				 * two-state switch, no added chrome.
				 */
				?>
				<button type="button" class="bd-header__theme-toggle" data-bd-theme-toggle aria-label="Change color theme">
					<svg class="bd-icon-light" width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4.5" stroke="currentColor" stroke-width="1.6"/><path d="M12 2.5v2.5M12 19v2.5M4.6 4.6l1.8 1.8M17.6 17.6l1.8 1.8M2.5 12H5M19 12h2.5M4.6 19.4l1.8-1.8M17.6 6.4l1.8-1.8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
					<svg class="bd-icon-warm" width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v2M12 19v2M4 12H2M22 12h-2M5.6 5.6l-1.4-1.4M19.8 19.8l-1.4-1.4M5.6 18.4l-1.4 1.4M19.8 4.2l-1.4 1.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="12" r="5" fill="currentColor"/></svg>
					<svg class="bd-icon-dark" width="17" height="17" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
				</button>
			</div>
		</div>
	</div>

	<?php
	/**
	 * Forex/commodity ticker (TradingView, addons/vendors/) sits directly
	 * below the utility bar, above the masthead — reader-requested
	 * placement matching businessday.ng's own live layout. Full-bleed by
	 * design (the widget's own container, no .bd-container wrapper).
	 */
	$bday_tradingview = function_exists( 'bday_vendor' ) ? bday_vendor( 'tradingview' ) : null;
	if ( $bday_tradingview instanceof Bday_Vendor_Tradingview ) {
		$bday_tradingview->render();
	}
	?>

	<div class="bd-header__masthead">
		<div class="bd-container bd-header__masthead-row">
			<div class="bd-header__masthead-left">
				<?php
				/**
				 * Desktop-hidden only (_header.scss) — at that width the
				 * category nav row already sits directly below this one,
				 * so the hamburger's overlay just repeats links already
				 * reachable without opening anything. Mobile has no such
				 * row (no horizontal space for it), so it keeps the toggle
				 * unchanged there — this is the only way into the nav at
				 * that width.
				 */
				?>
				<button type="button" class="bd-header__menu-toggle" data-bd-menu-toggle aria-label="Open menu" aria-expanded="false" aria-controls="bd-menu-overlay">
					<svg width="22" height="16" viewBox="0 0 22 16" fill="none" aria-hidden="true"><path d="M0 1h22M0 8h22M0 15h22" stroke="currentColor" stroke-width="1.6"/></svg>
					<span class="bd-header__menu-toggle-label">Menu</span>
				</button>
				<?php
				/**
				 * Reader-requested placement: a dedicated Today's Paper
				 * link on the left of the logo (moved here from the
				 * masthead-right, and dropped entirely from the utility
				 * bar above — one prominent entry point, not three).
				 * bday_epaper_url() resolves to the real Today's Paper
				 * page (addons/todays-paper/) rather than the plain
				 * e-paper category archive.
				 */
				?>
				<a class="bd-header__todays-paper" href="<?php echo esc_url( bday_epaper_url() ); ?>">Today's Paper</a>
			</div>

			<div class="bd-header__brand">
				<a class="bd-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> homepage">
					<img src="<?php echo esc_url( BDAY_THEME_URI . 'assets/build/images/bd-logo.png' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				</a>
				<?php
				$bday_tagline = is_array( $bday_masthead_opt ) ? ( $bday_masthead_opt['tagline'] ?? 'Tracking Trends | Informing Decisions' ) : 'Tracking Trends | Informing Decisions';
				if ( $bday_tagline ) :
					?>
					<p class="bd-header__tagline"><?php echo esc_html( $bday_tagline ); ?></p>
				<?php endif; ?>
			</div>

			<div class="bd-header__masthead-right">
				<?php
				/**
				 * Reader auth is a JWT-cookie concern the SDK owns
				 * client-side, not WordPress's own session login — a
				 * paying reader is never a wp_users row. The flyout
				 * (nav-menu.ts) starts logged-out on every server render
				 * and reveals the verified state in place; a static
				 * is_user_logged_in() check here would just always read
				 * "logged out" for every real reader, the exact "carried
				 * over from the legacy theme" auth bug flagged for fixing.
				 */
				if ( function_exists( 'bday_aero_nav_button' ) ) {
					$bday_nav_urls = class_exists( 'Bday_Aero_Nav_Button' ) ? Bday_Aero_Nav_Button::urls() : array();
					if ( ! empty( $bday_nav_urls['subscribe'] ) ) {
						?>
						<a class="bd-header__subscribe-now" href="<?php echo esc_url( $bday_nav_urls['subscribe'] ); ?>" data-aero-subscribe-cta>Subscribe Now</a>
						<?php
					}
					bday_aero_nav_button();
				} else {
					?>
					<a class="bd-header__login" href="<?php echo esc_url( bday_paywall_login_url() ); ?>">Log In</a>
					<a class="bd-header__subscribe" href="<?php echo esc_url( bday_paywall_login_url() ); ?>">Subscribe</a>
					<?php
				}
				?>
			</div>
		</div>
	</div>

	<nav class="bd-header__nav" aria-label="Primary">
		<div class="bd-container bd-header__nav-row">
			<?php
			/**
			 * Reader-requested: nav items centered, search moved out of
			 * the masthead to the far right of this row instead. The grid
			 * (CSS) gives the centered <ul> an empty balancing column on
			 * the left rather than just `margin: 0 auto`, which would
			 * drift off-true-center once this asymmetric search sibling
			 * sits on one side only.
			 */
			wp_nav_menu(
				array(
					'theme_location' => 'main_menu',
					'menu_class'     => 'bd-header__nav-list',
					'container'      => '',
					'walker'         => new Bday_Nav_Walker(),
				)
			);
			?>
			<button type="button" class="bd-header__search-toggle" data-bd-search-toggle aria-label="Open search" aria-expanded="false" aria-controls="bd-search-overlay">
				<svg width="19" height="19" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>
				<span class="bd-header__search-toggle-label">Search</span>
			</button>
		</div>
	</nav>

	<?php
	/**
	 * Reader-reported: the ticker used to sit *after* `</header>` — a
	 * plain sibling in normal document flow, not part of `.bd-header`'s
	 * own `position: sticky` unit. That meant it scrolled away with the
	 * page like any other content, and once the reader scrolled back up
	 * far enough for the sticky header to re-pin itself at the top, it
	 * visually landed *on top of* wherever the ticker happened to be
	 * sitting in the flow underneath — reading as the ticker "sliding
	 * under the menu." Moved inside `<header>` instead (last child, right
	 * after the nav row) so it's part of the exact same sticky block as
	 * everything above it — it now always sticks directly beneath the nav
	 * regardless of scroll position, and correctly shrinks/grows in place
	 * along with the rest of the header's own `.is-scrolled` condensed
	 * state rather than needing a separately-computed fixed offset.
	 */
	do_action( 'bday_header_ticker_zone' );
	?>
</header>

<div class="bd-menu-overlay bd-glass" id="bd-menu-overlay" data-bd-menu-overlay hidden>
	<div class="bd-container bd-menu-overlay__inner">
		<div class="bd-menu-overlay__top">
			<a class="bd-header__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> homepage">
				<img src="<?php echo esc_url( BDAY_THEME_URI . 'assets/build/images/bd-logo.png' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			</a>
			<div class="bd-menu-overlay__top-actions">
				<?php
				/**
				 * Mobile's own entry point to search — the desktop
				 * instance (bd-header__nav's nav row) is hidden below
				 * that breakpoint along with the rest of that row, so
				 * this is genuinely the only way to reach search on a
				 * small screen, not a duplicate.
				 */
				?>
				<button type="button" class="bd-header__search-toggle" data-bd-search-toggle aria-label="Open search" aria-expanded="false" aria-controls="bd-search-overlay">
					<svg width="19" height="19" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>
				</button>
				<button type="button" class="bd-menu-overlay__close" data-bd-menu-close aria-label="Close menu">
					<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M1 1l16 16M17 1L1 17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
				</button>
			</div>
		</div>
		<nav class="bd-menu-overlay__nav" aria-label="Menu">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'secondary_menu',
					'menu_class'     => 'bd-menu-overlay__list',
					'container'      => '',
					'walker'         => new Bday_Nav_Walker(),
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>
		<div class="bd-menu-overlay__actions">
			<?php
			// Second, independent trigger+panel instance — same contract
			// as the masthead one above, own open/close state.
			if ( function_exists( 'bday_aero_nav_button' ) ) {
				$bday_nav_urls = class_exists( 'Bday_Aero_Nav_Button' ) ? Bday_Aero_Nav_Button::urls() : array();
				if ( ! empty( $bday_nav_urls['subscribe'] ) ) {
					?>
					<a class="bd-header__subscribe-now" href="<?php echo esc_url( $bday_nav_urls['subscribe'] ); ?>" data-aero-subscribe-cta>Subscribe Now</a>
					<?php
				}
				bday_aero_nav_button();
			} else {
				?>
				<a class="bd-header__login" href="<?php echo esc_url( bday_paywall_login_url() ); ?>">Log In</a>
				<a class="bd-header__subscribe" href="<?php echo esc_url( bday_paywall_login_url() ); ?>">Subscribe</a>
				<?php
			}
			?>
		</div>
	</div>
</div>

<div class="bd-search-overlay bd-glass" id="bd-search-overlay" data-bd-search-overlay hidden>
	<div class="bd-container bd-search-overlay__inner">
		<form class="bd-search-overlay__form" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<svg width="19" height="19" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>
			<div class="bd-search-overlay__field">
				<input type="search" name="s" data-bd-search-input placeholder="Search BusinessDay" aria-label="Search">
				<span class="bd-search-overlay__badge">
					<svg class="bd-search-overlay__google-g" viewBox="0 0 48 48" aria-hidden="true">
						<path fill="#4285F4" d="M45.12 24.5c0-1.56-.14-3.06-.4-4.5H24v8.51h11.84c-.51 2.75-2.06 5.08-4.39 6.64v5.52h7.11c4.16-3.83 6.56-9.47 6.56-16.17z"/>
						<path fill="#34A853" d="M24 46c5.94 0 10.92-1.97 14.56-5.33l-7.11-5.52c-1.97 1.32-4.49 2.1-7.45 2.1-5.73 0-10.58-3.87-12.31-9.07H4.34v5.7C7.96 41.07 15.4 46 24 46z"/>
						<path fill="#FBBC05" d="M11.69 28.18A13.93 13.93 0 0 1 10.94 24c0-1.45.25-2.86.75-4.18v-5.7H4.34A21.93 21.93 0 0 0 2 24c0 3.55.85 6.91 2.34 9.88l7.35-5.7z"/>
						<path fill="#EA4335" d="M24 10.75c3.23 0 6.13 1.11 8.41 3.29l6.31-6.31C34.91 4.18 29.93 2 24 2 15.4 2 7.96 6.93 4.34 14.12l7.35 5.7c1.73-5.2 6.58-9.07 12.31-9.07z"/>
					</svg>
					Enhanced by Google
				</span>
			</div>
		</form>
		<button type="button" class="bd-search-overlay__close" data-bd-search-close aria-label="Close search">
			<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M1 1l16 16M17 1L1 17" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
		</button>
	</div>
</div>
