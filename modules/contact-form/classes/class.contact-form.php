<?php
include_once( 'class.contact-form-shortcode.php' );
include_once( 'class.contact-form-field.php' );

/**
 * Class for the contact-form shortcode.
 * Parses shortcode to output the contact form as HTML
 * Sends email and stores the contact form response (a.k.a. "feedback")
 */
class Grunion_Contact_Form extends Crunion_Contact_Form_Shortcode {
	var $shortcode_name = 'contact-form';

	/**
	 * @var WP_Error stores form submission errors
	 */
	var $errors;

	/**
	 * @var Grunion_Contact_Form The most recent (inclusive) contact-form shortcode processed
	 */
	static $last;

	/**
	 * @var bool Whether to print the grunion.css style when processing the contact-form shortcode
	 */
	static $style = false;

	function __construct( $attributes, $content = null ) {
		global $post;

		// Set up the default subject and recipient for this form
		$default_to = get_option( 'admin_email' );
		$default_subject = "[" . get_option( 'blogname' ) . "]";

		if ( !empty( $attributes['widget'] ) && $attributes['widget'] ) {
			$attributes['id'] = 'widget-' . $attributes['widget'];

			$default_subject = sprintf( _x( '%1$s Sidebar', '%1$s = blog name', 'jetpack' ), $default_subject );
		} else if ( $post ) {
			$attributes['id'] = $post->ID;
			$default_subject = sprintf( _x( '%1$s %2$s', '%1$s = blog name, %2$s = post title', 'jetpack' ), $default_subject, Grunion_Contact_Form_Plugin::strip_tags( $post->post_title ) );
			$post_author = get_userdata( $post->post_author );
			$default_to = $post_author->user_email;
		}

		$this->defaults = array(
			'to'                 => $default_to,
			'subject'            => $default_subject,
			'show_subject'       => 'no', // only used in back-compat mode
			'widget'             => 0,    // Not exposed to the user. Works with Grunion_Contact_Form_Plugin::widget_atts()
			'id'                 => null, // Not exposed to the user. Set above.
			'submit_button_text' => __( 'Submit &#187;', 'jetpack' ),
		);

		$attributes = shortcode_atts( $this->defaults, $attributes );

		// We only add the contact-field shortcode temporarily while processing the contact-form shortcode
		add_shortcode( 'contact-field', array( $this, 'parse_contact_field' ) );

		parent::__construct( $attributes, $content );

		// There were no fields in the contact form. The form was probably just [contact-form /]. Build a default form.
		if ( empty( $this->fields ) ) {
			// same as the original Grunion v1 form
			$default_form = '
				[contact-field label="' . __( 'Name', 'jetpack' )    . '" type="name"  required="true" /]
				[contact-field label="' . __( 'Email', 'jetpack' )   . '" type="email" required="true" /]
				[contact-field label="' . __( 'Website', 'jetpack' ) . '" type="url" /]';

			if ( 'yes' == strtolower( $this->get_attribute( 'show_subject' ) ) ) {
				$default_form .= '
					[contact-field label="' . __( 'Subject', 'jetpack' ) . '" type="subject" /]';
			}

			$default_form .= '
				[contact-field label="' . __( 'Message', 'jetpack' ) . '" type="textarea" /]';

			$this->parse_content( $default_form );
		}

		// $this->body and $this->fields have been setup.  We no longer need the contact-field shortcode.
		remove_shortcode( 'contact-field' );
	}

	/**
	 * Toggle for printing the grunion.css stylesheet
	 *
	 * @param bool $style
	 */
	static function style( $style ) {
		$previous_style = self::$style;
		self::$style = (bool) $style;
		return $previous_style;
	}

	/**
	 * Turn on printing of grunion.css stylesheet
	 * @see ::style()
	 * @internal
	 * @param bool $style
	 */
	static function _style_on() {
		return self::style( true );
	}

