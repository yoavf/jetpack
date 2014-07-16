<?php

/*
Plugin Name: Grunion Contact Form
Description: Add a contact form to any post, page or text widget.  Emails will be sent to the post's author by default, or any email address you choose.  As seen on WordPress.com.
Plugin URI: http://automattic.com/#
AUthor: Automattic, Inc.
Author URI: http://automattic.com/
Version: 2.4
License: GPLv2 or later
*/

define( 'GRUNION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GRUNION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

include_once( GRUNION_PLUGIN_DIR . '/classes/class.contact-form.php' );
include_once( GRUNION_PLUGIN_DIR . '/classes/class.contact-form-akismet-adapter.php' );
include_once( GRUNION_PLUGIN_DIR . '/classes/class.contact-form-csv-exporter.php' );

if ( is_admin() )
	require_once GRUNION_PLUGIN_DIR . '/admin.php';

/**
 * Sets up various actions, filters, post types, post statuses, shortcodes.
 */
class Grunion_Contact_Form_Plugin {
	/**
	 * @var string The Widget ID of the widget currently being processed.  Used to build the unique contact-form ID for forms embedded in widgets.
	 */
	var $current_widget_id;

	static function init() {
		static $instance = false;

		if ( !$instance ) {
			$instance = new Grunion_Contact_Form_Plugin;
		}

		return $instance;
	}

	/**
	 * Strips HTML tags from input.  Output is NOT HTML safe.
	 *
	 * @param string $string
	 * @return string
	 */
	public static function strip_tags( $string ) {
		$string = wp_kses( $string, array() );
		return str_replace( '&amp;', '&', $string ); // undo damage done by wp_kses_normalize_entities()
	}

	function __construct() {
		$this->add_shortcode();
		$this->register_custom_post_type_and_status();

		// Add a filter to replace tokens in the subject field with sanitized field values
		add_filter( 'contact_form_subject', array( $this, 'replace_tokens_with_input' ), 10, 2 );

		// While generating the output of a text widget with a contact-form shortcode, we need to know its widget ID.
		add_action( 'dynamic_sidebar', array( $this, 'track_current_widget' ) );

		// Add a "widget" shortcode attribute to all contact-form shortcodes embedded in widgets
		add_filter( 'widget_text', array( $this, 'widget_atts' ), 0 );

		// If Text Widgets don't get shortcode processed, hack ours into place.
		if ( !has_filter( 'widget_text', 'do_shortcode' ) )
			add_filter( 'widget_text', array( $this, 'widget_shortcode_hack' ), 5 );

		// Akismet to the rescue
		if ( defined( 'AKISMET_VERSION' ) || function_exists( 'akismet_http_post' ) ) {
			add_filter( 'contact_form_is_spam', array( 'Grunion_Contact_Form_Akismet_Adapter', 'is_spam' ), 10 );
			add_action( 'contact_form_akismet', array( 'Grunion_Contact_Form_Akismet_Adapter', 'submit_as' ), 10, 2 );
		}

		add_action( 'loop_start', array( 'Grunion_Contact_Form', '_style_on' ) );

		// Handle ajax
		add_action( 'wp_ajax_grunion-contact-form', array( $this, 'ajax_request' ) );
		add_action( 'wp_ajax_nopriv_grunion-contact-form', array( $this, 'ajax_request' ) );

		// Export to CSV feature
		if ( is_admin() ) {
			add_action( 'admin_init',            array( 'Grunion_Contact_Form_CSV_Exporter', 'download_feedback_as_csv' ) );
			add_action( 'admin_footer-edit.php', array( 'Grunion_Contact_Form_CSV_Exporter', 'export_form' ) );
		}

		// POST handler
		if ( $this->is_proper_post() ) {
			add_action( 'template_redirect', array( $this, 'process_form_submission' ) );
		}

		/* Can be dequeued by placing the following in wp-content/themes/yourtheme/functions.php
		 *
		 * 	function remove_grunion_style() {
		 *		wp_deregister_style('grunion.css');
		 *	}
		 *	add_action('wp_print_styles', 'remove_grunion_style');
		 */
		if( is_rtl() ){
			wp_register_style( 'grunion.css', GRUNION_PLUGIN_URL . 'css/rtl/grunion-rtl.css', array(), JETPACK__VERSION );
		} else {
			wp_register_style( 'grunion.css', GRUNION_PLUGIN_URL . 'css/grunion.css', array(), JETPACK__VERSION );
		}
	}

	function is_proper_post() {
		return isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' == strtoupper( $_SERVER['REQUEST_METHOD'] )
			&& isset( $_POST['action'] ) && 'grunion-contact-form' == $_POST['action']
			&& isset( $_POST['contact-form-id'] );
	}

	function register_custom_post_type_and_status() {
		// custom post type we'll use to keep copies of the feedback items
		register_post_type( 'feedback', array(
			'labels'            => array(
				'name'               => __( 'Feedback', 'jetpack' ),
				'singular_name'      => __( 'Feedback', 'jetpack' ),
				'search_items'       => __( 'Search Feedback', 'jetpack' ),
				'not_found'          => __( 'No feedback found', 'jetpack' ),
				'not_found_in_trash' => __( 'No feedback found', 'jetpack' )
			),
			'menu_icon'         => GRUNION_PLUGIN_URL . '/images/grunion-menu.png',
			'show_ui'           => TRUE,
			'show_in_admin_bar' => FALSE,
			'public'            => FALSE,
			'rewrite'           => FALSE,
			'query_var'         => FALSE,
			'capability_type'   => 'page'
		) );

		// Add "spam" as a post status
		register_post_status( 'spam', array(
			'label'                  => 'Spam',
			'public'                 => FALSE,
			'exclude_from_search'    => TRUE,
			'show_in_admin_all_list' => FALSE,
			'label_count'            => _n_noop( 'Spam <span class="count">(%s)</span>', 'Spam <span class="count">(%s)</span>', 'jetpack' ),
			'protected'              => TRUE,
			'_builtin'               => FALSE
		) );
	}

	/**
	 * Handles all contact-form POST submissions
	 *
	 * Conditionally attached to `template_redirect`
	 */
	function process_form_submission() {
		$id = stripslashes( $_POST['contact-form-id'] );

		if ( is_user_logged_in() ) {
			check_admin_referer( "contact-form_{$id}" );
		}

		$is_widget = 0 === strpos( $id, 'widget-' );

		$form = false;

		if ( $is_widget ) {
			// It's a form embedded in a text widget

			$this->current_widget_id = substr( $id, 7 ); // remove "widget-"
			$widget_type = implode( '-', array_slice( explode( '-', $this->current_widget_id ), 0, -1 ) ); // Remove trailing -#

			// Is the widget active?
			$sidebar = is_active_widget( false, $this->current_widget_id, $widget_type );

			// This is lame - no core API for getting a widget by ID
			$widget = isset( $GLOBALS['wp_registered_widgets'][$this->current_widget_id] ) ? $GLOBALS['wp_registered_widgets'][$this->current_widget_id] : false;

			if ( $sidebar && $widget && isset( $widget['callback'] ) ) {
				// This is lamer - no API for outputting a given widget by ID
				ob_start();
				// Process the widget to populate Grunion_Contact_Form::$last
				call_user_func( $widget['callback'], array(), $widget['params'][0] );
				ob_end_clean();
			}
		} else {
			// It's a form embedded in a post

			$post = get_post( $id );

			// Process the content to populate Grunion_Contact_Form::$last
			apply_filters( 'the_content', $post->post_content );
		}

		$form = Grunion_Contact_Form::$last;

		if ( ! $form )
			return false;

		if ( is_wp_error( $form->errors ) && $form->errors->get_error_codes() )
			return $form->errors;

		// Process the form
		return $form->process_submission();
	}

	function ajax_request() {
		$submission_result = self::process_form_submission();

		if ( ! $submission_result ) {
			header( "HTTP/1.1 500 Server Error", 500, true );
			echo '<div class="form-error"><ul class="form-errors"><li class="form-error-message">';
			esc_html_e( 'An error occurred. Please try again later.', 'jetpack' );
			echo '</li></ul></div>';
		} elseif ( is_wp_error( $submission_result ) ) {
			header( "HTTP/1.1 400 Bad Request", 403, true );
			echo '<div class="form-error"><ul class="form-errors"><li class="form-error-message">';
			echo esc_html( $submission_result->get_error_message() );
			echo '</li></ul></div>';
		} else {
			echo '<h3>' . esc_html__( 'Message Sent', 'jetpack' ) . '</h3>' . $submission_result;
		}

		die;
	}

	/**
	 * Ensure the post author is always zero for contact-form feedbacks
	 * Attached to `wp_insert_post_data`
	 *
	 * @see Grunion_Contact_Form::process_submission()
	 *
	 * @param array $data the data to insert
	 * @param array $postarr the data sent to wp_insert_post()
	 * @return array The filtered $data to insert
	 */
	function insert_feedback_filter( $data, $postarr ) {
		if ( $data['post_type'] == 'feedback' && $postarr['post_type'] == 'feedback' ) {
			$data['post_author'] = 0;
		}

		return $data;
	}
	/*
	 * Adds our contact-form shortcode
	 * The "child" contact-field shortcode is added as needed by the contact-form shortcode handler
	 */
	function add_shortcode() {
		add_shortcode( 'contact-form', array( 'Grunion_Contact_Form', 'parse' ) );
	}

	static function tokenize_label( $label ) {
		return '{' . trim( preg_replace( '#^\d+_#', '', $label ) ) . '}';
	}

	static function sanitize_value( $value ) {
		return preg_replace( '=((<CR>|<LF>|0x0A/%0A|0x0D/%0D|\\n|\\r)\S).*=i', null, $value );
	}

	/**
	 * Replaces tokens like {city} or {City} (case insensitive) with the value
	 * of an input field of that name
	 *
	 * @param string $subject
	 * @param array $field_values Array with field label => field value associations
	 *
	 * @return string The filtered $subject with the tokens replaced
	 */
	function replace_tokens_with_input( $subject, $field_values ) {
		// Wrap labels into tokens (inside {})
		$wrapped_labels = array_map( array( 'Grunion_Contact_Form_Plugin', 'tokenize_label' ), array_keys( $field_values ) );
		// Sanitize all values
		$sanitized_values = array_map( array( 'Grunion_Contact_Form_Plugin', 'sanitize_value' ), array_values( $field_values ) );

		// Search for all valid tokens (based on existing fields) and replace with the field's value
		$subject = str_ireplace( $wrapped_labels, $sanitized_values, $subject );
		return $subject;
	}

	/**
	 * Tracks the widget currently being processed.
	 * Attached to `dynamic_sidebar`
	 *
	 * @see $current_widget_id
	 *
	 * @param array $widget The widget data
	 */
	function track_current_widget( $widget ) {
		$this->current_widget_id = $widget['id'];
	}

	/**
	 * Adds a "widget" attribute to every contact-form embedded in a text widget.
	 * Used to tell the difference between post-embedded contact-forms and widget-embedded contact-forms
	 * Attached to `widget_text`
	 *
	 * @param string $text The widget text
	 * @return string The filtered widget text
	 */
	function widget_atts( $text ) {
		Grunion_Contact_Form::style( true );

		return preg_replace( '/\[contact-form([^a-zA-Z_-])/', '[contact-form widget="' . $this->current_widget_id . '"\\1', $text );
	}

	/**
	 * For sites where text widgets are not processed for shortcodes, we add this hack to process just our shortcode
	 * Attached to `widget_text`
	 *
	 * @param string $text The widget text
	 * @return string The contact-form filtered widget text
	 */
	function widget_shortcode_hack( $text ) {
		if ( !preg_match( '/\[contact-form([^a-zA-Z_-])/', $text ) ) {
			return $text;
		}

		$old = $GLOBALS['shortcode_tags'];
		remove_all_shortcodes();
		$this->add_shortcode();

		$text = do_shortcode( $text );

		$GLOBALS['shortcode_tags'] = $old;

		return $text;
	}

	public static function parse_fields_from_content( $post_id ) {
		static $post_fields;

		if ( !is_array( $post_fields ) )
			$post_fields = array();

		if ( isset( $post_fields[$post_id] ) )
			return $post_fields[$post_id];

		$all_values   = array();
		$post_content = get_post_field( 'post_content', $post_id );
		$content      = explode( '<!--more-->', $post_content );
		$lines        = array();

		if ( count( $content ) > 1 ) {
			$content  = str_ireplace( array( '<br />', ')</p>' ), '', $content[1] );
			$one_line = preg_replace( '/\s+/', ' ', $content );
			$one_line = preg_replace( '/.*Array \( (.*)\)/', '$1', $one_line );

			preg_match_all( '/\[([^\]]+)\] =\&gt\; ([^\[]+)/', $one_line, $matches );

			if ( count( $matches ) > 1 )
				$all_values = array_combine( array_map('trim', $matches[1]), array_map('trim', $matches[2]) );

			$lines = array_filter( explode( "\n", $content ) );
		}

		$var_map = array(
			'AUTHOR'       => '_feedback_author',
			'AUTHOR EMAIL' => '_feedback_author_email',
			'AUTHOR URL'   => '_feedback_author_url',
			'SUBJECT'      => '_feedback_subject',
			'IP'           => '_feedback_ip'
		);

		$fields = array();

		foreach( $lines as $line ) {
			$vars = explode( ': ', $line, 2 );
			if ( !empty( $vars ) ) {
				if ( isset( $var_map[$vars[0]] ) ) {
					$fields[$var_map[$vars[0]]] = self::strip_tags( trim( $vars[1] ) );
				}
			}
		}

		$fields['_feedback_all_fields'] = $all_values;

		$post_fields[$post_id] = $fields;

		return $fields;
	}

	public static function get_ip_address() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : null;
	}
}

