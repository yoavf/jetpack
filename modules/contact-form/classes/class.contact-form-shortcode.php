<?php

/**
 * Generic shortcode class.
 * Does nothing other than store structured data and output the shortcode as a string
 *
 * Not very general - specific to Grunion.
 */
class Grunion_Contact_Form_Shortcode {
	/**
	 * @var string the name of the shortcode: [$shortcode_name /]
 	 */
	var $shortcode_name;

	/**
	 * @var array key => value pairs for the shortcode's attributes: [$shortcode_name key="value" ... /]
	 */
	var $attributes;

	/**
	 * @var array key => value pair for attribute defaults
	 */
	var $defaults = array();

	/**
	 * @var null|string Null for selfclosing shortcodes.  Hhe inner content of otherwise: [$shortcode_name]$content[/$shortcode_name]
	 */
	var $content;

	/**
	 * @var array Associative array of inner "child" shortcodes equivalent to the $content: [$shortcode_name][child 1/][child 2/][/$shortcode_name]
	 */
	var $fields;

	/**
	 * @var null|string The HTML of the parsed inner "child" shortcodes".  Null for selfclosing shortcodes.
	 */
	var $body;

	/**
	 * @param array $attributes An associative array of shortcode attributes.  @see shortcode_atts()
	 * @param null|string $content Null for selfclosing shortcodes.  The inner content otherwise.
	 */
	function __construct( $attributes, $content = null ) {
		$this->attributes = $this->unesc_attr( $attributes );
		if ( is_array( $content ) ) {
			$string_content = '';
			foreach ( $content as $field ) {
				$string_content .= (string) $field;
			}

			$this->content = $string_content;
		} else {
			$this->content = $content;
		}

		$this->parse_content( $this->content );
	}

	/**
	 * Processes the shortcode's inner content for "child" shortcodes
	 *
	 * @param string $content The shortcode's inner content: [shortcode]$content[/shortcode]
	 */
	function parse_content( $content ) {
		if ( is_null( $content ) ) {
			$this->body = null;
		}

		$this->body = do_shortcode( $content );
	}

	/**
	 * Returns the value of the requested attribute.
	 *
	 * @param string $key The attribute to retrieve
	 * @return mixed
	 */
	function get_attribute( $key ) {
		return isset( $this->attributes[$key] ) ? $this->attributes[$key] : null;
	}

	function esc_attr( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'esc_attr' ), $value );
		}

		$value = Grunion_Contact_Form_Plugin::strip_tags( $value );
		$value = _wp_specialchars( $value, ENT_QUOTES, false, true );

		// Shortcode attributes can't contain "]"
		$value = str_replace( ']', '', $value );
		$value = str_replace( ',', '&#x002c;', $value ); // store commas encoded
		$value = strtr( $value, array( '%' => '%25', '&' => '%26' ) );

		// shortcode_parse_atts() does stripcslashes()
		$value = addslashes( $value );
		return $value;
	}

	function unesc_attr( $value ) {
		if ( is_array( $value ) ) {
			return array_map( array( $this, 'unesc_attr' ), $value );
		}

		// For back-compat with old Grunion encoding
		// Also, unencode commas
		$value = strtr( $value, array( '%26' => '&', '%25' => '%' ) );
		$value = preg_replace( array( '/&#x0*22;/i', '/&#x0*27;/i', '/&#x0*26;/i', '/&#x0*2c;/i' ), array( '"', "'", '&', ',' ), $value );
		$value = htmlspecialchars_decode( $value, ENT_QUOTES );
		$value = Grunion_Contact_Form_Plugin::strip_tags( $value );

		return $value;
	}

	/**
	 * Generates the shortcode
	 */
	function __toString() {
		$r = "[{$this->shortcode_name} ";

		foreach ( $this->attributes as $key => $value ) {
			if ( !$value ) {
				continue;
			}

			if ( isset( $this->defaults[$key] ) && $this->defaults[$key] == $value ) {
				continue;
			}

			if ( 'id' == $key ) {
				continue;
			}

			$value = $this->esc_attr( $value );

			if ( is_array( $value ) ) {
				$value = join( ',', $value );
			}

			if ( false === strpos( $value, "'" ) ) {
				$value = "'$value'";
			} elseif ( false === strpos( $value, '"' ) ) {
				$value = '"' . $value . '"';
			} else {
				// Shortcodes can't contain both '"' and "'".  Strip one.
				$value = str_replace( "'", '', $value );
				$value = "'$value'";
			}

			$r .= "{$key}={$value} ";
		}

		$r = rtrim( $r );

		if ( $this->fields ) {
			$r .= ']';

			foreach ( $this->fields as $field ) {
				$r .= (string) $field;
			}

			$r .= "[/{$this->shortcode_name}]";
		} else {
			$r .= '/]';
		}

		return $r;
	}
}

?>
