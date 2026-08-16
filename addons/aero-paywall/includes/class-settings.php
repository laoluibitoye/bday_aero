<?php
/**
 * Option keys/defaults for the native AeroPaywall integration. Same key
 * names and defaults as the retired connector-plugin's AeroPaywall_Settings
 * (aero_paywall_* options) so a site upgrading from the plugin to this
 * add-on inherits its configured values with zero re-entry — see the
 * migration note in addon.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Settings {

	public const ENABLED                    = 'aero_paywall_enabled';
	public const API_BASE_URL               = 'aero_paywall_api_base_url';
	public const API_KEY                    = 'aero_paywall_api_key';
	public const LICENSING_API_BASE_URL     = 'aero_paywall_licensing_api_base_url';
	public const LICENSE_KEY                = 'aero_paywall_license_key';
	public const SDK_CDN_BASE               = 'aero_paywall_sdk_cdn_base';
	public const SDK_VERSION                = 'aero_paywall_sdk_version';
	public const PREMIUM_TERMS              = 'aero_paywall_premium_terms';
	public const RESTRICTED_POST_TYPES      = 'aero_paywall_restricted_post_types';
	public const ACCENT_COLOR               = 'aero_paywall_accent_color';
	public const ADFREE_ENABLED             = 'aero_paywall_adfree_enabled';
	public const PRIVATE_MODE_ENFORCEMENT   = 'aero_paywall_private_mode_enforcement';
	public const ACCOUNT_PAGE_URL           = 'aero_paywall_account_page_url';
	public const LOGIN_PAGE_URL             = 'aero_paywall_login_page_url';
	public const REGISTER_PAGE_URL          = 'aero_paywall_register_page_url';
	public const SUBSCRIBE_PAGE_URL         = 'aero_paywall_subscribe_page_url';
	public const RESET_PASSWORD_PAGE_URL    = 'aero_paywall_reset_password_page_url';
	public const GOOGLE_CLIENT_ID           = 'aero_paywall_google_client_id';
	public const APPLE_CLIENT_ID            = 'aero_paywall_apple_client_id';
	public const RESTRICTION_RULES          = 'aero_paywall_restriction_rules';
	public const PREVIEW_WORD_COUNT         = 'aero_paywall_preview_word_count';
	public const PAYWALL_MODE               = 'aero_paywall_paywall_mode';
	public const BYPASS_ROLES               = 'aero_paywall_bypass_roles';
	public const JSONLD_ENABLED             = 'aero_paywall_jsonld_enabled';
	public const RESTRICTION_EXCEPTIONS     = 'aero_paywall_restriction_exceptions';
	public const PROMPT_COPY                = 'aero_paywall_prompt_copy';

	public static function enabled(): bool {
		return (bool) get_option( self::ENABLED, false );
	}

	public static function api_base_url(): string {
		return (string) get_option( self::API_BASE_URL, '' );
	}

	public static function api_key(): string {
		return (string) get_option( self::API_KEY, '' );
	}

	public static function licensing_api_base_url(): string {
		return (string) get_option( self::LICENSING_API_BASE_URL, '' );
	}

	public static function license_key(): string {
		return (string) get_option( self::LICENSE_KEY, '' );
	}

	public static function sdk_cdn_base(): string {
		return (string) get_option( self::SDK_CDN_BASE, 'https://cdn.aeropaywall.com/sdk' );
	}

	public static function sdk_version(): string {
		return (string) get_option( self::SDK_VERSION, 'latest' );
	}

	/** @return array<string, int[]> taxonomy => term ids */
	public static function premium_terms(): array {
		$terms = get_option( self::PREMIUM_TERMS, array() );
		return is_array( $terms ) ? $terms : array();
	}

	/** @return string[] */
	public static function restricted_post_types(): array {
		$types = get_option( self::RESTRICTED_POST_TYPES, array( 'post' ) );
		return is_array( $types ) && ! empty( $types ) ? $types : array( 'post' );
	}

	public static function accent_color(): string {
		// BusinessDay's real brand red (--bd-red in abstracts/_colors.scss)
		// — the retired connector-plugin's own generic-SaaS-blue default
		// made sense for an installable-on-any-site plugin; this add-on is
		// specifically BusinessDay's, so the reader-facing fallback accent
		// should match the site's actual brand from the start, not a color
		// every admin would have to remember to change.
		return (string) get_option( self::ACCENT_COLOR, '#E30613' );
	}

	public static function adfree_enabled(): bool {
		return (bool) get_option( self::ADFREE_ENABLED, true );
	}

	/** 'off' | 'soft' | 'hard' */
	public static function private_mode_enforcement(): string {
		return (string) get_option( self::PRIVATE_MODE_ENFORCEMENT, 'soft' );
	}

	public static function account_page_url(): string {
		return (string) get_option( self::ACCOUNT_PAGE_URL, '' );
	}

	public static function login_page_url(): string {
		return (string) get_option( self::LOGIN_PAGE_URL, '' );
	}

	public static function register_page_url(): string {
		return (string) get_option( self::REGISTER_PAGE_URL, '' );
	}

	public static function subscribe_page_url(): string {
		return (string) get_option( self::SUBSCRIBE_PAGE_URL, '' );
	}

	public static function reset_password_page_url(): string {
		return (string) get_option( self::RESET_PASSWORD_PAGE_URL, '' );
	}

	public static function google_client_id(): string {
		return (string) get_option( self::GOOGLE_CLIENT_ID, '' );
	}

	public static function apple_client_id(): string {
		return (string) get_option( self::APPLE_CLIENT_ID, '' );
	}

	/** @return array<int, array<string, mixed>> */
	public static function restriction_rules(): array {
		$rules = get_option( self::RESTRICTION_RULES, array() );
		return is_array( $rules ) ? $rules : array();
	}

	public static function preview_word_count(): int {
		return (int) get_option( self::PREVIEW_WORD_COUNT, 120 );
	}

	/** 'soft' | 'hard' */
	public static function paywall_mode(): string {
		return (string) get_option( self::PAYWALL_MODE, 'soft' );
	}

	/** @return string[] */
	public static function bypass_roles(): array {
		$roles = get_option( self::BYPASS_ROLES, array() );
		return is_array( $roles ) ? $roles : array();
	}

	public static function jsonld_enabled(): bool {
		return (bool) get_option( self::JSONLD_ENABLED, true );
	}

	/** @return array<string, int[]> */
	public static function restriction_exceptions(): array {
		$terms = get_option( self::RESTRICTION_EXCEPTIONS, array() );
		return is_array( $terms ) ? $terms : array();
	}

	/** @return array<string, array{headline: string, subcopy: string, cta: string, offerBadge: string}> */
	public static function prompt_copy(): array {
		$copy = get_option( self::PROMPT_COPY, array() );
		return is_array( $copy ) && ! empty( $copy ) ? $copy : self::default_prompt_copy();
	}

	/** @return array<string, array{headline: string, subcopy: string, cta: string, offerBadge: string}> */
	private static function default_prompt_copy(): array {
		return array(
			'register_prompt' => array(
				'headline'   => 'Create a free account to keep reading',
				'subcopy'    => 'Registration is free and only takes a moment.',
				'cta'        => 'Create free account',
				'offerBadge' => '',
			),
			'profile_prompt'   => array(
				'headline'   => 'Tell us a bit more about you',
				'subcopy'    => 'Complete your profile to continue reading.',
				'cta'        => 'Continue',
				'offerBadge' => '',
			),
			'paid_lock'        => array(
				'headline'   => 'Subscribe to keep reading',
				'subcopy'    => "You've reached your free article limit.",
				'cta'        => 'Subscribe now',
				'offerBadge' => '',
			),
		);
	}
}
