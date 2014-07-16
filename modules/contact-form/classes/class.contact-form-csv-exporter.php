<?php

class Grunion_Contact_Form_CSV_Exporter {
	/**
	 * Prints the menu
	 */
	static function export_form() {
		if ( get_current_screen()->id != 'edit-feedback' )
			return;

		// if there aren't any feedbacks, bail out
		if ( ! (int) wp_count_posts( 'feedback' )->publish )
			return;
		?>

		<div id="feedback-export" style="display:none">
			<h2><?php _e( 'Export feedback as CSV', 'jetpack' ) ?></h2>
			<div class="clear"></div>
			<form action="<?php echo admin_url( 'admin-post.php' ); ?>" method="post" class="form">
				<?php wp_nonce_field( 'feedback_export','feedback_export_nonce' ); ?>

				<input name="action" value="feedback_export" type="hidden">
				<label for="post"><?php _e( 'Select feedback to download', 'jetpack' ) ?></label>
				<select name="post">
					<option value="all"><?php esc_html_e( 'All posts', 'jetpack' ) ?></option>
					<?php echo self::get_feedbacks_as_options() ?>
				</select>

				<br><br>
				<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_html_e( 'Download', 'jetpack' ); ?>">
			</form>
		</div>

		<?php
		// There aren't any usable actions in core to output the "export feedback" form in the correct place,
		// so this inline JS moves it from the top of the page to the bottom.
		?>
		<script type='text/javascript'>
		var menu = document.getElementById( 'feedback-export' ),
		wrapper = document.getElementsByClassName( 'wrap' )[0];
		wrapper.appendChild(menu);
		menu.style.display = 'block';
		</script>
		<?php
	}

	/**
	 * download as a csv a contact form or all of them in a csv file
	 */
	static function download_feedback_as_csv() {
		if ( empty( $_POST['feedback_export_nonce'] ) )
			return;

		check_admin_referer( 'feedback_export', 'feedback_export_nonce' );

		$args = array(
			'posts_per_page'   => -1,
			'post_type'        => 'feedback',
			'post_status'      => 'publish',
			'order'            => 'ASC',
			'fields'           => 'ids',
			'suppress_filters' => false,
		);

		$filename = date( "Y-m-d" ) . '-feedback-export.csv';

		// Check if we want to download all the feedbacks or just a certain contact form
		if ( ! empty( $_POST['post'] ) && $_POST['post'] !== 'all' ) {
			$args['post_parent'] = (int) $_POST['post'];
			$filename            = date( "Y-m-d" ) . '-' . str_replace( '&nbsp;', '-', get_the_title( (int) $_POST['post'] ) ) . '.csv';
		}

		$feedbacks = get_posts( $args );
		$filename  = sanitize_file_name( $filename );
		$fields    = self::get_field_names( $feedbacks );

		array_unshift( $fields, __( 'Contact Form', 'jetpack' ) );

		if ( empty( $feedbacks ) )
			return;

		// Forces the download of the CSV instead of echoing
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'Content-Type: text/csv; charset=utf-8' );

		$output = fopen( 'php://output', 'w' );

		// Prints the header
		fputcsv( $output, $fields );

		// Create the csv string from the array of post ids
		foreach ( $feedbacks as $feedback ) {
			fputcsv( $output, self::make_csv_row_from_feedback( $feedback, $fields ) );
		}

		fclose( $output );
	}

	/**
	 * Creates a valid csv row from a post id
	 *
	 * @param  int    $post_id The id of the post
	 * @param  array  $fields  An array containing the names of all the fields of the csv
	 * @return String The csv row
	 */
	protected static function make_csv_row_from_feedback( $post_id, $fields ) {
		$content_fields = Grunion_Contact_Form_Plugin::parse_fields_from_content( $post_id );
		$all_fields     = array();

		if ( isset( $content_fields['_feedback_all_fields'] ) )
			$all_fields = $content_fields['_feedback_all_fields'];

		// The first element in all of the exports will be the subject
		$row_items[] = $content_fields['_feedback_subject'];

		// Loop the fields array in order to fill the $row_items array correctly
		foreach ( $fields as $field ) {
			if ( $field === __( 'Contact Form', 'jetpack' ) ) // the first field will ever be the contact form, so we can continue
				continue;
			elseif ( array_key_exists( $field, $all_fields ) )
				$row_items[] = $all_fields[$field];
			else
				$row_items[] = '';
		}

		return $row_items;
	}



	/**
	 * Returns a string of HTML <option> items from an array of posts
	 *
	 * @return string a string of HTML <option> items
	 */
	protected static function get_feedbacks_as_options() {
		$options = '';

		// Get the feedbacks' parents' post IDs
		$feedbacks = get_posts( array(
			'fields'           => 'id=>parent',
			'posts_per_page'   => 100000,
			'post_type'        => 'feedback',
			'post_status'      => 'publish',
			'suppress_filters' => false,
		) );
		$parents = array_unique( array_values( $feedbacks ) );

		$posts = get_posts( array(
			'orderby'          => 'ID',
			'posts_per_page'   => 1000,
			'post_type'        => 'any',
			'post__in'         => array_values( $parents ),
			'suppress_filters' => false,
		) );

		// creates the string of <option> elements
		foreach ( $posts as $post ) {
			$options .= sprintf( '<option value="%s">%s</option>', esc_attr( $post->ID ), esc_html( $post->post_title ) );
		}

		return $options;
	}

	/**
	 * Get the names of all the form's fields
	 *
	 * @param  array|int $posts the post we want the fields of
	 * @return array     the array of fields
	 */
	protected static function get_field_names( $posts ) {
		$posts = (array) $posts;
		$all_fields = array();

		foreach ( $posts as $post ){
			$fields = Grunion_Contact_Form_Plugin::parse_fields_from_content( $post );

			if ( isset( $fields['_feedback_all_fields'] ) ) {
				$extra_fields = array_keys( $fields['_feedback_all_fields'] );
				$all_fields = array_merge( $all_fields, $extra_fields );
			}
		}

		$all_fields = array_unique( $all_fields );
		return $all_fields;
	}
}

?>
