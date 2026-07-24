<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return string[] */
function bday_ads_matrix_zones(): array {
	return array(
		'in_article_after_p2'         => 'In-article, after 2nd paragraph',
		'below_share_buttons'         => 'Below social-share buttons',
		'sidebar'                     => 'Sidebar',
		'homepage_leaderboard'        => 'Homepage leaderboard',
		'below_article_recirculation' => 'Below-article recirculation',
	);
}

/**
 * Pure array lookup — no query. `bday_ads_matrix` is read once per request
 * into a static var, matching the guardrail against per-check queries.
 */
function bday_zone_should_render( string $zone, ?WP_Post $post = null ): bool {
	static $matrix = null;
	if ( null === $matrix ) {
		$matrix = get_option( 'bday_ads_matrix', array() );
	}

	$config = $matrix[ $zone ] ?? null;
	if ( ! $config || empty( $config['enabled_globally'] ) ) {
		return false;
	}

	if ( ! $post || empty( $config['post_types'] ) ) {
		return true;
	}

	$pt_config = $config['post_types'][ $post->post_type ] ?? null;
	if ( null === $pt_config ) {
		return true; // post type not explicitly narrowed — defaults on
	}
	if ( empty( $pt_config['enabled'] ) ) {
		return false;
	}

	if ( ! empty( $pt_config['categories'] ) ) {
		$post_cats = wp_get_post_categories( $post->ID, array( 'fields' => 'slugs' ) );
		return (bool) array_intersect( $post_cats, $pt_config['categories'] );
	}

	return true;
}

/** Renders whichever vendor/direct-sold creative is assigned to a zone, only if the zone matrix allows it here. */
function bday_ad_zone( string $zone, ?WP_Post $post = null ): void {
	if ( ! bday_page_allows_ads() || ! bday_zone_should_render( $zone, $post ) ) {
		return;
	}
	do_action( 'bday_render_ad_zone', $zone, $post );
}

function bday_render_ads_matrix_tab(): void {
	$saved       = get_option( 'bday_ads_matrix', array() );
	$post_types  = get_post_types( array( 'public' => true ), 'objects' );
	?>
	<p>Every zone defaults to on, for every post type. Narrow a zone only where an editorial rule requires it (e.g. "no ads on op-eds").</p>
	<?php foreach ( bday_ads_matrix_zones() as $zone => $label ) :
		$config = $saved[ $zone ] ?? array( 'enabled_globally' => true );
		?>
		<h3><?php echo esc_html( $label ); ?></h3>
		<table class="form-table" role="presentation"><tbody>
			<tr>
				<th scope="row">Enabled</th>
				<td><label><input type="checkbox" name="bday_ads_matrix[<?php echo esc_attr( $zone ); ?>][enabled_globally]" value="1" <?php checked( ! empty( $config['enabled_globally'] ) ); ?> /> On</label></td>
			</tr>
			<?php foreach ( $post_types as $pt ) :
				$pt_conf = $config['post_types'][ $pt->name ] ?? array( 'enabled' => true );
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $pt->labels->name ); ?></th>
					<td>
						<label><input type="checkbox" name="bday_ads_matrix[<?php echo esc_attr( $zone ); ?>][post_types][<?php echo esc_attr( $pt->name ); ?>][enabled]" value="1" <?php checked( ! empty( $pt_conf['enabled'] ) ); ?> /> Enabled for <?php echo esc_html( $pt->labels->name ); ?></label>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody></table>
	<?php endforeach;
}

/** @param mixed $input @return array<string, mixed> */
function bday_sanitize_ads_matrix( $input ): array {
	$input  = is_array( $input ) ? $input : array();
	$output = array();

	foreach ( array_keys( bday_ads_matrix_zones() ) as $zone ) {
		$zone_input = is_array( $input[ $zone ] ?? null ) ? $input[ $zone ] : array();
		$output[ $zone ] = array(
			'enabled_globally' => ! empty( $zone_input['enabled_globally'] ),
			'post_types'       => array(),
		);
		foreach ( (array) ( $zone_input['post_types'] ?? array() ) as $pt => $pt_conf ) {
			$output[ $zone ]['post_types'][ sanitize_key( $pt ) ] = array(
				'enabled'    => ! empty( $pt_conf['enabled'] ),
				'categories' => array_map( 'sanitize_key', (array) ( $pt_conf['categories'] ?? array() ) ),
			);
		}
	}

	return $output;
}
