<?php

class Jetpack_Translator {
	// this is a regex that we output, therefore the backslashes are doubled
	const PLACEHOLDER_REGEX = '%([0-9]\\\\*\\$)?';
	const PLACEHOLDER_MAXLENGTH = 200;

	private $glotpress_project_slugs = array();

	private $strings_used = array(), $placeholders_used = array();
	private $blacklisted = array( 'Loading&#8230;' => true );
	private $textdomains_to_translate = array();
	private static $instance = array();

	public static function init() {
		if ( ! self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function __construct() {

		require_once( JETPACK__GLOTPRESS_LOCALES_PATH );

		$this->textdomains_to_translate = apply_filters( 'jetpack_translator_textdomains', $this->textdomains_to_translate );
		$this->textdomains_to_translate[] = 'jetpack';

		add_action( 'gettext', array( $this, 'translate' ), 10, 3 );
		add_action( 'gettext_with_context', array( $this, 'translate_with_context' ), 10, 4 );
		add_action( 'gettext', array( $this, 'translate' ), 10, 4 );
		add_action( 'gettext_with_context', array( $this, 'translate_with_context' ), 10, 5 );
		add_action( 'wp_footer', array( $this, 'load_translator' ), 1000 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) ) ;

		add_action( 'admin_footer', array( $this, 'load_translator' ), 1000 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) ) ;

		add_action( 'wp_ajax_inform_translator', array( $this, 'load_ajax_translator' ), 1000 );
		add_action( 'wp_ajax_inform_admin_translator', array( $this, 'load_ajax_admin_translator' ), 1000 );

		$this->set_glotpress_slugs();
	}

	public function enqueue_scripts() {
		wp_enqueue_style( 'translator-jumpstart', plugins_url( 'translator-jumpstart.css', __FILE__ ) );
		wp_enqueue_script( 'translator', 'https://widgets.wp.com/community-translator/inject-nojquery.js' );
		wp_enqueue_script( 'translator-jumpstart', plugins_url( 'translator-jumpstart.js', __FILE__ ) , array( 'translator' ) );
	}

	private function set_glotpress_slugs() {
		$this->glotpress_project_slugs[] = 'jetpack/3.5';
	}

	/**
	 * This returns true for text that consists just of placeholders or placeholders + one letter,
	 * for example '%sy': time in years abbreviation
	 * as it leads to lots of translatable text which just matches the regex
	 *
	 * @param  string $text the string to check
	 * @return boolean      true if it contains just placeholders
	 */
	private function contains_just_placeholders( $text ) {
		$placeholderless_text = trim( preg_replace( '#' . self::PLACEHOLDER_REGEX . '[sd]#', '', $text ) );
		return strlen( $text ) !== strlen( $placeholderless_text ) && strlen( $placeholderless_text ) <= 1;
	}

	private function contains_placeholder( $text ) {
		return (bool) preg_match( '#' . self::PLACEHOLDER_REGEX . '[sd]#', $text );
	}

	private function already_checked( $key ) {
		return
			isset( $this->placeholders_used[ $key ] ) ||
			isset( $this->strings_used[ $key ] );
	}

	private function convert_placeholders_to_regex( $text, $string_placeholder = null, $numeric_placeholder = null ) {
		if ( is_null( $string_placeholder ) ) {
			$string_placeholder = '(.{0,' . self::PLACEHOLDER_MAXLENGTH . '}?)';
		}
		if ( is_null( $numeric_placeholder ) ) {
			$numeric_placeholder = '([0-9]{0,15}?)';
		}

		$text = html_entity_decode( $text );
		$text = preg_quote( $text, '/' );
		$text = preg_replace( '#' . self::PLACEHOLDER_REGEX . 's#', $string_placeholder, $text );
		$text = preg_replace( '#' . self::PLACEHOLDER_REGEX . 'd#', $numeric_placeholder, $text );
		$text = str_replace( '%%', '%', $text );
		return $text;
	}

	private function get_hash_key( $original, $context = null ) {
		if ( ! empty( $context ) && $context !== 'default' ) {
			$context .= "\u0004";
		} else {
			$context = '';
		}

		return $context . html_entity_decode( $original );
	}

	private function add_context( $key, $context = null, $new_entry = false ) {
		if ( ! $context ) {
			return;
		}

		if ( isset( $this->strings_used[ $key ] ) ) {
			if ( ! isset( $this->strings_used[ $key ][ 1 ] ) ) {
				$this->strings_used[ $key ][ 1 ] = array();

				if ( ! $new_entry ) {
					// the first entry had an empty context, so add it now
					$this->strings_used[ $key ][ 1 ][] = '';
				}
			}

			if ( ! in_array( $context, $this->strings_used[ $key ][ 1 ] ) ) {
				$this->strings_used[ $key ][ 1 ][] = $context;
			}

		} elseif ( isset( $this->placeholders_used[ $key ] ) ) {
			if ( ! isset( $this->placeholders_used[ $key ][ 2 ] ) ) {
				$this->placeholders_used[ $key ][ 2 ] = array();

				if ( ! $new_entry ) {
					// the first entry had an empty context, so add it now
					$this->placeholders_used[ $key ][ 2 ][] = '';
				}
			}

			if ( ! in_array( $context, $this->placeholders_used[ $key ][ 2 ] ) ) {
				$this->placeholders_used[ $key ][ 2 ][] = $context;
			}
		}
	}

