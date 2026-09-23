<?php
/**
 * Addon Name: Homepage Rail Banner
 * Addon Slug: homepage-rail-banner
 * Description: An editor-uploaded ad banner in the homepage "In Other News" section's sidebar — replaces the fixed Opinion box and algorithmic Most Popular list that used to sit there.
 * Cache Namespace: homepage_rail_banner
 * Settings Tab: Homepage Rail Banner
 * Default: on
 *
 * Editor-requested (2026-09-23): the "In Other News" section's sidebar
 * rail (homepage-sections/in-other-news.php) hardcoded an Opinion box
 * and an algorithmic "Most Popular" (most-commented site-wide) list,
 * neither admin-configurable. Replaces both with one editor-uploaded
 * banner slot — same "image + link + optional label" shape and image
 * field type (core/options/field-types/render.php, wp.media picker) as
 * addons/sidebar-promo/'s article-sidebar slots, reusing that
 * component's own CSS (.bday-sidebar-promo) rather than a new one, since
 * the visual need is identical: a plain bordered card holding an
 * editor-supplied image of whatever size or orientation they upload,
 * scaled to the column's width either way.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'bday_homepage_rail_banner_zone',
	static function (): void {
		$settings = get_option( 'bday_addon_homepage_rail_banner', array() );
		if ( empty( $settings['enabled'] ) || empty( $settings['image'] ) ) {
			return;
		}
		$image_id = (int) $settings['image'];
		$image    = wp_get_attachment_image( $image_id, 'large', false, array( 'class' => 'bday-sidebar-promo__image' ) );
		if ( ! $image ) {
			return;
		}
		$link  = $settings['url'] ?? '';
		$label = $settings['label'] ?? '';
		?>
		<div class="bday-sidebar-promo" data-bday-homepage-rail-banner>
			<?php if ( $label ) : ?><span class="bday-sidebar-promo__label"><?php echo esc_html( $label ); ?></span><?php endif; ?>
			<?php if ( $link ) : ?><a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="sponsored noopener"><?php endif; ?>
				<?php echo $image; ?>
			<?php if ( $link ) : ?></a><?php endif; ?>
		</div>
		<?php
	}
);

add_filter(
	'bday_settings_schema',
	static function ( array $schema ): array {
		$schema['homepage-rail-banner'] = array(
			'tab_label' => 'Homepage Rail Banner',
				'group'     => 'editorial',
			'option'    => 'bday_addon_homepage_rail_banner',
			'intro'     => 'The ad banner in the homepage\'s "In Other News" section sidebar, in place of what used to be a fixed Opinion box and an algorithmic Most Popular list. Upload any image — landscape or portrait, any dimensions — it scales to fit the column.',
			'about'     => '<p>Hidden entirely until an image is uploaded and this is enabled. This is separate from the GAM/direct-sold ad zones (Ads & Sharing Matrix) elsewhere on the page — use this specifically for something an editor manages directly rather than through the ad server, same as the article-page Sidebar Promo slots.</p>',
			'fields'    => array(
				array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enabled', 'default' => false, 'description' => 'Turns the banner on. It stays hidden even with an image uploaded until this is checked — safe to prepare it ahead of a campaign\'s start date.' ),
				array( 'key' => 'image', 'type' => 'image', 'label' => 'Banner image', 'description' => 'The creative itself, from the media library. Any size or orientation works — landscape or portrait — it scales to the sidebar column\'s width.' ),
				array( 'key' => 'url', 'type' => 'url', 'label' => 'Link URL', 'description' => 'Where clicking the banner goes. Leave blank for a non-clickable image.' ),
				array( 'key' => 'label', 'type' => 'text', 'label' => 'Sponsor label', 'description' => 'Small label shown above the image (e.g. "Sponsored").' ),
			),
		);
		return $schema;
	}
);
