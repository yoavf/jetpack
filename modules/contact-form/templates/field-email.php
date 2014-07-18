<div>
	<label for=" <?php echo esc_attr( $field_id ); ?>" class='grunion-field-label email <?php echo ( $field->is_error() ? ' form-error' : '' ); ?>" >
		<?php echo esc_html( $field_label ) . ( $field_required ? '<span>' . __( "(required)", 'jetpack' ) . '</span>' : '' ) ?>
	</label>
	<input type="email" name="<?php echo esc_attr( $field_id ); ?>" id="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $field_value ); ?>" class="email" <?php echo $field_placeholder; ?> "/>
</div>
