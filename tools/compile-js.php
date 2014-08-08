<?php

define( 'ABSPATH', dirname( dirname( __FILE__ ) ) . '/' );
require_once( 'jshrink.php' );

exec( 'find ' . ABSPATH . 'modules -type f -name \*.js', $js_files_absolute );

$js_files_relative = array_map( function( $path ) {
	return substr( $path, strlen( ABSPATH ) );
}, $js_files_absolute );

$js_files = array_combine( $js_files_relative, $js_files_absolute );
$output   = '';
$output_m = '';
$included = array();
$skip     = array(
	// Should be admin only, safe to skip.
	'modules/after-the-deadline/atd-autoproofread.js',
	'modules/after-the-deadline/atd-nonvis-editor-plugin.js',
	'modules/after-the-deadline/atd.core.js',
	'modules/after-the-deadline/jquery.atd.js',
	'modules/after-the-deadline/tinymce/editor_plugin.js',
	'modules/after-the-deadline/tinymce/plugin.js',
	'modules/contact-form/js/grunion.js',
	'modules/custom-post-types/js/menu-checkboxes.js',
	'modules/custom-css/custom-css/js/codemirror.min.js',
	'modules/custom-css/custom-css/js/css-editor.js',
	'modules/custom-css/custom-css/js/use-codemirror.js',
	'modules/gplus-authorship/admin/connect.js',
	'modules/gplus-authorship/admin/listener.js',
	'modules/holiday-snow/snowstorm.js',
	'modules/minileven/theme/pub/minileven/js/small-menu.js',
	'modules/post-by-email/post-by-email.js',
	'modules/publicize/assets/publicize.js',
	'modules/sharedaddy/admin-sharing.js',
	'modules/shortcodes/js/jmpress.js',
	'modules/widget-visibility/widget-conditions/widget-conditions.js',
	'modules/widgets/gallery/js/admin.js',
);

foreach ( $js_files as $rel => $abs ) {
	if ( in_array( $rel, $skip ) ) {
		continue;
	}
	$incd_js[] = $rel;

	$raw_js    = file_get_contents( $abs );
	$output   .= "\r\nif ( jpconcat.files['{$rel}'] ) {\r\n\r\n";
	$output   .= $raw_js;
	$output   .= "\r\n\r\n} /* end {$rel} */\r\n";
	$size      = strlen( $raw_js );

	$min_js    = \JShrink\Minifier::minify( $raw_js );
	$output_m .= "if(jpconcat.files['{$rel}']){";
	$output_m .= $min_js;
	$output_m .= "}";
	$size_min  = strlen( $min_js );

	echo "Adding $rel …\r\n";
}

echo "Done! Added " . sizeof( $incd_js ) . " scripts!\r\n";

file_put_contents( ABSPATH . 'jetpack-combined-scripts.js',     $output );
file_put_contents( ABSPATH . 'jetpack-combined-scripts.min.js', $output_m );
file_put_contents( ABSPATH . 'jetpack-combined-scripts.json',   json_encode( $incd_js ) );
