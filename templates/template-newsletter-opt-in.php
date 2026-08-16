<?php
/**
 * Template Name: Newsletter Opt-In
 *
 * Reader-requested — modeled on semafor.com/newsletters' *shape* (every
 * newsletter/email-brief offering presented at once, each with a
 * description, picked via checkboxes rather than one at a time) in this
 * site's own visual language, not a literal clone of Semafor's styling.
 *
 * Two independently-sourced offerings share one page:
 *  - Newsletters: addons/newsletter-fluentcrm/'s existing, already-working
 *    list-subscribe mechanism (bday_newsletter_get_lists() + the
 *    `bday_newsletter_subscribe` AJAX action wired up by
 *    includes/shortcode.php, which auto-attaches to ANY
 *    `.bday-newsletter-form__form` on a singular page — this template IS
 *    singular, so that existing handler works here with zero new PHP).
 *    Per-list descriptions are a new small addition (list_descriptions
 *    setting, includes/settings.php) since FluentCRM's own bridge API
 *    never exposed one.
 *  - "Email brief": subscription-service's single global digest
 *    preference (notification-preference.service.ts) — a completely
 *    separate system from the newsletters above (different backend, no
 *    shared account concept with FluentCRM's own contact list), so it's
 *    visually grouped into the same picker but wired independently via
 *    sdk/src/notification-preferences.ts, only actionable for a
 *    signed-in AeroPaywall reader (a FluentCRM newsletter only ever needs
 *    an email address, no account).
 *
 * "Automatically register the user with whatever they've opted into"
 * (the reader's own phrasing): for a signed-in reader,
 * sdk/src/newsletter-opt-in.ts hides the name/email fields entirely and
 * fills them from the reader's own account before submit — one click
 * subscribes them under their real account email, no re-typing. An
 * anonymous visitor still sees the classic name/email fields (the
 * existing FluentCRM flow only ever needed an email, never an AeroPaywall
 * account).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$bday_all_lists = function_exists( 'bday_newsletter_get_lists' ) ? bday_newsletter_get_lists() : array();
$bday_settings  = get_option( 'bday_addon_newsletter', array() );
$bday_visible   = array_map( 'intval', (array) ( $bday_settings['visible_lists'] ?? array() ) );
$bday_lists     = array_values( array_filter( $bday_all_lists, static fn( $l ) => in_array( (int) $l['id'], $bday_visible, true ) ) );
?>
<section class="bday-newsletter-opt-in">
	<div class="bday-container bday-newsletter-opt-in__inner">
		<header class="bday-newsletter-opt-in__header">
			<span class="bday-eyebrow">Newsletters &amp; Email Briefs</span>
			<h1>Get BusinessDay in your inbox</h1>
			<p class="bday-newsletter-opt-in__subhead">Pick as many as you like — each one covers a different beat, at its own pace. You can change your selections any time.</p>
		</header>

		<form id="bday-newsletter-opt-in-form" class="bday-newsletter-opt-in__form bday-newsletter-form__form">
			<?php wp_nonce_field( 'bday_newsletter_subscribe', 'bday_newsletter_nonce' ); ?>

			<div class="bday-newsletter-opt-in__grid">
				<div class="bday-newsletter-opt-in__card bday-newsletter-opt-in__card--brief" id="bday-newsletter-opt-in-brief-card">
					<div class="bday-newsletter-opt-in__card-body">
						<h3>The Daily Brief</h3>
						<p>A periodic email digest of the stories our editors think you might have missed — pulled from your reading habits and followed topics, not a fixed schedule.</p>
					</div>
					<div class="bday-newsletter-opt-in__card-action" id="bday-newsletter-opt-in-brief-toggle-mount" data-aero-requires-auth>
						<a href="<?php echo esc_url( add_query_arg( 'tab', 'register', bday_paywall_login_url() ) ); ?>" class="bday-newsletter-opt-in__signin-link">Sign in to enable</a>
					</div>
				</div>

				<?php foreach ( $bday_lists as $bday_list ) :
					$bday_desc = function_exists( 'bday_newsletter_get_list_description' ) ? bday_newsletter_get_list_description( (int) $bday_list['id'] ) : '';
					?>
					<div class="bday-newsletter-opt-in__card">
						<div class="bday-newsletter-opt-in__card-body">
							<h3><?php echo esc_html( $bday_list['title'] ); ?></h3>
							<?php if ( $bday_desc ) : ?>
								<p><?php echo esc_html( $bday_desc ); ?></p>
							<?php else : ?>
								<p class="bday-newsletter-opt-in__no-desc">Curated updates from this beat, sent straight to your inbox.</p>
							<?php endif; ?>
						</div>
						<label class="bday-newsletter-opt-in__card-action bday-newsletter-opt-in__checkbox">
							<input type="checkbox" name="list_ids[]" value="<?php echo esc_attr( $bday_list['id'] ); ?>">
							<span></span>
						</label>
					</div>
				<?php endforeach; ?>

				<?php if ( empty( $bday_lists ) ) : ?>
					<p class="description">No newsletters are configured yet — check back soon.</p>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $bday_lists ) ) : ?>
				<div class="bday-newsletter-opt-in__identity">
					<div class="bday-newsletter-opt-in__identity-fields">
						<div class="bday-newsletter-opt-in__row">
							<input type="text" name="first_name" placeholder="First name" required>
							<input type="text" name="last_name" placeholder="Last name" required>
						</div>
						<input type="email" name="email" placeholder="Email address" required>
					</div>
					<p class="bday-newsletter-opt-in__identity-signedin" id="bday-newsletter-opt-in-signedin" hidden>Signed in as <strong data-aero-nav-email></strong></p>
					<button type="submit" class="bday-newsletter-opt-in__submit">Subscribe</button>
					<div class="bday-newsletter-form__message" role="status"></div>
				</div>
			<?php endif; ?>
		</form>
	</div>
</section>
<?php
get_footer();
