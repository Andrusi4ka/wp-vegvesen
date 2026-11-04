<?php
defined( 'ABSPATH' ) || exit;

/**
 * Shortcodes ("variables"). All of them work in any theme and any builder.
 *
 *  [bv field="merke"]                 one value
 *  [bv_label field="merke"]           the field's Norwegian label
 *  [bv_if field="co2"]…[/bv_if]       show content only when a condition holds
 *  [bv_search] [bv_plate] [bv_status] [bv_print] [bv_image] [bv_table] [bv_tiles] [bv_card]
 */
class BV_Shortcodes {

	const DEFAULT_TILES  = 'registrert_aar,drivstoff,effekt,slagvolum';
	const TABLE_GENERAL  = 'merke,modell,variant,karosseri,farge,sitteplasser,egenvekt,totalvekt';
	const TABLE_DATES    = 'registrert_forste,sist_eu,neste_eu';
	const TABLE_MILJO    = 'co2,forbruk,el_forbruk,rekkevidde,euroklasse';

	public static function init() {
		$map = array(
			'bv'        => 'field',
			'bv_label'  => 'label',
			'bv_if'     => 'cond',
			'bv_search' => 'search',
			'bv_plate'  => 'plate',
			'bv_status' => 'status',
			'bv_print'  => 'print',
			'bv_image'  => 'image',
			'bv_logo'   => 'logo',
			'bv_table'  => 'table',
			'bv_tiles'  => 'tiles',
			'bv_card'   => 'card',
		);
		foreach ( $map as $tag => $method ) {
			add_shortcode( $tag, array( __CLASS__, $method ) );
		}

		// Tokens {bv:merke} for one-line fields (titles, buttons, list items) where [bv] can't be used.
		if ( ! is_admin() || wp_doing_ajax() ) {
			add_filter( 'do_shortcode_tag', array( __CLASS__, 'replace_tokens' ), 20 );
			add_filter( 'the_content', array( __CLASS__, 'replace_tokens' ), 99 );
			add_filter( 'widget_text', array( __CLASS__, 'replace_tokens' ), 99 );
			add_filter( 'widget_block_content', array( __CLASS__, 'replace_tokens' ), 99 );
			add_filter( 'the_title', array( __CLASS__, 'replace_tokens' ), 99 );
		}

		// Load CSS in <head> on pages that use the shortcodes.
		add_action( 'wp_enqueue_scripts', function () {
			$post = get_post();
			if ( BV_Context::requested() || ( is_singular() && $post && false !== strpos( $post->post_content, '[bv' ) ) ) {
				wp_enqueue_style( 'bv' );
				wp_enqueue_script( 'bv' );
			}
		}, 20 );
	}

	/**
	 * {bv:merke}            → Volkswagen
	 * {bv:egenvekt|unit}    → 1 615 kg
	 * {bv:neste_eu|j. M Y}  → 21. mar. 2027 (anything else after | is a date format)
	 * {bv:label:egenvekt}   → Egenvekt
	 */
	public static function replace_tokens( $html ) {
		if ( ! is_string( $html ) || false === strpos( $html, '{bv:' ) ) {
			return $html;
		}
		return preg_replace_callback( '/\{bv:(label:)?([a-z0-9_]+)(?:\|([^}]*))?\}/', function ( $m ) {
			if ( ! empty( $m[1] ) ) {
				return esc_html( BV_Fields::label( $m[2] ) );
			}
			$l = self::lookup( array() );
			if ( 'status' === $m[2] ) {
				return esc_html( $l ? $l['status'] : 'ingen' );
			}
			if ( ! self::found( $l ) ) {
				return '';
			}
			$opt = $m[3] ?? '';
			$o   = 'unit' === $opt ? array( 'unit' => 1 ) : ( '' !== $opt ? array( 'format' => $opt ) : array() );
			return esc_html( BV_Render::value( $l, $m[2], $o ) );
		}, $html );
	}

	private static function lookup( $atts ) {
		$l = BV_Context::current( isset( $atts['regnr'] ) ? (string) $atts['regnr'] : '' );
		BV_Render::note( $l );
		return $l;
	}

	private static function found( $l ) {
		return $l && 'funnet' === $l['status'];
	}

