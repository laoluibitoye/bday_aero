<?php
/**
 * Addon Name: Sidebar Promo
 * Addon Slug: sidebar-promo
 * Description: Editor-uploaded promotional image and ad slots in the article sidebar.
 * Cache Namespace: sidebar_promo
 * Settings Tab: Sidebar Promo
 * Default: on
 *
 * Reader-requested: editors need somewhere to drop promotional material/
 * custom ads into the article sidebar without touching code. The generic
 * WordPress widget system already technically allows this (page_sidebar
 * in single-default.php's <aside>), but a Custom HTML/Image widget asks
 * an editor to already know HTML or trust a raw embed box — this gives
 * two dedicated, obviously-editorial slots (image + link + alt text) on
 * their own settings tab instead, image field type via the media library
 * (core/options/field-types/render.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return array<int, array{key:string,label:string}> */
function bday_sidebar_promo_slots(): array {
	return array(
		array( 'key' => 'slot_1', 'label' => 'Promo slot 1' ),
		array( 'key' => 'slot_2', 'label' => 'Promo slot 2' ),
	);
}

add_action(
	'bday_article_sidebar_zone',
	static function (): void {
		$settings = get_option( 'bday_addon_sidebar_promo', array() );
		foreach ( bday_sidebar_promo_slots() as $slot ) {
			$key = $slot['key'];
			if ( empty( $settings[ $key . '_enabled' ] ) || empty( $settings[ $key . '_image' ] ) ) {
				continue;
			}
			$image_id = (int) $settings[ $key . '_image' ];
			$image    = wp_get_attachment_image( $image_id, 'medium', false, array( 'class' => 'bday-sidebar-promo__image' ) );
			if ( ! $image ) {
				continue;
			}
			$link = $settings[ $key . '_url' ] ?? '';
			$alt  = $settings[ $key . '_label' ] ?? '';
			?>
			<div class="bday-sidebar-promo" data-bday-sidebar-promo>
				<?php if ( $alt ) : ?><span class="bday-sidebar-promo__label"><?php echo esc_html( $alt ); ?></span><?php endif; ?>
				<?php if ( $link ) : ?><a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="sponsored noopener"><?php endif; ?>
					<?php echo $image; ?>
				<?php if ( $link ) : ?></a><?php endif; ?>
			</div>
			<?php
		}
	}
);

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$fields = array();
		foreach ( bday_sidebar_promo_slots() as $slot ) {
			$key      = $slot['key'];
			$fields[] = array( 'key' => $key . '_enabled', 'type' => 'checkbox', 'label' => $slot['label'] . ' — enabled', 'default' => false, 'description' => 'Turns this slot on. It stays hidden even with an image uploaded until this is checked — safe to prepare a slot ahead of a campaign\'s start date.' );
			$fields[] = array( 'key' => $key . '_image', 'type' => 'image', 'label' => $slot['label'] . ' — image', 'description' => 'The creative itself, from the media library. Any size works, but a roughly square-to-portrait image sits most naturally in the article sidebar\'s column.' );
			$fields[] = array( 'key' => $key . '_url', 'type' => 'url', 'label' => $slot['label'] . ' — link URL', 'description' => 'Where clicking the promo image goes. Leave blank for a non-clickable image.' );
			$fields[] = array( 'key' => $key . '_label', 'type' => 'text', 'label' => $slot['label'] . ' — sponsor label', 'description' => 'Small label shown above the image (e.g. "Sponsored").' );
		}

		$schema['sidebar-promo'] = array(
			'tab_label' => 'Sidebar Promo',
				'group'     => 'editorial',
			'option'    => 'bday_addon_sidebar_promo',
			'intro'     => 'Two dedicated advertising/promotional slots in the sidebar that runs alongside every article — no HTML or ad-tag knowledge required, just an image, an optional link, and an optional small sponsor label above it. This is separate from the GAM/direct-sold ad zones (Ads & Sharing Matrix) elsewhere on the page; use this specifically for something an editor manages directly rather than through the ad server.',
			'about'     => '<p>Renders in the article sidebar, below any active widget-area content and above the regular ad zone. Each of the two slots is fully independent — enable one, both, or neither.</p>',
			'use_cases' => array(
				'A house ad for the print edition or an event, swapped out manually each week.',
				'A direct-sold sponsorship an advertiser paid for outside the regular ad-server pipeline.',
				'Cross-promoting a sister publication or a subscription offer.',
			),
			'fields'    => $fields,
		);
		return $schema;
	}
);
