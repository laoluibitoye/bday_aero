<?php
/**
 * Markup-only account/login flyout the SDK's nav-menu script binds to
 * client-side (data-aero-nav-* attribute contract). Available to any
 * template via the bday_aero_nav_button() helper below.
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

	/** @return array<string, string> */
	public static function urls(): array {
		$account = Bday_Aero_Settings::account_page_url();
		$login   = Bday_Aero_Settings::login_page_url() ?: ( $account . '?tab=login' );
		$register = Bday_Aero_Settings::register_page_url() ?: ( $account . '?tab=register' );

		return array(
			'login'    => $login,
			'register' => $register,
			'account'  => $account,
			'team'     => $account . '?tab=team',
			'settings' => $account . '?tab=settings',
			'subscribe' => $account . '?tab=subscribe',
		);
	}

	public static function render(): void {
		if ( ! self::is_available() ) {
			return;
		}
		$urls = self::urls();
		?>
		<div class="aero-nav-flyout" data-aero-nav-root hidden>
			<a data-aero-nav-login href="<?php echo esc_url( $urls['login'] ); ?>">Log In</a>
			<a data-aero-nav-register href="<?php echo esc_url( $urls['register'] ); ?>">Sign Up</a>
			<a data-aero-nav-account href="<?php echo esc_url( $urls['account'] ); ?>">My Account</a>
			<a data-aero-nav-subscribe href="<?php echo esc_url( $urls['subscribe'] ); ?>">Subscribe</a>
		</div>
		<?php
	}
}

if ( ! function_exists( 'bday_aero_nav_button' ) ) {
	function bday_aero_nav_button(): void {
		Bday_Aero_Nav_Button::render();
	}
}
