<?php
/**
 * Addon Name: Translate
 * Addon Slug: translate
 * Description: Adds a Google Translate language picker to the header.
 * Cache Namespace: translate
 * Settings Tab: Translate
 * Default: on
 *
 * Header language picker — wraps Google's official (free, no API key,
 * no backend) Website Translator widget rather than building/maintaining
 * real translation infrastructure. header.php's `bday_header_translate_zone`
 * hook renders the trigger button + Google's mount div; this file only
 * owns the settings schema (which languages are offered) and the actual
 * script enqueue. script.js's bdayInitTranslate() owns the open/close
 * interaction, matching the search-toggle/menu-toggle pattern already
 * used elsewhere in the header.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display names for the language codes this theme's settings schema
 * offers (Google Translate's own codes). Only used for the theme-owned
 * dropdown label — Google's widget itself (wp_footer, below) still does
 * the real translating, keyed off the same codes.
 */
function bday_translate_language_names(): array {
	return array(
		'ar'    => 'Arabic',
		'zh-CN' => 'Chinese (Simplified)',
		'fr'    => 'French',
		'de'    => 'German',
		'ha'    => 'Hausa',
		'ig'    => 'Igbo',
		'pt'    => 'Portuguese (Brazil)',
		'es'    => 'Spanish',
		'sw'    => 'Swahili',
		'yo'    => 'Yoruba',
	);
}

add_action(
	'bday_header_translate_zone',
	static function (): void {
		$settings = get_option( 'bday_addon_translate', array() );
		if ( ! isset( $settings['enabled'] ) || ! $settings['enabled'] ) {
			return;
		}
		$languages = ! empty( $settings['languages'] )
			? $settings['languages']
			: 'fr,es,ar,pt,sw,ha,yo,ig,zh-CN,de';

		$bday_names   = bday_translate_language_names();
		$bday_codes   = array_filter( array_map( 'trim', explode( ',', $languages ) ) );
		$bday_options = array();
		foreach ( $bday_codes as $bday_code ) {
			$bday_options[ $bday_code ] = $bday_names[ $bday_code ] ?? $bday_code;
		}
		asort( $bday_options );

		// Reflects the reader's current selection into the trigger label on
		// first paint (no flash of "EN" before JS runs) — reads the same
		// googtrans cookie script.js's language links write and Google's
		// own widget script (wp_footer, below) honors, so the label and
		// the actual applied translation can never disagree.
		$bday_current = 'EN';
		if ( ! empty( $_COOKIE['googtrans'] ) ) {
			$bday_parts = explode( '/', sanitize_text_field( wp_unslash( $_COOKIE['googtrans'] ) ) );
			$bday_code  = end( $bday_parts );
			if ( $bday_code && isset( $bday_options[ $bday_code ] ) ) {
				$bday_current = strtoupper( $bday_code );
			}
		}
		?>
		<div class="bd-header__translate" data-bd-translate-root>
			<button type="button" class="bd-header__translate-toggle" data-bd-translate-toggle aria-label="<?php esc_attr_e( 'Translate this page', 'bday-premium' ); ?>" aria-expanded="false" aria-controls="bd-translate-panel">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z"/></svg>
				<span class="bd-header__translate-label" data-bd-translate-current><?php echo esc_html( $bday_current ); ?></span>
				<svg class="bd-header__translate-caret" width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden="true"><path d="M2 3.5l3 3 3-3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
			</button>
			<div class="bd-header__translate-panel" id="bd-translate-panel" data-bd-translate-panel hidden>
				<ul class="bd-header__translate-list" role="list">
					<li><button type="button" class="bd-header__translate-option" data-bd-translate-lang="en">English <span class="bd-header__translate-native">(original)</span></button></li>
					<?php foreach ( $bday_options as $bday_code => $bday_label ) : ?>
						<li><button type="button" class="bd-header__translate-option" data-bd-translate-lang="<?php echo esc_attr( $bday_code ); ?>"><?php echo esc_html( $bday_label ); ?></button></li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>
		<?php
		/**
		 * Google's own dropdown UI (what InlineLayout.SIMPLE renders into
		 * this mount) opens inside a cross-origin iframe
		 * (.goog-te-menu-frame, translate.google.com content) that no
		 * amount of this theme's own CSS can restyle — that's the actual
		 * reason the language picker kept reading as "unstyled" no matter
		 * how the panel around it was dressed up. Solution: keep the
		 * widget mounted (still required for it to read the googtrans
		 * cookie and apply translation) but visually hidden
		 * (.bd-header__translate-mount, clip-based, not display:none —
		 * the widget doesn't reliably init inside a display:none host),
		 * and drive it entirely through the theme-owned list above via
		 * the cookie contract instead of ever showing Google's own UI.
		 */
		?>
		<div id="bday-google-translate-mount" class="bd-header__translate-mount" aria-hidden="true"></div>
		<?php
	}
);

add_action(
	'wp_footer',
	static function (): void {
		$settings = get_option( 'bday_addon_translate', array() );
		if ( ! isset( $settings['enabled'] ) || ! $settings['enabled'] ) {
			return;
		}
		$languages = ! empty( $settings['languages'] )
			? $settings['languages']
			: 'fr,es,ar,pt,sw,ha,yo,ig,zh-CN,de';
		?>
		<script>
			function bdayGoogleTranslateInit() {
				new google.translate.TranslateElement(
					{
						pageLanguage: 'en',
						includedLanguages: <?php echo wp_json_encode( $languages ); ?>,
						layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
						autoDisplay: false,
					},
					'bday-google-translate-mount'
				);
			}
		</script>
		<script src="https://translate.google.com/translate_a/element.js?cb=bdayGoogleTranslateInit" async></script>
		<?php
	}
);

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['translate'] = array(
			'tab_label' => 'Translate',
				'group'     => 'technical',
			'option'    => 'bday_addon_translate',
			'intro'     => 'A free machine-translation option for readers whose first language isn\'t English, powered by Google\'s own translation engine — no API key, no cost, and no translation infrastructure for this site to maintain. Translation quality is Google\'s, not editorially reviewed; this is a convenience for readers, not a substitute for a real multilingual edition.',
			'about'     => '<p>Adds a language picker to the dark utility bar at the top of the header. The dropdown is styled to match the rest of the site (not Google\'s default widget chrome); picking a language reloads the current page translated.</p>',
			'fields'    => array(
				array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable', 'default' => true, 'description' => 'Shows or hides the language picker in the header entirely.' ),
				array(
					'key'         => 'languages',
					'type'        => 'text',
					'label'       => 'Offered languages (Google Translate codes, comma-separated)',
					'default'     => 'fr,es,ar,pt,sw,ha,yo,ig,zh-CN,de',
					'description' => 'Which languages appear in the dropdown, as Google Translate language codes (e.g. "fr" for French, "zh-CN" for Chinese Simplified). Trim this list to the languages your actual readership needs — a shorter list is a faster decision for a reader, not just a shorter menu.',
				),
			),
		);
		return $schema;
	}
);
