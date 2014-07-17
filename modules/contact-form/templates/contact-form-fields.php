<form action="<?php echo esc_url( $url ) ?>" method="post" class="contact-form commentsblock">
	<?php echo $form->body; ?>
	<p class='contact-submit'>
		<input type="submit" value="<?php echo esc_attr( $form->get_attribute( 'submit_button_text' ) ) ?>" class="pushbutton-wide"/>
		<?php if ( is_user_logged_in() ): ?>
			<?php wp_nonce_field( 'contact-form_' . $id, '_wpnonce', true, true ); ?>
		<?php endif; ?>
		<input type="hidden" name="contact-form-id" value="<?php echo $id; ?>" />
		<input type='hidden' name='action' value='grunion-contact-form' />
	</p>
</form>
