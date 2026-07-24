<?php
/**
 * Core theme setup — add_theme_support, image sizes, nav menus, sidebars.
 * Mechanism only, no feature-specific logic; every add-on's own setup
 * lives in its own addon.php, not here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

		register_sidebar(
			array_merge(
				array(
					'name'        => 'Page Sidebar',
					'id'          => 'page_sidebar',
					'description' => 'Widgets shown on standard pages.',
					'before_widget' => '<span>',
					'after_widget'  => '</span>',
					'before_title'  => '<h3 class="widget-title">',
					'after_title'   => '</h3>',
					'before_sidebar' => '<span>',
					'after_sidebar' => '</span>',
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
			array(
				'name'          => 'Homepage Sidebar',
				'id'            => 'homepage_sidebar',
				'description'   => 'Widgets shown in the homepage right rail.',
				'before_widget' => '<span>',
				'after_widget'  => '</span>',
				'before_title'  => '<h3 class="widget-title">',
				'after_title'   => '</h3>',
				'before_sidebar' => '<span>',
				'after_sidebar' => '</span>',
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

/**
 * Lazy-loads post thumbnails via a data-src swap (script.js activates it).
 * No jQuery dependency — the previous theme's script.js required jQuery
 * for this and unrelated UI, then a separate hook deregistered jQuery
 * after the fact (a real, reproducible bug fixed by this rebuild's script.js
 * being dependency-free vanilla JS instead).
 */
add_filter(
	'post_thumbnail_html',
	static function ( string $html, int $post_id, $thumbnail_id, $size, $attr ): string {
		if ( is_admin() || ( is_array( $attr ) && ! empty( $attr['no_lazy'] ) ) ) {
			return $html;
		}
		$id     = get_post_thumbnail_id( $post_id );
		$src    = wp_get_attachment_image_src( $id, $size );
		$srcset = wp_get_attachment_image_srcset( $id, $size );
		$sizes  = wp_get_attachment_image_sizes( $id, $size );
		$alt    = get_the_title( $id );
		$class  = is_array( $attr ) && ! empty( $attr['class'] ) ? $attr['class'] : '';

		if ( empty( $src ) ) {
			return $html;
		}

		$placeholder = apply_filters( 'bday_thumbnail_fallback_url', 'https://cdn.businessday.ng/wp-content/uploads/2023/11/Business-Day-Grey-e1691776368938.jpg' );

		return sprintf(
			'<img src="%1$s" data-src="%2$s" data-srcset="%3$s" sizes="%4$s" alt="%5$s" class="%6$s img-lazy-load">',
			esc_url( $placeholder ),
			esc_url( $src[0] ),
			esc_attr( (string) $srcset ),
			esc_attr( (string) $sizes ),
			esc_attr( $alt ),
			esc_attr( trim( $class ) )
		);
	},
	99,
	5
);
