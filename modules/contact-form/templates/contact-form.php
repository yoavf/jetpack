<div id="contact-form-<?php echo $id; ?>">
	<?php
	if ( is_wp_error( $form->errors ) && $form->errors->get_error_codes() ) {
		// There are errors.  Display them
		Grunion_Contact_Form_Plugin::template_e( 'submission-errors', array( 'form' => $form ) );
	}

	if ( isset( $_GET['contact-form-id'] ) && $_GET['contact-form-id'] == Grunion_Contact_Form::$last->get_attribute( 'id' ) && isset( $_GET['contact-form-sent'] ) ) {
		// The contact form was submitted.  Show the success message/results
		Grunion_Contact_Form_Plugin::template_e( 'submission-success', array(
			'feedback_id' => (int) $_GET['contact-form-sent'],
			'back_url' => remove_query_arg( array( 'contact-form-id', 'contact-form-sent', '_wpnonce' ) ),
			'form' => $form
		) );
	} else {
		// Nothing special - show the normal contact form
		Grunion_Contact_Form_Plugin::template_e( 'contact-form-fields', array(
			'url' => $submit_url,
			'id' => $id,
			'form' => $form
		) );
	}
	?>
</div>
