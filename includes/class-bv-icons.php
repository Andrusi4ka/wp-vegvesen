<?php
defined( 'ABSPATH' ) || exit;

/** Small inline SVG icon set (stroke icons, inherit currentColor). */
class BV_Icons {

	private static $paths = array(
		'car'      => '<path d="M5 17h14M5 17a2 2 0 1 1-4 0v-4l2.5-5.5A2 2 0 0 1 5.3 6.3h13.4a2 2 0 0 1 1.8 1.2L23 13v4a2 2 0 1 1-4 0"/><path d="M3 12h18"/><circle cx="7" cy="15" r="0.6"/><circle cx="17" cy="15" r="0.6"/>',
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
		'check'    => '<circle cx="12" cy="12" r="10" fill="currentColor" stroke="none"/><path d="m7.5 12.5 3 3 6-6.5" stroke="#fff"/>',
		'alert'    => '<circle cx="12" cy="12" r="10"/><path d="M12 7v6M12 16.5v.5"/>',
		'printer'  => '<path d="M6 9V3h12v6"/><rect x="3" y="9" width="18" height="8" rx="2"/><path d="M6 14h12v7H6z"/>',
		'calendar' => '<rect x="3" y="4.5" width="18" height="16.5" rx="2"/><path d="M3 10h18M8 2.5v4M16 2.5v4"/>',
		'fuel'     => '<path d="M4 21V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v16M3 21h13M4 10h11"/><path d="M15 8h2a2 2 0 0 1 2 2v7a1.5 1.5 0 0 0 3 0V8.5L19 5"/>',
		'gauge'    => '<path d="M3.5 18a9 9 0 1 1 17 0"/><path d="m12 14 4-5"/>',
		'engine'   => '<path d="M7 6h6M10 6v3M5 9h10l2 2h2v-2h2v8h-2v-2h-2l-2 3H8l-3-3H3v-6h2z"/>',
		'info'     => '<circle cx="12" cy="12" r="10"/><path d="M12 16.5v-5M12 8v-.5"/>',
		'leaf'     => '<path d="M11 20A7 7 0 0 1 4 13c0-6 6-9 16-9 0 10-3 16-9 16z"/><path d="M4 21c3-6 7-9 11-11"/>',
		'weight'   => '<circle cx="12" cy="5" r="2.5"/><path d="M6.5 8h11l2.5 13H4z"/>',
		'seat'     => '<path d="M7 3h4l1 9h6l1 6h-3M7 3l1 15h8"/>',
		'circle-x' => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>',
		'gear'     => '<circle cx="6" cy="5" r="2"/><circle cx="12" cy="5" r="2"/><circle cx="18" cy="5" r="2"/><circle cx="6" cy="19" r="2"/><circle cx="12" cy="19" r="2"/><path d="M6 7v10M12 7v10M18 7v5H6"/>',
	);

	/** Option with the icon chosen per field on the Variables page: [ field => icon name | 'none' ]. */
	const OPTION = 'bv_field_icons';

	private static $all = null;

	/** The plugin's own icons plus the extra set (includes/icons-lucide.php). */
	public static function all() {
		if ( null === self::$all ) {
			$extra     = include __DIR__ . '/icons-lucide.php';
			self::$all = self::$paths + ( is_array( $extra ) ? $extra : array() );
		}
		return self::$all;
	}

	public static function get( $name, $class = '' ) {
		$all = self::all();
		if ( ! isset( $all[ $name ] ) ) {
			return '';
		}
		return '<svg class="bv-icon ' . esc_attr( $class ) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $all[ $name ] . '</svg>';
	}

	public static function names() {
		return array_keys( self::all() );
	}

	/** Icons grouped for the picker: [ group label => [ names ] ]. Icons not listed land in "Other". */
	public static function groups() {
		$groups = array(
			__( 'Vehicle', 'bruktdeler-vegvesen' )            => array( 'car', 'car-front', 'bus', 'truck', 'caravan', 'bike', 'disc-3', 'circle-dot', 'key-round', 'seat', 'armchair', 'users', 'user' ),
			__( 'Engine and energy', 'bruktdeler-vegvesen' )  => array( 'engine', 'fuel', 'gauge', 'gear', 'cog', 'settings', 'cylinder', 'battery', 'battery-charging', 'plug-zap', 'zap', 'thermometer', 'droplet', 'droplets', 'wind', 'snowflake', 'sun', 'leaf' ),
			__( 'Dates and time', 'bruktdeler-vegvesen' )     => array( 'calendar', 'calendar-check', 'calendar-clock', 'clock', 'timer', 'history' ),
			__( 'Size and weight', 'bruktdeler-vegvesen' )    => array( 'weight', 'scale', 'ruler', 'move-horizontal', 'move-vertical', 'package', 'container' ),
			__( 'Status', 'bruktdeler-vegvesen' )             => array( 'check', 'circle-check', 'badge-check', 'shield-check', 'clipboard-check', 'alert', 'triangle-alert', 'circle-x', 'info' ),
		);
		$listed = array();
		foreach ( $groups as $label => $names ) {
			$groups[ $label ] = array_values( array_intersect( $names, self::names() ) );
			$listed           = array_merge( $listed, $groups[ $label ] );
		}
		$groups[ __( 'Other', 'bruktdeler-vegvesen' ) ] = array_values( array_diff( self::names(), $listed ) );
		return $groups;
	}

	/** The field's built-in icon ('info' when it has none). */
	public static function default_field_icon( $key ) {
		$f = BV_Fields::get( $key );
		return ! empty( $f['icon'] ) ? $f['icon'] : 'info';
	}

	/** Icon for a field in tiles: the one chosen on the Variables page, else the built-in one. '' = no icon. */
	public static function field_icon( $key ) {
		$chosen = (array) get_option( self::OPTION, array() );
		if ( isset( $chosen[ $key ] ) ) {
			return 'none' === $chosen[ $key ] ? '' : (string) $chosen[ $key ];
		}
		return self::default_field_icon( $key );
	}

	/**
	 * Saves the icon of one field: an icon name, 'none' (no icon) or '' (back to the built-in one).
	 * Returns false for an unknown field or icon.
	 */
	public static function set_field_icon( $key, $icon ) {
		if ( ! BV_Fields::get( $key ) || ( '' !== $icon && 'none' !== $icon && ! isset( self::all()[ $icon ] ) ) ) {
			return false;
		}
		$chosen = (array) get_option( self::OPTION, array() );
		if ( '' === $icon || self::default_field_icon( $key ) === $icon ) {
			unset( $chosen[ $key ] );
		} else {
			$chosen[ $key ] = $icon;
		}
		update_option( self::OPTION, $chosen, false );
		return true;
	}
}
