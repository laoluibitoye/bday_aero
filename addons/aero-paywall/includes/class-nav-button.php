<?php
/**
 * Markup-only account/login flyout the SDK's nav-menu.ts binds to
 * client-side (data-aero-nav-* attribute contract — see nav-menu.ts for
 * the full behavior: trigger/backdrop/panel open-close, inline login/
 * register/OAuth for guests, account-section drilldown for verified
 * readers). Ported from connector-plugin's AeroPaywall_Nav_Button, whose
 * full contract-compliant markup this native add-on's render() had never
 * been brought up to (it previously emitted 4 bare links with no
 * trigger/panel/backdrop structure at all — nav-menu.ts's actual
 * [data-aero-nav-trigger]/[data-aero-nav-panel] listeners had nothing to
 * bind to). Class names are the SDK's own (`aero-paywall-nav-*`, styled
 * theme-agnostically in styles.ts), not Bday_Aero-specific, so no new CSS
 * is needed here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Nav_Button {

	public static function is_available(): bool {
		return Bday_Aero_Settings::enabled()
			&& Bday_Aero_License_Client::is_active()
			&& '' !== Bday_Aero_Settings::account_page_url();
	}

	/** @return array<string, string> empty when no Account page is configured yet */
	public static function urls(): array {
		$account = Bday_Aero_Settings::account_page_url();
		if ( '' === $account ) {
			return array();
		}

		$login     = Bday_Aero_Settings::login_page_url();
		$register  = Bday_Aero_Settings::register_page_url();
		$subscribe = Bday_Aero_Settings::subscribe_page_url();
		$reset     = Bday_Aero_Settings::reset_password_page_url();

		return array(
			'login'     => '' !== $login ? $login : add_query_arg( 'tab', 'login', $account ),
			'register'  => '' !== $register ? $register : add_query_arg( 'tab', 'register', $account ),
			'account'   => $account,
			'team'      => add_query_arg( 'tab', 'team', $account ),
			'profile'   => add_query_arg( 'tab', 'profile', $account ),
			'password'  => add_query_arg( 'tab', 'password', $account ),
			'subscribe' => '' !== $subscribe ? $subscribe : add_query_arg( 'tab', 'subscribe', $account ),
			'reset'     => '' !== $reset ? $reset : add_query_arg( 'tab', 'reset', $account ),
		);
	}

	/**
	 * A theme calling this more than once (e.g. one trigger in the
	 * persistent utility bar, another inside the mobile menu overlay) gets
	 * one independent trigger+panel instance per call — each opens/closes
	 * on its own, same as the plugin's version.
	 */
	public static function render(): void {
		$urls = self::urls();
		if ( empty( $urls ) ) {
			return;
		}
		?>
		<div class="aero-paywall-nav" data-aero-nav-root>
			<?php
			/**
			 * Reader-requested split: the icon/name is a plain link straight
			 * to the standalone My Account page (a guest lands on its
			 * sign-in form; a signed-in reader lands on their dashboard) —
			 * it no longer opens this panel at all. A separate hamburger
			 * button next to it is the only thing that still opens the
			 * flyout, which itself now only ever shows Saved Articles/Read
			 * History/Go to My Account/Log Out — everything else
			 * (Subscription, Upgrade, Settings, etc.) lives exclusively on
			 * the full My Account page now.
			 */
			?>
			<a
				href="<?php echo esc_url( $urls['account'] ); ?>"
				class="aero-paywall-nav-account-link"
				aria-label="<?php esc_attr_e( 'My Account', 'bday-premium' ); ?>"
			>
				<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
				<?php
				/**
				 * Starts as "Sign In" (the guest state every server render
				 * assumes); nav-sync.ts's syncNavMenuState() already
				 * broadcasts the reader's display name to every element
				 * carrying data-aero-nav-name once a valid session is
				 * found — reusing it here means this label flips to the
				 * reader's name for free, no new JS.
				 */
				?>
				<span class="aero-paywall-nav-trigger-label" data-aero-nav-name><?php esc_html_e( 'Sign In', 'bday-premium' ); ?></span>
			</a>

			<button
				type="button"
				class="aero-paywall-nav-trigger"
				data-aero-nav-trigger
				aria-haspopup="dialog"
				aria-expanded="false"
				aria-label="<?php esc_attr_e( 'Saved articles and reading history', 'bday-premium' ); ?>"
			>
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
			</button>

			<div class="aero-paywall-nav-backdrop" data-aero-nav-backdrop></div>

			<div class="aero-paywall-nav-panel" data-aero-nav-panel role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Account', 'bday-premium' ); ?>">
				<div class="aero-paywall-nav-panel-header">
					<span class="aero-paywall-nav-panel-email" data-aero-nav-email></span>
					<button type="button" class="aero-paywall-nav-close" data-aero-nav-close aria-label="<?php esc_attr_e( 'Close', 'bday-premium' ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
					</button>
				</div>

				<div class="aero-paywall-nav-panel-body" data-aero-nav-state="out">
					<p class="aero-paywall-nav-panel-headline"><?php esc_html_e( 'Welcome', 'bday-premium' ); ?></p>
					<p class="aero-paywall-nav-panel-subcopy"><?php esc_html_e( 'Log in or create a free account to get started.', 'bday-premium' ); ?></p>
					<div data-aero-nav-auth-mount></div>
				</div>

				<div class="aero-paywall-nav-panel-body" data-aero-nav-state="in" hidden>
					<div data-aero-nav-menu>
						<?php
						/**
						 * Reader-requested: "Good morning/afternoon/evening,"
						 * ahead of the reader's name, based on their own
						 * local clock — server-rendered "Welcome back," is
						 * just the no-JS/first-paint fallback text;
						 * nav-sync.ts's syncNavMenuState() overwrites
						 * data-aero-nav-greeting with the real time-of-day
						 * string the moment a valid session is confirmed,
						 * same pattern data-aero-nav-name already uses for
						 * the name right next to it.
						 */
						?>
						<p class="aero-paywall-nav-panel-headline"><span data-aero-nav-greeting><?php esc_html_e( 'Welcome back,', 'bday-premium' ); ?></span> <span data-aero-nav-name></span></p>

						<?php
						/**
						 * Reader-requested: the flyout only ever shows a
						 * handful of full-width flat "cards" now — Saved
						 * Articles, Read History, and My News (a feed from
						 * the reader's own Personalization picks). Clicking
						 * one doesn't expand it inline; it drills into a
						 * dedicated full-scroll screen with a back button
						 * (nav-menu.ts's bindSignedInPanel), the same
						 * menu/detail pattern this panel used before, just
						 * scoped to these three items instead of the whole
						 * dashboard. "Go to My Account" is a plain link, not
						 * a drill-in card. Everything else that used to live
						 * here (Subscription, Upgrade, Refer a Friend,
						 * Account Settings, Team) is reachable exclusively
						 * via that link now.
						 */
						?>
						<button type="button" class="aero-paywall-nav-card" data-aero-nav-card-toggle="saved">
							<?php esc_html_e( 'Saved Articles', 'bday-premium' ); ?>
							<svg class="aero-paywall-nav-card-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"></polyline></svg>
						</button>

						<button type="button" class="aero-paywall-nav-card" data-aero-nav-card-toggle="history">
							<?php esc_html_e( 'Read History', 'bday-premium' ); ?>
							<svg class="aero-paywall-nav-card-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"></polyline></svg>
						</button>

						<button type="button" class="aero-paywall-nav-card" data-aero-nav-card-toggle="my-news">
							<?php esc_html_e( 'My News', 'bday-premium' ); ?>
							<svg class="aero-paywall-nav-card-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 6 15 12 9 18"></polyline></svg>
						</button>

						<a href="<?php echo esc_url( $urls['account'] ); ?>" class="aero-paywall-nav-card aero-paywall-nav-card--link"><?php esc_html_e( 'Go to My Account', 'bday-premium' ); ?></a>

						<button type="button" class="aero-paywall-nav-panel-logout" data-aero-nav-logout><?php esc_html_e( 'Log Out', 'bday-premium' ); ?></button>
					</div>

					<div data-aero-nav-detail hidden>
						<button type="button" class="aero-paywall-nav-detail-back" data-aero-nav-back>
							<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
							<span data-aero-nav-detail-title></span>
						</button>
						<div class="aero-paywall-nav-detail-list" data-aero-nav-saved-list hidden></div>
						<div class="aero-paywall-nav-detail-list" data-aero-nav-history-list hidden></div>
						<div class="aero-paywall-nav-detail-list" data-aero-nav-my-news-list hidden></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}

if ( ! function_exists( 'bday_aero_nav_button' ) ) {
	function bday_aero_nav_button(): void {
		Bday_Aero_Nav_Button::render();
	}
}
