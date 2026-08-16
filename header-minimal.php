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
	 *
	 * Reader-reported: this page previously ignored the site's light/warm/
	 * dark switcher entirely — no toggle button, and no copy of header.php's
	 * before-first-paint localStorage read, so a reader's stored preference
	 * (or an OS-level prefers-color-scheme: dark) never reached this shell.
	 * The hardcoded #fff/#111827 below made that worse: even though the
	 * page's own content components (template-subscribe.php's
	 * .bday-subscribe-* classes, _topic-list.scss) already read the same
	 * --color-* tokens body.scss uses everywhere else, this shell's fixed
	 * white background couldn't repaint with them — so dark mode ended up
	 * light text tokens over a background frozen at white, which is the
	 * "very very low readability" bug report. Same no-FOUC script as
	 * header.php, and the shell now reads the tokens too, so both are fixed
	 * together.
	 */
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
	<style>
		html, body {
			margin: 0;
			padding: 0;
			background: var(--color-bg);
			color: var(--color-ink-1);
			font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
		}
		.bday-minimal-header {
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 16px;
			padding: 24px 16px;
		}
		.bday-minimal-header__spacer {
			width: 34px;
		}
		.bday-minimal-header a {
			display: inline-flex;
		}
		.bday-minimal-header img {
			height: 32px;
			width: auto;
		}
		.bday-minimal-theme-toggle {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 34px;
			height: 34px;
			border: 1px solid var(--color-border);
			border-radius: 50%;
			background: none;
			color: var(--color-ink-1);
			cursor: pointer;
			padding: 0;
		}
		.bday-minimal-theme-toggle .bd-icon-warm,
		.bday-minimal-theme-toggle .bd-icon-dark {
			display: none;
		}
		@media (prefers-color-scheme: dark) {
			:root:not([data-theme]) .bday-minimal-theme-toggle .bd-icon-light,
			:root[data-theme="dark"] .bday-minimal-theme-toggle .bd-icon-light,
			:root:not([data-theme]) .bday-minimal-theme-toggle .bd-icon-warm,
			:root[data-theme="dark"] .bday-minimal-theme-toggle .bd-icon-warm {
				display: none;
			}
			:root:not([data-theme]) .bday-minimal-theme-toggle .bd-icon-dark,
			:root[data-theme="dark"] .bday-minimal-theme-toggle .bd-icon-dark {
				display: inline-block;
			}
		}
		:root[data-theme="dark"] .bday-minimal-theme-toggle .bd-icon-light,
		:root[data-theme="dark"] .bday-minimal-theme-toggle .bd-icon-warm {
			display: none;
		}
		:root[data-theme="dark"] .bday-minimal-theme-toggle .bd-icon-dark {
			display: inline-block;
		}
		:root[data-theme="warm"] .bday-minimal-theme-toggle .bd-icon-light,
		:root[data-theme="warm"] .bday-minimal-theme-toggle .bd-icon-dark {
			display: none;
		}
		:root[data-theme="warm"] .bday-minimal-theme-toggle .bd-icon-warm {
			display: inline-block;
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
	<button type="button" class="bday-minimal-theme-toggle" data-bd-theme-toggle aria-label="Change color theme">
		<svg class="bd-icon-light" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="4.5" stroke="currentColor" stroke-width="1.6"/><path d="M12 2.5v2.5M12 19v2.5M4.6 4.6l1.8 1.8M17.6 17.6l1.8 1.8M2.5 12H5M19 12h2.5M4.6 19.4l1.8-1.8M17.6 6.4l1.8-1.8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
		<svg class="bd-icon-warm" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3v2M12 19v2M4 12H2M22 12h-2M5.6 5.6l-1.4-1.4M19.8 19.8l-1.4-1.4M5.6 18.4l-1.4 1.4M19.8 4.2l-1.4 1.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><circle cx="12" cy="12" r="5" fill="currentColor"/></svg>
		<svg class="bd-icon-dark" width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a7 7 0 0 0 10.5 10.5z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>
	</button>
</header>

<main class="bday-minimal-main">
