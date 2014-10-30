<?php

/**
* Required for class.media-extractor.php to match expected function naming convention.
*
* @param $url Can be just the $url or the whole $atts array
* @return bool|mixed The Youtube video ID via jetpack_get_youtube_id
*/

function jetpack_shortcode_get_youtube_id( $url ) {
    return jetpack_get_youtube_id( $url );
}

/**
* @param $url Can be just the $url or the whole $atts array
* @return bool|mixed The Youtube video ID
*/
function jetpack_get_youtube_id( $url ) {
	// Do we have an $atts array?  Get first att
	if ( is_array( $url ) )
		$url = $url[0];

	$url = youtube_sanitize_url( $url );
	$url = parse_url( $url );
	$id  = false;

	if ( ! isset( $url['query'] ) )
		return false;

	parse_str( $url['query'], $qargs );

	if ( ! isset( $qargs['v'] ) && ! isset( $qargs['list'] ) )
		return false;

	if ( isset( $qargs['list'] ) )
		$id = preg_replace( '|[^_a-z0-9-]|i', '', $qargs['list'] );

	if ( empty( $id ) )
		$id = preg_replace( '|[^_a-z0-9-]|i', '', $qargs['v'] );

	return $id;
}

if ( !function_exists( 'youtube_sanitize_url' ) ) :
/**
* Normalizes a YouTube URL to include a v= parameter and a query string free of encoded ampersands.
*
* @param string $url
* @return string The normalized URL
*/
function youtube_sanitize_url( $url ) {
	$url = trim( $url, ' "' );
	$url = trim( $url );
	$url = str_replace( array( 'youtu.be/', '/v/', '#!v=', '&amp;', '&#038;', 'playlist' ), array( 'youtu.be/?v=', '/?v=', '?v=', '&', '&', 'videoseries' ), $url );

	// Replace any extra question marks with ampersands - the result of a URL like "http://www.youtube.com/v/9FhMMmqzbD8?fs=1&hl=en_US" being passed in.
	$query_string_start = strpos( $url, "?" );

	if ( false !== $query_string_start ) {
		$url = substr( $url, 0, $query_string_start + 1 ) . str_replace( "?", "&", substr( $url, $query_string_start + 1 ) );
	}

	return $url;
}
endif;


/**
 * Audio Shortcode backwards compatability
 * If they are still using the old school audio shortcoes like
 * [audio http://wpcom.files.wordpress.com/2007/01/mattmullenweg-interview.mp3|width=180|titles=1|artists=2]
 * or have inserted multiple audio URL's separated by comma.
 *
 * If is old multiple [audio], such as
 * [audio http://src1.mp3, http://src2.mp3, http://src3.mp3|titles=Title1, Title2, Title3|artists=Artist1, Artist2, Artist3 ]
 * we fall back to jetpack's old [audio] way of doing things.
 *
 * @since 3.3
 *
 */

