<div>
	<label for="contact-form-comment-<?php echo esc_attr( $field_id ); ?>" class="grunion-field-label textarea <?php echo ( $field->is_error() ? ' form-error' : '' ); ?>">
		<?php echo esc_html( $field_label ) . ( $field_required ? '<span>' . __( "(required)", 'jetpack' ) . '</span>' : '' ); ?>
	</label>
	<textarea name="<?php echo esc_attr( $field_id ); ?>" id="contact-form-comment-<?php echo esc_attr( $field_id ); ?>" rows="20" <?php echo $field_placeholder; ?>>
		<?php echo esc_textarea( $field_value ); ?>
	</textarea>
</div>
