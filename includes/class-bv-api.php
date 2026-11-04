<?php
defined( 'ABSPATH' ) || exit;

/**
 * Talks to the Statens vegvesen API. The key never leaves the server.
 * Responses are cached (raw JSON), so field mapping changes apply without clearing the cache.
 */
class BV_API {

	/**
	 * Cleans user input. Returns ['type' => 'kjennemerke'|'understellsnummer', 'value' => ...] or null.
	 */
	public static function parse_query( $input ) {
		$q = mb_strtoupper( preg_replace( '/[\s\-]+/u', '', (string) $input ), 'UTF-8' );
		if ( preg_match( '/^[A-HJ-NPR-Z0-9]{17}$/', $q ) ) {
			return array( 'type' => 'understellsnummer', 'value' => $q );
		}
		// Ordinary plates (AB12345) and personalised plates (2–7 characters, incl. ÆØÅ).
		if ( preg_match( '/^[A-ZÆØÅ0-9]{2,7}$/u', $q ) ) {
			return array( 'type' => 'kjennemerke', 'value' => $q );
		}
		return null;
	}

	/**
	 * Looks up a vehicle.
	 *
	 * @param string $input   Plate or VIN.
	 * @param array  $args    'fresh' => skip cache, 'rate_limit' => apply per-IP limit.
	 * @return array status (funnet|ikke_funnet|ugyldig|feil), query, data, raw, fetched, demo, error
	 */
	public static function lookup( $input, $args = array() ) {
		$args  = wp_parse_args( $args, array( 'fresh' => false, 'rate_limit' => true ) );
		$query = self::parse_query( $input );
		$base  = array( 'query' => $query ? $query['value'] : (string) $input, 'data' => array(), 'raw' => null, 'fetched' => 0, 'demo' => false, 'error' => '' );

		if ( ! $query ) {
			return array_merge( $base, array( 'status' => 'ugyldig', 'error' => __( 'Invalid registration number or VIN.', 'bruktdeler-vegvesen' ) ) );
		}

		$demo      = BV_Settings::is_demo();
		$cache_key = 'bv_' . md5( ( $demo ? 'demo' : 'live' ) . '|' . $query['type'] . '|' . $query['value'] );
		$cached    = $args['fresh'] ? false : get_transient( $cache_key );

		if ( false === $cached ) {
			if ( ! $demo ) {
				// Search engines and scripts may see answers already cached, but never spend the daily quota.
				if ( self::is_bot() ) {
					return array_merge( $base, array( 'status' => 'feil', 'error' => __( 'Automatic lookups are not allowed.', 'bruktdeler-vegvesen' ) ) );
				}
				if ( $args['rate_limit'] && ! self::rate_ok() ) {
					return array_merge( $base, array( 'status' => 'feil', 'error' => __( 'Too many searches. Please wait a moment and try again.', 'bruktdeler-vegvesen' ) ) );
				}
				if ( ! self::daily_ok() ) {
					return array_merge( $base, array( 'status' => 'feil', 'error' => __( 'The daily number of searches has been reached. Please try again tomorrow.', 'bruktdeler-vegvesen' ) ) );
				}
			}
			$cached = $demo ? self::demo_response( $query ) : self::request( $query );

			if ( 'feil' !== $cached['status'] ) {
				$ttl = 'funnet' === $cached['status'] ? max( 1, (int) BV_Settings::get( 'cache_hours' ) ) * HOUR_IN_SECONDS : HOUR_IN_SECONDS;
				set_transient( $cache_key, $cached, $ttl );
			}
		}

		$result = array_merge( $base, $cached, array( 'demo' => $demo ) );
		if ( 'funnet' === $result['status'] ) {
			$result['data']                   = BV_Fields::normalize( $result['raw'] );
			$result['data']['sist_oppdatert'] = wp_date( 'Y-m-d H:i', $result['fetched'] );
		}
		$result['data']['status'] = $result['status'];
		return $result;
	}

