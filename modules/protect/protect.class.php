<?php


class Jetpack_Protect {
	private $user_ip;
	private $use_https;
	private $api_key;
	private $local_host;
	private $api_endpoint;
	private $admin;

	/**
	 * Hooks into WordPress actions with self-contained callbacks
	 *
	 * @return VOID
	 */
	function __construct() {
		add_action( 'login_head', array( &$this, 'check_use_math' ) );
		add_filter( 'authenticate', array( &$this, 'check_preauth' ), 10, 3 );
		add_action( 'wp_login_failed', array( &$this, 'log_failed_attempt' ) );
	}
	
	/**
	 * Checks for loginability BEFORE authentication so that bots don't get to go around the log in form.
	 *
	 * If we are using our math fallback, authenticate via math-fallback.php
	 *
	 * @param string $username Passed via WordPress action. Not used.
	 *
	 * @return VOID
	 */
	function check_preauth( $user = 'Not Used By Jetpack Protect', $username = 'Not Used By Jetpack Protect', $password = 'Not Used By Jetpack Protect' ) {
		$this->check_loginability( true );
		$bum = get_site_transient( 'jetpack_protect_use_math' );

		if ( $bum == 1 && isset( $_POST['log'] ) ) :

			Jetpack_Protect_Math_Authenticate::math_authenticate();

		endif;
	}

	/**
	 * Retrives and sets the ip address the person logging in
	 *
	 * @return string
	 */
	function get_ip() {
		if ( isset( $this->user_ip ) ) {
			return $this->user_ip;
		}

		$server_headers = array(
			'HTTP_CLIENT_IP',
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR'
		);

		if ( function_exists( 'filter_var' ) ) :
			foreach ( $server_headers as $key ) :
				if ( array_key_exists( $key, $_SERVER ) === true ) :
					foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) :
						$ip = trim( $ip ); // just to be safe

						if ( $ip == '127.0.0.1' || $ip == '::1' ) {
							return $ip;
						}

