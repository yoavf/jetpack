<?php
include_once( 'class.contact-form-shortcode.php' );

/**
 * Class for the contact-field shortcode.
 * Parses shortcode to output the contact form field as HTML.
 * Validates input.
 */
class Grunion_Contact_Form_Field extends Grunion_Contact_Form_Shortcode {
	var $shortcode_name = 'contact-field';

	/**
	 * @var Grunion_Contact_Form parent form
	 */
	var $form;

	/**
	 * @var string default or POSTed value
	 */
	var $value;

	/**
	 * @var bool Is the input invalid?
	 */
	var $error = false;

	/**
	 * @param array $attributes An associative array of shortcode attributes.  @see shortcode_atts()
	 * @param null|string $content Null for selfclosing shortcodes.  The inner content otherwise.
	 * @param Grunion_Contact_Form $form The parent form
	 */
	function __construct( $attributes, $content = null, $form = null ) {
		$attributes = shortcode_atts( array(
			'label'       => null,
			'type'        => 'text',
			'required'    => false,
			'options'     => array(),
			'id'          => null,
			'default'     => null,
			'placeholder' => null,
		), $attributes );

		// special default for subject field
		if ( 'subject' == $attributes['type'] && is_null( $attributes['default'] ) && !is_null( $form ) ) {
			$attributes['default'] = $form->get_attribute( 'subject' );
		}

		// allow required=1 or required=true
		if ( '1' == $attributes['required'] || 'true' == strtolower( $attributes['required'] ) ) {
			$attributes['required'] = true;
		} else {
			$attributes['required'] = false;
		}

		// parse out comma-separated options list (for selects and radios)
		if ( !empty( $attributes['options'] ) && is_string( $attributes['options'] ) ) {
			$attributes['options'] = array_map( 'trim', explode( ',', $attributes['options'] ) );
		}

		if ( $form ) {
			// make a unique field ID based on the label, with an incrementing number if needed to avoid clashes
			$form_id = $form->get_attribute( 'id' );
			$id = isset( $attributes['id'] ) ? $attributes['id'] : false;

			$unescaped_label = $this->unesc_attr( $attributes['label'] );
			$unescaped_label = str_replace( '%', '-', $unescaped_label ); // jQuery doesn't like % in IDs?
			$unescaped_label = preg_replace( '/[^a-zA-Z0-9.-_:]/', '', $unescaped_label );

			if ( empty( $id ) ) {
				$id = sanitize_title_with_dashes( 'g' . $form_id . '-' . $unescaped_label );
				$i = 0;
				$max_tries = 24;
				while ( isset( $form->fields[$id] ) ) {
					$i++;
					$id = sanitize_title_with_dashes( 'g' . $form_id . '-' . $unescaped_label . '-' . $i );

					if ( $i > $max_tries ) {
						break;
					}
				}
			}

			$attributes['id'] = $id;
		}

		parent::__construct( $attributes, $content );

		// Store parent form
		$this->form = $form;
	}

	/**
	 * This field's input is invalid.  Flag as invalid and add an error to the parent form
	 *
	 * @param string $message The error message to display on the form.
	 */
	function add_error( $message ) {
		$this->is_error = true;

		if ( !is_wp_error( $this->form->errors ) ) {
			$this->form->errors = new WP_Error;
		}

		$this->form->errors->add( $this->get_attribute( 'id' ), $message );
	}

	/**
	 * Is the field input invalid?
	 *
	 * @see $error
	 *
	 * @return bool
	 */
	function is_error() {
		return $this->error;
	}

	/**
	 * Validates the form input
	 */
	function validate() {
		// If it's not required, there's nothing to validate
		if ( !$this->get_attribute( 'required' ) ) {
			return;
		}

		$field_id    = $this->get_attribute( 'id' );
		$field_type  = $this->get_attribute( 'type' );
		$field_label = $this->get_attribute( 'label' );

		$field_value = isset( $_POST[$field_id] ) ? stripslashes( $_POST[$field_id] ) : '';

		switch ( $field_type ) {
		case 'email' :
			// Make sure the email address is valid
			if ( !is_email( $field_value ) ) {
				$this->add_error( sprintf( __( '%s requires a valid email address', 'jetpack' ), $field_label ) );
			}
			break;
		default :
			// Just check for presence of any text
			if ( !strlen( trim( $field_value ) ) ) {
				$this->add_error( sprintf( __( '%s is required', 'jetpack' ), $field_label ) );
			}
		}
	}

	/**
	 * Outputs the HTML for this form field
	 *
	 * @return string HTML
	 */
	function render() {
		global $current_user, $user_identity;

		$field_id   = $this->get_attribute( 'id' );
		$field_type = $this->get_attribute( 'type' );
		$field_label = Grunion_Contact_Form_Plugin::strip_tags( $this->get_attribute( 'label' ) );

		if ( isset( $_POST[$field_id] ) ) {
			$this->value = stripslashes( (string) $_POST[$field_id] );
		} elseif ( is_user_logged_in() ) {
			// Special defaults for logged-in users
			switch ( $this->get_attribute( 'type' ) ) {
			case 'email';
				$this->value = $current_user->data->user_email;
				break;
			case 'name' :
				$this->value = $user_identity;
				break;
			case 'url' :
				$this->value = $current_user->data->user_url;
				break;
			default :
				$this->value = $this->get_attribute( 'default' );
			}
		} else {
			$this->value = $this->get_attribute( 'default' );
		}

		$special = array( 'date', 'radio', 'select', 'textarea', 'checkbox' );
		if ( ! in_array( $field_type, $special ) ) {
			$field_type = 'default';
		}

		$html = Grunion_Contact_Form_Plugin::template( 'field-' . $field_type, array(
			'field_id' => $field_id,
			'field_type' => $this->get_attribute( 'type' ),
			'field_label' => $field_label,
			'field_value' => Grunion_Contact_Form_Plugin::strip_tags( $this->value ),
			'field_required' => $this->get_attribute( 'required' ),
			'field_placeholder' => ( ! empty( $placeholder ) ) ? "placeholder='" . esc_attr( $placeholder ) . "'" : '',
			'placeholder' => $this->get_attribute( 'placeholder' ),
			'field' => $this
		) );

		return apply_filters( 'grunion_contact_form_field_html', $html, $field_label, ( in_the_loop() ? get_the_ID() : null ) );
	}
}

?>