	/**
	 * The contact-form shortcode processor
	 *
	 * @param array $attributes Key => Value pairs as parsed by shortcode_parse_atts()
	 * @param string|null $content The shortcode's inner content: [contact-form]$content[/contact-form]
	 * @return string HTML for the concat form.
	 */
	static function parse( $attributes, $content ) {
		// Create a new Grunion_Contact_Form object (this class)
		$form = new Grunion_Contact_Form( $attributes, $content );

		$id = $form->get_attribute( 'id' );

		if ( !$id ) { // something terrible has happened
			return '[contact-form]';
		}

		if ( is_feed() ) {
			return '[contact-form]';
		}

		// Only allow one contact form per post/widget
		if ( self::$last && $id == self::$last->get_attribute( 'id' ) ) {
			// We're processing the same post

			if ( self::$last->attributes != $form->attributes || self::$last->content != $form->content ) {
				// And we're processing a different shortcode;
				return '';
			} // else, we're processing the same shortcode - probably a separate run of do_shortcode() - let it through

		} else {
			self::$last = $form;
		}

		// Enqueue the grunion.css stylesheet if self::$style allows it
		if ( self::$style && ( empty( $_REQUEST['action'] ) || $_REQUEST['action'] != 'grunion_shortcode_to_json' ) ) {
			// Enqueue the style here instead of printing it, because if some other plugin has run the_post()+rewind_posts(),
			// (like VideoPress does), the style tag gets "printed" the first time and discarded, leaving the contact form unstyled.
			// when WordPress does the real loop.
			wp_enqueue_style( 'grunion.css' );
		}

		$r = '';
		$r .= "<div id='contact-form-$id'>\n";

		if ( is_wp_error( $form->errors ) && $form->errors->get_error_codes() ) {
			// There are errors.  Display them
			$r .= "<div class='form-error'>\n<h3>" . __( 'Error!', 'jetpack' ) . "</h3>\n<ul class='form-errors'>\n";
			foreach ( $form->errors->get_error_messages() as $message )
				$r .= "\t<li class='form-error-message'>" . esc_html( $message ) . "</li>\n";
			$r .= "</ul>\n</div>\n\n";
		}

		if ( isset( $_GET['contact-form-id'] ) && $_GET['contact-form-id'] == self::$last->get_attribute( 'id' ) && isset( $_GET['contact-form-sent'] ) ) {
			// The contact form was submitted.  Show the success message/results

			$feedback_id = (int) $_GET['contact-form-sent'];

			$back_url = remove_query_arg( array( 'contact-form-id', 'contact-form-sent', '_wpnonce' ) );

			$r_success_message =
				"<h3>" . __( 'Message Sent', 'jetpack' ) .
				' (<a href="' . esc_url( $back_url ) . '">' . esc_html__( 'go back', 'jetpack' ) . '</a>)' .
				"</h3>\n\n";

			// Don't show the feedback details unless the nonce matches
			if ( $feedback_id && wp_verify_nonce( stripslashes( $_GET['_wpnonce'] ), "contact-form-sent-{$feedback_id}" ) ) {
				$r_success_message .= self::success_message( $feedback_id, $form );
			}

			$r .= apply_filters( 'grunion_contact_form_success_message', $r_success_message );
		} else {
			// Nothing special - show the normal contact form

			if ( $form->get_attribute( 'widget' ) ) {
				// Submit form to the current URL
				$url = remove_query_arg( array( 'contact-form-id', 'contact-form-sent', 'action', '_wpnonce' ) );
			} else {
				// Submit form to the post permalink
				$url = get_permalink();
			}

			// May eventually want to send this to admin-post.php...
			$url = apply_filters( 'grunion_contact_form_form_action', "{$url}#contact-form-{$id}", $GLOBALS['post'], $id );

			$r .= "<form action='" . esc_url( $url ) . "' method='post' class='contact-form commentsblock'>\n";
			$r .= $form->body;
			$r .= "\t<p class='contact-submit'>\n";
			$r .= "\t\t<input type='submit' value='" . esc_attr( $form->get_attribute( 'submit_button_text' ) ) . "' class='pushbutton-wide'/>\n";
			if ( is_user_logged_in() ) {
				$r .= "\t\t" . wp_nonce_field( 'contact-form_' . $id, '_wpnonce', true, false ) . "\n"; // nonce and referer
			}
			$r .= "\t\t<input type='hidden' name='contact-form-id' value='$id' />\n";
			$r .= "\t\t<input type='hidden' name='action' value='grunion-contact-form' />\n";
			$r .= "\t</p>\n";
			$r .= "</form>\n";
		}

		$r .= "</div>";

		return $r;
	}

