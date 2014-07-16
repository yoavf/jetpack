<?php

class Grunion_Contact_Form_Akismet_Adapter {
	/**
	 * Populate an array with all values necessary to submit a NEW contact-form feedback to Akismet.
	 * Note that this includes the current user_ip etc, so this should only be called when accepting a new item via $_POST
	 *
	 * @param array $form Contact form feedback array
	 * @return array feedback array with additional data ready for submission to Akismet
	 */
	static function prepare_for_akismet( $form ) {
		$form['comment_type'] = 'contact_form';
		$form['user_ip']      = preg_replace( '/[^0-9., ]/', '', $_SERVER['REMOTE_ADDR'] );
		$form['user_agent']   = $_SERVER['HTTP_USER_AGENT'];
		$form['referrer']     = $_SERVER['HTTP_REFERER'];
		$form['blog']         = get_option( 'home' );

		$ignore = array( 'HTTP_COOKIE' );

		foreach ( $_SERVER as $k => $value ) {
			if ( !in_array( $k, $ignore ) && is_string( $value ) ) {
				$form["$k"] = $value;
			}
		}

		return $form;
	}

	/**
	 * Submit contact-form data to Akismet to check for spam.
	 * If you're accepting a new item via $_POST, run it Grunion_Contact_Form_Plugin::prepare_for_akismet() first
	 * Attached to `contact_form_is_spam`
	 *
	 * @param array $form
	 * @return bool|WP_Error TRUE => spam, FALSE => not spam, WP_Error => stop processing entirely
	 */
	static function is_spam( $form ) {
		global $akismet_api_host, $akismet_api_port;

		if ( !function_exists( 'akismet_http_post' ) && !defined( 'AKISMET_VERSION' ) ) {
			return false;
		}

		$query_string = http_build_query( $form );

		if ( method_exists( 'Akismet', 'http_post' ) ) {
		    $response = Akismet::http_post( $query_string, 'comment-check' );
		} else {
		    $response = akismet_http_post( $query_string, $akismet_api_host, '/1.1/comment-check', $akismet_api_port );
		}

		$result = false;

		if ( isset( $response[0]['x-akismet-pro-tip'] ) && 'discard' === trim( $response[0]['x-akismet-pro-tip'] ) && get_option( 'akismet_strictness' ) === '1' ) {
			$result = new WP_Error( 'feedback-discarded', __('Feedback discarded.', 'jetpack' ) );
		} elseif ( isset( $response[1] ) && 'true' == trim( $response[1] ) ) { // 'true' is spam
			$result = true;
		}

		return apply_filters( 'contact_form_is_spam_akismet', $result, $form );
	}

	/**
	 * Submit a feedback as either spam or ham
	 *
	 * @param string $as Either 'spam' or 'ham'.
	 * @param array $form the contact-form data
	 */
	static function submit_as( $as, $form ) {
		global $akismet_api_host, $akismet_api_port;

		if ( !in_array( $as, array( 'ham', 'spam' ) ) ) {
			return false;
		}

		$query_string = '';
		if ( is_array( $form ) ) {
			$query_string = http_build_query( $form );
		}

		if ( method_exists( 'Akismet', 'http_post' ) ) {
		    $response = Akismet::http_post( $query_string, "submit-{$as}" );
		} else {
		    $response = akismet_http_post( $query_string, $akismet_api_host, "/1.1/submit-{$as}", $akismet_api_port );
		}

		return trim( $response[1] );
	}
}

?>
