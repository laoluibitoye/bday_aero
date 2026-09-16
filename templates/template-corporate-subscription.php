<?php
/**
 * Template Name: Corporate Subscription
 *
 * A sales-assisted "talk to us" lead-capture page — distinct from the self-serve Corporate toggle
 * on template-subscribe.php (instant checkout for a reader who already knows their plan). Content
 * ported from the current live site's own Corporate Subscriptions page; the form itself is
 * rendered by the SDK (corporate-subscription.ts) into #aero-corporate-subscription-mount, since
 * the team-size dropdown options and receiver emails are admin-editable (Aero Admin Console →
 * Marketing → Corporate Inquiries) rather than baked into this template.
 *
 * Reader-reported: this page used to carry its own bespoke inline <style> block — a different
 * font stack, hardcoded near-black instead of the theme's --bd-ink, and a subscription-type
 * radio pair that visibly broke (see the matching CSS fix in sdk/src/styles.ts). Rebuilt on the
 * same hero/value-grid/banner classes template-subscribe.php already established
 * (assets/src/scss/components/_topic-list.scss) so this page reads as part of the same site
 * rather than a one-off landing page with its own visual identity — same get_header()/
 * get_footer() every other account-flow page already uses, same tokens, same Georgia headline
 * face for section titles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$bday_corp_benefits = array(
	array(
		'title' => 'Client-Facing Roles',
		'desc'  => 'Become the subject matter expert and land the next deal with relevant, timely business news.',
		'icon'  => '<path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H6l-3 3V11.5a8.5 8.5 0 0 1 8.5-8.5h1A8.5 8.5 0 0 1 21 11.5z"/>',
	),
	array(
		'title' => 'Business Analysts &amp; Researchers',
		'desc'  => 'Build, validate, and deliver actionable recommendations with trusted and accessible business information and analysis.',
		'icon'  => '<circle cx="10" cy="10" r="6"/><path d="m20.5 20.5-5-5"/>',
	),
	array(
		'title' => 'Procurement &amp; Vendor Specialists',
		'desc'  => 'Increase value with subscription bundles that offer immediacy, exclusivity, and reliability.',
		'icon'  => '<path d="M21 8 12 3 3 8l9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/>',
	),
	array(
		'title' => 'Authoritative, Expert Insights',
		'desc'  => 'Breaking and in-depth coverage across hundreds of topics and industry segments provides an authoritative voice in business and financial news.',
		'icon'  => '<path d="M3 11v2a1 1 0 0 0 1 1h2l4 4V6l-4 4H4a1 1 0 0 0-1 1z"/><path d="M14 8a4 4 0 0 1 0 8"/><path d="M17 5a8 8 0 0 1 0 14"/>',
	),
	array(
		'title' => 'Robust Business &amp; Financial Data',
		'desc'  => 'Comprehensive company profiles, market and economic data to create actionable business insights.',
		'icon'  => '<path d="M4 20V10"/><path d="M10 20V4"/><path d="M16 20v-7"/><path d="M4 20h16"/>',
	),
	array(
		'title' => 'Flexible and Easy to Manage',
		'desc'  => 'Multiple corporate signup options with simple onboarding, so your team spends less time on admin.',
		'icon'  => '<path d="M4 6h4M12 6h8"/><circle cx="8" cy="6" r="2"/><path d="M4 12h8M16 12h4"/><circle cx="14" cy="12" r="2"/><path d="M4 18h4M12 18h8"/><circle cx="8" cy="18" r="2"/>',
	),
);
?>
<main>
<section class="bday-corporate-hero">
	<div class="bday-corporate-hero__inner">
		<div class="bday-corporate-hero__text">
			<span class="bday-corporate-hero__eyebrow">BusinessDay Corporate Subscriptions</span>
			<h1 class="bday-corporate-hero__title">Give your organisation the credible, current and complete business intelligence it needs to see clearly and move first.</h1>
			<p class="bday-corporate-hero__lead">Every day, the decisions you take as a leader determine where your company goes next. BusinessDay supports those decisions by bringing award-winning journalism, market data and expert analysis together on one platform — relevant to your world, shaped around your needs, and built to power growth across every level of your business.</p>
		</div>
		<div class="bday-corporate-hero__form">
			<div id="aero-corporate-subscription-mount"></div>
		</div>
	</div>
</section>

<section class="bday-corporate-values">
	<div class="bday-corporate-values__inner">
		<h2 class="bday-corporate-values__title">Empower Critical Business Decisions</h2>
		<div class="bday-corporate-values__grid">
			<?php foreach ( $bday_corp_benefits as $bday_benefit ) : ?>
				<div class="bday-corporate-value">
					<span class="bday-corporate-value__icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?php echo $bday_benefit['icon']; // phpcs:ignore -- static, hand-authored inline SVG paths, not user input ?></svg>
					</span>
					<h3><?php echo wp_kses_post( $bday_benefit['title'] ); ?></h3>
					<p><?php echo esc_html( $bday_benefit['desc'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="bday-corporate-values__tagline">Become one of the many companies that empower their workforce with a Corporate Subscription.</p>
	</div>
</section>

<section class="bday-subscribe-banner">
	<div class="bday-subscribe-banner__inner">
		<h2 class="bday-subscribe-banner__headline">Get the trusted resource your team needs</h2>
		<a href="#aero-corporate-subscription-mount" class="bday-subscribe-banner__cta">Get Pricing</a>
	</div>
</section>
</main>
<?php
get_footer();