	static function success_message( $feedback_id, $form ) {
		$r_success_message = '';

		$feedback       = get_post( $feedback_id );
		$field_ids      = $form->get_field_ids();
		$content_fields = Grunion_Contact_Form_Plugin::parse_fields_from_content( $feedback_id );

		// Maps field_ids to post_meta keys
		$field_value_map = array(
			'name'     => 'author',
			'email'    => 'author_email',
			'url'      => 'author_url',
			'subject'  => 'subject',
			'textarea' => false, // not a post_meta key.  This is stored in post_content
		);

		$contact_form_message = "<blockquote>\n";

		// "Standard" field whitelist
		foreach ( $field_value_map as $type => $meta_key ) {
			if ( isset( $field_ids[$type] ) ) {
				$field = $form->fields[$field_ids[$type]];

				if ( $meta_key ) {
					if ( isset( $content_fields["_feedback_{$meta_key}"] ) )
						$value = $content_fields["_feedback_{$meta_key}"];
				} else {
					// The feedback content is stored as the first "half" of post_content
					$value = $feedback->post_content;
					list( $value ) = explode( '<!--more-->', $value );
					$value = trim( $value );
				}

				$contact_form_message .= sprintf(
					_x( '%1$s: %2$s', '%1$s = form field label, %2$s = form field value', 'jetpack' ),
					wp_kses( $field->get_attribute( 'label' ), array() ),
					wp_kses( $value, array() )
				) . '<br />';
			}
		}

		// Extra fields' prefixes start counting after all_fields
		$i = count( $content_fields['_feedback_all_fields'] ) + 1;

		// "Non-standard" fields
		if ( $field_ids['extra'] ) {
			// array indexed by field label (not field id)
			$extra_fields = get_post_meta( $feedback_id, '_feedback_extra_fields', true );

			foreach ( $field_ids['extra'] as $field_id ) {
				$field = $form->fields[$field_id];

				$label = $field->get_attribute( 'label' );
				$contact_form_message .= sprintf(
					_x( '%1$s: %2$s', '%1$s = form field label, %2$s = form field value', 'jetpack' ),
					wp_kses( $label, array() ),
					wp_kses( $extra_fields[$i . '_' . $label], array() )
				) . '<br />';

				$i++; // Increment prefix counter
			}
		}

		$contact_form_message .= "</blockquote><br /><br />";

		$r_success_message .= wp_kses( $contact_form_message, array( 'br' => array(), 'blockquote' => array() ) );

		return $r_success_message;
	}

	/**
	 * The contact-field shortcode processor
	 * We use an object method here instead of a static Grunion_Contact_Form_Field class method to parse contact-field shortcodes so that we can tie them to the contact-form object.
	 *
	 * @param array $attributes Key => Value pairs as parsed by shortcode_parse_atts()
	 * @param string|null $content The shortcode's inner content: [contact-field]$content[/contact-field]
	 * @return HTML for the contact form field
	 */
	function parse_contact_field( $attributes, $content ) {
		$field = new Grunion_Contact_Form_Field( $attributes, $content, $this );

		$field_id = $field->get_attribute( 'id' );
		if ( $field_id ) {
			$this->fields[$field_id] = $field;
		} else {
			$this->fields[] = $field;
		}

		if (
			isset( $_POST['action'] ) && 'grunion-contact-form' === $_POST['action']
		&&
			isset( $_POST['contact-form-id'] ) && $this->get_attribute( 'id' ) == $_POST['contact-form-id']
		) {
			// If we're processing a POST submission for this contact form, validate the field value so we can show errors as necessary.
			$field->validate();
		}

		// Output HTML
		return $field->render();
	}

	/**
	 * Loops through $this->fields to generate a (structured) list of field IDs
	 * @return array
	 */
	function get_field_ids() {
		$field_ids = array(
			'all'   => array(), // array of all field_ids
			'extra' => array(), // array of all non-whitelisted field IDs

			// Whitelisted "standard" field IDs:
			// 'email'    => field_id,
			// 'name'     => field_id,
			// 'url'      => field_id,
			// 'subject'  => field_id,
			// 'textarea' => field_id,
		);

		foreach ( $this->fields as $id => $field ) {
			$field_ids['all'][] = $id;

			$type = $field->get_attribute( 'type' );
			if ( isset( $field_ids[$type] ) ) {
				// This type of field is already present in our whitelist of "standard" fields for this form
				// Put it in extra
				$field_ids['extra'][] = $id;
				continue;
			}

			switch ( $type ) {
			case 'email' :
			case 'name' :
			case 'url' :
			case 'subject' :
			case 'textarea' :
				$field_ids[$type] = $id;
				break;
			default :
				// Put everything else in extra
				$field_ids['extra'][] = $id;
			}
		}

		return $field_ids;
	}

