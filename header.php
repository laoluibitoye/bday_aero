<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php
	/*
	 * Page shell only. Every vendor/analytics/ad script that used to be
	 * hardcoded here (the old header.php was ~1,500 lines of script soup)
	 * now prints through addons/vendors/ drivers on wp_head/wp_footer,
	 * each independently configurable and kill-switchable; the Custom Code
	 * injection slots are core/custom-code.php.
	 */
	?>
	<link rel="preconnect" href="https://fonts.gstatic.com">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://securepubads.g.doubleclick.net">
	<link rel="preconnect" href="https://pagead2.googlesyndication.com">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="bday-site-header">
	<?php do_action( 'bday_homepage_leaderboard_zone' ); ?>

	<section class="bday-topbar">
		<div class="bday-container">
			<span><?php echo esc_html( date_i18n( 'l, F d, Y' ) ); ?></span>
			<span class="bday-topbar__clock" data-bday-clock aria-live="off"></span>
		</div>
	</section>

	<section class="bday-masthead desktop-only">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Homepage">
			<img class="logo-banner" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> logo" src="<?php echo esc_url( BDAY_THEME_URI . 'assets/build/images/bd-logo.png' ); ?>">
		</a>
	</section>

	<nav class="navbar navbar-expand-lg navbar-light main-menu">
		<div class="container-fluid">
			<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#bdayMainNav" aria-controls="bdayMainNav" aria-expanded="false" aria-label="Toggle navigation">
				<span class="navbar-toggler-icon"></span>
			</button>

			<div class="collapse navbar-collapse" id="bdayMainNav">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'main_menu',
						'menu_class'     => 'navbar-nav',
						'container'      => '',
						'walker'         => new Bday_Nav_Walker(),
					)
				);
				?>
			</div>

			<div class="mobile-logo">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Homepage">
					<img alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> logo" src="<?php echo esc_url( BDAY_THEME_URI . 'assets/build/images/businessday.png' ); ?>">
				</a>
			</div>

			<div class="menu-action">
				<ul>
					<?php
					$bday_masthead   = get_option( 'bday_masthead', array() );
					$bday_cta_label  = is_array( $bday_masthead ) ? ( $bday_masthead['cta_label'] ?? '' ) : '';
					$bday_cta_url    = is_array( $bday_masthead ) ? ( $bday_masthead['cta_url'] ?? '' ) : '';
					if ( '' !== $bday_cta_label && '' !== $bday_cta_url ) :
						?>
						<li class="menu-cta-item">
							<a class="bday-header-cta" href="<?php echo esc_url( $bday_cta_url ); ?>"><?php echo esc_html( $bday_cta_label ); ?></a>
						</li>
					<?php endif; ?>
					<li>
						<a href="<?php echo esc_url( home_url( '/search-page/' ) ); ?>" aria-label="Search">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>
						</a>
					</li>
					<li class="user-menu-item dropdown">
						<?php
						$bday_is_logged_in = is_user_logged_in();
						$bday_user_name    = '';
						if ( $bday_is_logged_in ) {
							$bday_user  = wp_get_current_user();
							$bday_user_name = trim( $bday_user->first_name . ' ' . $bday_user->last_name ) ?: $bday_user->display_name;
							$bday_user_name = ucwords( strtolower( $bday_user_name ) );
						}
						?>
						<a href="#" class="dropdown-toggle" id="bdayUserMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="User account">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 4c1.93 0 3.5 1.57 3.5 3.5S13.93 13 12 13s-3.5-1.57-3.5-3.5S10.07 6 12 6zm0 14c-2.03 0-4.43-.82-6.14-2.88C7.55 15.8 9.68 15 12 15s4.45.8 6.14 2.12C16.43 19.18 14.03 20 12 20z"/></svg>
							<span class="user-menu-label"><?php echo esc_html( $bday_is_logged_in ? $bday_user_name : 'Login / Signup' ); ?></span>
						</a>
						<ul class="dropdown-menu dropdown-menu-end" aria-labelledby="bdayUserMenu">
							<?php if ( $bday_is_logged_in ) : ?>
								<li class="dropdown-header">
									<div class="user-welcome">Welcome,</div>
									<div class="user-name text-truncate"><?php echo esc_html( $bday_user_name ); ?></div>
								</li>
								<li><a class="dropdown-item" href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a></li>
								<li><a class="dropdown-item" href="<?php echo esc_url( bday_paywall_login_url() ); ?>">My Account</a></li>
								<li><a class="dropdown-item text-danger" href="<?php echo esc_url( bday_direct_logout_url() ); ?>">Log Out</a></li>
							<?php else : ?>
								<li><a class="dropdown-item" href="<?php echo esc_url( bday_paywall_login_url() ); ?>">Log In / Sign Up</a></li>
							<?php endif; ?>
						</ul>
					</li>
					<li class="offcanvvas-toggler">
						<a href="#bdayOffcanvas" aria-label="Menu" data-bs-toggle="offcanvas" role="button" aria-controls="bdayOffcanvas">
							<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/></svg>
						</a>
					</li>
				</ul>
			</div>
		</div>
	</nav>
</header>

<div class="offcanvas offcanvas-start" tabindex="-1" id="bdayOffcanvas" aria-labelledby="bdayOffcanvasLabel">
	<div class="offcanvas-header">
		<button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close">X</button>
	</div>
	<div class="offcanvas-body">
		<p class="text-center"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><img src="<?php echo esc_url( BDAY_THEME_URI . 'assets/build/images/businessday.png' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"></a></p>
		<p class="site-title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
		<div class="search">
			<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<input type="search" name="s" value="" placeholder="Search...">
			</form>
		</div>
		<div class="menu">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'secondary_menu',
					'container'      => '',
					'walker'         => new Bday_Nav_Walker(),
				)
			);
			?>
		</div>
	</div>
</div>

<?php
// TradingView FX ticker — a vendor driver with its own toggle, rendered
// inline here (not via wp_head) because it's page furniture, not tracking.
$bday_tradingview = function_exists( 'bday_vendor' ) ? bday_vendor( 'tradingview' ) : null;
if ( $bday_tradingview instanceof Bday_Vendor_Tradingview ) {
	$bday_tradingview->render();
}

// Live-match ticker — dispatches to zero listeners unless the add-on is
// enabled (its code is then never even loaded, let alone queried).
do_action( 'bday_header_ticker_zone' );
?>
<script>
// Topbar live-local-time clock — dependency-free, matches the theme's
// existing no-jQuery convention. Ticks client-side only; the server-
// rendered date next to it is what search engines/no-JS visitors see.
(function () {
	var el = document.querySelector( '[data-bday-clock]' );
	if ( ! el ) {
		return;
	}
	function tick() {
		el.textContent = new Date().toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } );
	}
	tick();
	setInterval( tick, 30000 );
})();
</script>
