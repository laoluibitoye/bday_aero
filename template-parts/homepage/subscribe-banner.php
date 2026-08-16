<?php
/**
 * Homepage subscribe banner (WSJ-layout adoption's "Special Offer" strip).
 * CTA is a plain link to the register flow, not a call into
 * openSubscribeModal() (sdk/src/subscribe-modal.ts) — that function reads
 * an existing reader JWT via getAccessTokenFromCookie() and silently
 * no-ops without one, which is exactly the visitor this banner targets.
 * Same class of "looks wired, does nothing" bug already found once this
 * session in the old nav-button stub; avoided here by not reusing a
 * component built for a signed-in flow.
 *
 * No server-side "hide if already subscribed" check — this theme has no
 * way to read a reader's JWT-cookie subscription state at PHP render
 * time (by design, the SDK owns that client-side); the whole `<section>`
 * carries `data-aero-subscribe-cta` instead, so nav-sync.ts's
 * syncNavMenuState() (which already resolves subscriptionStatus for the
 * flyout nav) hides it client-side the moment it confirms an active
 * subscription/org membership — a full-banner hide, not just the button,
 * since the entire banner's copy is a pitch to subscribe.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$register_url = add_query_arg( 'tab', 'register', bday_paywall_login_url() );
?>
<section class="bday-subscribe-banner" data-aero-subscribe-cta>
	<div class="bday-container bday-subscribe-banner__inner">
		<span class="bday-subscribe-banner__eyebrow">Special Offer</span>
		<h2 class="bday-subscribe-banner__headline">Unlimited access to BusinessDay's reporting and analysis</h2>
		<p class="bday-subscribe-banner__subcopy">Subscribe today for full access to every story, the daily e-paper, and our archive.</p>
		<a href="<?php echo esc_url( $register_url ); ?>" class="bday-subscribe-banner__cta">Subscribe Now</a>
	</div>
</section>
