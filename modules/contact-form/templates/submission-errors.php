<div class="form-error">
	<h3><?php _e( 'Error!', 'jetpack' ); ?></h3>
	<ul class="form-errors">
		<?php foreach ( $form->errors->get_error_messages() as $message ): ?>
			<li class="form-error-message"><?php echo esc_html( $message ); ?></li>
		<?php endforeach; ?>
	</ul>
</div>