	private static function request( $query ) {
		$key = BV_Settings::api_key();
		$url = add_query_arg( $query['type'], rawurlencode( $query['value'] ), BV_Settings::get( 'endpoint' ) );

		$res = wp_remote_get( $url, array(
			'timeout' => 12,
			'headers' => array(
				'Accept'                         => 'application/json',
				BV_Settings::get( 'auth_header' ) => BV_Settings::get( 'auth_prefix' ) . $key,
			),
		) );

		if ( is_wp_error( $res ) ) {
			/* translators: %s: technical error message. */
			return array( 'status' => 'feil', 'error' => sprintf( __( 'Could not contact Statens vegvesen: %s', 'bruktdeler-vegvesen' ), $res->get_error_message() ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( wp_remote_retrieve_body( $res ), true );

		if ( 200 === $code && ! empty( $body['kjoretoydataListe'] ) ) {
			return array( 'status' => 'funnet', 'raw' => $body, 'fetched' => time() );
		}
		// A 200 that is not JSON (a maintenance page, a wrong URL) is an error, not "not found",
		// so it is not cached and real vehicles are found again as soon as the API is back.
		if ( 200 === $code && ! is_array( $body ) ) {
			return array( 'status' => 'feil', 'error' => __( 'Statens vegvesen sent an unexpected response. Please try again later.', 'bruktdeler-vegvesen' ) );
		}
		if ( 200 === $code || 204 === $code || 404 === $code ) {
			return array( 'status' => 'ikke_funnet', 'raw' => $body, 'fetched' => time() );
		}

		if ( 429 === $code ) {
			self::alert( 'quota' );
		}
		$messages = array(
			401 => __( 'The API key was rejected (401). Check the key and the authorization header.', 'bruktdeler-vegvesen' ),
			403 => __( 'Access denied (403). Check the API key.', 'bruktdeler-vegvesen' ),
			429 => __( 'The daily quota at Statens vegvesen has been used up (429).', 'bruktdeler-vegvesen' ),
		);
		/* translators: %d: HTTP status code. */
		$msg = $messages[ $code ] ?? sprintf( __( 'Error from Statens vegvesen (HTTP %d).', 'bruktdeler-vegvesen' ), $code );
		if ( ! empty( $body['feilmelding'] ) ) {
			$msg .= ' ' . sanitize_text_field( $body['feilmelding'] );
		}
		return array( 'status' => 'feil', 'error' => $msg, 'raw' => $body );
	}

	/** Max N uncached lookups per IP per minute (editors are not limited). */
	private static function rate_ok() {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}
		$limit = (int) BV_Settings::get( 'rate_limit' );
		if ( $limit <= 0 ) {
			return true;
		}
		$key = 'bv_rl_' . md5( self::client_ip() );
		// A fixed one-minute window from the first search: each search must not push the reset further away.
		$now = time();
		$w   = get_transient( $key );
		if ( ! is_array( $w ) || empty( $w['start'] ) || $now - (int) $w['start'] >= MINUTE_IN_SECONDS ) {
			$w = array( 'start' => $now, 'n' => 0 );
		}
		if ( (int) $w['n'] >= $limit ) {
			return false;
		}
		$w['n'] = (int) $w['n'] + 1;
		set_transient( $key, $w, max( 1, MINUTE_IN_SECONDS - ( $now - (int) $w['start'] ) ) );
		return true;
	}

	/** Proxy headers the visitor's IP can come from (Settings → Visitor IP). */
	const IP_HEADERS = array(
		'cloudflare' => 'HTTP_CF_CONNECTING_IP',
		'forwarded'  => 'HTTP_X_FORWARDED_FOR',
		'real_ip'    => 'HTTP_X_REAL_IP',
	);

	/**
	 * The visitor's IP. Behind a proxy or CDN every visitor arrives from the proxy's address,
	 * so the header chosen in Settings is used. A header is only trusted when chosen there:
	 * without a proxy in front, anyone could send it.
	 */
	public static function client_ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$headers = self::IP_HEADERS;
		$header  = $headers[ (string) BV_Settings::get( 'ip_source' ) ] ?? '';
		if ( $header && ! empty( $_SERVER[ $header ] ) ) {
			// X-Forwarded-For can be a list: "client, proxy1, proxy2" – the first valid address is the visitor.
			foreach ( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) ) as $ip ) {
				$ip = trim( $ip );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return $remote ? $remote : 'unknown';
	}

	/** Search engines, link previews and scripts (no browser). Filter 'bv_is_bot'. */
	public static function is_bot() {
		if ( is_user_logged_in() ) {
			return false;
		}
		$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? (string) wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched.
		$bot = '' === $ua || (bool) preg_match( '/bot\b|bot\/|crawl|spider|slurp|mediapartners|bingpreview|facebookexternalhit|embedly|preview|headless|lighthouse|python|curl\/|wget|scrapy|httpclient|java\/|go-http|okhttp|axios|node-fetch|libwww|feed/i', $ua );
		return (bool) apply_filters( 'bv_is_bot', $bot, $ua );
	}

	/** Today's real requests to Vegvesen: [ 'day' => Y-m-d, 'n' => count ]. */
	public static function usage_today() {
		$u     = get_option( 'bv_day_count' );
		$today = wp_date( 'Y-m-d' );
		return is_array( $u ) && ( $u['day'] ?? '' ) === $today ? array( 'day' => $today, 'n' => (int) $u['n'] ) : array( 'day' => $today, 'n' => 0 );
	}

	/** Counts one request against the daily limit (Settings → Daily limit); false when it is used up. */
	private static function daily_ok() {
		$limit = (int) BV_Settings::get( 'daily_limit' );
		$u     = self::usage_today();
		if ( $limit > 0 && $u['n'] >= $limit ) {
			self::alert( 'limit' );
			return false;
		}
		$u['n']++;
		update_option( 'bv_day_count', $u, false );
		return true;
	}

	/** Remembers for today that searches stopped (own limit or Vegvesen's quota), for the admin notice. */
	private static function alert( $reason ) {
		$a = get_option( 'bv_quota_alert' );
		if ( ! is_array( $a ) || ( $a['day'] ?? '' ) !== wp_date( 'Y-m-d' ) ) {
			update_option( 'bv_quota_alert', array( 'day' => wp_date( 'Y-m-d' ), 'reason' => $reason, 'time' => time() ), false );
		}
	}

	/** Today's alert, or null. */
	public static function alert_today() {
		$a = get_option( 'bv_quota_alert' );
		return is_array( $a ) && ( $a['day'] ?? '' ) === wp_date( 'Y-m-d' ) ? $a : null;
	}

	/** Sample response in the Vegvesen format (used in demo mode). "XX00000" returns "not found". */
	private static function demo_response( $query ) {
		if ( 'XX00000' === $query['value'] ) {
			return array( 'status' => 'ikke_funnet', 'raw' => array(), 'fetched' => time() );
		}
		$raw = json_decode( file_get_contents( BV_DIR . 'assets/demo/kjoretoy.json' ), true );
		$raw['kjoretoydataListe'][0]['kjoretoyId'][ $query['type'] ] = $query['value'];
		return array( 'status' => 'funnet', 'raw' => $raw, 'fetched' => time() );
	}
}