	/**
	 * Process the contact form's POST submission
	 * Stores feedback.  Sends email.
	 */
	function process_submission() {
		global $post;

		$plugin = Grunion_Contact_Form_Plugin::init();

		$id     = $this->get_attribute( 'id' );
		$to     = $this->get_attribute( 'to' );
		$widget = $this->get_attribute( 'widget' );

		$contact_form_subject = $this->get_attribute( 'subject' );

		$to = str_replace( ' ', '', $to );
		$emails = explode( ',', $to );

		$valid_emails = array();

		foreach ( (array) $emails as $email ) {
			if ( !is_email( $email ) ) {
				continue;
			}

			if ( function_exists( 'is_email_address_unsafe' ) && is_email_address_unsafe( $email ) ) {
				continue;
			}

			$valid_emails[] = $email;
		}

		// No one to send it to :(
		if ( !$valid_emails ) {
			return false;
		}

		$to = $valid_emails;

		// Make sure we're processing the form we think we're processing... probably a redundant check.
		if ( $widget ) {
			if ( 'widget-' . $widget != $_POST['contact-form-id'] ) {
				return false;
			}
		} else {
			if ( $post->ID != $_POST['contact-form-id'] ) {
				return false;
			}
		}

		$field_ids = $this->get_field_ids();

		// Initialize all these "standard" fields to null
		$comment_author_email = $comment_author_email_label = // v
		$comment_author       = $comment_author_label       = // v
		$comment_author_url   = $comment_author_url_label   = // v
		$comment_content      = $comment_content_label      = null;

		// For each of the "standard" fields, grab their field label and value.

		if ( isset( $field_ids['name'] ) ) {
			$field = $this->fields[$field_ids['name']];
			$comment_author = Grunion_Contact_Form_Plugin::strip_tags( stripslashes( apply_filters( 'pre_comment_author_name', addslashes( $field->value ) ) ) );
			$comment_author_label = Grunion_Contact_Form_Plugin::strip_tags( $field->get_attribute( 'label' ) );
		}

		if ( isset( $field_ids['email'] ) ) {
			$field = $this->fields[$field_ids['email']];
			$comment_author_email = Grunion_Contact_Form_Plugin::strip_tags( stripslashes( apply_filters( 'pre_comment_author_email', addslashes( $field->value ) ) ) );
			$comment_author_email_label = Grunion_Contact_Form_Plugin::strip_tags( $field->get_attribute( 'label' ) );
		}

		if ( isset( $field_ids['url'] ) ) {
			$field = $this->fields[$field_ids['url']];
			$comment_author_url = Grunion_Contact_Form_Plugin::strip_tags( stripslashes( apply_filters( 'pre_comment_author_url', addslashes( $field->value ) ) ) );
			if ( 'http://' == $comment_author_url ) {
				$comment_author_url = '';
			}
			$comment_author_url_label = Grunion_Contact_Form_Plugin::strip_tags( $field->get_attribute( 'label' ) );
		}

		if ( isset( $field_ids['textarea'] ) ) {
			$field = $this->fields[$field_ids['textarea']];
			$comment_content = trim( Grunion_Contact_Form_Plugin::strip_tags( $field->value ) );
			$comment_content_label = Grunion_Contact_Form_Plugin::strip_tags( $field->get_attribute( 'label' ) );
		}

		if ( isset( $field_ids['subject'] ) ) {
			$field = $this->fields[$field_ids['subject']];
			if ( $field->value ) {
				$contact_form_subject = Grunion_Contact_Form_Plugin::strip_tags( $field->value );
			}
		}

		$all_values = $extra_values = array();
		$i = 1; // Prefix counter for stored metadata

		// For all fields, grab label and value
		foreach ( $field_ids['all'] as $field_id ) {
			$field = $this->fields[$field_id];
			$label = $i . '_' . $field->get_attribute( 'label' );
			$value = $field->value;

			$all_values[$label] = $value;
			$i++; // Increment prefix counter for the next field
		}

		// For the "non-standard" fields, grab label and value
		// Extra fields have their prefix starting from count( $all_values ) + 1
		foreach ( $field_ids['extra'] as $field_id ) {
			$field = $this->fields[$field_id];
			$label = $i . '_' . $field->get_attribute( 'label' );
			$value = $field->value;

			$extra_values[$label] = $value;
			$i++; // Increment prefix counter for the next extra field
		}

		$contact_form_subject = trim( $contact_form_subject );

		$comment_author_IP = Grunion_Contact_Form_Plugin::get_ip_address();

		$vars = array( 'comment_author', 'comment_author_email', 'comment_author_url', 'contact_form_subject', 'comment_author_IP' );
		foreach ( $vars as $var )
			$$var = str_replace( array( "\n", "\r" ), '', $$var );
		$vars[] = 'comment_content';

		$spam = '';
		$akismet_values = $plugin->prepare_for_akismet( compact( $vars ) );

		// Is it spam?
		$is_spam = apply_filters( 'contact_form_is_spam', $akismet_values );
		if ( is_wp_error( $is_spam ) ) // WP_Error to abort
			return $is_spam; // abort
		elseif ( $is_spam === TRUE )  // TRUE to flag a spam
			$spam = '***SPAM*** ';

		if ( !$comment_author )
			$comment_author = $comment_author_email;

		$to = (array) apply_filters( 'contact_form_to', $to );
		foreach ( $to as $to_key => $to_value ) {
			$to[$to_key] = Grunion_Contact_Form_Plugin::strip_tags( $to_value );
		}

		$blog_url = parse_url( site_url() );
		$from_email_addr = 'wordpress@' . $blog_url['host'];

		$reply_to_addr = $to[0];
		if ( ! empty( $comment_author_email ) ) {
			$reply_to_addr = $comment_author_email;
		}

		$headers =  'From: "' . $comment_author  .'" <' . $from_email_addr  . ">\r\n" .
					'Reply-To: "' . $comment_author . '" <' . $reply_to_addr  . ">\r\n" .
					"Content-Type: text/plain; charset=\"" . get_option('blog_charset') . "\"";

		$subject = apply_filters( 'contact_form_subject', $contact_form_subject, $all_values );
		$url     = $widget ? home_url( '/' ) : get_permalink( $post->ID );

		$date_time_format = _x( '%1$s \a\t %2$s', '{$date_format} \a\t {$time_format}', 'jetpack' );
		$date_time_format = sprintf( $date_time_format, get_option( 'date_format' ), get_option( 'time_format' ) );
		$time = date_i18n( $date_time_format, current_time( 'timestamp' ) );

		$message = "$comment_author_label: $comment_author\n";
		if ( !empty( $comment_author_email ) ) {
			$message .= "$comment_author_email_label: $comment_author_email\n";
		}
		if ( !empty( $comment_author_url ) ) {
			$message .= "$comment_author_url_label: $comment_author_url\n";
		}
		if ( !empty( $comment_content_label ) ) {
			$message .= "$comment_content_label: $comment_content\n";
		}
		if ( !empty( $extra_values ) ) {
			foreach ( $extra_values as $label => $value ) {
				$message .= preg_replace( '#^\d+_#i', '', $label ) . ': ' . trim( $value ) . "\n";
			}
		}
		$message .= "\n";
		$message .= __( 'Time:', 'jetpack' ) . ' ' . $time . "\n";
		$message .= __( 'IP Address:', 'jetpack' ) . ' ' . $comment_author_IP . "\n";
		$message .= __( 'Contact Form URL:', 'jetpack' ) . " $url\n";

		if ( is_user_logged_in() ) {
			$message .= "\n";
			$message .= sprintf(
				__( 'Sent by a verified %s user.', 'jetpack' ),
				isset( $GLOBALS['current_site']->site_name ) && $GLOBALS['current_site']->site_name ? $GLOBALS['current_site']->site_name : '"' . get_option( 'blogname' ) . '"'
			);
		} else {
			$message .= __( 'Sent by an unverified visitor to your site.', 'jetpack' );
		}

		$message = apply_filters( 'contact_form_message', $message );
		$message = Grunion_Contact_Form_Plugin::strip_tags( $message );

		// keep a copy of the feedback as a custom post type
		$feedback_time   = current_time( 'mysql' );
		$feedback_title  = "{$comment_author} - {$feedback_time}";
		$feedback_status = $is_spam === TRUE ? 'spam' : 'publish';

		foreach ( (array) $akismet_values as $av_key => $av_value ) {
			$akismet_values[$av_key] = Grunion_Contact_Form_Plugin::strip_tags( $av_value );
		}

		foreach ( (array) $all_values as $all_key => $all_value ) {
			$all_values[$all_key] = Grunion_Contact_Form_Plugin::strip_tags( $all_value );
		}

		foreach ( (array) $extra_values as $ev_key => $ev_value ) {
			$extra_values[$ev_key] = Grunion_Contact_Form_Plugin::strip_tags( $ev_value );
		}

		/* We need to make sure that the post author is always zero for contact
		 * form submissions.  This prevents export/import from trying to create
		 * new users based on form submissions from people who were logged in
		 * at the time.
		 *
		 * Unfortunately wp_insert_post() tries very hard to make sure the post
		 * author gets the currently logged in user id.  That is how we ended up
		 * with this work around. */
		add_filter( 'wp_insert_post_data', array( $plugin, 'insert_feedback_filter' ), 10, 2 );

		$post_id = wp_insert_post( array(
			'post_date'    => addslashes( $feedback_time ),
			'post_type'    => 'feedback',
			'post_status'  => addslashes( $feedback_status ),
			'post_parent'  => (int) $post->ID,
			'post_title'   => addslashes( wp_kses( $feedback_title, array() ) ),
			'post_content' => addslashes( wp_kses( $comment_content . "\n<!--more-->\n" . "AUTHOR: {$comment_author}\nAUTHOR EMAIL: {$comment_author_email}\nAUTHOR URL: {$comment_author_url}\nSUBJECT: {$subject}\nIP: {$comment_author_IP}\n" . print_r( $all_values, TRUE ), array() ) ), // so that search will pick up this data
			'post_name'    => md5( $feedback_title ),
		) );

		// once insert has finished we don't need this filter any more
		remove_filter( 'wp_insert_post_data', array( $plugin, 'insert_feedback_filter' ), 10, 2 );

		update_post_meta( $post_id, '_feedback_extra_fields', $this->addslashes_deep( $extra_values ) );
		update_post_meta( $post_id, '_feedback_akismet_values', $this->addslashes_deep( $akismet_values ) );
		update_post_meta( $post_id, '_feedback_email', $this->addslashes_deep( compact( 'to', 'message' ) ) );

		do_action( 'grunion_pre_message_sent', $post_id, $all_values, $extra_values );

		// schedule deletes of old spam feedbacks
		if ( !wp_next_scheduled( 'grunion_scheduled_delete' ) ) {
			wp_schedule_event( time() + 250, 'daily', 'grunion_scheduled_delete' );
		}

		if ( $is_spam !== TRUE && true === apply_filters( 'grunion_should_send_email', true, $post_id ) ) {
			wp_mail( $to, "{$spam}{$subject}", $message, $headers );
		} elseif ( true === $is_spam && apply_filters( 'grunion_still_email_spam', FALSE ) == TRUE ) { // don't send spam by default.  Filterable.
			wp_mail( $to, "{$spam}{$subject}", $message, $headers );
		}

		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return self::success_message( $post_id, $this );
		}

		$redirect = wp_get_referer();
		if ( !$redirect ) { // wp_get_referer() returns false if the referer is the same as the current page
			$redirect = $_SERVER['REQUEST_URI'];
		}

		$redirect = add_query_arg( urlencode_deep( array(
			'contact-form-id'   => $id,
			'contact-form-sent' => $post_id,
			'_wpnonce'          => wp_create_nonce( "contact-form-sent-{$post_id}" ), // wp_nonce_url HTMLencodes :(
		) ), $redirect );

		$redirect = apply_filters( 'grunion_contact_form_redirect_url', $redirect, $id, $post_id );

		wp_safe_redirect( $redirect );
		exit;
	}

	function addslashes_deep( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'addslashes_deep' ), $value );
		} elseif ( is_object( $value ) ) {
			$vars = get_object_vars( $value );
			foreach ( $vars as $key => $data ) {
				$value->{$key} = $this->addslashes_deep( $data );
			}
			return $value;
		}

		return addslashes( $value );
	}
}

?>
