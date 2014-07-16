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
		if ( '1' == $attributes['required'] || 'true' == strtolower( $attributes['required'] ) )
			$attributes['required'] = true;
		else
			$attributes['required'] = false;

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

		$r = '';

		$field_id          = $this->get_attribute( 'id' );
		$field_type        = $this->get_attribute( 'type' );
		$field_label       = $this->get_attribute( 'label' );
		$field_required    = $this->get_attribute( 'required' );
		$placeholder       = $this->get_attribute( 'placeholder' );
		$field_placeholder = ( ! empty( $placeholder ) ) ? "placeholder='" . esc_attr( $placeholder ) . "'" : '';

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

		$field_value = Grunion_Contact_Form_Plugin::strip_tags( $this->value );
		$field_label = Grunion_Contact_Form_Plugin::strip_tags( $field_label );

		switch ( $field_type ) {
		case 'email' :
			$r .= "\n<div>\n";
			$r .= "\t\t<label for='" . esc_attr( $field_id ) . "' class='grunion-field-label email" . ( $this->is_error() ? ' form-error' : '' ) . "'>" . esc_html( $field_label ) . ( $field_required ? '<span>' . __( "(required)", 'jetpack' ) . '</span>' : '' ) . "</label>\n";
			$r .= "\t\t<input type='email' name='" . esc_attr( $field_id ) . "' id='" . esc_attr( $field_id ) . "' value='" . esc_attr( $field_value ) . "' class='email' " . $field_placeholder . "/>\n";
			$r .= "\t</div>\n";
			break;
		case 'textarea' :
			$r .= "\n<div>\n";
			$r .= "\t\t<label for='contact-form-comment-" . esc_attr( $field_id ) . "' class='grunion-field-label textarea" . ( $this->is_error() ? ' form-error' : '' ) . "'>" . esc_html( $field_label ) . ( $field_required ? '<span>' . __( "(required)", 'jetpack' ) . '</span>' : '' ) . "</label>\n";
			$r .= "\t\t<textarea name='" . esc_attr( $field_id ) . "' id='contact-form-comment-" . esc_attr( $field_id ) . "' rows='20' " . $field_placeholder . ">" . esc_textarea( $field_value ) . "</textarea>\n";
			$r .= "\t</div>\n";
			break;
		case 'radio' :
			$r .= "\t<div><label class='grunion-field-label" . ( $this->is_error() ? ' form-error' : '' ) . "'>" . esc_html( $field_label ) . ( $field_required ? '<span>' . __( "(required)", 'jetpack' ) . '</span>' : '' ) . "</label>\n";
			foreach ( $this->get_attribute( 'options' ) as $option ) {
				$option = Grunion_Contact_Form_Plugin::strip_tags( $option );
				$r .= "\t\t<label class='grunion-radio-label radio" . ( $this->is_error() ? ' form-error' : '' ) . "'>";
				$r .= "<input type='radio' name='" . esc_attr( $field_id ) . "' value='" . esc_attr( $option ) . "' class='radio' " . checked( $option, $field_value, false ) . " /> ";
				$r .= esc_html( $option ) . "</label>\n";
				$r .= "\t\t<div class='clear-form'></div>\n";
			}
			$r .= "\t\t</div>\n";
			break;
		case 'checkbox' :
			$r .= "\t<div>\n";
			$r .= "\t\t<label class='grunion-field-label checkbox" . ( $this->is_error() ? ' form-error' : '' ) . "'>\n";
			$r .= "\t\t<input type='checkbox' name='" . esc_attr( $field_id ) . "' value='" . esc_attr__( 'Yes', 'jetpack' ) . "' class='checkbox' " . checked( (bool) $field_value, true, false ) . " /> \n";
			$r .= "\t\t" . esc_html( $field_label ) . ( $field_required ? '<span>'. __( "(required)", 'jetpack' ) . '</span>' : '' ) . "</label>\n";
			$r .= "\t\t<div class='clear-form'></div>\n";
			$r .= "\t</div>\n";
			break;
		case 'select' :
			$r .= "\n<div>\n";
			$r .= "\t\t<label for='" . esc_attr( $field_id ) . "' class='grunion-field-label select" . ( $this->is_error() ? ' form-error' : '' ) . "'>" . esc_html( $field_label ) . ( $field_required ? '<span>'. __( "(required)", 'jetpack' ) . '</span>' : '' ) . "</label>\n";
			$r .= "\t<select name='" . esc_attr( $field_id ) . "' id='" . esc_attr( $field_id ) . "' class='select' >\n";
			foreach ( $this->get_attribute( 'options' ) as $option ) {
				$option = Grunion_Contact_Form_Plugin::strip_tags( $option );
				$r .= "\t\t<option" . selected( $option, $field_value, false ) . ">" . esc_html( $option ) . "</option>\n";
			}
			$r .= "\t</select>\n";
			$r .= "\t</div>\n";
			break;
		case 'date' :
			$r .= "\n<div>\n";
			$r .= "\t\t<label for='" . esc_attr( $field_id ) . "' class='grunion-field-label " . esc_attr( $field_type ) . ( $this->is_error() ? ' form-error' : '' ) . "'>" . esc_html( $field_label ) . ( $field_required ? '<span>' . __( "(required)", 'jetpack' ) . '</span>' : '' ) . "</label>\n";
			$r .= "\t\t<input type='date' name='" . esc_attr( $field_id ) . "' id='" . esc_attr( $field_id ) . "' value='" . esc_attr( $field_value ) . "' class='" . esc_attr( $field_type ) . "'/>\n";
			$r .= "\t</div>\n";

			wp_enqueue_script( 'grunion-frontend', plugins_url( 'js/grunion-frontend.js', __FILE__ ), array( 'jquery', 'jquery-ui-datepicker' ) );
			break;
		default : // text field
			// note that any unknown types will produce a text input, so we can use arbitrary type names to handle
			// input fields like name, email, url that require special validation or handling at POST
			$r .= "\n<div>\n";
			$r .= "\t\t<label for='" . esc_attr( $field_id ) . "' class='grunion-field-label " . esc_attr( $field_type ) . ( $this->is_error() ? ' form-error' : '' ) . "'>" . esc_html( $field_label ) . ( $field_required ? '<span>' . __( "(required)", 'jetpack' ) . '</span>' : '' ) . "</label>\n";
			$r .= "\t\t<input type='text' name='" . esc_attr( $field_id ) . "' id='" . esc_attr( $field_id ) . "' value='" . esc_attr( $field_value ) . "' class='" . esc_attr( $field_type ) . "' " . $field_placeholder . "/>\n";
			$r .= "\t</div>\n";
		}

		return apply_filters( 'grunion_contact_form_field_html', $r, $field_label, ( in_the_loop() ? get_the_ID() : null ) );
	}
}

?>