add_action( 'init', array( 'Grunion_Contact_Form_Plugin', 'init' ) );

add_action( 'grunion_scheduled_delete', 'grunion_delete_old_spam' );

/**
 * Deletes old spam feedbacks to keep the posts table size under control
 */
function grunion_delete_old_spam() {
	global $wpdb;

	$grunion_delete_limit = 100;

	$now_gmt = current_time( 'mysql', 1 );
	$sql = $wpdb->prepare( "
		SELECT `ID`
		FROM $wpdb->posts
		WHERE DATE_SUB( %s, INTERVAL 15 DAY ) > `post_date_gmt`
			AND `post_type` = 'feedback'
			AND `post_status` = 'spam'
		LIMIT %d
	", $now_gmt, $grunion_delete_limit );
	$post_ids = $wpdb->get_col( $sql );

	foreach ( (array) $post_ids as $post_id ) {
		# force a full delete, skip the trash
		wp_delete_post( $post_id, TRUE );
	}

	# Arbitrary check points for running OPTIMIZE
	# nothing special about 5000 or 11
	# just trying to periodically recover deleted rows
	$random_num = mt_rand( 1, 5000 );
	if ( apply_filters( 'grunion_optimize_table', ( $random_num == 11 ) ) ) {
		$wpdb->query( "OPTIMIZE TABLE $wpdb->posts" );
	}

	# if we hit the max then schedule another run
	if ( count( $post_ids ) >= $grunion_delete_limit ) {
		wp_schedule_single_event( time() + 700, 'grunion_scheduled_delete' );
	}
}