	public function ntranslate( $translation, $singular, $plural, $count, $context = null ) {
		return $this->translate_with_context( $translation, $original, $context, $domain );
	}

	public function translate( $translation, $original = null, $domain = null ) {
		return $this->translate_with_context( $translation, $original, null, $domain );
	}

	public function translate_with_context( $translation, $original = null, $context = null, $domain = null ) {

		if ( ! in_array( $domain, $this->textdomains_to_translate ) ) {
			return $translation;
		}

		if ( ! $original ) {
			$original = $translation;
		}
		if ( isset( $this->blacklisted[ $original ] ) )  {
			return $translation;
		}

		if ( $this->contains_just_placeholders( $original ) ) {
			$this->blacklisted[ $original ] = true;
			return $translation;
		}
		$key = $this->get_hash_key( $translation );

		if ( $this->already_checked( $key ) ) {

			$this->add_context( $key, $context );

		} else {

			if ( $this->contains_placeholder( $translation ) ) {
				$string_placeholder = null;

				//TODO - general solution for strings starting and ending with placeholders
				if ( $original === '%1$s on %2$s' && $context == 'Recent Comments Widget' ) {
					// for this original both variables will be HTML Links
					$string_placeholder = '(<a [^>]+>.{0,' . self::PLACEHOLDER_MAXLENGTH . '}?</a>)';
				}

				$this->placeholders_used[ $key ] = array(
					$original,
					$this->convert_placeholders_to_regex( $translation, $string_placeholder ),
				);

			} else {
				$this->strings_used[ $key ] = array(
					$original,
					'domain' => $domain
				);
			}

			$this->add_context( $key, $context, true );
		}


		return $translation;
	}

	public function load_ajax_admin_translator() {
		return $this->load_ajax_translator( get_user_lang_code() );
	}

	public function load_ajax_translator( $locale_code = null ) {
		if ( empty( $locale_code ) ) {
			$locale_code = get_locale();
		}

		if ( $locale_code === 'en' ) {
			return false;
		}

		echo '<script type="text','/javascript">';
		echo 'var newTranslatorJumpstart = ';
		echo json_encode( array(
			'stringsUsedOnPage' => $this->strings_used,
			'placeholdersUsedOnPage' => $this->placeholders_used
		) );
		echo ';';
		echo '</script>';

	}

	public function load_translator( $locale_code = null ) {

		if ( empty( $locale_code ) ) {
			$locale_code = get_locale();
		}

		if ( $locale_code === 'en_US' ) {
			return false;
		}

		echo '<script type="text','/javascript">';
		echo 'translatorJumpstart = ', json_encode( $this->get_jumpstart_object( $locale_code ) ), ';';
		echo '</script>';

		?><div id="translator-launcher" class="translator">
		<a href="" title="<?php _e( 'Jetpack Translator' ); ?>">
				<span class="noticon noticon-website">
				</span>
			<div class="text disabled">
				<div class="enable">
					Enable Translator
				</div>
				<div class="disable">
					Disable Translator
				</div>
			</div>
		</a>
		</div><?php
	}

	private function get_jumpstart_object( $locale_code ) {

		$gp_locale = GP_Locales::by_field( 'wp_locale', $locale_code );
		$locale_slug = isset( $gp_locale->slug ) ? $gp_locale->slug : $locale_code;

		$plural_forms = 'nplurals=2; plural=(n != 1)';

		if ( property_exists( $gp_locale, 'nplurals' )
		     || property_exists( $gp_locale, 'plural_expression' ) ) {
			$plural_forms = 'nplurals=' . $gp_locale->nplurals . '; plural='. $gp_locale->plural_expression;
		}

		return array(
			'stringsUsedOnPage' => $this->strings_used,
			'placeholdersUsedOnPage' => $this->placeholders_used,
			'localeCode' => $locale_slug,
			'languageName' => html_entity_decode( get_bloginfo('language') ),
			'pluralForms' => $plural_forms,
			'glotPress' => array(
				'url' => 'https://translate.wordpress.com',
				'project' => implode( ',', $this->glotpress_project_slugs ),
			)
		);
	}
}

add_action( 'init', array( 'Jetpack_Translator', 'init' ) );
