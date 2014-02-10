<?php

/**
 * Helper utility to add menus to Jetpack
 *
 * @since 2.9
 * @package Jetpack
 **/

class Jetpack_Menu {

	/**
	 * Holds a static copy of Jetpack_Menu for the singleton
	 *
	 * @since 2.9
	 * @var Jetpack_Menu
	 **/
	private static $instance = null;

	/**
	 * Holds an array of submenu items
	 *
	 * @since 2.9
	 * @var array
	 **/
	private $submenu_items = array();

	/**
	 * Default settings when adding a new submenu
	 *
	 * @since 2.9
	 * @var array
	 **/
	private $submenu_defaults;
	
	/**
	 * Provides access to an instance of Jetpack_Menu
	 *
	 * This is how the Jetpack_Menu object should *always* be accessed
	 *
	 * @since 2.9
	 * @return Jetpack_Menu
	 **/
	public static function init(){
		if( !self::$instance || !is_a( self::$instance, 'Jetpack_Menu' )) {
			self::$instance = new Jetpack_Menu;
		}

		return self::$instance;
	}

	private function __construct() {

		$this->submenu_defaults = array(
			'parent_slug'   => 'jetpack',
			'capability'    => 'manage_options', 
			'menu_slug'     => 'jetpack_' . uniqid(),  
		);

		add_action( 'jetpack_menu', array( $this, 'build_submenu' ) );
	}

	/**
	 * Adds another element to the Jetpack submenu
	 *
	 * Pass an array of options.
	 * If 'title' is passed it will be used for menu_title and page_title
	 *
	 * Options:
	 * - parent_slug - Default: jetpack
	 * - page_title - Falls back to title
	 * - menu_title - Falls back to title
	 * - capability - Default: manage_options
	 * - menu_slug - Default: jetpack_uniqid()
	 * - function
	 *
	 * @since 2.9
	 * @param array
	 **/
	public function add_submenu( $args ) {
		$args = wp_parse_args( $args, $this->submenu_defaults );	

		// Use title for page_title
		if( !isset( $args['page_title'] ) && isset( $args['title'] ) )
			$args['page_title'] = $args['title'];

		// Use title for menu_title
		if( !isset( $args['menu_title'] ) && isset( $args['title'] ) )
			$args['menu_title'] = $args['title'];

		
		$this->submenu_items[] = array(
			'parent_slug'	=> $args['parent_slug'],
			'page_title'	=> $args['page_title'],
			'menu_title'	=> $args['menu_title'],
			'capability'	=> $args['capability'],
			'menu_slug'		=> $args['menu_slug'],
			'function'		=> $args['function']
		);
	}

	public function build_submenu() {
		foreach( $this->submenu_items AS $item ) {
			add_submenu_page(
				$item['parent_slug'],
				$item['page_title'],
				$item['menu_title'],
				$item['capability'],
				$item['menu_slug'],
				$item['function']
			);
		}
	}
}  // end class

Jetpack_Menu::init();
