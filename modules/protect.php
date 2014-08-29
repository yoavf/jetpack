<?php
/**
 * Module Name: Protect
 * Module Description: Protect your site from brute-force attacks that want to have their way with your data.
 * Sort Order: 28
 * First Introduced: 3.9
 * Requires Connection: Yes
 * Auto Activate: Yes
 * Module Tags: Security
 */

include( 'protect/protect.class.php' );
$jpp = new Jetpack_Protect;

if ( isset( $pagenow ) && $pagenow == 'wp-login.php' ) {
	$jpp->check_loginability();
} else {
	//	This is in case the wp-login.php pagenow variable fails
	add_action( 'login_head', array( &$jpp, 'check_loginability' ) );
}

