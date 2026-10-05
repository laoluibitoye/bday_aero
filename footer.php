<?php
/**
 * Full rebuild — the old footer.php was still Bootstrap markup (container/
 * row/col-md-*, text-white, a CDN-loaded bootstrap.bundle.min.js nothing
 * else on the page used) untouched since before the Bday_Aero rebuild
 * started.
 *
 * Reader-reported: previously stayed fixed-dark (--bd-ink/--bd-paper) like
 * the header's utility bar, regardless of the site's light/warm/dark
 * switcher — but as a full-width block most readers scroll past on every
 * page (not a thin always-dark strip), that read as broken rather than
 * intentional. Now uses the theme-relative --color-* tokens (_footer.scss)
 * so it repaints with the rest of the page, and the logo matches
 * header.php's own bd-logo.png rather than the icon-only mark it used
 * before.
 */
$bday_footer_columns = array(
	'The Company'    => array(
		'About Us'            => 'https://about.businessday.ng/index.php',
		'BusinessDay Pro'     => 'https://pro.businessday.ng/',
		'Research & Insight'  => 'https://businessdayintelligence.ng/',
		'Conferences'         => 'https://conferences.businessday.ng/',
		'BD Fx'               => 'https://currency.businessday.ng/',
	),
	'Legal & Privacy' => array(
		'Privacy Policy' => home_url( '/app-privacy-policy/' ),
		'Copyright'      => home_url( '/copyright/' ),
	),
	'Quick Links'     => array(
		'Contact Us'      => home_url( '/contact-us/' ),
		'Adverts & Rates' => home_url( '/advert-and-rates/' ),
		'Companies'       => home_url( '/category/companies/' ),
		'Market'          => home_url( '/category/markets/' ),
		'Economy'         => home_url( '/category/business-economy/' ),
	),
);
?>
<footer class="bd-footer">
	<div class="bd-container bd-footer__inner">
		<div class="bd-footer__brand">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="bd-footer__logo" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> homepage">
				<img src="<?php echo esc_url( BDAY_THEME_URI . 'assets/build/images/bd-logo.png' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			</a>
			<p class="bd-footer__about">
				BusinessDay, established in 2001, is a daily business newspaper based in Lagos — the only Nigerian newspaper with a bureau in Accra, Ghana. It publishes both daily and Sunday titles, circulating across Nigeria and Ghana.
				<a href="https://about.businessday.ng/index.php">Read more</a>
			</p>
		</div>

		<div class="bd-footer__columns">
			<?php foreach ( $bday_footer_columns as $bday_footer_heading => $bday_footer_links ) : ?>
				<div class="bd-footer__column">
					<h2><?php echo esc_html( $bday_footer_heading ); ?></h2>
					<ul>
						<?php foreach ( $bday_footer_links as $bday_link_label => $bday_link_url ) : ?>
							<li><a href="<?php echo esc_url( $bday_link_url ); ?>"><?php echo esc_html( $bday_link_label ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>

			<div class="bd-footer__column">
				<h2>Support</h2>
				<ul>
					<li><a href="mailto:digitalsales@businessday.ng">digitalsales@<wbr />businessday.ng</a></li>
					<li><a href="tel:+2348033042209">+234 803 304 2209</a></li>
					<li><a href="tel:+2348026011296">+234 802 601 1296</a></li>
					<li><a href="tel:+2348138243822">+234 813 824 3822</a></li>
				</ul>
				<?php
				/**
				 * Reader-requested (2026-10-05): same "Download App" modal
				 * as the header trigger (bdayInitDownloadAppModal() in
				 * script.js binds every [data-bd-download-app-trigger] on
				 * the page, not just the header's).
				 */
				?>
				<button type="button" class="bd-footer__download-app" data-bd-download-app-trigger>Download App</button>
			</div>
		</div>
	</div>

	<div class="bd-footer__bottom">
		<div class="bd-container bd-footer__bottom-row">
			<span>&copy; BusinessDay Media Ltd <?php echo esc_html( date_i18n( 'Y' ) ); ?>.</span>
			<span class="bd-footer__credit">All rights reserved.</span>
		</div>
	</div>
</footer>

<?php
/**
 * Reader-requested (2026-10-05): the "Download App" modal — a QR code
 * plus a single store-agnostic download link (onelink.to resolves to the
 * right store per device), opened from the header trigger beside Today's
 * Paper and the footer trigger in Support. Rendered once, globally, here
 * (not per-section like the market-pulse popup) since both triggers live
 * on every page. bdayInitDownloadAppModal() (script.js) binds every
 * [data-bd-download-app-trigger] on the page to this one dialog.
 */
?>
<div class="bd-download-app-modal" id="bd-download-app-modal" role="dialog" aria-modal="true" aria-labelledby="bd-download-app-modal-title" hidden>
	<div class="bd-download-app-modal__card">
		<button type="button" class="bd-download-app-modal__close" data-bd-download-app-close aria-label="Close">&times;</button>
		<h2 class="bd-download-app-modal__title" id="bd-download-app-modal-title">Download the BusinessDay App</h2>
		<img class="bd-download-app-modal__qr" src="<?php echo esc_url( BDAY_THEME_URI . 'assets/build/images/app-download-qr.png' ); ?>" alt="QR code to download the BusinessDay app" width="180" height="180" loading="lazy">
		<p class="bd-download-app-modal__caption">Scan to download</p>
		<a class="bd-download-app-modal__cta" href="https://onelink.to/p9exap" target="_blank" rel="noopener">
			<i class="bi bi-apple" aria-hidden="true"></i>
			<i class="bi bi-google-play" aria-hidden="true"></i>
			Click here to download
		</a>
	</div>
</div>

<?php
/**
 * Reader-requested (2026-10-06): site-wide popups (addons/popups/) —
 * each matching, published popup's markup + trigger/device config, for
 * bdayInitPopups() (script.js) to act on. No-ops entirely when no popup
 * matches the current page (render.php's own early return).
 */
do_action( 'bday_popup_zone' );
?>

<?php wp_footer(); ?>
</body>
</html>
