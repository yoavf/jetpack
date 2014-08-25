<?php

/**
 * Disable error reporting
 *
 * Set this to error_reporting( E_ALL ) or error_reporting( E_ALL | E_STRICT ) for debugging
 */
error_reporting(0);

/**
 * Find the file and get the content
 * @param  $path path to the file
 * @return string      
 */
function get_file($path) {

	if ( function_exists('realpath') )
		$path = realpath($path);

	if ( ! $path || ! @is_file($path) )
		return '';

	return @file_get_contents($path);
}

/**
 * Converts any url in a stylesheet, to the correct absolute url.
 *
 * Considerations:
 *  - Normal, relative URLs     `feh.png`
 *  - Data URLs                 `data:image/gif;base64,eh129ehiuehjdhsa==`
 *  - Schema-agnostic URLs      `//domain.com/feh.png`
 *  - Absolute URLs             `http://domain.com/feh.png`
 *  - Domain root relative URLs `/feh.png`
 *
 * @param $css string: The raw CSS -- should be read in directly from the file.
 * @param $css_file_url: The URL that the file can be accessed at, for calculating paths from.
 */
function absolutize_css_urls( $css, $css_file_url ) {
	$pattern = '#url\((?P<path>[^)]*)\)#i';
	$css_dir = dirname( $css_file_url );
	$p       = parse_url( $css_dir );
	$domain  = sprintf(
				'%1$s//%2$s%3$s%4$s',
				isset( $p['scheme'] )           ? "{$p['scheme']}:" : '',
				isset( $p['user'], $p['pass'] ) ? "{$p['user']}:{$p['pass']}@" : '',
				$p['host'],
				isset( $p['port'] )             ? ":{$p['port']}" : ''
			);

	if ( preg_match_all( $pattern, $css, $matches, PREG_SET_ORDER ) ) {
		$find = $replace = array();
		foreach ( $matches as $match ) {
			$url = trim( $match['path'], "'\" \t" );

			// If this is a data url, we don't want to mess with it.
			if ( 'data:' === substr( $url, 0, 5 ) ) {
				continue;
			}

			// If this is an absolute or protocol-agnostic url,
			// we don't want to mess with it.
			if ( preg_match( '#^(https?:)?//#i', $url ) ) {
				continue;
			}

			switch ( substr( $url, 0, 1 ) ) {
				case '/':
					$absolute = $domain . $url;
					break;
				default:
					$absolute = $css_dir . '/' . $url;
			}

			$find[]    = $match[0];
			$replace[] = sprintf( 'url("%s")', $absolute );
		}
		$css = str_replace( $find, $replace, $css );
	}

	return $css;
}

$load = array_unique( explode( ',',  $_GET['load']) );

if ( empty($load) )
	exit;

$compress = ( isset($_GET['c']) && $_GET['c'] );
$force_gzip = ( $compress && 'gzip' == $_GET['c'] );
$rtl = ( isset($_GET['dir']) && 'rtl' == $_GET['dir'] );
$expires_offset = 31536000; // 1 year
$out = '';
$top = ''; // used to display errors at the very top of the css in case there are any

// Adds the $jetpack_style_files array that is being used as a whitelist
require_once( 'jetpack-style-files.php' );

foreach( $load as $path ) {
	if ( !in_array( $path, $jetpack_style_files ) ) {
		$top.= '/* ERROR: Didn\'t load ' . $path . ' becasue it wasn\'t found in our whitelist */'. "\n"."\n";
		continue;
	}

	$content = get_file( $path ); 
	
	if( empty( $content ) ) {
		$top.= '/* ERROR: Coun\'t find ' . $path . ', please update the whitelist */'. "\n"."\n";
	}
	
	// convert relative to absolute paths
	$out .= absolutize_css_urls( $content, $path);

	$content;
}

if( $top )
	$out = $top . $out;

header('Content-Type: text/css; charset=UTF-8');
header('Expires: ' . gmdate( "D, d M Y H:i:s", time() + $expires_offset ) . ' GMT');
header("Cache-Control: public, max-age=$expires_offset");

if ( $compress && ! ini_get('zlib.output_compression') && 'ob_gzhandler' != ini_get('output_handler') && isset($_SERVER['HTTP_ACCEPT_ENCODING']) ) {
	header('Vary: Accept-Encoding'); // Handle proxies
	if ( false !== stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'deflate') && function_exists('gzdeflate') && ! $force_gzip ) {
		header('Content-Encoding: deflate');
		$out = gzdeflate( $out, 3 );
	} elseif ( false !== stripos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') && function_exists('gzencode') ) {
		header('Content-Encoding: gzip');
		$out = gzencode( $out, 3 );
	}
}

echo $out;
exit;
