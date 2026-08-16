<?php
/**
 * Section Name: Newsletter
 * Section Slug: newsletter
 * Description: Dark subscribe banner — its button goes to the real Subscribe page (Bday_Aero_Nav_Button::urls(), the same resolver the header's own "Subscribe Now" button already uses), not a guessed URL.
 * Default Enabled: yes
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nav_urls     = class_exists( 'Bday_Aero_Nav_Button' ) ? Bday_Aero_Nav_Button::urls() : array();
$subscribe_url = $nav_urls['subscribe'] ?? '';
if ( '' === $subscribe_url ) {
	return; // AeroPaywall isn't configured (no account page set) — no real subscribe destination to send readers to.
}
?>
<section class="bday-rd-newsletter" data-screen-label="Newsletter">
	<div class="bday-container bday-rd-newsletter__grid">
		<div class="bday-rd-newsletter__copy">
			<span class="bday-rd-kicker bday-rd-kicker--tint">Membership</span>
			<h2>Subscribe to BusinessDay</h2>
			<p>Unlimited access to BD Pro analysis, the daily e-paper, every newsletter and the full archive.</p>
		</div>
		<div class="bday-rd-newsletter__cta">
			<a href="<?php echo esc_url( $subscribe_url ); ?>" class="bday-rd-btn bday-rd-btn--pill" data-aero-subscribe-cta>Subscribe Now</a>
			<span class="bday-rd-kicker bday-rd-kicker--faint">Cancel any time · Free newsletters included</span>
		</div>
	</div>
</section>
