<div>
	<label for="<?php echo esc_attr( $field_id ); ?>" class="grunion-field-label select<?php echo ( $field->is_error() ? ' form-error' : '' ); ?>">
		<?php echo esc_html( $field_label ) . ( $field_required ? '<span>'. __( "(required)", 'jetpack' ) . '</span>' : '' ); ?>
	</label>
	<select name="<?php echo esc_attr( $field_id ); ?>" id="<?php echo esc_attr( $field_id ); ?>" class='select' >
		<?php foreach ( $field->get_attribute( 'options' ) as $option ): ?>
			<?php $option = Grunion_Contact_Form_Plugin::strip_tags( $option ); ?>
			<option <?php echo selected( $option, $field_value, false ); ?>><?php echo esc_html( $option ); ?></option>
		<?php endforeach; ?>
	</select>
</div>
