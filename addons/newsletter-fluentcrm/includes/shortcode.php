<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'fluentcrm_remote_form', 'bday_newsletter_shortcode' );

function bday_newsletter_shortcode( $atts ): string {
	$atts = shortcode_atts(
		array(
			'title'       => 'Subscribe to our Newsletter',
			'description' => 'Stay updated with our latest news and analysis.',
			'button_text' => 'Subscribe',
			'lists'       => '',
		),
		$atts
	);

	$all_lists = bday_newsletter_get_lists();
	$visible_ids = '' !== $atts['lists']
		? array_map( 'intval', explode( ',', $atts['lists'] ) )
		: array_map( 'intval', (array) bday_newsletter_setting( 'visible_lists', array() ) );

	$lists = array_values( array_filter( $all_lists, static fn( $l ) => in_array( (int) $l['id'], $visible_ids, true ) ) );
	if ( empty( $lists ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="bday-newsletter-form">
		<?php if ( $atts['title'] ) : ?><h3><?php echo esc_html( $atts['title'] ); ?></h3><?php endif; ?>
		<?php if ( $atts['description'] ) : ?><p><?php echo esc_html( $atts['description'] ); ?></p><?php endif; ?>
		<?php bday_newsletter_render_form_fields( $lists, $atts['button_text'] ); ?>
	</div>
	<?php
	return (string) ob_get_clean();
}

function bday_newsletter_render_form_fields( array $lists, string $button_text ): void {
	?>
	<form class="bday-newsletter-form__form">
		<?php wp_nonce_field( 'bday_newsletter_subscribe', 'bday_newsletter_nonce' ); ?>
		<div class="bday-newsletter-form__row">
			<input type="text" name="first_name" placeholder="First name" required>
			<input type="text" name="last_name" placeholder="Last name" required>
		</div>
		<input type="email" name="email" placeholder="Email address" required>
		<div class="bday-newsletter-form__lists">
			<?php foreach ( $lists as $list ) : ?>
				<label><input type="checkbox" name="list_ids[]" value="<?php echo esc_attr( $list['id'] ); ?>" checked> <?php echo esc_html( $list['title'] ); ?></label>
			<?php endforeach; ?>
		</div>
		<button type="submit"><?php echo esc_html( $button_text ); ?></button>
		<div class="bday-newsletter-form__message" role="status"></div>
	</form>
	<?php
}

/**
 * Reader-requested: newsletters + Category Alerts need to render inline
 * inside the SDK's My Account tab and the combined onboarding modal, not
 * only on the dedicated /newsletter-opt-in/ page the [fluentcrm_remote_form]
 * shortcode renders — those SDK mount points have no server-rendered
 * nonce field to read the way the shortcode's own inline script does, so
 * this hands one back in the payload for the SDK to echo on submit.
 * "alertEligibleCategoryIds" is just the category_mappings keys that
 * actually have a list mapped — a category with "— Do not map —" left
 * selected was never meant to trigger an alert either, and this is the
 * same source of truth an admin already fills in for the contextual
 * newsletter box, not a second curation step to maintain.
 */
add_action( 'wp_ajax_bday_newsletter_options', 'bday_newsletter_handle_options' );
add_action( 'wp_ajax_nopriv_bday_newsletter_options', 'bday_newsletter_handle_options' );

function bday_newsletter_handle_options(): void {
	$all_lists    = bday_newsletter_get_lists();
	$visible_ids  = array_map( 'intval', (array) bday_newsletter_setting( 'visible_lists', array() ) );
	$descriptions = (array) bday_newsletter_setting( 'list_descriptions', array() );
	$mappings     = (array) bday_newsletter_setting( 'category_mappings', array() );

	$lists = array_values(
		array_map(
			static function ( $list ) use ( $descriptions ) {
				return array(
					'id'          => (int) $list['id'],
					'title'       => $list['title'],
					'description' => (string) ( $descriptions[ $list['id'] ] ?? '' ),
				);
			},
			array_filter( $all_lists, static fn( $l ) => in_array( (int) $l['id'], $visible_ids, true ) )
		)
	);

	$alert_eligible_category_ids = array_values(
		array_map( 'intval', array_keys( array_filter( $mappings, static fn( $list_id ) => (int) $list_id > 0 ) ) )
	);

	wp_send_json_success(
		array(
			'lists'                    => $lists,
			'alertEligibleCategoryIds' => $alert_eligible_category_ids,
			'nonce'                    => wp_create_nonce( 'bday_newsletter_subscribe' ),
		)
	);
}

add_action( 'wp_ajax_bday_newsletter_subscribe', 'bday_newsletter_handle_subscribe' );
add_action( 'wp_ajax_nopriv_bday_newsletter_subscribe', 'bday_newsletter_handle_subscribe' );

function bday_newsletter_handle_subscribe(): void {
	check_ajax_referer( 'bday_newsletter_subscribe', 'nonce' );

	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => 'A valid email is required.' ), 400 );
	}

	$allowed  = array_map( 'intval', (array) bday_newsletter_setting( 'visible_lists', array() ) );
	$selected = array_map( 'intval', (array) ( $_POST['list_ids'] ?? array() ) );

	$response = bday_newsletter_api_request(
		'subscribe',
		'POST',
		array(
			'email'        => $email,
			'first_name'   => sanitize_text_field( wp_unslash( $_POST['first_name'] ?? '' ) ),
			'last_name'    => sanitize_text_field( wp_unslash( $_POST['last_name'] ?? '' ) ),
			'lists'        => array_values( array_intersect( $allowed, $selected ) ),
			'detach_lists' => array_values( array_diff( $allowed, $selected ) ),
		)
	);

	if ( is_wp_error( $response ) ) {
		wp_send_json_error( array( 'message' => 'Sync failed: ' . $response->get_error_message() ), 500 );
	}
	wp_send_json_success( array( 'message' => 'You are subscribed.' ) );
}

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		if ( ! is_singular() ) {
			return;
		}
		wp_register_script( 'bday-newsletter', false, array(), null, true );
		wp_enqueue_script( 'bday-newsletter' );
		wp_add_inline_script( 'bday-newsletter', bday_newsletter_inline_js( admin_url( 'admin-ajax.php' ) ) );
	}
);