	/* [bv field="egenvekt" suffix=" kg" format="d.m.Y" number="1" decimals="0" unit="1" before="" after="" empty="—"] */
	public static function field( $atts ) {
		$a = shortcode_atts( array(
			'field' => '', 'format' => '', 'number' => '1', 'decimals' => '', 'unit' => '0',
			'before' => '', 'after' => '', 'suffix' => '', 'empty' => '', 'regnr' => '', 'el_class' => '', 'css' => '',
		), $atts, 'bv' );

		$l   = self::lookup( $a );
		$val = '';
		if ( 'status' === $a['field'] ) {
			$val = $l ? $l['status'] : 'ingen';
		} elseif ( self::found( $l ) ) {
			$val = BV_Render::value( $l, $a['field'], $a );
		}

		$out = '' === $val ? $a['empty'] : $a['before'] . $val . $a['suffix'] . $a['after'];
		$out = esc_html( $out );
		if ( $a['el_class'] || $a['css'] ) {
			$out = '<span class="' . BV_Render::classes( 'bv-field', $a ) . '">' . $out . '</span>';
		}
		return $out;
	}

	/* [bv_label field="egenvekt"] */
	public static function label( $atts ) {
		$a = shortcode_atts( array( 'field' => '' ), $atts, 'bv_label' );
		return esc_html( BV_Fields::label( $a['field'] ) );
	}

	/*
	 * [bv_if field="co2"]…[/bv_if]                  field has a value
	 * [bv_if field="eu_status" is="utlopt,snart"]   value is one of
	 * [bv_if field="drivstoff" not="Elektrisitet"]  value is not
	 * [bv_if status="funnet|ikke_funnet|ugyldig|feil|ingen"]
	 * [bv_if field="co2" empty="1"]                 field is empty
	 */
	public static function cond( $atts, $content = '' ) {
		$a = shortcode_atts( array( 'field' => '', 'is' => '', 'not' => '', 'status' => '', 'empty' => '', 'regnr' => '' ), $atts, 'bv_if' );
		$l = self::lookup( $a );
		$ok = true;

		if ( '' !== $a['status'] ) {
			$status = $l ? $l['status'] : 'ingen';
			$ok     = in_array( $status, array_map( 'trim', explode( ',', $a['status'] ) ), true );
		}
		if ( $ok && '' !== $a['field'] ) {
			$val = self::found( $l ) && isset( $l['data'][ $a['field'] ] ) ? (string) $l['data'][ $a['field'] ] : '';
			$lc  = function ( $s ) {
				return array_map( function ( $x ) {
					return mb_strtolower( trim( $x ) );
				}, explode( ',', $s ) );
			};
			if ( '' !== $a['is'] ) {
				$ok = in_array( mb_strtolower( $val ), $lc( $a['is'] ), true );
			} elseif ( '' !== $a['not'] ) {
				$ok = '' !== $val && ! in_array( mb_strtolower( $val ), $lc( $a['not'] ), true );
			} elseif ( '1' === $a['empty'] ) {
				$ok = '' === $val;
			} else {
				$ok = '' !== $val;
			}
		}
		return $ok ? do_shortcode( shortcode_unautop( $content ) ) : '';
	}

	/*
	 * [bv_search layout="inline|hero|compact" title="" subtitle="" image="" placeholder="" button=""]
	 * The hero layout gets a translated title/subtitle unless they are set (title="" hides it).
	 */
	public static function search( $atts ) {
		$atts = (array) $atts;
		$hero = isset( $atts['layout'] ) && 'hero' === $atts['layout'];
		$a    = shortcode_atts( array(
			'layout'      => 'inline',
			'title'       => $hero ? __( 'Search for a vehicle', 'bruktdeler-vegvesen' ) : '',
			'subtitle'    => $hero ? __( 'Get technical information about a vehicle from Statens vegvesen.', 'bruktdeler-vegvesen' ) : '',
			'image'       => '',
			'placeholder' => __( 'Registration number (e.g. AB12345)', 'bruktdeler-vegvesen' ),
			'button'      => __( 'Search', 'bruktdeler-vegvesen' ),
			'max_width'   => '',
			'message'     => '1',
			'el_class'    => '',
			'css'         => '',
		), $atts, 'bv_search' );

		// Short message right under the field when a search found nothing (so it is visible without scrolling).
		$a['message_text']   = '';
		$a['message_status'] = '';
		if ( '1' === $a['message'] && '' !== BV_Context::requested() ) {
			$l = BV_Context::current();
			if ( $l && 'funnet' !== $l['status'] ) {
				$texts = array(
					/* translators: %s: the registration number or VIN that was searched for. */
					'ikke_funnet' => sprintf( __( 'We found no vehicle with “%s”. Check that the number is correct.', 'bruktdeler-vegvesen' ), $l['query'] ),
					'ugyldig'     => __( 'Enter a registration number (e.g. AB12345) or a 17-character VIN.', 'bruktdeler-vegvesen' ),
					'feil'        => $l['error'],
				);
				$a['message_text']   = $texts[ $l['status'] ] ?? '';
				$a['message_status'] = $l['status'];
			}
		}

		// max_width="600" → 600px; also accepts 80%, 40rem etc. Empty = full width (hero: 820px).
		$mw = trim( (string) $a['max_width'] );
		if ( is_numeric( $mw ) ) {
			$mw .= 'px';
		}
		$a['max_width'] = preg_match( '/^\d+(\.\d+)?(px|%|rem|em|vw)$/', $mw ) ? $mw : '';

		$image = $a['image'];
		if ( is_numeric( $image ) ) {
			$image = wp_get_attachment_image_url( (int) $image, 'full' );
		}
		$a['image_url'] = $image ? $image : '';
		$a['action']    = BV_Context::url();
		$a['value']     = BV_Context::requested();
		$a['class']     = BV_Render::classes( 'bv bv-search bv-search--' . sanitize_html_class( $a['layout'] ), $a );
		return BV_Render::template( 'search', array( 'a' => $a ) );
	}

