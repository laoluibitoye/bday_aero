<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php
	/**
	 * Deliberately not header.php: this is templates/template-funnel.php's
	 * own header — no ad-network preconnects, no vendor/analytics scripts,
	 * no main navbar, none of header.php's masthead styling. wp_head() is
	 * still required — it's the hook core/custom-code.php's header-code
	 * injection fires on, and where any future SDK/tracking script needs
	 * to land.
	 */
	wp_head();
	?>
	<style>
		html, body {
			margin: 0;
			padding: 0;
			background: #fff;
			color: #111827;
			font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
		}
		.bday-minimal-header {
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 24px 16px;
		}
		.bday-minimal-header a {
			display: inline-flex;
		}
		.bday-minimal-header img {
			height: 32px;
			width: auto;
		}
		.bday-minimal-main {
			max-width: 1100px;
			margin: 0 auto;
			padding: 24px 16px 64px;
			box-sizing: border-box;
		}
	</style>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="bday-minimal-header">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
		<img src="<?php echo esc_url( BDAY_THEME_URI . 'assets/build/images/businessday.png' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
	</a>
</header>

<main class="bday-minimal-main">
