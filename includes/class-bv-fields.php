<?php
defined( 'ABSPATH' ) || exit;

/**
 * Field registry: our own stable field names → where they live in the Vegvesen response.
 * If Vegvesen changes its format, only the paths here need updating; shortcodes on pages stay the same.
 *
 * Paths are dot-separated, relative to one item in "kjoretoydataListe".
 * "td." is short for "godkjenning.tekniskGodkjenning.tekniskeData.". "*" matches any list item.
 * Filter 'bv_fields' can add or change fields.
 *
 * Note: values such as fuel, body type or colour come from Vegvesen in Norwegian and are shown as-is.
 */
class BV_Fields {

	private static $fields = null;

	public static function all() {
		if ( null !== self::$fields ) {
			return self::$fields;
		}

		$g_general = __( 'General', 'bruktdeler-vegvesen' );
		$g_engine  = __( 'Engine', 'bruktdeler-vegvesen' );
		$g_weight  = __( 'Weight and dimensions', 'bruktdeler-vegvesen' );
		$g_dates   = __( 'Dates', 'bruktdeler-vegvesen' );
		$g_env     = __( 'Environment and consumption', 'bruktdeler-vegvesen' );
		$g_system  = __( 'System', 'bruktdeler-vegvesen' );
		$hp        = __( 'hp', 'bruktdeler-vegvesen' );

		$f = array(
			// General.
			'regnr'             => array( 'group' => $g_general, 'label' => __( 'Registration number', 'bruktdeler-vegvesen' ), 'desc' => __( 'Number plate as returned by the API', 'bruktdeler-vegvesen' ), 'paths' => array( 'kjoretoyId.kjennemerke', 'kjennemerke.0.kjennemerke' ) ),
			'regnr_formatted'   => array( 'group' => $g_general, 'label' => __( 'Registration number', 'bruktdeler-vegvesen' ), 'desc' => __( 'Number plate with a space (AB 12345)', 'bruktdeler-vegvesen' ) ),
			'vin'               => array( 'group' => $g_general, 'label' => __( 'VIN', 'bruktdeler-vegvesen' ), 'desc' => __( 'Vehicle identification number', 'bruktdeler-vegvesen' ), 'paths' => array( 'kjoretoyId.understellsnummer' ) ),
			'merke'             => array( 'group' => $g_general, 'label' => __( 'Make', 'bruktdeler-vegvesen' ), 'desc' => __( 'Manufacturer', 'bruktdeler-vegvesen' ), 'case' => 'title', 'paths' => array( 'td.generelt.merke.0.merke' ) ),
			'modell'            => array( 'group' => $g_general, 'label' => __( 'Model', 'bruktdeler-vegvesen' ), 'desc' => __( 'Model (trade name)', 'bruktdeler-vegvesen' ), 'case' => 'title', 'paths' => array( 'td.generelt.handelsbetegnelse.0' ) ),
			'merke_modell'      => array( 'group' => $g_general, 'label' => __( 'Vehicle', 'bruktdeler-vegvesen' ), 'desc' => __( 'Make + model', 'bruktdeler-vegvesen' ) ),
			'variant'           => array( 'group' => $g_general, 'label' => __( 'Variant', 'bruktdeler-vegvesen' ), 'desc' => __( 'Variant / type designation', 'bruktdeler-vegvesen' ), 'case' => 'title', 'paths' => array( 'td.generelt.typebetegnelse' ) ),
			'karosseri'         => array( 'group' => $g_general, 'label' => __( 'Body type', 'bruktdeler-vegvesen' ), 'desc' => __( 'Body type (e.g. estate)', 'bruktdeler-vegvesen' ), 'clean' => 'code', 'paths' => array( 'td.karosseriOgLasteplan.karosseritype.kodeNavn', 'td.karosseriOgLasteplan.karosseritype.kodeBeskrivelse' ) ),
			'kategori'          => array( 'group' => $g_general, 'label' => __( 'Vehicle category', 'bruktdeler-vegvesen' ), 'desc' => __( 'Technical code (M1, N1…)', 'bruktdeler-vegvesen' ), 'paths' => array( 'godkjenning.tekniskGodkjenning.kjoretoyklassifisering.tekniskKode.kodeVerdi' ) ),
			'kategori_navn'     => array( 'group' => $g_general, 'label' => __( 'Vehicle category', 'bruktdeler-vegvesen' ), 'desc' => __( 'Category name + code, e.g. Personbil (M1)', 'bruktdeler-vegvesen' ), 'paths' => array( 'godkjenning.tekniskGodkjenning.kjoretoyklassifisering.tekniskKode.kodeNavn', 'godkjenning.tekniskGodkjenning.kjoretoyklassifisering.tekniskKode.kodeBeskrivelse' ) ),
			'farge'             => array( 'group' => $g_general, 'label' => __( 'Colour', 'bruktdeler-vegvesen' ), 'desc' => __( 'Colour', 'bruktdeler-vegvesen' ), 'paths' => array( 'td.karosseriOgLasteplan.rFarge.0.kodeNavn', 'td.karosseriOgLasteplan.rFarge.0.kodeBeskrivelse' ) ),
			'farge_hex'         => array( 'group' => $g_general, 'label' => __( 'Colour code', 'bruktdeler-vegvesen' ), 'desc' => __( 'HEX colour for the swatch (calculated)', 'bruktdeler-vegvesen' ) ),
			'sitteplasser'      => array( 'group' => $g_general, 'label' => __( 'Seats', 'bruktdeler-vegvesen' ), 'desc' => __( 'Number of seats', 'bruktdeler-vegvesen' ), 'type' => 'int', 'icon' => 'seat', 'paths' => array( 'td.persontall.sitteplasserTotalt' ) ),

			// Engine.
			'drivstoff'         => array( 'group' => $g_engine, 'label' => __( 'Fuel', 'bruktdeler-vegvesen' ), 'desc' => __( 'Fuel (hybrids: Bensin / Elektrisk)', 'bruktdeler-vegvesen' ), 'icon' => 'fuel', 'paths' => array( 'td.motorOgDrivverk.motor.0.drivstoff.0.drivstoffKode.kodeNavn', 'td.motorOgDrivverk.motor.0.drivstoff.0.drivstoffKode.kodeBeskrivelse' ) ),
			'effekt_kw'         => array( 'group' => $g_engine, 'label' => __( 'Power (kW)', 'bruktdeler-vegvesen' ), 'desc' => __( 'Power in kW', 'bruktdeler-vegvesen' ), 'type' => 'int', 'unit' => 'kW', 'icon' => 'gauge', 'paths' => array( 'td.motorOgDrivverk.motor.0.drivstoff.0.maksNettoEffekt', 'td.motorOgDrivverk.motor.0.maksNettoEffekt' ) ),
			'effekt_hk'         => array( 'group' => $g_engine, 'label' => __( 'Power (hp)', 'bruktdeler-vegvesen' ), 'desc' => __( 'Power in hp (kW × 1.36)', 'bruktdeler-vegvesen' ), 'type' => 'int', 'unit' => $hp, 'icon' => 'gauge' ),
			'effekt'            => array( 'group' => $g_engine, 'label' => __( 'Power', 'bruktdeler-vegvesen' ), 'desc' => __( 'Power combined: 140 kW (190 hp)', 'bruktdeler-vegvesen' ), 'icon' => 'gauge' ),
			'slagvolum'         => array( 'group' => $g_engine, 'label' => __( 'Engine displacement', 'bruktdeler-vegvesen' ), 'desc' => __( 'Engine displacement, cm³', 'bruktdeler-vegvesen' ), 'type' => 'int', 'unit' => 'cm³', 'icon' => 'engine', 'paths' => array( 'td.motorOgDrivverk.motor.0.slagvolum' ) ),
			'girkasse'          => array( 'group' => $g_engine, 'label' => __( 'Transmission', 'bruktdeler-vegvesen' ), 'desc' => __( 'Gearbox type', 'bruktdeler-vegvesen' ), 'icon' => 'gear', 'paths' => array( 'td.motorOgDrivverk.girkassetype.kodeNavn', 'td.motorOgDrivverk.girkassetype.kodeBeskrivelse' ) ),
			'hybrid'            => array( 'group' => $g_engine, 'label' => __( 'Hybrid category', 'bruktdeler-vegvesen' ), 'desc' => __( 'Hybrid category', 'bruktdeler-vegvesen' ), 'paths' => array( 'td.motorOgDrivverk.hybridKategori.kodeNavn', 'td.motorOgDrivverk.hybridKategori.kodeBeskrivelse' ) ),

			// Weight and dimensions.
			'egenvekt'          => array( 'group' => $g_weight, 'label' => __( 'Kerb weight', 'bruktdeler-vegvesen' ), 'desc' => __( 'Kerb weight, kg', 'bruktdeler-vegvesen' ), 'type' => 'int', 'unit' => 'kg', 'icon' => 'weight', 'paths' => array( 'td.vekter.egenvekt' ) ),
			'totalvekt'         => array( 'group' => $g_weight, 'label' => __( 'Gross vehicle weight', 'bruktdeler-vegvesen' ), 'desc' => __( 'Maximum permitted weight, kg', 'bruktdeler-vegvesen' ), 'type' => 'int', 'unit' => 'kg', 'icon' => 'weight', 'paths' => array( 'td.vekter.tillattTotalvekt' ) ),
			'tilhengervekt'     => array( 'group' => $g_weight, 'label' => __( 'Towing capacity (braked)', 'bruktdeler-vegvesen' ), 'desc' => __( 'Braked trailer weight, kg', 'bruktdeler-vegvesen' ), 'type' => 'int', 'unit' => 'kg', 'icon' => 'weight', 'paths' => array( 'td.vekter.tillattTilhengervektMedBrems' ) ),
			'lengde'            => array( 'group' => $g_weight, 'label' => __( 'Length', 'bruktdeler-vegvesen' ), 'desc' => __( 'Length, mm', 'bruktdeler-vegvesen' ), 'type' => 'int', 'unit' => 'mm', 'paths' => array( 'td.dimensjoner.lengde' ) ),
			'bredde'            => array( 'group' => $g_weight, 'label' => __( 'Width', 'bruktdeler-vegvesen' ), 'desc' => __( 'Width, mm', 'bruktdeler-vegvesen' ), 'type' => 'int', 'unit' => 'mm', 'paths' => array( 'td.dimensjoner.bredde' ) ),
			'hoyde'             => array( 'group' => $g_weight, 'label' => __( 'Height', 'bruktdeler-vegvesen' ), 'desc' => __( 'Height, mm', 'bruktdeler-vegvesen' ), 'type' => 'int', 'unit' => 'mm', 'paths' => array( 'td.dimensjoner.hoyde' ) ),

			// Dates.
			'registrert_forste' => array( 'group' => $g_dates, 'label' => __( 'First registered', 'bruktdeler-vegvesen' ), 'desc' => __( 'Date of first registration in Norway', 'bruktdeler-vegvesen' ), 'type' => 'date', 'icon' => 'calendar', 'paths' => array( 'forstegangsregistrering.registrertForstegangNorgeDato' ) ),
			'registrert_aar'    => array( 'group' => $g_dates, 'label' => __( 'Year of registration', 'bruktdeler-vegvesen' ), 'desc' => __( 'Year of first registration', 'bruktdeler-vegvesen' ), 'icon' => 'calendar' ),
			'sist_eu'           => array( 'group' => $g_dates, 'label' => __( 'Last approved EU inspection', 'bruktdeler-vegvesen' ), 'desc' => __( 'Last periodic inspection', 'bruktdeler-vegvesen' ), 'type' => 'date', 'icon' => 'calendar', 'paths' => array( 'periodiskKjoretoyKontroll.sistGodkjent' ) ),
			'neste_eu'          => array( 'group' => $g_dates, 'label' => __( 'Next EU inspection', 'bruktdeler-vegvesen' ), 'desc' => __( 'Next periodic inspection deadline', 'bruktdeler-vegvesen' ), 'type' => 'date', 'icon' => 'calendar', 'paths' => array( 'periodiskKjoretoyKontroll.kontrollfrist' ) ),
			'eu_status'         => array( 'group' => $g_dates, 'label' => __( 'Inspection status', 'bruktdeler-vegvesen' ), 'desc' => __( 'Inspection status code: ok / snart / utlopt', 'bruktdeler-vegvesen' ) ),
			'eu_status_tekst'   => array( 'group' => $g_dates, 'label' => __( 'EU inspection', 'bruktdeler-vegvesen' ), 'desc' => __( 'Inspection status as text', 'bruktdeler-vegvesen' ) ),

			// Environment.
			'co2'               => array( 'group' => $g_env, 'label' => __( 'CO₂ emissions', 'bruktdeler-vegvesen' ), 'desc' => __( 'CO₂ emissions, g/km', 'bruktdeler-vegvesen' ), 'type' => 'float', 'decimals' => 0, 'unit' => 'g/km', 'icon' => 'leaf', 'paths' => array( 'td.miljodata.miljoOgdrivstoffGruppe.*.forbrukOgUtslipp.*.co2BlandetKjoring', 'td.miljodata.miljoOgdrivstoffGruppe.*.forbrukOgUtslipp.*.vektetKombinertDrivstoffCO2' ) ),
			'forbruk'           => array( 'group' => $g_env, 'label' => __( 'Fuel consumption (combined)', 'bruktdeler-vegvesen' ), 'desc' => __( 'Fuel consumption, l/100 km', 'bruktdeler-vegvesen' ), 'type' => 'float', 'decimals' => 1, 'unit' => 'l/100 km', 'icon' => 'fuel', 'paths' => array( 'td.miljodata.miljoOgdrivstoffGruppe.*.forbrukOgUtslipp.*.forbrukBlandetKjoring', 'td.miljodata.miljoOgdrivstoffGruppe.*.forbrukOgUtslipp.*.vektetKombinertDrivstoff' ) ),
			'el_forbruk'        => array( 'group' => $g_env, 'label' => __( 'Energy consumption', 'bruktdeler-vegvesen' ), 'desc' => __( 'Electric energy consumption, Wh/km (electric / hybrid)', 'bruktdeler-vegvesen' ), 'type' => 'int', 'unit' => 'Wh/km', 'icon' => 'fuel', 'paths' => array( 'td.miljodata.miljoOgdrivstoffGruppe.*.forbrukOgUtslipp.*.elEnergiforbruk' ) ),
			'rekkevidde'        => array( 'group' => $g_env, 'label' => __( 'Electric range', 'bruktdeler-vegvesen' ), 'desc' => __( 'Range on electricity, km', 'bruktdeler-vegvesen' ), 'type' => 'int', 'unit' => 'km', 'icon' => 'gauge', 'paths' => array( 'td.miljodata.miljoOgdrivstoffGruppe.*.forbrukOgUtslipp.*.rekkeviddeKm' ) ),
			'euroklasse'        => array( 'group' => $g_env, 'label' => __( 'Emission class', 'bruktdeler-vegvesen' ), 'desc' => __( 'Euro emission class (Euro 6)', 'bruktdeler-vegvesen' ), 'icon' => 'leaf', 'clean' => 'dash', 'paths' => array( 'td.miljodata.euroKlasse.kodeNavn', 'td.miljodata.euroKlasse.kodeBeskrivelse' ) ),

			// System.
			'sist_oppdatert'    => array( 'group' => $g_system, 'label' => __( 'Last updated', 'bruktdeler-vegvesen' ), 'desc' => __( 'When the data was fetched from Vegvesen', 'bruktdeler-vegvesen' ), 'type' => 'date' ),
			'status'            => array( 'group' => $g_system, 'label' => __( 'Status', 'bruktdeler-vegvesen' ), 'desc' => __( 'Search status: funnet / ikke_funnet / ugyldig / feil / ingen', 'bruktdeler-vegvesen' ) ),
		);

		self::$fields = apply_filters( 'bv_fields', $f );
		return self::$fields;
	}

