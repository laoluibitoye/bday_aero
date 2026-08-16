<?php
/**
 * One field-rendering code path shared by every tab, generalizing the
 * previous theme's five near-duplicate bd_settings_*_fields() functions
 * into a single implementation driven by a field's declared 'type'.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bday_render_field( array $field, array $values ): void {
	$key   = $field['key'];
	$value = $values[ $key ] ?? ( $field['default'] ?? '' );
	$name  = $field['_option'] . '[' . $key . ']';

	switch ( $field['type'] ) {
		case 'checkbox':
			printf(
				'<input type="checkbox" name="%s" value="1" %s />',
				esc_attr( $name ),
				checked( (bool) $value, true, false )
			);
			break;

		case 'number':
			printf(
				'<input type="number" name="%s" value="%s" min="%s" step="1" class="small-text" />',
				esc_attr( $name ),
				esc_attr( (string) $value ),
				esc_attr( (string) ( $field['min'] ?? 0 ) )
			);
			break;

		case 'select':
			printf( '<select name="%s">', esc_attr( $name ) );
			foreach ( (array) ( $field['options'] ?? array() ) as $opt_value => $opt_label ) {
				printf(
					'<option value="%s" %s>%s</option>',
					esc_attr( (string) $opt_value ),
					selected( $value, $opt_value, false ),
					esc_html( $opt_label )
				);
			}
			echo '</select>';
			break;

		case 'code-editor':
			// Verbatim storage/output by design — this is the custom-code
			// injection surface. Gated by the settings page's own
			// manage_options requirement (WordPress's options.php checks
			// this by default for any registered setting), so anyone who
			// can reach this field could already do anything else a
			// manage_options user can do; this isn't a new privilege.
			printf(
				'<textarea name="%s" rows="8" class="large-text code" style="font-family:monospace;">%s</textarea>',
				esc_attr( $name ),
				esc_textarea( (string) $value )
			);
			break;

		case 'url':
			printf( '<input type="text" name="%s" value="%s" class="regular-text" />', esc_attr( $name ), esc_attr( (string) $value ) );
			break;

		case 'image':
			// Media-library picker (wp.media, enqueued for every page
			// under this framework's menu — class-options-framework.php's
			// enqueue_media()). $value is an attachment ID; the field
			// stores that ID, not a URL, so the source stays valid even
			// if the image is later replaced/regenerated at another size.
			$attachment_id = (int) $value;
			$preview_url   = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
			$field_id      = 'bday-image-field-' . sanitize_html_class( $name );
			?>
			<div class="bday-image-field" data-bday-image-field>
				<div class="bday-image-field__preview" style="margin-bottom:8px;">
					<?php if ( $preview_url ) : ?>
						<img src="<?php echo esc_url( $preview_url ); ?>" alt="" style="max-width:220px;height:auto;display:block;border:1px solid #ddd;">
					<?php endif; ?>
				</div>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( (string) $attachment_id ); ?>" data-bday-image-input>
				<button type="button" class="button" data-bday-image-select>Select image</button>
				<button type="button" class="button" data-bday-image-remove style="<?php echo $attachment_id ? '' : 'display:none;'; ?>">Remove</button>
			</div>
			<?php
			break;

		case 'text':
		default:
			printf( '<input type="text" name="%s" value="%s" class="regular-text" />', esc_attr( $name ), esc_attr( (string) $value ) );
			break;
	}

	if ( ! empty( $field['description'] ) ) {
		echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
	}
}

/**
 * Sanitizes one option's full value array against its schema field list —
 * every field the schema declares is normalized (so an unchecked checkbox,
 * absent from $input entirely, still ends up false rather than sticking at
 * a stale prior value), and code-editor fields are stored verbatim
 * (wp_unslash only) since arbitrary script/style output is the entire
 * point of that field type.
 *
 * @param array<int, array<string, mixed>> $fields
 * @param mixed                             $input
 * @return array<string, mixed>
 */
function bday_sanitize_fields( array $fields, $input ): array {
	$input  = is_array( $input ) ? $input : array();
	$output = array();

	foreach ( $fields as $field ) {
		$key = $field['key'];
		$raw = $input[ $key ] ?? null;

		switch ( $field['type'] ) {
			case 'checkbox':
				$output[ $key ] = ! empty( $raw );
				break;
			case 'number':
				$output[ $key ] = max( (int) ( $field['min'] ?? 0 ), (int) $raw );
				break;
			case 'select':
				$allowed        = array_keys( (array) ( $field['options'] ?? array() ) );
				$output[ $key ] = in_array( $raw, $allowed, true ) ? $raw : ( $field['default'] ?? ( $allowed[0] ?? '' ) );
				break;
			case 'url':
				$output[ $key ] = esc_url_raw( wp_unslash( (string) $raw ) );
				break;
			case 'image':
				$output[ $key ] = absint( $raw );
				break;
			case 'code-editor':
				$output[ $key ] = wp_unslash( (string) $raw );
				break;
			case 'text':
			default:
				$output[ $key ] = sanitize_text_field( wp_unslash( (string) $raw ) );
				break;
		}
	}

	return $output;
}
