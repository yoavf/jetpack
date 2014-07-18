<div>
	<label class="grunion-field-label<?php echo ( $field->is_error() ? ' form-error' : '' ); ?>">"
		<?php echo esc_html( $field_label ) . ( $field_required ? '<span>' . __( "(required)", 'jetpack' ) . '</span>' : '' ); ?>
	</label>

	<?php foreach ( $field->get_attribute( 'options' ) as $option ): ?>
		<?php $option = Grunion_Contact_Form_Plugin::strip_tags( $option ); ?>
		<label class="grunion-radio-label radio<?php echo ( $field->is_error() ? ' form-error' : '' ); ?>">
			<input type='radio' name=" <?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $option ); ?>" class='radio' <?php echo checked( $option, $field_value, false ); ?> />
				<?php echo esc_html( $option ); ?>
		</label>
		<div class="clear-form"></div>
	<?php endforeach; ?>
</div>
