<div>
	<label for="<?php echo esc_attr( $field_id ); ?>" class="grunion-field-label <?php echo esc_attr( $field_type ) . ( $field->is_error() ? ' form-error' : '' ); ?>">
		<?php echo esc_html( $field_label ) . ( $field_required ? '<span>' . __( "(required)", 'jetpack' ) . '</span>' : '' ); ?>
	</label>
	<input type="text" name="<?php echo esc_attr( $field_id ); ?>" id="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $field_value ); ?>" class="<?php echo esc_attr( $field_type ); ?>" <?php echo $field_placeholder; ?> />
</div>
