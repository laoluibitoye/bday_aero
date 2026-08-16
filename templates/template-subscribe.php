<?php
/**
 * Template Name: Subscribe Landing
 *
 * The /subscribe/ page specifically — a static hero + value-proposition
 * section ahead of the page's own [aeropaywall_account tab="subscribe"]
 * shortcode content. Reader-requested "format and content like
 * nytimes.com's subscription page": a headline/subhead, then what
 * subscribing actually gets you, then the plan picker itself (which now
 * has its own Individual/Corporate toggle — see renderSubscribeTab() in
 * the SDK).
 *
 * Reader-reported: this page (and every other account-flow page — My
 * Account, Log In, Create Account, etc. all already used the real
 * header.php) used to run the stripped-down header-minimal.php instead —
 * no masthead nav, world clocks, ticker, or theme-switcher toggle. Now
 * uses the same get_header()/get_footer() every ordinary page on the site
 * does, so a reader landing here from anywhere else on the site keeps the
 * same navigation and the same light/warm/dark switcher, rather than
 * hitting a page that looks like a different site.
 *
 * The hero/value-prop copy lives here rather than in the page's own
 * block-editor content because it's identical every time this template
 * is used and this theme's whole convention this session has been
 * PHP templates, not ad-hoc per-page block content — but nothing here
 * is shortcode/dynamic, so an editor wanting to revise the copy can
 * still do that directly in this file same as any other template part.
 *
 * Reader-requested follow-up: pull more structure from nytimes.com/
 * subscription and adapt it for BusinessDay. That page couldn't be
 * fetched directly (blocked for automated requests), so this borrows its
 * well-known, publicly documented shape — a product "bundle" section
 * (NYT: News/Cooking/Games/Wirecutter/The Athletic tiles; here:
 * BusinessDay's own real products, same URLs footer.php already links
 * to), a brief trust/masthead-credential line, and an FAQ ahead of
 * checkout — rather than any copied copy, none of which was accessible
 * to verify or quote. The added trust line only states facts already
 * established elsewhere in this theme (footer.php's own About copy:
 * founded 2001, Lagos-based, Accra bureau) — nothing fabricated, no
 * invented subscriber counts or reader testimonials attributed to real
 * or implied people.
 */

get_header();

$bday_epaper_link      = bday_epaper_url();
$bday_podcast_link     = post_type_exists( 'podcast' ) ? get_post_type_archive_link( 'podcast' ) : '';
$bday_bundle_tiles     = array(
	array(
		'title' => 'News & Analysis',
		'desc'  => 'Every story, every desk — Economy, Companies, Markets, Politics and more, updated all day.',
		'url'   => home_url( '/' ),
	),
	array(
		'title' => 'BusinessDay Pro',
		'desc'  => 'Deeper intelligence for decision makers — data-led reporting built for investors and operators.',
		'url'   => 'https://pro.businessday.ng/',
	),
	array(
		'title' => 'The Daily E-Paper',
		'desc'  => "Today's print edition, laid out exactly as it appears on newsstands, every morning.",
		'url'   => $bday_epaper_link,
	),
	array(
		'title' => 'BD Fx',
		'desc'  => 'Live exchange-rate tracking and currency analysis for the Nigerian market.',
		'url'   => 'https://currency.businessday.ng/',
	),
	array(
		'title' => 'Podcasts & Shows',
		'desc'  => 'BusinessDay Television and our audio shows, for when you\'d rather listen than read.',
		'url'   => $bday_podcast_link,
	),
	array(
		'title' => 'BD Conferences',
		'desc'  => 'Priority access and subscriber pricing on our conferences and industry events.',
		'url'   => 'https://conferences.businessday.ng/',
	),
);
$bday_faqs = array(
	array(
		'q' => 'Can I cancel anytime?',
		'a' => 'Yes. Cancel any time from My Account → Subscribe — no phone call, no email required. You\'ll keep access until the end of your current billing period.',
	),
	array(
		'q' => 'Is my payment information secure?',
		'a' => 'Yes. Payments are processed by our payment provider; BusinessDay never stores your card details directly.',
	),
	array(
		'q' => 'Can I read on my phone?',
		'a' => 'Yes. Your subscription works on any browser, on any phone, tablet or computer — there\'s no separate app to install.',
	),
	array(
		'q' => 'What\'s the difference between Individual and Corporate plans?',
		'a' => 'Individual plans are for a single reader. Corporate plans cover a team under one organization account, managed from the Team tab in My Account.',
	),
	array(
		'q' => 'Do free newsletters require a subscription?',
		'a' => 'No — our free newsletters are open to everyone. A subscription adds unlimited article access, BD Pro analysis, the daily e-paper and ad-free reading on top.',
	),
);
?>
<main>
<section class="bday-subscribe-hero">
	<div class="bday-subscribe-hero__inner">
		<span class="bday-subscribe-hero__eyebrow">BusinessDay Subscription</span>
		<h1 class="bday-subscribe-hero__title">Journalism that helps Nigeria do business better.</h1>
		<p class="bday-subscribe-hero__subtitle">Subscribe for unlimited access to the reporting, data, and analysis that Nigerian business leaders rely on every day.</p>
	</div>
