<h3><?php _e( 'Message Sent', 'jetpack' ) ?> (<a href="<?php echo esc_url( $back_url ) ?>"><?php esc_html_e( 'go back', 'jetpack' ) ?></a>)</h3>

<?php
// Don't show the feedback details unless the nonce matches
if ( $feedback_id && wp_verify_nonce( stripslashes( $_GET['_wpnonce'] ), "contact-form-sent-{$feedback_id}" ) ) {
	echo Grunion_Contact_Form::success_message( $feedback_id, $form );
}
?>
