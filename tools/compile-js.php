<?php

define( 'ABSPATH', dirname( dirname( __FILE__ ) ) . '/' );
require_once( 'jshrink.php' );

exec( 'find ' . ABSPATH . 'modules -type f -name \*.js', $js_files_absolute );

$js_files_relative = array_map( function( $path ) {
	return substr( $path, strlen( ABSPATH ) );
}, $js_files_absolute );

$js_files = array_combine( $js_files_relative, $js_files_absolute );
$output   = '';

foreach ( $js_files as $rel => $abs ) {
	$output .= "\r\nif ( jpconcat.files['{$rel}'] ) {\r\n\r\n";
	$output .= \JShrink\Minifier::minify( file_get_contents( $abs ) );
	$output .= "\r\n\r\n} /* end {$rel} */\r\n";
}

file_put_contents( ABSPATH . 'jetpack-combined-script.js', $output );
