<?php
/**
 * Core theme setup — add_theme_support, image sizes, nav menus, sidebars.
 * Mechanism only, no feature-specific logic; every add-on's own setup
 * lives in its own addon.php, not here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * "Technical Team" — a role with an Editor's day-to-day content
 * capabilities, that also gets default access (see the first-run seed in
 * core/homepage/admin.php) to the settings tabs that repoint a homepage
 * section's title or its underlying tag/category, a capability
 * deliberately kept out of Editor/Author's reach since it changes what a
 * section *is*, not just what's published through it. get_role() guards
 * this so it only ever runs the (cheap but non-free) add_role() once —
 * every later request finds the role already there and does nothing,
 * same idempotency every add_option() first-run seed in this theme
 * already relies on.
 */
add_action(
	'after_setup_theme',
	static function (): void {
		if ( ! get_role( 'bday_technical_team' ) ) {
			$editor = get_role( 'editor' );
			add_role( 'bday_technical_team', 'Technical Team', $editor ? $editor->capabilities : array( 'read' => true ) );
		}
	}
);

add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		set_post_thumbnail_size( 1568, 9999 );
		add_image_size( 'top_story', 810, 405 );
		add_image_size( 'featured', 1200, 675 );
		add_image_size( 'medium_rectangle', 284, 165 );
		add_image_size( 'medium_standard', 254, 198 );
		add_image_size( 'small_category', 210, 134 );
		add_image_size( 'small', 100, 100 );
		add_image_size( 'pdf_thumbnail', 285, 403 );

		register_nav_menus(
			array(
				'main_menu'      => __( 'Main Menu', 'bday-premium' ),
				'secondary_menu' => __( 'Secondary Menu', 'bday-premium' ),
			)
		);

		$plain_sidebar_args = array(
			'before_widget' => '',
			'after_widget'  => '',
			'before_title'  => '',
			'after_title'   => '',
			'before_sidebar' => '',
			'after_sidebar' => '',
		);

		/**
		 * Every sidebar below previously used `<span>` for before_widget/
		 * before_sidebar — an inline element wrapping block content (widget
		 * lists, search forms, images), invalid nesting that also meant
		 * `.bday-sidebar`'s CSS had nothing block-level to actually target,
		 * which is why the default-widgets sidebar rendered as a completely
		 * unstyled markup dump. `<div>` fixes both the validity bug and
		 * gives `_premium.scss` real selectors to style.
		 */
		$widget_wrapper_args = array(
			'before_widget'  => '<div class="bday-widget">',
			'after_widget'   => '</div>',
			'before_title'   => '<h3 class="widget-title">',
			'after_title'    => '</h3>',
			'before_sidebar' => '<div class="bday-sidebar-widgets">',
			'after_sidebar'  => '</div>',
		);

		register_sidebar(
			array_merge(
				$widget_wrapper_args,
				array(
					'name'        => 'Page Sidebar',
					'id'          => 'page_sidebar',
					'description' => 'Widgets shown on standard pages.',
				)
			)
		);

		foreach ( array( 'homepage_mobile_1', 'homepage_section_1', 'homepage_section_2', 'homepage_section_3', 'homepage_section_4' ) as $id ) {
			register_sidebar(
				array_merge(
					$plain_sidebar_args,
					array(
						'name' => ucwords( str_replace( '_', ' ', $id ) ),
						'id'   => $id,
					)
				)
			);
		}

		register_sidebar(
			array_merge(
				$widget_wrapper_args,
				array(
					'name'        => 'Homepage Sidebar',
					'id'          => 'homepage_sidebar',
					'description' => 'Widgets shown in the homepage right rail.',
				)
			)
		);

		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );
		add_theme_support( 'post-formats', array( 'image', 'video' ) );
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 350,
				'width'       => 300,
				'flex-width'  => true,
				'flex-height' => true,
			)
		);
		add_theme_support( 'responsive-embeds' );
	}
);

// Housekeeping dequeues — unrelated to any single add-on, always-on.
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		wp_dequeue_style( 'wp-block-library' );
		wp_deregister_style( 'wp-components' );
		wp_dequeue_style( 'wp-block-library-theme' );
	},
	100
);

add_action(
	'wp_print_scripts',
	static function (): void {
		if ( ! is_admin() ) {
			wp_dequeue_script( 'comment-reply' );
		}
	},
	100
);

remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );

/*
 * Post thumbnails use WordPress's own <img> markup: real src/srcset,
 * width/height and native loading="lazy". This replaced a data-src swap
 * that needed script.js, which AMP pages strip, so AMP only ever showed
 * the grey placeholder. The width/height attributes are neutralised in
 * CSS (components/_post-thumbnail.scss) so existing sizing rules still
 * apply.
 */

add_filter(
	'wp_get_attachment_image_attributes',
	static function ( array $attr, WP_Post $attachment ): array {
		if ( ! isset( $attr['class'] ) || ! str_contains( $attr['class'], 'wp-post-image' ) ) {
			return $attr;
		}
		// Keep the old alt fallback: no alt text set → use the image's title.
		if ( '' === trim( (string) ( $attr['alt'] ?? '' ) ) ) {
			$attr['alt'] = get_the_title( $attachment );
		}
		// AMP ignores a CSS object-fit on <amp-img>; it needs the attribute.
		// The theme crops every thumbnail with object-fit: cover.
		if ( function_exists( 'amp_is_request' ) && amp_is_request() ) {
			$attr['object-fit'] = 'cover';
		}
		return $attr;
	},
	20, // after core adds the wp-post-image class (priority 10)
	2
);

/*
 * Core skips lazy-loading for every image between the header and the main
 * loop. The homepage never runs the main loop, so without this cap every
 * thumbnail on it would load at once. Outside the main loop, only the
 * first few thumbnails (core's own threshold, 3 by default) load eagerly.
 */
add_filter(
	'wp_get_loading_optimization_attributes',
	static function ( $loading_attrs, $tag_name, $attr, $context ) {
		static $eager_count = 0;

		if (
			'the_post_thumbnail' !== $context
			|| ! is_array( $loading_attrs )
			|| isset( $loading_attrs['loading'] )                   // already lazy
			|| isset( $attr['loading'] ) || isset( $attr['fetchpriority'] ) // caller chose
			|| ( in_the_loop() && is_main_query() )                 // core counts these
		) {
			return $loading_attrs;
		}

		if ( ++$eager_count > wp_omit_loading_attr_threshold() ) {
			unset( $loading_attrs['fetchpriority'] );
			$loading_attrs['loading'] = 'lazy';
		}
		return $loading_attrs;
	},
	10,
	4
);