function jetpack_compat_audio_shortcode( $attr, $content = '' ) {
	global $post;

	if ( ! function_exists( 'wp_audio_shortcode' ) ) {
		return;
	}

	if ( ! isset( $attr[0] ) ) {
		return wp_audio_shortcode( $attr, $content );
	}

	$attr = implode( ' ', $attr );
	$attr = ltrim( $attr, '=' );
	$attr = trim( $attr, ' "' );

	$data = explode( '|', $attr );
	$src = explode( ',', $data[0] );

	// Single audio file.
	if ( count( $src ) === 1 ) {
		$src = reset( $src );
		$src = strip_tags( $src ); // Previously users were able to use [audio <a href="URL">URL</a>] and other nonsense tags
		$src = esc_url_raw( $src );

		if ( is_ssl() ) {
			$src = preg_replace( '#^http://([^.]+).files.wordpress.com/#', 'https://$1.files.wordpress.com/', $src );
		}

		$loop = '';
		$autoplay = '';

		// Support some legacy options.
		foreach ( $data as $pair ) {
			$pair = explode( '=', $pair );
			$key = strtolower( $pair[0] );
			$value = ! empty( $pair[1] ) ? $pair[1] : '';

			if ( $key == 'autostart' && $value == 'yes' ) {
				$autoplay = 'on';
			} elseif ( $key == 'loop' && $value == 'yes' ) {
				$loop = 'on';
			}
		}

		return wp_audio_shortcode( array(
			'src' => $src,
			'loop' => $loop,
			'autoplay' => $autoplay,
		), $content );


		// Multiple audio files; let's build a playlist.
		// We are handling this the old jetpack [audio] way.
	} elseif ( count( $src ) > 1 ) {

		$artists  = array();
		$playlist = array();
		$songs    = array_filter( array_map( 'esc_url_raw', $src ) );

		foreach ( $data as $shortcode_part ) {
			if ( 0 === strpos( $shortcode_part, 'artists=' ) ) {
				$artists = explode( ',', substr( $shortcode_part, 8 ) );
				break;
			}
		}

		// Song URL/artist pairs.
		for ( $i = 0, $i_count = count( $songs ); $i < $i_count; $i++ ) {
			$filename = explode( '/', untrailingslashit( $songs[ $i ] ) );
			$filename = array_pop( $filename );

			$artist_name = '';
			if ( ! empty( $artists[ $i ] ) ) {
				$artist_name = $artists[ $i ];
			}

			$playlist[ $songs[ $i ] ] = array( $filename, $artist_name );
		}

		if ( is_feed() ) {
			$output = "\n";
			foreach ( $playlist as $song_url => $artist ) {
				$output .= sprintf( '<a href="%s">%s</a> ' . "\n", esc_url( $song_url ), esc_html( $artist[0] ) );
			}

			return $output;
		}

		// Data for playlist JS.
		$playlist_data = array(
			'artists'      => true,
			'images'       => false,
			'tracklist'    => true,
			'tracknumbers' => true,
			'tracks'       => array(),
			'type'         => 'audio',
		);

		$tracks = array();
		foreach ( $playlist as $song_url => $artist ) {
			$tracks[] = array(
				'caption'     => '',
				'description' => '',
				'meta'        => array( 'artist' => $artist[1] ),
				'src'         => esc_url_raw( $song_url ),
				'title'       => $artist[0],
			);
		}
		$playlist_data['tracks'] = $tracks;

		//Enqueue the old script for multiple [audio] tracks in the same shortcode

		if ( ! isset( $ap_playerID ) ) {
			$ap_playerID = 1;
		} else {
			$ap_playerID++;
		}

		if ( ! isset( $load_audio_script ) ) {
			$load_audio_script = true;
		}

		// prep the audio files
		$options = array();
		$sound_file = $data[0];
		$sound_files = explode( ',', $sound_file );

		if ( is_ssl() ) {
			for ( $i = 0; $i < count( $sound_files ); $i++ ) {
				$sound_files[ $i ] = preg_replace( '#^http://([^.]+).files.wordpress.com/#', 'https://$1.files.wordpress.com/', $sound_files[ $i ] );
			}
		}

		$sound_files = array_map( 'trim', $sound_files );
		$sound_files = array_map( 'esc_url_raw', $sound_files ); // Ensure each is a valid URL
		$num_files = count( $sound_files );

		for ( $i = 1; $i < count( $data ); $i++ ) {
			$pair = explode( "=", $data[$i] );
			if ( strtolower( $pair[0] ) != 'autostart' ) {
				$options[$pair[0]] = $pair[1];
			}
		}

		$options['soundFile'] = join( ',', $sound_files ); // Rebuild the option with our now sanitized data
		$flash_vars = array();
		foreach ( $options as $key => $value ) {
			$flash_vars[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
		}
		$flash_vars = implode( '&amp;', $flash_vars );
		$flash_vars = esc_attr( $flash_vars );

		// extract some of the options to insert into the markup
		if ( isset( $options['bgcolor'] ) && preg_match( '/^(0x)?[a-f0-9]{6}$/i', $options['bgcolor'] ) ) {
			$bgcolor = preg_replace( '/^(0x)?/', '#', $options['bgcolor'] );
			$bgcolor = esc_attr( $bgcolor );
		} else {
			$bgcolor = '#FFFFFF';
		}

		if ( isset( $options['width'] ) ) {
			$width = intval( $options['width'] );
		} else {
			$width = 290;
		}

		$loop = '';
		$script_loop = 'false';
		if ( isset( $options['loop'] ) && 'yes' == $options['loop'] ) {
			$script_loop = 'true';
			if ( 1 == $num_files ) {
				$loop = 'loop';
			}
		}

		$volume = 0.6;
		if ( isset( $options['initialvolume'] ) &&
		     0.0 < floatval( $options['initialvolume'] ) &&
		     100.0 >= floatval( $options['initialvolume'] ) ) {

			$volume = floatval( $options['initialvolume'] )/100.0;
		}

		$file_artists = array_pad( array(), $num_files, '' );
		if ( isset( $options['artists'] ) ) {
			$artists = preg_split( '/,/', $options['artists'] );
			foreach ( $artists as $i => $artist ) {
				$file_artists[$i] = esc_html( $artist ) . ' - ';
			}
		}

		// generate default titles
		$file_titles = array();
		for ( $i = 0; $i < $num_files; $i++ ) {
			$file_titles[] = 'Track #' . ($i+1);
		}

		// replace with real titles if they exist
		if ( isset( $options['titles'] ) ) {
			$titles = preg_split( '/,/', $options['titles'] );
			foreach ( $titles as $i => $title ) {
				$file_titles[$i] = esc_html( $title );
			}
		}

		// fallback for the fallback, just a download link
		$not_supported = '';
		foreach ( $sound_files as $sfile ) {
			$not_supported .= sprintf(
				__( 'Download: <a href="%s">%s</a><br />', 'jetpack' ),
				esc_url( $sfile ),
				esc_html( basename( $sfile ) ) );
		}

		// HTML5 audio tag
		$html5_audio = '';
		$all_mp3 = true;
		$num_good = 0;
		$to_remove = array();
		foreach ( $sound_files as $i => $sfile ) {
			$file_extension = pathinfo( $sfile, PATHINFO_EXTENSION );
			if ( ! preg_match( '/^(mp3|wav|ogg|oga|m4a|aac|webm)$/i', $file_extension ) ) {
				$html5_audio .= '<!-- Audio shortcode unsupported audio format -->';
				if ( 1 == $num_files ) {
					$html5_audio .= $not_supported;
				}

				$to_remove[] = $i; // make a note of the bad files
				$all_mp3 = false;
				continue;
			} elseif ( ! preg_match( '/^mp3$/i', $file_extension ) ) {
				$all_mp3 = false;
			}

			if ( 0 == $i ) { // only need one player
				$html5_audio .= <<<AUDIO
				<span id="wp-as-{$post->ID}_{$ap_playerID}-container">
					<audio id='wp-as-{$post->ID}_{$ap_playerID}' controls preload='none' $loop style='background-color:$bgcolor;width:{$width}px;'>
						<span id="wp-as-{$post->ID}_{$ap_playerID}-nope">$not_supported</span>
					</audio>
				</span>
				<br />
AUDIO;
			}
			$num_good++;
		}

		// player controls, if needed
		if ( 1 < $num_files ) {
			$html5_audio .= <<<CONTROLS
				<span id='wp-as-{$post->ID}_{$ap_playerID}-controls' style='display:none;'>
					<a id='wp-as-{$post->ID}_{$ap_playerID}-prev'
						href='javascript:audioshortcode.prev_track( "{$post->ID}_{$ap_playerID}" );'
						style='font-size:1.5em;'>&laquo;</a>
					|
					<a id='wp-as-{$post->ID}_{$ap_playerID}-next'
						href='javascript:audioshortcode.next_track( "{$post->ID}_{$ap_playerID}", true, $script_loop );'
						style='font-size:1.5em;'>&raquo;</a>
				</span>
CONTROLS;
		}
		$html5_audio .= "<span id='wp-as-{$post->ID}_{$ap_playerID}-playing'></span>";

		if ( is_ssl() )
			$protocol = 'https';
		else
			$protocol = 'http';

		$swfurl = apply_filters(
			'jetpack_static_url',
			"$protocol://en.wordpress.com/wp-content/plugins/audio-player/player.swf" );

		// all the fancy javascript is causing Google Reader to break, just include flash in GReader
		// override html5 audio code w/ just not supported code
		if ( is_feed() ) {
			$html5_audio = $not_supported;
		}

		if ( $all_mp3 ) {
			// process regular flash player, inserting HTML5 tags into object as fallback
			$audio_tags = <<<FLASH
				<object id='wp-as-{$post->ID}_{$ap_playerID}-flash' type='application/x-shockwave-flash' data='$swfurl' width='$width' height='24'>
					<param name='movie' value='$swfurl' />
					<param name='FlashVars' value='{$flash_vars}' />
					<param name='quality' value='high' />
					<param name='menu' value='false' />
					<param name='bgcolor' value='$bgcolor' />
					<param name='wmode' value='opaque' />
					$html5_audio
				</object>
FLASH;
		} else { // just HTML5 for non-mp3 versions
			$audio_tags = $html5_audio;
		}

		// strip out all the bad files before it reaches .js
		foreach ( $to_remove as $i ) {
			array_splice( $sound_files, $i, 1 );
			array_splice( $file_artists, $i, 1 );
			array_splice( $file_titles, $i, 1 );
		}

		// mashup the artist/titles for the script
		$script_titles = array();
		for ( $i = 0; $i < $num_files; $i++ ) {
			$script_titles[] = $file_artists[$i] . $file_titles[$i];

		}

		// javacript to control audio
		$script_files   = json_encode( $sound_files );
		$script_titles  = json_encode( $script_titles );
		$script = <<<SCRIPT
			<script type='text/javascript'>
			//<![CDATA[
			(function() {
				var prep = function() {
					if ( 'undefined' === typeof window.audioshortcode ) { return; }
					audioshortcode.prep(
						'{$post->ID}_{$ap_playerID}',
						$script_files,
						$script_titles,
						$volume,
						$script_loop
					);
				};
				if ( 'undefined' === typeof jQuery ) {
					if ( document.addEventListener ) {
						window.addEventListener( 'load', prep, false );
					} else if ( document.attachEvent ) {
						window.attachEvent( 'onload', prep );
					}
				} else {
					jQuery(document).on( 'ready as-script-load', prep );
				}
			})();
			//]]>
			</script>
SCRIPT;

		// add the special javascript, if needed
		if ( 0 < $num_good && ! is_feed() ) {
			$audio_tags .= $script;
		}

		return "<span style='text-align:left;display:block;'><p>$audio_tags</p></span>";
	}
}

add_shortcode( 'audio', 'jetpack_compat_audio_shortcode' );