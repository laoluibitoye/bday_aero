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
 * Deliberately styled as its own self-contained landing page (scoped inline styles below) rather
 * than reusing the rest of the theme's article/homepage styling — a dedicated enterprise-sales
 * page reads better with its own visual identity than inheriting news-reading typography.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<style>
	.bday-corp { --bday-corp-accent: #E30613; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif; }
	.bday-corp__hero { background: #0d0d0d; color: #fff; padding: 56px 24px; }
	.bday-corp__hero-inner { max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: 1fr 440px; gap: 48px; align-items: start; }
	@media (max-width: 900px) { .bday-corp__hero-inner { grid-template-columns: 1fr; } }
	.bday-corp__eyebrow { font-style: italic; font-size: 15px; letter-spacing: 0.04em; text-transform: uppercase; color: var(--bday-corp-accent); margin: 0 0 12px; }
	.bday-corp__title { font-size: 34px; line-height: 1.25; font-weight: 700; margin: 0 0 20px; }
	.bday-corp__lead { font-size: 16px; line-height: 1.7; opacity: 0.85; margin: 0 0 16px; }
	.bday-corp__form-card { background: #fff; color: #1a1a1a; border-radius: 12px; padding: 32px; box-shadow: 0 20px 60px rgba(0,0,0,0.35); }
	.bday-corp__benefits { max-width: 1100px; margin: 0 auto; padding: 64px 24px; text-align: center; }
	.bday-corp__benefits h2 { font-style: italic; font-size: 28px; margin: 0 0 40px; }
	.bday-corp__grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px 24px; text-align: left; margin-bottom: 40px; }
	@media (max-width: 800px) { .bday-corp__grid { grid-template-columns: 1fr; text-align: center; } }
	.bday-corp__grid h3 { font-size: 13px; letter-spacing: 0.04em; text-transform: uppercase; margin: 0 0 10px; }
	.bday-corp__grid p { font-size: 14px; line-height: 1.6; color: #555; margin: 0; }
	.bday-corp__cta-band { background: #0d0d0d; color: #fff; text-align: center; padding: 48px 24px; }
	.bday-corp__cta-band h2 { font-size: 22px; font-weight: 700; margin: 0 0 20px; }
	.bday-corp__btn { display: inline-block; background: var(--bday-corp-accent); color: #fff; font-weight: 700; padding: 14px 32px; border-radius: 6px; text-decoration: none; border: none; cursor: pointer; }
	.bday-corp__tagline { font-style: italic; font-size: 16px; color: #333; margin-top: 32px; }
	#aero-corporate-subscription-mount .aero-paywall-form { max-width: none; }
</style>

<div class="bday-corp">
	<section class="bday-corp__hero">
		<div class="bday-corp__hero-inner">
			<div>
				<p class="bday-corp__eyebrow">BusinessDay Corporate Subscriptions</p>
				<h1 class="bday-corp__title">Give your organisation the credible, current and complete business intelligence it needs to see clearly and move first.</h1>
				<p class="bday-corp__lead">Every day, the decisions you take as a leader determine where your company goes next. BusinessDay supports those decisions by bringing award-winning journalism, market data and expert analysis together on one platform — relevant to your world, shaped around your needs, and built to power growth across every level of your business.</p>
			</div>
			<div class="bday-corp__form-card">
				<div id="aero-corporate-subscription-mount"></div>
			</div>
		</div>
	</section>

	<section class="bday-corp__benefits">
		<h2>Empower Critical Business Decisions</h2>
		<div class="bday-corp__grid">
			<div>
				<h3>Client-Facing Roles</h3>
				<p>Become the subject matter expert and land the next deal with relevant, timely business news.</p>
			</div>
			<div>
				<h3>Business Analysts &amp; Researchers</h3>
				<p>Build, validate, and deliver actionable recommendations with trusted and accessible business information and analysis.</p>
			</div>
			<div>
				<h3>Procurement &amp; Vendor Specialists</h3>
				<p>Increase value with subscription bundles that offer immediacy, exclusivity, and reliability.</p>
			</div>
			<div>
				<h3>Authoritative, Expert Insights</h3>
				<p>Breaking and in-depth coverage across hundreds of topics and industry segments provides an authoritative voice in business and financial news.</p>
			</div>
			<div>
				<h3>Robust Business &amp; Financial Data</h3>
				<p>Comprehensive company profiles, market and economic data to create actionable business insights.</p>
			</div>
			<div>
				<h3>Flexible and Easy to Manage</h3>
				<p>Multiple corporate signup options with simple onboarding, so your team spends less time on admin.</p>
			</div>
		</div>
		<a href="#aero-corporate-subscription-mount" class="bday-corp__btn">Get Pricing</a>
		<p class="bday-corp__tagline">Become one of the many companies that empower their workforce with a Corporate Subscription.</p>
	</section>

	<section class="bday-corp__cta-band">
		<h2>Get the trusted resource your team needs</h2>
		<a href="#aero-corporate-subscription-mount" class="bday-corp__btn">Get Pricing</a>
	</section>
</div>
<?php
get_footer();
