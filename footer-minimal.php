<?php
/**
 * Pairs with header-minimal.php / templates/template-funnel.php. Not a
 * stripped-down copy of footer.php — that file's dark, multi-column
 * sitemap footer depends on header.php's own stylesheet being loaded,
 * which header-minimal.php deliberately doesn't do. This is a real,
 * small, self-contained footer instead, with no dependency on any other
 * template's CSS.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	<style>
		.bday-minimal-footer {
			margin-top: 48px;
			padding: 24px 16px 32px;
			border-top: 1px solid var(--color-border);
			text-align: center;
			font: 13px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
			color: var(--color-ink-3);
		}
		.bday-minimal-footer a {
			color: var(--color-ink-3);
			text-decoration: underline;
		}
		.bday-minimal-footer a:hover {
			color: var(--color-ink-1);
		}
		.bday-minimal-footer nav {
			margin-bottom: 8px;
		}
		.bday-minimal-footer nav a {
			margin: 0 8px;
		}
	</style>
	<footer class="bday-minimal-footer">
		<nav>
			<a href="<?php echo esc_url( home_url( '/app-privacy-policy/' ) ); ?>">Privacy Policy</a>
			<a href="<?php echo esc_url( home_url( '/copyright/' ) ); ?>">Copyright</a>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a>
		</nav>
		<p>&copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> BUSINESSDAY MEDIA LTD.</p>
	</footer>
	<?php
	/**
	 * core/custom-code.php's footer-code injection and any Vite-enqueued
	 * assets still need this — a minimal chrome doesn't mean a minimal
	 * asset pipeline, it means no navbar/masthead markup.
	 */
	wp_footer();
	?>
</body>
</html>
