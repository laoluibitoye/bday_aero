<?php
/**
 * "Popup Settings" meta box — position, trigger, device target, and
 * display rules for one bday_popup post. Same nonce/save_post_{post_type}
 * pattern every other CPT meta box in this theme already uses
 * (addons/events/addon.php is the clearest example), just with more
 * field types (select/number/checkbox-group) than that one's plain text
 * inputs.
 *
 * Deliberately a single-select "where" mode (site-wide / homepage only /
 * specific post types / specific category-or-tag / specific URLs) rather
 * than a combinable AND/OR rule builder — covers the actual "which pages
 * does this show on" question a reasonable popup setup needs without the
 * much larger surface a true rule-builder UI would require.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @return array<string, string> slug => label, 'attachment' excluded same as every other content-type picker in this theme. */
function bday_popups_public_post_types(): array {
	$post_types = get_post_types( array( 'public' => true ), 'objects' );
	$out        = array();
	foreach ( $post_types as $post_type ) {
		if ( 'attachment' === $post_type->name ) {
			continue;
		}
		$out[ $post_type->name ] = $post_type->label;
	}
	return $out;
}

/** @return array<string, array{label: string, terms: array<int, array{id: int, name: string}>}> taxonomy slug => {label, terms} for category + post_tag only — the two taxonomies an editor actually thinks in terms of for "which topic is this popup about." */
function bday_popups_targetable_taxonomies(): array {
	$out = array();
	foreach ( array( 'category', 'post_tag' ) as $taxonomy_name ) {
		$taxonomy = get_taxonomy( $taxonomy_name );
		if ( ! $taxonomy ) {
			continue;
		}
		$terms = get_terms( array( 'taxonomy' => $taxonomy_name, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			continue;
		}
		$out[ $taxonomy_name ] = array(
			'label' => $taxonomy->label,
			'terms' => array_map(
				static fn( WP_Term $term ): array => array( 'id' => $term->term_id, 'name' => $term->name ),
				$terms
			),
		);
	}
	return $out;
}

add_action(
	'add_meta_boxes',
	static function (): void {
		add_meta_box( 'bday_popup_settings', 'Popup Settings', 'bday_popups_render_metabox', 'bday_popup', 'normal', 'high' );
	}
);

function bday_popups_render_metabox( WP_Post $post ): void {
	wp_nonce_field( 'bday_popups_save', 'bday_popups_nonce' );

	$position      = (string) get_post_meta( $post->ID, '_bday_popup_position', true ) ?: 'center';
	$trigger       = (string) get_post_meta( $post->ID, '_bday_popup_trigger', true ) ?: 'immediate';
	$trigger_delay = (string) get_post_meta( $post->ID, '_bday_popup_trigger_delay', true ) ?: '5';
	$trigger_scroll = (string) get_post_meta( $post->ID, '_bday_popup_trigger_scroll', true ) ?: '50';
	$device        = (string) get_post_meta( $post->ID, '_bday_popup_device', true ) ?: 'both';
	$rule_mode     = (string) get_post_meta( $post->ID, '_bday_popup_rule_mode', true ) ?: 'site_wide';
	$rule_post_types = (array) get_post_meta( $post->ID, '_bday_popup_rule_post_types', true );
	$rule_terms    = (array) get_post_meta( $post->ID, '_bday_popup_rule_terms', true ); // each entry "taxonomy:term_id"
	$rule_urls     = (string) get_post_meta( $post->ID, '_bday_popup_rule_urls', true );
	?>
	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><label for="bday_popup_position">Position</label></th>
				<td>
					<select name="_bday_popup_position" id="bday_popup_position">
						<option value="center" <?php selected( $position, 'center' ); ?>>Center modal</option>
						<option value="top_bar" <?php selected( $position, 'top_bar' ); ?>>Top bar</option>
						<option value="bottom_bar" <?php selected( $position, 'bottom_bar' ); ?>>Bottom bar</option>
						<option value="bottom_right" <?php selected( $position, 'bottom_right' ); ?>>Bottom-right corner</option>
						<option value="fullscreen" <?php selected( $position, 'fullscreen' ); ?>>Full screen</option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bday_popup_trigger">Trigger</label></th>
				<td>
					<select name="_bday_popup_trigger" id="bday_popup_trigger" data-bday-popup-trigger-select>
						<option value="immediate" <?php selected( $trigger, 'immediate' ); ?>>Immediately on page load</option>
						<option value="delay" <?php selected( $trigger, 'delay' ); ?>>After a time delay</option>
						<option value="scroll" <?php selected( $trigger, 'scroll' ); ?>>After scrolling a percentage of the page</option>
						<option value="exit_intent" <?php selected( $trigger, 'exit_intent' ); ?>>On exit intent (cursor leaves the page, desktop only)</option>
					</select>
					<p class="description" data-bday-popup-trigger-field="delay" <?php echo 'delay' === $trigger ? '' : 'style="display:none;"'; ?>>
						Delay (seconds): <input type="number" name="_bday_popup_trigger_delay" value="<?php echo esc_attr( $trigger_delay ); ?>" min="0" step="1" class="small-text">
					</p>
					<p class="description" data-bday-popup-trigger-field="scroll" <?php echo 'scroll' === $trigger ? '' : 'style="display:none;"'; ?>>
						Scroll percentage: <input type="number" name="_bday_popup_trigger_scroll" value="<?php echo esc_attr( $trigger_scroll ); ?>" min="0" max="100" step="5" class="small-text">%
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bday_popup_device">Device target</label></th>
				<td>
					<select name="_bday_popup_device" id="bday_popup_device">
						<option value="both" <?php selected( $device, 'both' ); ?>>Desktop and mobile</option>
						<option value="desktop" <?php selected( $device, 'desktop' ); ?>>Desktop only</option>
						<option value="mobile" <?php selected( $device, 'mobile' ); ?>>Mobile only</option>
					</select>
					<p class="description">Checked in the visitor's own browser (viewport width), not on the server — so it stays correct even on a cached page.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="bday_popup_rule_mode">Show on</label></th>
				<td>
					<select name="_bday_popup_rule_mode" id="bday_popup_rule_mode" data-bday-popup-rule-select>
						<option value="site_wide" <?php selected( $rule_mode, 'site_wide' ); ?>>Entire site</option>
						<option value="home_only" <?php selected( $rule_mode, 'home_only' ); ?>>Homepage only</option>
						<option value="post_types" <?php selected( $rule_mode, 'post_types' ); ?>>Specific content types</option>
						<option value="terms" <?php selected( $rule_mode, 'terms' ); ?>>Specific category or tag</option>
						<option value="urls" <?php selected( $rule_mode, 'urls' ); ?>>Specific URLs</option>
					</select>

					<div data-bday-popup-rule-field="post_types" <?php echo 'post_types' === $rule_mode ? '' : 'style="display:none;"'; ?> style="margin-top:10px;">
						<?php foreach ( bday_popups_public_post_types() as $slug => $label ) : ?>
							<label style="display:inline-block;margin:0 16px 6px 0;">
								<input type="checkbox" name="_bday_popup_rule_post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $rule_post_types, true ) ); ?>>
								<?php echo esc_html( $label ); ?>
							</label>
						<?php endforeach; ?>
					</div>

					<div data-bday-popup-rule-field="terms" <?php echo 'terms' === $rule_mode ? '' : 'style="display:none;"'; ?> style="margin-top:10px;">
						<?php foreach ( bday_popups_targetable_taxonomies() as $taxonomy_slug => $taxonomy ) : ?>
							<p class="description" style="margin:0 0 4px;"><strong><?php echo esc_html( $taxonomy['label'] ); ?></strong></p>
							<?php foreach ( $taxonomy['terms'] as $term ) : ?>
								<?php $term_key = $taxonomy_slug . ':' . $term['id']; ?>
								<label style="display:inline-block;margin:0 16px 6px 0;">
									<input type="checkbox" name="_bday_popup_rule_terms[]" value="<?php echo esc_attr( $term_key ); ?>" <?php checked( in_array( $term_key, $rule_terms, true ) ); ?>>
									<?php echo esc_html( $term['name'] ); ?>
								</label>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</div>

					<div data-bday-popup-rule-field="urls" <?php echo 'urls' === $rule_mode ? '' : 'style="display:none;"'; ?> style="margin-top:10px;">
						<textarea name="_bday_popup_rule_urls" rows="4" class="large-text" placeholder="/about-us/&#10;/category/markets/"><?php echo esc_textarea( $rule_urls ); ?></textarea>
						<p class="description">One path per line (e.g. <code>/about-us/</code>), matched against the current page's URL path.</p>
					</div>
				</td>
			</tr>
		</tbody>
	</table>
	<script>
	(function () {
		var triggerSelect = document.querySelector( '[data-bday-popup-trigger-select]' );
		if ( triggerSelect ) {
			triggerSelect.addEventListener( 'change', function () {
				document.querySelectorAll( '[data-bday-popup-trigger-field]' ).forEach( function ( field ) {
					field.style.display = field.getAttribute( 'data-bday-popup-trigger-field' ) === triggerSelect.value ? '' : 'none';
				} );
			} );
		}
		var ruleSelect = document.querySelector( '[data-bday-popup-rule-select]' );
		if ( ruleSelect ) {
			ruleSelect.addEventListener( 'change', function () {
				document.querySelectorAll( '[data-bday-popup-rule-field]' ).forEach( function ( field ) {
					field.style.display = field.getAttribute( 'data-bday-popup-rule-field' ) === ruleSelect.value ? '' : 'none';
				} );
			} );
		}
	})();
	</script>
	<?php
}

add_action(
	'save_post_bday_popup',
	static function ( int $post_id ): void {
		if ( ! isset( $_POST['bday_popups_nonce'] ) || ! wp_verify_nonce( $_POST['bday_popups_nonce'], 'bday_popups_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$positions = array( 'center', 'top_bar', 'bottom_bar', 'bottom_right', 'fullscreen' );
		$position  = sanitize_key( wp_unslash( $_POST['_bday_popup_position'] ?? 'center' ) );
		update_post_meta( $post_id, '_bday_popup_position', in_array( $position, $positions, true ) ? $position : 'center' );

		$triggers = array( 'immediate', 'delay', 'scroll', 'exit_intent' );
		$trigger  = sanitize_key( wp_unslash( $_POST['_bday_popup_trigger'] ?? 'immediate' ) );
		update_post_meta( $post_id, '_bday_popup_trigger', in_array( $trigger, $triggers, true ) ? $trigger : 'immediate' );

		update_post_meta( $post_id, '_bday_popup_trigger_delay', max( 0, (int) ( $_POST['_bday_popup_trigger_delay'] ?? 5 ) ) );
		update_post_meta( $post_id, '_bday_popup_trigger_scroll', max( 0, min( 100, (int) ( $_POST['_bday_popup_trigger_scroll'] ?? 50 ) ) ) );

		$devices = array( 'both', 'desktop', 'mobile' );
		$device  = sanitize_key( wp_unslash( $_POST['_bday_popup_device'] ?? 'both' ) );
		update_post_meta( $post_id, '_bday_popup_device', in_array( $device, $devices, true ) ? $device : 'both' );

		$rule_modes = array( 'site_wide', 'home_only', 'post_types', 'terms', 'urls' );
		$rule_mode  = sanitize_key( wp_unslash( $_POST['_bday_popup_rule_mode'] ?? 'site_wide' ) );
		update_post_meta( $post_id, '_bday_popup_rule_mode', in_array( $rule_mode, $rule_modes, true ) ? $rule_mode : 'site_wide' );

		$valid_post_types = array_keys( bday_popups_public_post_types() );
		$submitted_types  = array_map( 'sanitize_key', wp_unslash( (array) ( $_POST['_bday_popup_rule_post_types'] ?? array() ) ) );
		update_post_meta( $post_id, '_bday_popup_rule_post_types', array_values( array_intersect( $valid_post_types, $submitted_types ) ) );

		// "taxonomy:term_id" pairs — re-validated against get_term() rather
		// than trusted as submitted, same "never trust a hidden checkbox
		// value as-is" posture as every other sanitizer in this theme.
		$submitted_terms = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['_bday_popup_rule_terms'] ?? array() ) ) );
		$valid_terms      = array();
		foreach ( $submitted_terms as $entry ) {
			$parts = explode( ':', $entry, 2 );
			if ( 2 !== count( $parts ) ) {
				continue;
			}
			list( $taxonomy, $term_id ) = $parts;
			if ( in_array( $taxonomy, array( 'category', 'post_tag' ), true ) && get_term( (int) $term_id, $taxonomy ) ) {
				$valid_terms[] = $taxonomy . ':' . (int) $term_id;
			}
		}
		update_post_meta( $post_id, '_bday_popup_rule_terms', $valid_terms );

		$urls = isset( $_POST['_bday_popup_rule_urls'] ) ? sanitize_textarea_field( wp_unslash( $_POST['_bday_popup_rule_urls'] ) ) : '';
		update_post_meta( $post_id, '_bday_popup_rule_urls', $urls );
	}
);