	/* [bv_plate size="normal|small|large"] */
	public static function plate( $atts ) {
		$a = shortcode_atts( array( 'size' => 'normal', 'regnr' => '', 'el_class' => '', 'css' => '' ), $atts, 'bv_plate' );
		$l = self::lookup( $a );
		if ( ! $l ) {
			return '';
		}
		$text = self::found( $l ) && ! empty( $l['data']['regnr_formatted'] ) ? $l['data']['regnr_formatted'] : BV_Fields::format_plate( $l['query'] );
		return BV_Render::template( 'plate', array(
			'text'  => $text,
			'class' => BV_Render::classes( 'bv bv-plate bv-plate--' . sanitize_html_class( $a['size'] ), $a ),
		) );
	}

	/* [bv_status found="Vehicle found" updated="1"] */
	public static function status( $atts ) {
		$a = shortcode_atts( array( 'found' => __( 'Vehicle found', 'bruktdeler-vegvesen' ), 'updated' => '1', 'regnr' => '', 'el_class' => '', 'css' => '' ), $atts, 'bv_status' );
		$l = self::lookup( $a );
		if ( ! $l ) {
			return '';
		}
		return BV_Render::template( 'status', array( 'l' => $l, 'a' => $a, 'class' => BV_Render::classes( 'bv bv-status', $a ) ) );
	}

	/* [bv_print label="Print"] */
	public static function print( $atts ) {
		$a = shortcode_atts( array( 'label' => __( 'Print', 'bruktdeler-vegvesen' ), 'el_class' => '', 'css' => '' ), $atts, 'bv_print' );
		wp_enqueue_style( 'bv' );
		wp_enqueue_script( 'bv' );
		return '<button type="button" class="' . BV_Render::classes( 'bv bv-print', $a ) . '" data-bv-print>' . BV_Icons::get( 'printer' ) . '<span>' . esc_html( $a['label'] ) . '</span></button>';
	}

	/* [bv_image] */
	public static function image( $atts ) {
		$a = shortcode_atts( array( 'regnr' => '', 'el_class' => '', 'css' => '' ), $atts, 'bv_image' );
		$l = self::lookup( $a );
		if ( ! self::found( $l ) ) {
			return '';
		}
		$color = BV_Render::body_color( $l['data'] );
		return BV_Render::template( 'image', array(
			'url'   => BV_Render::image_url( $l['data'] ),
			'alt'   => $l['data']['merke_modell'] ?? '',
			'class' => BV_Render::classes( 'bv bv-image', $a ),
			'data'  => $l['data'],
			'type'  => BV_Render::body_type( $l['data'] ),
			'color' => $color,
			'light' => BV_Render::is_light( $color ),
		) );
	}

	/* [bv_logo size="64"] – brand logo; outputs nothing if the make has no logo. */
	public static function logo( $atts ) {
		$a = shortcode_atts( array( 'size' => '64', 'regnr' => '', 'el_class' => '', 'css' => '' ), $atts, 'bv_logo' );
		$l = self::lookup( $a );
		if ( ! self::found( $l ) || empty( $l['data']['merke_logo'] ) ) {
			return '';
		}
		return BV_Render::template( 'logo', array(
			'url'   => $l['data']['merke_logo'],
			'alt'   => $l['data']['merke'] ?? '',
			'size'  => max( 16, min( 400, (int) $a['size'] ) ),
			'class' => BV_Render::classes( 'bv bv-logo', $a ),
		) );
	}

