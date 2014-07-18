<div>\n";
	<label class="grunion-field-label checkbox<?php echo ( $field->is_error() ? ' form-error' : '' ); ?>>
		<input type='checkbox' name="<?php echo esc_attr( $field_id ); ?>" value="<?php esc_attr_e( 'Yes', 'jetpack' ); ?>" class="checkbox"<?php echo checked( (bool) $field_value, true, false ); ?>" />
		<?php echo esc_html( $field_label ) . ( $field_required ? '<span>'. __( "(required)", 'jetpack' ) . '</span>' : '' ); ?>
	</label>
	<div class="clear-form"></div>
</div>