	public static function get( $key ) {
		$all = self::all();
		return $all[ $key ] ?? null;
	}

	public static function label( $key ) {
		$f = self::get( $key );
		return $f ? $f['label'] : $key;
	}

	public static function choices() {
		$out = array();
		foreach ( self::all() as $key => $f ) {
			$out[ $key ] = $f['label'] . ' (' . $key . ')';
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Normalization                                                       */
	/* ------------------------------------------------------------------ */

	/** Reads a dotted path. "*" means "any list item" – the first non-empty match wins. */
	public static function dig( $arr, $path ) {
		$path = preg_replace( '/^td\./', 'godkjenning.tekniskGodkjenning.tekniskeData.', $path );
		return self::dig_parts( $arr, explode( '.', $path ) );
	}

	private static function dig_parts( $arr, $parts ) {
		if ( ! $parts ) {
			return $arr;
		}
		$seg = array_shift( $parts );
		if ( '*' === $seg ) {
			if ( ! is_array( $arr ) ) {
				return null;
			}
			foreach ( $arr as $item ) {
				$v = self::dig_parts( $item, $parts );
				if ( null !== $v && '' !== $v && ! is_array( $v ) ) {
					return $v;
				}
			}
			return null;
		}
		return ( is_array( $arr ) && array_key_exists( $seg, $arr ) ) ? self::dig_parts( $arr[ $seg ], $parts ) : null;
	}

	/** Turns a raw API response into a flat [field => value] array. */
	public static function normalize( $raw ) {
		$item = $raw['kjoretoydataListe'][0] ?? $raw;
		$data = array();

		foreach ( self::all() as $key => $f ) {
			if ( empty( $f['paths'] ) ) {
				continue;
			}
			$val = null;
			foreach ( $f['paths'] as $p ) {
				$v = self::dig( $item, $p );
				if ( null !== $v && '' !== $v && ! is_array( $v ) ) {
					$val = $v;
					break;
				}
			}
			if ( null === $val ) {
				continue;
			}
			$type = $f['type'] ?? 'text';
			if ( 'int' === $type ) {
				$val = (int) round( (float) $val );
			} elseif ( 'float' === $type ) {
				$val = (float) $val;
			} elseif ( 'date' === $type ) {
				$val = substr( (string) $val, 0, 10 );
			} else {
				$val = trim( (string) $val );
				if ( 'code' === ( $f['clean'] ?? '' ) ) {
					// "Stasjonsvogn (AC)" → "Stasjonsvogn".
					$val = preg_replace( '/\s*\([A-Z0-9]{1,4}\)$/', '', $val );
				} elseif ( 'dash' === ( $f['clean'] ?? '' ) ) {
					// "Euro 6L - EF 715/2007*EF 459/2012 (…)" → "Euro 6L".
					$val = preg_replace( '/\s+-\s+.*$/u', '', $val );
				}
				if ( 'title' === ( $f['case'] ?? '' ) ) {
					$val = self::title_case( $val );
				}
			}
			$data[ $key ] = $val;
		}

		// Computed fields.
		// Hybrids have several fuels: "Bensin / Elektrisk".
		$fuels = array();
		foreach ( (array) self::dig( $item, 'td.motorOgDrivverk.motor' ) as $motor ) {
			foreach ( (array) ( $motor['drivstoff'] ?? array() ) as $d ) {
				$name = $d['drivstoffKode']['kodeNavn'] ?? ( $d['drivstoffKode']['kodeBeskrivelse'] ?? '' );
				if ( $name && ! in_array( $name, $fuels, true ) ) {
					$fuels[] = $name;
				}
			}
		}
		if ( count( $fuels ) > 1 ) {
			$data['drivstoff'] = implode( ' / ', $fuels );
		}
		if ( ! empty( $data['regnr'] ) ) {
			$data['regnr']           = strtoupper( str_replace( ' ', '', $data['regnr'] ) );
			$data['regnr_formatted'] = self::format_plate( $data['regnr'] );
		}
		$data['merke_modell'] = trim( ( $data['merke'] ?? '' ) . ' ' . ( $data['modell'] ?? '' ) );
		if ( ! empty( $data['effekt_kw'] ) ) {
			$data['effekt_hk'] = (int) round( $data['effekt_kw'] * 1.35962 );
			/* translators: 1: power in kW, 2: power in horsepower. */
			$data['effekt'] = sprintf( __( '%1$s kW (%2$s hp)', 'bruktdeler-vegvesen' ), $data['effekt_kw'], $data['effekt_hk'] );
		}
		if ( ! empty( $data['registrert_forste'] ) ) {
			$data['registrert_aar'] = substr( $data['registrert_forste'], 0, 4 );
		}
		if ( ! empty( $data['kategori'] ) ) {
			$name                  = $data['kategori_navn'] ?? self::category_name( $data['kategori'] );
			$data['kategori_navn'] = $name ? $name . ' (' . $data['kategori'] . ')' : $data['kategori'];
		}
		if ( ! empty( $data['farge'] ) ) {
			$data['farge_hex'] = self::color_hex( $data['farge'] );
		}
		if ( ! empty( $data['neste_eu'] ) ) {
			$due   = strtotime( $data['neste_eu'] );
			$today = strtotime( current_time( 'Y-m-d' ) );
			$warn  = (int) BV_Settings::get( 'eu_warn_days' ) * DAY_IN_SECONDS;
			if ( $due < $today ) {
				$data['eu_status']       = 'utlopt';
				$data['eu_status_tekst'] = __( 'Overdue', 'bruktdeler-vegvesen' );
			} elseif ( $due - $today <= $warn ) {
				$data['eu_status']       = 'snart';
				$data['eu_status_tekst'] = __( 'Due soon', 'bruktdeler-vegvesen' );
			} else {
				$data['eu_status']       = 'ok';
				$data['eu_status_tekst'] = __( 'Approved', 'bruktdeler-vegvesen' );
			}
		}

		return apply_filters( 'bv_normalized_data', array_filter( $data, function ( $v ) {
			return null !== $v && '' !== $v;
		} ), $raw );
	}

	public static function format_plate( $regnr ) {
		if ( preg_match( '/^([A-Z]{2})(\d{4,5})$/', $regnr, $m ) ) {
			return $m[1] . ' ' . $m[2];
		}
		return $regnr;
	}

	private static function title_case( $s ) {
		$words = preg_split( '/(\s+|-)/u', $s, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $words as &$w ) {
			// Keep short brand acronyms (BMW, VW) and words with digits (ID.4, XC60) as they are.
			if ( '' === trim( $w ) || '-' === $w || preg_match( '/\d/', $w ) || ( mb_strlen( $w ) <= 3 && mb_strtoupper( $w ) === $w ) ) {
				continue;
			}
			$w = mb_convert_case( mb_strtolower( $w ), MB_CASE_TITLE, 'UTF-8' );
		}
		return implode( '', $words );
	}

	/** Fallback when the API has no category name. Values are Vegvesen's own (Norwegian) terms. */
	private static function category_name( $code ) {
		$map = array(
			'M1' => 'Personbil', 'M2' => 'Buss', 'M3' => 'Buss', 'N1' => 'Varebil', 'N2' => 'Lastebil', 'N3' => 'Lastebil',
			'L1' => 'Moped', 'L3' => 'Motorsykkel', 'L5' => 'Trehjulet motorsykkel', 'L6' => 'Lett firehjuling', 'L7' => 'Firehjuling',
			'O1' => 'Tilhenger', 'O2' => 'Tilhenger', 'O3' => 'Tilhenger', 'O4' => 'Tilhenger', 'T' => 'Traktor',
		);
		$code = strtoupper( $code );
		return $map[ $code ] ?? ( $map[ substr( $code, 0, 2 ) ] ?? ( $map[ substr( $code, 0, 1 ) ] ?? '' ) );
	}

	/** Maps Vegvesen colour names (Norwegian) to a swatch colour. */
	private static function color_hex( $name ) {
		$map = array(
			'grå' => '#5f6670', 'svart' => '#1c1d1f', 'hvit' => '#f4f4f2', 'sølv' => '#c3c7cc', 'blå' => '#2c5aa0',
			'rød' => '#c0392b', 'grønn' => '#2e7d4f', 'brun' => '#7b5234', 'beige' => '#d8c7a0', 'gul' => '#e8c547',
			'oransje' => '#e67e22', 'lilla' => '#7d4fa3', 'fiolett' => '#7d4fa3', 'gull' => '#c9a43a', 'rosa' => '#e48bb0',
		);
		$name = mb_strtolower( $name );
		foreach ( $map as $k => $hex ) {
			if ( false !== mb_strpos( $name, $k ) ) {
				return $hex;
			}
		}
		return '#9aa0a6';
	}

	/* ------------------------------------------------------------------ */
	/* Formatting                                                          */
	/* ------------------------------------------------------------------ */

	/** Default date format for tables (translatable: Norwegian uses d.m.Y). */
	public static function default_date_format() {
		/* translators: PHP date format for dates in tables, see https://www.php.net/manual/datetime.format.php */
		return __( 'd/m/Y', 'bruktdeler-vegvesen' );
	}

	/**
	 * Formats a value for output (numbers and dates follow the site language).
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Raw normalized value.
	 * @param array  $o     Options: format, number (0/1), decimals, unit (0/1).
	 */
	public static function format( $key, $value, $o = array() ) {
		$f    = self::get( $key ) ?: array();
		$type = $f['type'] ?? 'text';

		if ( 'date' === $type && $value ) {
			$fmt = ! empty( $o['format'] ) ? $o['format'] : self::default_date_format();
			$out = self::date( $fmt, strtotime( $value ) );
		} elseif ( ( 'int' === $type || 'float' === $type ) && is_numeric( $value ) ) {
			$decimals = isset( $o['decimals'] ) && '' !== $o['decimals'] ? (int) $o['decimals'] : (int) ( $f['decimals'] ?? 0 );
			if ( isset( $o['number'] ) && '0' === (string) $o['number'] ) {
				$out = number_format( (float) $value, $decimals, '.', '' );
			} else {
				$out = number_format_i18n( (float) $value, $decimals );
			}
		} else {
			$out = (string) $value;
		}

		if ( ! empty( $o['unit'] ) && ! empty( $f['unit'] ) && '' !== $out ) {
			$out .= "\xC2\xA0" . $f['unit'];
		}
		return $out;
	}

	/** Localised date (month names follow the site language). */
	public static function date( $fmt, $ts ) {
		return wp_date( $fmt, $ts, new DateTimeZone( 'UTC' ) );
	}
}
