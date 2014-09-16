<?php

/**
 * Module Name: Security
 * Module Description: Keeps you apprised of whether your WordPress install is kept safe from accidents and malicious individuals.
 * Sort Order: 20
 * First Introduced: 3.3
 * Requires Connection: No
 * Auto Activate: Yes
 * Module Tags: Other
 */

class Jetpack_Security_Center {

	static $instance = null;

	public static function get_instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new Jetpack_Security_Center;
		}

		return self::$instance;
	}

	/**
	 * Private constructor, shouldn't be called directly.
	 * Call Jetpack_Security::get_instance() instead.
	 */
	private function __construct() {
		self::$instance = $this;
		add_action( 'jetpack_admin_menu',               array( $this, 'jetpack_admin_menu' ) );
		add_action( 'jetpack_security_section_spam',    array( $this, 'jetpack_security_section_spam' ) );
		add_action( 'jetpack_security_section_login',   array( $this, 'jetpack_security_section_login' ) );
		add_action( 'jetpack_security_section_malware', array( $this, 'jetpack_security_section_malware' ) );
	}

	/**
	 * Runs on jetpack_admin_menu action.
	 *
	 * Adds admin page, and enqueues admin_page_styles function.
	 */
	public function jetpack_admin_menu() {
		// Short out early. Don't waste cycles running the filters if the user can't see the page anyway.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Find out how many areas 
		$sections = array(
			'spam',
			'login',
			'malware',
		);
		$issues = 0;
		foreach ( $sections as $section ) {
			if ( empty( self::check_section( $section ) ) ) {
				$issues++;
			}
		}

		$menu_label = __( 'Security', 'jetpack' );

		// If we have issues, add an update notification to the submenu item. Borrow core styles.
		if ( $issues ) {
			$menu_label = sprintf( '%1$s <span class="update-plugins count-%2$u"><span class="update-count">%2$u</span></span>', $menu_label, $issues );
		}

		$this->slug = add_submenu_page( 'jetpack', __( 'Security', 'jetpack' ), $menu_label, 'manage_options', 'jetpack_security', array( $this, 'admin_security_page' ) );
		add_action( "admin_print_styles-{$this->slug}", array( $this, 'admin_page_styles' ) );
	}

	/**
	 * Runs on admin_print_styles-{$this->slug} action.
	 */
	public function admin_page_styles() {
		
	}

	public function admin_security_page() {
		?>
		<div class="wrap">
			<h2 class="page-title"><?php esc_html_e( 'Jetpack Security Center', 'jetpack' ); ?></h2>
			<br class="clear" />
			<?php do_action( 'jetpack_security_before' ); ?>
			<table class="jp-security-summary">
			<tbody>
				<?php do_action( 'jetpack_security_section_spam' ); ?>
				<?php do_action( 'jetpack_security_section_login' ); ?>
				<?php do_action( 'jetpack_security_section_malware' ); ?>
			</tbody>
			</table>
			<?php do_action( 'jetpack_security_after' ); ?>
		</div><!-- /wrap -->
		<?php
	}

	/**
	 * Handle rendering of the Spam section.
	 */
	public function jetpack_security_section_spam() {
		$plugins = self::check_section( 'spam' );
		$label   = __( 'Spam Control', 'jetpack' );
		self::render_jetpack_security_section( $label, $plugins );
	}

	/**
	 * Handle rendering of the Login section.
	 */
	public function jetpack_security_section_login() {
		$plugins = self::check_section( 'login' );
		$label   = __( 'Login Protection', 'jetpack' );
		self::render_jetpack_security_section( $label, $plugins );
	}


	/**
	 * Handle rendering of the Malware section.
	 */
	public function jetpack_security_section_malware() {
		$plugins = self::check_section( 'malware' );
		$label   = __( 'Malware Scanning', 'jetpack' );
		self::render_jetpack_security_section( $label, $plugins );
	}

	/**
	 * Fires off the filter to find out if any plugins are covering a given area.
	 *
	 * @param $section string - One of `spam` `login` or `malware`.
	 *                          More options may be added at a later date.
	 * @return array
	 */
	public static function check_section( $section ) {
		return apply_filters( "jetpack_security_plugins_handling_{$section}", array() );
	}

	/**
	 * Handle the rendering of each section.
	 */
	public static function render_jetpack_security_section( $label, $plugins ) {
		?>
		<tr class="jp-security-spam">
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td class="<?php echo $plugins ? 'covered' : 'not-covered'; ?>">
				<h4><?php echo $plugins ? esc_html__( 'You\'re covered!', 'jetpack' ) : esc_html__( 'You may not be covered!', 'jetpack' ); ?></h4>
				<?php if ( $plugins ) : ?>
					<p><?php esc_html_e( 'You are covered by the following plugin(s)', 'jetpack' ); ?></p>
					<ul>
					<?php foreach ( (array) $plugins as $slug => $label ) : ?>
						<li><?php echo esc_html( $label ); ?></li>
					<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

}

Jetpack_Security_Center::get_instance();