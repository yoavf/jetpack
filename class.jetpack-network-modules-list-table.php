<?php

if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Jetpack_Network_Modules_List_Table extends WP_List_Table {

	private $auto_activated_modules;
	private $show_modules_list;

	public function get_columns() {
		// site name, status, username connected under
		$columns = array(
			//'cb'        => '<input type="checkbox" />',
			'auto_activate' => __( 'Auto-Activate', 'jetpack'  ),
			'show_module' => __( 'Visible', 'jetpack' ),
			'module' => __( 'Module', 'jetpack' ),
			//'connected' => __( 'Connected', 'jetpack' ),
		);

		return $columns;
	}

	public function prepare_items() {
		$jpms = Jetpack_Network::init();

		// Setup some thing pre-display to save system resources
		$this->auto_activated_modules = $jpms->get_option( 'auto_activated_modules' );
		$this->show_modules_list = $jpms->get_option( 'show_modules_list' );

		// Get a list of modules
		$modules = array();
		$module_slugs = Jetpack::get_available_modules();
		foreach ( $module_slugs as $slug ) {
			$module = Jetpack::get_module( $slug );
			$module['module'] = $slug;
			$modules[] = $module;
		}
		
		sort( $modules );


		// Setup pagination
		$per_page = 40;
		$current_page = $this->get_pagenum();
		$total_items = count( $modules );
		$modules = array_slice( $modules, ( ( $current_page-1 ) * $per_page ), $per_page );
		
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page
		) );

		$columns = $this->get_columns();
		$hidden = array();
		$sortable = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items = $modules;
	}

	public function column_auto_activate( $item ) {
		$checked = checked( in_array( $item['module'], $this->auto_activated_modules ), true, false );

		return '<input type="checkbox" name="auto_activated[]" value="' . $item['module'] . '" '  . $checked . '" />';
	}

	public function column_show_module( $item ) {
		$checked = checked( in_array( $item['module'], $this->show_modules_list ), true, false );

		return '<input type="checkbox" name="show_module[]" value="' . $item['module'] . '" '  . $checked . '" />';
	}

	public function column_module( $item ) {
		return $item['name'];
	}
} // end h
