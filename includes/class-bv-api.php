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
			if ( $args['rate_limit'] && ! $demo && ! self::rate_ok() ) {
				return array_merge( $base, array( 'status' => 'feil', 'error' => __( 'Too many searches. Please wait a moment and try again.', 'bruktdeler-vegvesen' ) ) );
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
		if ( 200 === $code || 204 === $code || 404 === $code ) {
			return array( 'status' => 'ikke_funnet', 'raw' => $body, 'fetched' => time() );
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
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'x';
		$key = 'bv_rl_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= $limit ) {
			return false;
		}
		set_transient( $key, $n + 1, MINUTE_IN_SECONDS );
		return true;
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