						if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) :
							$this->user_ip = $ip;

							return $this->user_ip;
						endif;
					endforeach;
				endif;
			endforeach;
		else : // PHP filter extension isn't available
		{
			foreach ( $server_headers as $key ) :
				if ( array_key_exists( $key, $_SERVER ) === true ) :
					foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) :
						$ip            = trim( $ip ); // just to be safe
						$this->user_ip = $ip;

						return $this->user_ip;
					endforeach;
				endif;
			endforeach;
		}
		endif;
	}

	function is_on_localhost() {
		$ip = $this->get_ip();
		//return false;
		//Never block login from localhost
		if ( $ip == '127.0.0.1' || $ip == '::1' ) {
			return true;
		}

		return false;
	}

	/**
	 * Checks the status for a given IP. API results are cached as transients in the wp_options table
	 *
	 * @param bool $preauth Wether or not we are checking prior to authorization
	 *
	 * @return bool Either returns true, fires $this->kill_login, or includes a math fallback
	 */
	function check_loginability( $preauth = false ) {

		$ip = $this->get_ip();

		//Never block login from localhost
		if ( $this->is_on_localhost() ) {
			return true;
		}

		$transient_name  = 'loginable_' . str_replace( '.', '_', $ip );
		$transient_value = get_site_transient( $transient_name );

		/*
			TODO add interface for whitelisting
		*/
		//Never block login from whitelisted IPs
		$whitelist = get_site_option( 'ip_whitelist' );
		$wl_items  = explode( PHP_EOL, $whitelist );
		$iplong    = ip2long( $ip );

		if ( is_array( $wl_items ) ) :  foreach ( $wl_items as $item ) :

			$item = trim( $item );

			if ( $ip == $item ) //exact match
			{
				return true;
			}

			if ( strpos( $item, '*' ) === false ) //no match, no wildcard
			{
				continue;
			}

			$ip_low  = ip2long( str_replace( '*', '0', $item ) );
			$ip_high = ip2long( str_replace( '*', '255', $item ) );

			if ( $iplong >= $ip_low && $iplong <= $ip_high ) //IP is within wildcard range
			{
				return true;
			}

		endforeach; endif;


		//Check out our transients
		if ( isset( $transient_value ) && $transient_value['status'] == 'ok' ) {
			return true;
		}

		if ( isset( $transient_value ) && $transient_value['status'] == 'blocked' ) {
			if ( $transient_value['expire'] < time() ) {
				//the block is expired but the transient didn't go away naturally, clear it out and allow login.
				delete_site_transient( $transient_name );

				return true;
			}
			//there is a current block-- prevent login
			$this->kill_login();
		}

		//If we've reached this point, this means that the IP isn't cached.
		//Now we check with the bruteprotect.com servers to see if we should allow login
		$response = $this->call( $action = 'check_ip' );

		if ( isset( $response['math'] ) && ! function_exists( 'math_authenticate' ) ) {
			include_once 'math-fallback.php';

		}

		if ( $response['status'] == 'blocked' ) {
			$this->kill_login( $response['blocked_attempts'] );
		}

		return true;
	}

	/**
	 * Checks for a WordPress transient to decide if we must use our math fall back
	 *
	 * @return VOID
	 */
	function check_use_math() {
		$bp_use_math = get_site_transient( 'jetpack_protect_use_math' );

		if ( $bp_use_math ) {
			include_once 'math-fallback.php';
			new Jetpack_Protect_Math_Authenticate;
		}
	}

	function kill_login() {
		do_action( 'kill_login', $this->get_ip() );
		$this->log_blocked_attempt();
		wp_die( 'Your IP (' . $this->get_ip() . ') has been flagged for potential security violations.  Please try again in a little while...' );
	}

	/**
	 * Called via WP action wp_login_failed to log failed attempt with the api
	 *
	 * Fires custom, plugable action log_failed_attempt with
	 *
	 * @return void
	 */
	function log_failed_attempt() {
		do_action( 'log_failed_attempt', $this->get_ip() );
		$this->call( 'failed_attempt' );
	}

	function get_local_host() {
		if ( isset( $this->local_host ) ) {
			return $this->local_host;
		}

		$uri = 'http://' . strtolower( $_SERVER['HTTP_HOST'] );

		if ( is_multisite() ) {
			$uri = network_home_url();
		}

		$uridata = parse_url( $uri );

		$domain = $uridata['host'];

		//if we still don't have it, get the site_url
		if ( ! $domain ) {
			$uri     = get_site_url( 1 );
			$uridata = parse_url( $uri );
			$domain  = $uridata['host'];
		}

		$this->local_host = $domain;

		return $this->local_host;
	}

	/**
	 * Checks if server can use https, and returns api endpoint
	 *
	 * @return string URL of api with either http or https protocol
	 */
	function get_bruteprotect_host() {
		if ( isset( $this->api_endpoint ) ) {
			return $this->api_endpoint;
		}

		//Some servers can't access https-- we'll check once a day to see if we can.
		$use_https = get_site_transient( 'bruteprotect_use_https' );

		if ( $use_https == 'yes' ) {
			$this->api_endpoint = 'https://api.bruteprotect.com/';
		} else {
			$this->api_endpoint = 'http://api.bruteprotect.com/';
		}

		if ( ! $use_https ) {
			$test      = wp_remote_get( 'https://api.bruteprotect.com/https_check.php' );
			$use_https = 'no';
			if ( ! is_wp_error( $test ) && $test['body'] == 'ok' ) {
				$use_https = 'yes';
			}
			set_site_transient( 'bruteprotect_use_https', $use_https, 86400 );
		}

		return $this->api_endpoint;
	}

	function get_blocked_attempts() {
		$blocked_count = get_site_option( 'jetpack_protect_blocked_attempt_count' );
		if ( ! $blocked_count ) {
			$blocked_count = 0;
		}

		return $blocked_count;
	}

	function log_blocked_attempt( $api_count = 0 ) {
		$attempt_count = $this->get_blocked_attempts();

		if ( ! $attempt_count ) {
			$attempt_count = 0;
		}

		if ( $attempt_count < $api_count ) {
			$attempt_count = $api_count;
		}
		$attempt_count++;

		update_site_option( 'jetpack_protect_blocked_attempt_count', $attempt_count );

		return $attempt_count;
	}


	/**
	 * Calls over to the api using wp_remote_post
	 *
	 * @param string $action 'check_ip', 'check_key', or 'failed_attempt'
	 * @param array $request Any custom data to post to the api
	 * @param bool $sign Should we sign the request?
	 *
	 * @return array
	 */
	function call( $action = 'check_ip', $request = array() ) {
		global $wp_version, $wpdb, $current_user;

		$api_key = get_site_option( 'bruteprotect_api_key' );
		/*
			TODO remove this key...
		*/
		$api_key = '962068a8ba587f8b75f378310e8e47117ae5f6ff';

		$ua = "WordPress/{$wp_version} | ";
		$ua .= 'JetpackProtect/' . constant( 'JETPACK__VERSION' );

		$request[ 'action' ]               = $action;
		$request[ 'ip' ]                   = $this->get_ip();
		$request[ 'host' ]                 = $this->get_local_host();
		$request[ 'blocked_attempts' ]     = strval( $this->get_blocked_attempts() );
		$request[ 'api_key' ]              = $api_key;
		$request[ 'multisite' ]            = "0";
		$request[ 'wp_user_id' ]           = strval( $current_user->ID );

		$log['request'] = $request;

		$args           = array(
			'body'        => $request,
			'user-agent'  => $ua,
			'httpversion' => '1.0',
			'timeout'     => 15
		);

		$response_json   = wp_remote_post( $this->get_bruteprotect_host(), $args );
		$log['response'] = $response_json;

		$ip             = $_SERVER['REMOTE_ADDR'];
		$transient_name = 'loginable_' . str_replace( '.', '_', $ip );
		delete_site_transient( $transient_name );

		if ( is_array( $response_json ) ) {
			$response = json_decode( $response_json['body'], true );
		}

		if ( isset( $response['status'] ) && ! isset( $response['error'] ) ) :
			$response['expire'] = time() + $response['seconds_remaining'];
			set_site_transient( $transient_name, $response, $response['seconds_remaining'] );
			delete_site_transient( 'jetpack_protect_use_math' );
		else : //no response from the API host?  Let's use math!
			set_site_transient( 'jetpack_protect_use_math', 1, 600 );
			$response['status'] = 'ok';
			$response['math']   = true;
		endif;

		if ( isset( $response['error'] ) ) :
			update_site_option( 'bruteprotect_error', $response['error'] );
		else :
			delete_site_option( 'bruteprotect_error' );
		endif;

		return $response;
	}
}