	/* [bv_table fields="merke,modell" title="Generell informasjon" icon="info" highlight="neste_eu" labels="merke:Bilmerke"] */
	public static function table( $atts ) {
		$a = shortcode_atts( array(
			'fields' => self::TABLE_GENERAL, 'title' => '', 'icon' => '', 'highlight' => '', 'labels' => '',
			'hide_empty' => '1', 'regnr' => '', 'el_class' => '', 'css' => '',
		), $atts, 'bv_table' );
		$l = self::lookup( $a );
		if ( ! self::found( $l ) ) {
			return '';
		}

		$labels = array();
		foreach ( array_filter( explode( ',', $a['labels'] ) ) as $pair ) {
			$parts = array_map( 'trim', explode( ':', $pair, 2 ) );
			if ( 2 === count( $parts ) ) {
				$labels[ $parts[0] ] = $parts[1];
			}
		}

		$rows = array();
		foreach ( array_map( 'trim', explode( ',', $a['fields'] ) ) as $key ) {
			$val = BV_Render::value( $l, $key, array( 'unit' => 1 ) );
			if ( '' === $val && '1' === $a['hide_empty'] ) {
				continue;
			}
			$row = array(
				'key'   => $key,
				'label' => $labels[ $key ] ?? BV_Fields::label( $key ),
				'value' => '' === $val ? '—' : $val,
				'class' => 'bv-row--' . sanitize_html_class( $key ),
				'color' => 'farge' === $key ? ( $l['data']['farge_hex'] ?? '' ) : '',
			);
			if ( $key === $a['highlight'] ) {
				$row['class'] .= ' bv-row--highlight' . ( 'neste_eu' === $key && ! empty( $l['data']['eu_status'] ) ? ' bv-row--' . $l['data']['eu_status'] : '' );
			}
			$rows[] = $row;
		}
		if ( ! $rows ) {
			return '';
		}
		return BV_Render::template( 'table', array( 'a' => $a, 'rows' => $rows, 'class' => BV_Render::classes( 'bv bv-panel', $a ) ) );
	}

	/* [bv_tiles fields="registrert_aar,drivstoff,effekt,slagvolum" columns="4"] */
	public static function tiles( $atts ) {
		$a = shortcode_atts( array( 'fields' => self::DEFAULT_TILES, 'columns' => '4', 'regnr' => '', 'el_class' => '', 'css' => '' ), $atts, 'bv_tiles' );
		$l = self::lookup( $a );
		if ( ! self::found( $l ) ) {
			return '';
		}
		$tiles = array();
		foreach ( array_map( 'trim', explode( ',', $a['fields'] ) ) as $key ) {
			$val = BV_Render::value( $l, $key, array( 'unit' => 1 ) );
			if ( '' === $val ) {
				continue;
			}
			$f       = BV_Fields::get( $key );
			$tiles[] = array(
				'label' => BV_Fields::label( $key ),
				'value' => $val,
				'icon'  => $f['icon'] ?? 'info',
			);
		}
		return BV_Render::template( 'tiles', array(
			'tiles'   => $tiles,
			'columns' => max( 1, min( 6, (int) $a['columns'] ) ),
			'class'   => BV_Render::classes( 'bv bv-tiles', $a ),
		) );
	}

	/* [bv_card] – the full layout. Shows a message if nothing is found. */
	public static function card( $atts ) {
		$a = shortcode_atts( array(
			'tiles' => self::DEFAULT_TILES, 'general' => self::TABLE_GENERAL, 'dates' => self::TABLE_DATES, 'miljo' => self::TABLE_MILJO,
			'print' => '1', 'image' => '1', 'logo' => '1', 'regnr' => '', 'el_class' => '', 'css' => '',
		), $atts, 'bv_card' );
		$l = self::lookup( $a );
		if ( ! $l ) {
			return '';
		}
		$class = BV_Render::classes( 'bv bv-card', $a );
		if ( ! self::found( $l ) ) {
			return BV_Render::template( 'notice', array( 'l' => $l, 'class' => $class ) );
		}
		return BV_Render::template( 'card', array( 'l' => $l, 'a' => $a, 'class' => $class ) );
	}
}