function bday_newsletter_inline_js( string $ajax_url ): string {
	return <<<JS
document.addEventListener('DOMContentLoaded', function () {
	document.querySelectorAll('.bday-newsletter-form__form').forEach(function (form) {
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var msg = form.querySelector('.bday-newsletter-form__message');
			var data = new FormData(form);
			data.append('action', 'bday_newsletter_subscribe');
			fetch('{$ajax_url}', { method: 'POST', body: data })
				.then(function (r) { return r.json(); })
				.then(function (json) {
					if (msg) { msg.textContent = json.data.message; msg.className = 'bday-newsletter-form__message ' + (json.success ? 'is-success' : 'is-error'); }
					if (json.success) form.reset();
				})
				.catch(function () { if (msg) { msg.textContent = 'Connection failed.'; msg.className = 'bday-newsletter-form__message is-error'; } });
		});
	});
});
JS;
}

/** Contextual "subscribe to this column's newsletter" box appended to single posts, category-mapped. */
add_filter(
	'the_content',
	static function ( string $content ): string {
		if ( ! is_single() || ! is_main_query() ) {
			return $content;
		}

		$mappings = (array) bday_newsletter_setting( 'category_mappings', array() );
		$list_id  = 0;
		$category = null;
		foreach ( get_the_category() as $cat ) {
			if ( ! empty( $mappings[ $cat->term_id ] ) ) {
				$list_id  = (int) $mappings[ $cat->term_id ];
				$category = $cat;
				break;
			}
		}
		if ( ! $list_id ) {
			return $content;
		}

		$lists = array_values( array_filter( bday_newsletter_get_lists(), static fn( $l ) => (int) $l['id'] === $list_id ) );
		if ( empty( $lists ) ) {
			return $content;
		}

		ob_start();
		?>
		<div class="bday-newsletter-contextual">
			<h4>More from our <?php echo esc_html( $category->name ); ?> column</h4>
			<?php bday_newsletter_render_form_fields( $lists, 'Subscribe' ); ?>
		</div>
		<?php
		return $content . (string) ob_get_clean();
	}
);