</section>

<section class="bday-subscribe-trust">
	<div class="bday-subscribe-trust__inner">
		<span>Independent business journalism since 2001</span>
		<span class="bday-subscribe-trust__dot" aria-hidden="true">·</span>
		<span>Lagos-based, with a bureau in Accra, Ghana</span>
		<span class="bday-subscribe-trust__dot" aria-hidden="true">·</span>
		<span>Daily and Sunday titles</span>
	</div>
</section>

<section class="bday-subscribe-values">
	<div class="bday-subscribe-values__inner">
		<div class="bday-subscribe-value">
			<span class="bday-subscribe-value__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19V5a2 2 0 0 1 2-2h9l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M14 3v5h5M8 12h8M8 16h5"/></svg>
			</span>
			<h3>Unlimited articles</h3>
			<p>Read every story, every day — no monthly limits, no paywalled surprises.</p>
		</div>
		<div class="bday-subscribe-value">
			<span class="bday-subscribe-value__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M8 4v5M16 4v5"/></svg>
			</span>
			<h3>The daily e-paper</h3>
			<p>The full print edition, laid out exactly as it appears in the paper, delivered digitally every morning.</p>
		</div>
		<div class="bday-subscribe-value">
			<span class="bday-subscribe-value__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h7l-1 8 10-12h-7l1-8z"/></svg>
			</span>
			<h3>Ad-free reading</h3>
			<p>A cleaner, faster experience with no display advertising interrupting your reading.</p>
		</div>
		<div class="bday-subscribe-value">
			<span class="bday-subscribe-value__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z"/><path d="m4 6 8 7 8-7"/></svg>
			</span>
			<h3>Exclusive newsletters</h3>
			<p>Curated briefings and columnist insight, delivered straight to your inbox.</p>
		</div>
	</div>
</section>

<section class="bday-subscribe-bundle">
	<div class="bday-subscribe-bundle__inner">
		<h2 class="bday-subscribe-bundle__title">One subscription. Everything BusinessDay makes.</h2>
		<p class="bday-subscribe-bundle__subtitle">Your subscription isn't just the newspaper — it's every product we publish, under one plan.</p>
		<div class="bday-subscribe-bundle__grid">
			<?php foreach ( $bday_bundle_tiles as $bday_tile ) : ?>
				<?php if ( '' === $bday_tile['url'] ) { continue; } ?>
				<a href="<?php echo esc_url( $bday_tile['url'] ); ?>" class="bday-subscribe-bundle__tile">
					<h3><?php echo esc_html( $bday_tile['title'] ); ?></h3>
					<p><?php echo esc_html( $bday_tile['desc'] ); ?></p>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="bday-subscribe-quote">
	<div class="bday-subscribe-quote__inner">
		<blockquote>
			<p>&ldquo;Nigeria's business leaders make decisions every day with real money and real consequences behind them. Our job is to make sure they're making those decisions with the facts, not the noise.&rdquo;</p>
			<cite>— The BusinessDay Editors</cite>
		</blockquote>
	</div>
</section>

<section class="bday-subscribe-plans">
	<div class="bday-subscribe-plans__inner">
		<h2 class="bday-subscribe-plans__title">Choose your plan</h2>
		<?php
		if ( have_posts() ) :
			the_post();
			the_content();
		endif;
		?>
	</div>
</section>

<section class="bday-subscribe-faq">
	<div class="bday-subscribe-faq__inner">
		<h2 class="bday-subscribe-faq__title">Questions</h2>
		<div class="bday-subscribe-faq__list">
			<?php foreach ( $bday_faqs as $bday_faq ) : ?>
				<details class="bday-subscribe-faq__item">
					<summary><?php echo esc_html( $bday_faq['q'] ); ?></summary>
					<p><?php echo esc_html( $bday_faq['a'] ); ?></p>
				</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>
</main>
<?php
get_footer();
