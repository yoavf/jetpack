<div>
	<label for="<?php echo esc_attr( $field_id ); ?>" class='grunion-field-label <?php echo esc_attr( $field_type ) . ( $field->is_error() ? ' form-error' : '' ); ?>">
		<?php echo esc_html( $field_label ) . ( $field_required ? '<span>' . __( "(required)", 'jetpack' ) . '</span>' : '' ); ?>
	</label>
		<input type='date' name="<?php echo esc_attr( $field_id ); ?>" id="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( $field_value ); ?>" class="<?php echo esc_attr( $field_type ); ?>"/>
</div>

<?php wp_enqueue_script( 'grunion-frontend', plugins_url( 'js/grunion-frontend.js', __FILE__ ), array( 'jquery', 'jquery-ui-datepicker' ) ); ?>
