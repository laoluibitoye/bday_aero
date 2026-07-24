<?php
/**
 * Nav menu rendering: a Bootstrap-flavored Walker_Nav_Menu, login/signup/
 * subscribe visibility by auth state, and page-title hiding. Ported from
 * the previous theme's functions/bootstrap_walker.php + inc/nav-menus.php
 * unchanged in behavior — the only difference is the account URL now comes
 * from bday_paywall_login_url() (core/boundary/paywall-contract.php)
 * instead of reading the plugin's option directly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bday_Nav_Walker extends Walker_Nav_Menu {

	public function start_lvl( &$output, $depth = 0, $args = array() ) {
		$indent  = str_repeat( "\t", $depth );
		$output .= "\n$indent<ul role=\"menu\" class=\"dropdown-menu\">\n";
	}

	public function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
		$indent = $depth ? str_repeat( "\t", $depth ) : '';

		$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'menu-item-' . $item->ID;
		$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args ) );

		$has_children = is_object( $args ) && ! empty( $args->has_children );
		if ( $has_children ) {
			$class_names .= ' dropdown';
		}
		if ( in_array( 'current-menu-item', $classes, true ) ) {
			$class_names .= ' active';
		}

		$output .= $indent . '<li class="' . esc_attr( $class_names ) . '">';

		$atts = array(
			'title'  => $item->title,
			'target' => $item->target,
			'rel'    => $item->xfn,
		);

		if ( $has_children && 0 === $depth ) {
			$atts['href']          = $item->url;
			$atts['data-bs-toggle'] = 'dropdown';
			$atts['class']         = 'dropdown-toggle';
			$atts['aria-haspopup'] = 'true';
		} else {
			$atts['href'] = $item->url;
		}

		$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args );

		$attributes = '';
		foreach ( $atts as $attr => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$value       = 'href' === $attr ? esc_url( $value ) : esc_attr( $value );
			$attributes .= ' ' . $attr . '="' . $value . '"';
		}

		$title = apply_filters( 'the_title', $item->title, $item->ID );

		$output .= '<a' . $attributes . '>' . $title . ( $has_children && 0 === $depth ? ' <span class="caret"></span>' : '' ) . '</a>';
	}

	public function display_element( $element, &$children_elements, $max_depth, $depth, $args, &$output ) {
		if ( ! $element ) {
			return;
		}
		$id_field = $this->db_fields['id'];
		if ( isset( $args[0] ) && is_object( $args[0] ) ) {
			$args[0]->has_children = ! empty( $children_elements[ $element->$id_field ] );
		}
		parent::display_element( $element, $children_elements, $max_depth, $depth, $args, $output );
	}
}

/**
 * Hides "Login"/"Sign Up" nav items for logged-in users, and "Subscribe"
 * items for logged-out users — generic title-matching, no plugin coupling.
 */
add_filter(
	'wp_nav_menu_objects',
	static function ( array $items ): array {
		foreach ( $items as $key => $item ) {
			if ( is_user_logged_in() ) {
				if ( in_array( $item->title, array( 'Login', 'SignUp' ), true ) ) {
					unset( $items[ $key ] );
				}
			} elseif ( false !== strpos( trim( wp_strip_all_tags( $item->title ) ), 'Subscribe to our Premium' ) ) {
					unset( $items[ $key ] );
			}
		}
		return $items;
	},
	10,
	1
);

/** Hides the page title on WP Pages (they carry their own heading in content); leaves post titles alone. */
add_action(
	'wp_head',
	static function (): void {
		if ( is_singular( 'post' ) ) {
			return;
		}
		echo '<style>.page-title,.entry-title,.single-post-title,h1.post-title{display:none !important;}</style>';
	}
);
