<?php
/**
 * Adds a "Premium" column to the post-list table for each restricted post
 * type, so an editor can see gating status without opening every post.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bday_Aero_Post_List_Badge {

	private Bday_Aero_Premium_Map $premium_map;

	public function __construct( Bday_Aero_Premium_Map $premium_map ) {
		$this->premium_map = $premium_map;
		foreach ( Bday_Aero_Settings::restricted_post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
	}

	/** @param string[] $columns @return string[] */
	public function add_column( array $columns ): array {
		$columns['aero_premium'] = 'Premium';
		return $columns;
	}

	public function render_column( string $column, int $post_id ): void {
		if ( 'aero_premium' !== $column ) {
			return;
		}
		echo $this->premium_map->is_premium( $post_id )
			? '<span style="color:#b45309;font-weight:600;">&#9733; Premium</span>'
			: '<span style="color:#6b7280;">Free</span>';
	}
}
