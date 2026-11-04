<?php
defined( 'ABSPATH' ) || exit;

/** Admin: settings page + variables reference with live test lookup. */
class BV_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		// Who sees WP Vegvesen: administrators + the roles chosen in Settings (also allowed to save them).
		add_filter( 'user_has_cap', array( 'BV_Settings', 'grant_access' ), 10, 4 );
		add_filter( 'option_page_capability_bv', function () {
			return BV_Settings::CAP;
		} );
		// WordPress's own colour picker for "Primary colour" (Settings page only).
		add_action( 'admin_enqueue_scripts', function ( $hook ) {
			if ( 'toplevel_page_bv-settings' !== $hook ) {
				return;
			}
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
			wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){$(".bv-color").wpColorPicker();});' );
		} );
		// Variables page: change a field's icon (saved at once, no page reload).
		add_action( 'wp_ajax_bv_field_icon', array( __CLASS__, 'ajax_field_icon' ) );
		// Searches stopped today (own daily limit or Vegvesen's quota): tell the people who run the plugin.
		add_action( 'admin_notices', array( __CLASS__, 'quota_notice' ) );
		// Rebuild rewrite rules when the result page changes.
		add_action( 'update_option_' . BV_Settings::OPTION, function () {
			delete_option( 'rewrite_rules' );
		} );
		add_filter( 'plugin_action_links_' . plugin_basename( BV_FILE ), function ( $links ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=bv-settings' ) ) . '">' . esc_html__( 'Settings', 'bruktdeler-vegvesen' ) . '</a>' );
			return $links;
		} );
	}

	public static function menu() {
		add_menu_page( 'WP Vegvesen', 'WP Vegvesen', BV_Settings::CAP, 'bv-settings', array( __CLASS__, 'settings_page' ), 'dashicons-car', 58 );
		add_submenu_page( 'bv-settings', __( 'Settings', 'bruktdeler-vegvesen' ), __( 'Settings', 'bruktdeler-vegvesen' ), BV_Settings::CAP, 'bv-settings', array( __CLASS__, 'settings_page' ) );
		add_submenu_page( 'bv-settings', __( 'Variables', 'bruktdeler-vegvesen' ), __( 'Variables', 'bruktdeler-vegvesen' ), BV_Settings::CAP, 'bv-variables', array( __CLASS__, 'variables_page' ) );
	}

	/** Saves one field's icon: icon name, 'none' or '' (built-in). Answers with the new button content. */
	public static function ajax_field_icon() {
		check_ajax_referer( 'bv_field_icon' );
		if ( ! current_user_can( BV_Settings::CAP ) ) {
			wp_send_json_error( null, 403 );
		}
		$key  = isset( $_POST['field'] ) ? sanitize_key( wp_unslash( $_POST['field'] ) ) : '';
		$icon = isset( $_POST['icon'] ) ? sanitize_key( wp_unslash( $_POST['icon'] ) ) : '';
		if ( ! BV_Icons::set_field_icon( $key, $icon ) ) {
			wp_send_json_error( null, 400 );
		}
		wp_send_json_success( self::icon_button_state( $key ) );
	}

	/** Warning in the admin for the rest of the day once searches have stopped. */
	public static function quota_notice() {
		$alert = BV_API::alert_today();
		if ( ! $alert || ! current_user_can( BV_Settings::CAP ) ) {
			return;
		}
		$text = 'quota' === $alert['reason']
			? __( 'Statens vegvesen answers that the daily quota of the API key is used up. Vehicle searches on the site do not work until tomorrow; cached vehicles are still shown.', 'bruktdeler-vegvesen' )
			: __( 'The daily limit of requests to Vegvesen has been reached. Vehicle searches on the site do not work until tomorrow; cached vehicles are still shown. If this is normal traffic, raise the limit.', 'bruktdeler-vegvesen' );
		printf(
			'<div class="notice notice-error"><p><strong>WP Vegvesen:</strong> %1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html( $text ),
			esc_url( admin_url( 'admin.php?page=bv-settings' ) ),
			esc_html__( 'Settings', 'bruktdeler-vegvesen' )
		);
	}

	/** What the icon button of a field shows: the icon, whether it was changed, whether it is the generic one. */
	private static function icon_button_state( $key ) {
		$icon = BV_Icons::field_icon( $key );
		$own  = BV_Fields::get( $key )['icon'] ?? '';
		return array(
			'icon'    => '' === $icon ? 'none' : $icon,
			'html'    => '' === $icon ? '' : BV_Icons::get( $icon ),
			'changed' => isset( ( (array) get_option( BV_Icons::OPTION, array() ) )[ $key ] ),
			'generic' => '' === $own && 'info' === $icon,
		);
	}

	public static function register() {
		register_setting( 'bv', BV_Settings::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $in ) {
		$old = BV_Settings::all();
		$out = BV_Settings::defaults();
		$in  = is_array( $in ) ? $in : array();

		// Keep the stored key if the field was left empty (it is never printed back).
		$out['api_key']       = '' !== trim( $in['api_key'] ?? '' ) ? sanitize_text_field( $in['api_key'] ) : $old['api_key'];
		if ( ! empty( $in['api_key_clear'] ) ) {
			$out['api_key'] = '';
		}
		$out['endpoint']      = esc_url_raw( $in['endpoint'] ?? $out['endpoint'] );
		$out['auth_header']   = sanitize_text_field( $in['auth_header'] ?? $out['auth_header'] );
		$out['auth_prefix']   = sanitize_text_field( $in['auth_prefix'] ?? '' ) . ( preg_match( '/\s$/', $in['auth_prefix'] ?? '' ) ? ' ' : '' );
		$out['demo_mode']     = empty( $in['demo_mode'] ) ? 0 : 1;
		$out['result_page']   = absint( $in['result_page'] ?? 0 );
		$out['cache_hours']   = max( 1, absint( $in['cache_hours'] ?? 24 ) );
		$out['rate_limit']    = absint( $in['rate_limit'] ?? 20 );
		$out['ip_source']     = in_array( $in['ip_source'] ?? '', array( 'remote', 'cloudflare', 'forwarded', 'real_ip' ), true ) ? $in['ip_source'] : $old['ip_source'];
		$out['daily_limit']   = isset( $in['daily_limit'] ) ? absint( $in['daily_limit'] ) : (int) $old['daily_limit'];
		$out['preview_regnr'] = strtoupper( sanitize_text_field( $in['preview_regnr'] ?? '' ) );
		$out['eu_warn_days']  = absint( $in['eu_warn_days'] ?? 60 );
		$out['primary_color'] = sanitize_hex_color( $in['primary_color'] ?? '' ) ?: '';
		$out['scroll']        = empty( $in['scroll'] ) ? 0 : 1;
		$out['scroll_offset'] = isset( $in['scroll_offset'] ) && '' !== trim( (string) $in['scroll_offset'] ) ? (string) absint( $in['scroll_offset'] ) : '';
		// Only administrators decide who has access (otherwise a role could hand access on to others).
		if ( current_user_can( 'manage_options' ) && isset( $in['roles_sent'] ) ) {
			$known        = array_keys( wp_roles()->get_names() );
			$out['roles'] = array_values( array_diff( array_intersect( array_map( 'sanitize_key', (array) ( $in['roles'] ?? array() ) ), $known ), array( 'administrator' ) ) );
		} else {
			$out['roles'] = (array) $old['roles'];
		}
		return $out;
	}

	/** "User guide (PDF)" button for page headings: the Norwegian guide for a Norwegian admin, else English. */
	private static function guide_link() {
		$lang = in_array( substr( get_user_locale(), 0, 2 ), array( 'nb', 'nn', 'no' ), true ) ? 'nb' : 'en';
		return sprintf(
			'<a class="page-title-action" href="%1$s" target="_blank" rel="noopener" hreflang="%2$s" title="%3$s"><span class="dashicons dashicons-media-document" aria-hidden="true" style="font-size:16px;width:16px;height:16px;vertical-align:-3px;margin-right:3px"></span>%4$s</a>',
			esc_url( BV_URL . 'assets/docs/wp-vegvesen-guide-' . $lang . '.pdf?ver=' . BV_VERSION ),
			esc_attr( $lang ),
			esc_attr__( 'How to use the variables: shortcodes and tokens, formats, conditions, visibility classes, ready-made blocks, examples and the list of all fields.', 'bruktdeler-vegvesen' ),
			esc_html__( 'User guide (PDF)', 'bruktdeler-vegvesen' )
		);
	}

	public static function settings_page() {
		$s       = BV_Settings::all();
		$name    = BV_Settings::OPTION;
		$const   = defined( 'BV_API_KEY' ) && BV_API_KEY;
		$has_key = '' !== BV_Settings::api_key();
		$demo    = BV_Settings::is_demo();
		$page_id = BV_Settings::result_page_id();
		$code    = array( 'code' => array() );
		?>
		<div class="wrap bv-settings">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'WP Vegvesen – settings', 'bruktdeler-vegvesen' ); ?></h1>
			<?php echo self::guide_link(); // phpcs:ignore -- escaped inside ?>
			<hr class="wp-header-end">

			<?php if ( $demo ) : ?>
				<div class="notice notice-warning inline"><p>
					<strong><?php esc_html_e( 'Demo mode is on.', 'bruktdeler-vegvesen' ); ?></strong>
					<?php esc_html_e( 'The site shows sample data (Volkswagen Passat) for any number. The number XX00000 simulates "not found". To get real data, add an API key and turn off demo mode.', 'bruktdeler-vegvesen' ); ?>
				</p></div>
			<?php endif; ?>

			<?php // Overview: the three things that decide whether the plugin works. ?>
			<ul class="bv-overview">
				<li>
					<span class="bv-overview__label"><?php esc_html_e( 'API key', 'bruktdeler-vegvesen' ); ?></span>
					<span class="bv-overview__value bv-overview__value--<?php echo $has_key ? 'ok' : 'warn'; ?>">
						<?php
						if ( $const ) {
							esc_html_e( 'In wp-config.php', 'bruktdeler-vegvesen' );
						} elseif ( $has_key ) {
							esc_html_e( 'Saved', 'bruktdeler-vegvesen' );
						} else {
							esc_html_e( 'Missing', 'bruktdeler-vegvesen' );
						}
						?>
					</span>
				</li>
				<li>
					<span class="bv-overview__label"><?php esc_html_e( 'Data', 'bruktdeler-vegvesen' ); ?></span>
					<span class="bv-overview__value bv-overview__value--<?php echo $demo ? 'warn' : 'ok'; ?>">
						<?php $demo ? esc_html_e( 'Demo (sample data)', 'bruktdeler-vegvesen' ) : esc_html_e( 'Live from Vegvesen', 'bruktdeler-vegvesen' ); ?>
					</span>
				</li>
				<li>
					<span class="bv-overview__label"><?php esc_html_e( 'Result page', 'bruktdeler-vegvesen' ); ?></span>
					<span class="bv-overview__value bv-overview__value--<?php echo $page_id ? 'ok' : 'warn'; ?>">
						<?php if ( $page_id ) : ?>
							<a href="<?php echo esc_url( BV_Context::url( $s['preview_regnr'] ? $s['preview_regnr'] : 'AB12345' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $page_id ) ); ?></a>
						<?php else : ?>
							<?php esc_html_e( '— not selected —', 'bruktdeler-vegvesen' ); ?>
						<?php endif; ?>
					</span>
				</li>
				<li>
					<?php
					$bv_used  = BV_API::usage_today()['n'];
					$bv_limit = (int) $s['daily_limit'];
					$bv_full  = null !== BV_API::alert_today() || ( $bv_limit > 0 && $bv_used >= $bv_limit * 0.9 );
					?>
					<span class="bv-overview__label"><?php esc_html_e( 'Requests today', 'bruktdeler-vegvesen' ); ?></span>
					<span class="bv-overview__value bv-overview__value--<?php echo $bv_full ? 'warn' : 'ok'; ?>">
						<?php echo esc_html( number_format_i18n( $bv_used ) . ( $bv_limit > 0 ? ' / ' . number_format_i18n( $bv_limit ) : '' ) ); ?>
					</span>
				</li>
			</ul>

			<form method="post" action="options.php">
				<?php settings_fields( 'bv' ); ?>

				<section class="bv-box">
					<header class="bv-box__head">
						<h2><?php esc_html_e( 'Statens vegvesen API', 'bruktdeler-vegvesen' ); ?></h2>
						<p><?php esc_html_e( 'The connection to Statens vegvesen and how often it is asked.', 'bruktdeler-vegvesen' ); ?></p>
					</header>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="bv_key"><?php esc_html_e( 'API key', 'bruktdeler-vegvesen' ); ?></label></th>
							<td>
								<?php if ( $const ) : ?>
									<p><span class="dashicons dashicons-yes" style="color:#1e8a4c"></span> <?php echo wp_kses( __( 'The key is set with the <code>BV_API_KEY</code> constant in <code>wp-config.php</code>.', 'bruktdeler-vegvesen' ), $code ); ?></p>
								<?php else : ?>
									<input type="password" id="bv_key" name="<?php echo esc_attr( $name ); ?>[api_key]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $has_key ? esc_attr__( '•••••••• (saved)', 'bruktdeler-vegvesen' ) : ''; ?>">
									<?php if ( $has_key ) : ?>
										<label style="margin-left:8px"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[api_key_clear]" value="1"> <?php esc_html_e( 'remove', 'bruktdeler-vegvesen' ); ?></label>
									<?php endif; ?>
									<p class="description"><?php echo wp_kses( __( 'It is safer to set the key in <code>wp-config.php</code>: <code>define( \'BV_API_KEY\', \'your-key\' );</code> – then it is not stored in the database.', 'bruktdeler-vegvesen' ), $code ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Demo mode', 'bruktdeler-vegvesen' ); ?></th>
							<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[demo_mode]" value="1" <?php checked( $s['demo_mode'] ); ?>> <?php esc_html_e( 'Show sample data instead of calling Vegvesen', 'bruktdeler-vegvesen' ); ?></label></td>
						</tr>
						<tr>
							<th scope="row"><label for="bv_cache"><?php esc_html_e( 'Cache, hours', 'bruktdeler-vegvesen' ); ?></label></th>
							<td><input type="number" min="1" id="bv_cache" name="<?php echo esc_attr( $name ); ?>[cache_hours]" value="<?php echo esc_attr( $s['cache_hours'] ); ?>" class="small-text">
								<p class="description"><?php esc_html_e( 'How long to keep the response for one number. Saves the daily request quota.', 'bruktdeler-vegvesen' ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="bv_rate"><?php esc_html_e( 'Search limit', 'bruktdeler-vegvesen' ); ?></label></th>
							<td><input type="number" min="0" id="bv_rate" name="<?php echo esc_attr( $name ); ?>[rate_limit]" value="<?php echo esc_attr( $s['rate_limit'] ); ?>" class="small-text"> <?php esc_html_e( 'new requests per minute from one IP address (0 = no limit)', 'bruktdeler-vegvesen' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="bv_ip"><?php esc_html_e( 'Visitor IP', 'bruktdeler-vegvesen' ); ?></label></th>
							<td>
								<select id="bv_ip" name="<?php echo esc_attr( $name ); ?>[ip_source]">
									<?php
									$bv_ip_sources = array(
										'remote'     => __( 'Direct connection (no proxy)', 'bruktdeler-vegvesen' ),
										'cloudflare' => __( 'Cloudflare (CF-Connecting-IP)', 'bruktdeler-vegvesen' ),
										'forwarded'  => __( 'Proxy or load balancer (X-Forwarded-For)', 'bruktdeler-vegvesen' ),
										'real_ip'    => __( 'Nginx proxy (X-Real-IP)', 'bruktdeler-vegvesen' ),
									);
									foreach ( $bv_ip_sources as $bv_val => $bv_label ) :
										?>
										<option value="<?php echo esc_attr( $bv_val ); ?>" <?php selected( $s['ip_source'], $bv_val ); ?>><?php echo esc_html( $bv_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( 'Behind a CDN or proxy every visitor arrives from the same address, so the search limit would stop everyone at once. Choose where the real address is. Choose a proxy only if the site really is behind one: otherwise visitors could fake their address.', 'bruktdeler-vegvesen' ); ?></p>
								<?php if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && 'cloudflare' !== $s['ip_source'] ) : // phpcs:ignore WordPress.Security.ValidatedSanitizedInput ?>
									<p class="bv-hint"><span class="dashicons dashicons-info-outline"></span> <?php esc_html_e( 'This site seems to be behind Cloudflare. Choose "Cloudflare".', 'bruktdeler-vegvesen' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="bv_daily"><?php esc_html_e( 'Daily limit', 'bruktdeler-vegvesen' ); ?></label></th>
							<td>
								<?php $bv_usage = BV_API::usage_today(); ?>
								<input type="number" min="0" id="bv_daily" name="<?php echo esc_attr( $name ); ?>[daily_limit]" value="<?php echo esc_attr( $s['daily_limit'] ); ?>" class="small-text" style="width:7em"> <?php esc_html_e( 'requests to Vegvesen per day (0 = no limit)', 'bruktdeler-vegvesen' ); ?>
								<p class="description">
									<?php
									/* translators: %s: number of requests made today. */
									printf( esc_html__( 'Vegvesen allows 50,000 per API key per day; keep a margin. Today so far: %s. Answers from the cache, demo data and search engines do not count.', 'bruktdeler-vegvesen' ), '<strong>' . esc_html( number_format_i18n( $bv_usage['n'] ) ) . '</strong>' );
									?>
								</p>
							</td>
						</tr>
					</table>
					<?php // Rarely changed: folded away. ?>
					<details class="bv-box__more">
						<summary><?php esc_html_e( 'Advanced: API address and authorization', 'bruktdeler-vegvesen' ); ?></summary>
						<p class="description"><?php esc_html_e( 'Change these only if Statens vegvesen changes its API.', 'bruktdeler-vegvesen' ); ?></p>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="bv_endpoint"><?php esc_html_e( 'API URL', 'bruktdeler-vegvesen' ); ?></label></th>
								<td><input type="url" id="bv_endpoint" name="<?php echo esc_attr( $name ); ?>[endpoint]" value="<?php echo esc_attr( $s['endpoint'] ); ?>" class="large-text code">
									<p class="description"><?php echo wp_kses( __( 'The <code>?kjennemerke=</code> or <code>?understellsnummer=</code> parameter is added automatically.', 'bruktdeler-vegvesen' ), $code ); ?></p></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Authorization header', 'bruktdeler-vegvesen' ); ?></th>
								<td>
									<input type="text" name="<?php echo esc_attr( $name ); ?>[auth_header]" value="<?php echo esc_attr( $s['auth_header'] ); ?>" class="regular-text code" placeholder="SVV-Authorization">
									<input type="text" name="<?php echo esc_attr( $name ); ?>[auth_prefix]" value="<?php echo esc_attr( $s['auth_prefix'] ); ?>" class="code" style="width:9em" placeholder="Apikey ">
									<p class="description">
										<?php
										/* translators: %s: the HTTP header that will be sent. */
										printf( esc_html__( 'Sent as: %s', 'bruktdeler-vegvesen' ), '<code>' . esc_html( $s['auth_header'] . ': ' . $s['auth_prefix'] ) . '&lt;key&gt;</code>' );
										?>
									</p>
								</td>
							</tr>
						</table>
					</details>
				</section>

				<section class="bv-box">
					<header class="bv-box__head">
						<h2><?php esc_html_e( 'Page and appearance', 'bruktdeler-vegvesen' ); ?></h2>
						<p><?php esc_html_e( 'Where the search leads and how the vehicle data looks.', 'bruktdeler-vegvesen' ); ?></p>
					</header>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="bv_page"><?php esc_html_e( 'Result page', 'bruktdeler-vegvesen' ); ?></label></th>
							<td>
								<?php wp_dropdown_pages( array( 'name' => $name . '[result_page]', 'id' => 'bv_page', 'selected' => (int) $s['result_page'], 'show_option_none' => __( '— not selected —', 'bruktdeler-vegvesen' ), 'option_none_value' => 0 ) ); ?>
								<?php if ( $page_id ) : ?>
									<a href="<?php echo esc_url( BV_Context::url( $s['preview_regnr'] ? $s['preview_regnr'] : 'AB12345' ) ); ?>" target="_blank" class="button button-small"><?php esc_html_e( 'View', 'bruktdeler-vegvesen' ); ?></a>
									<a href="<?php echo esc_url( get_edit_post_link( $page_id ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit page', 'bruktdeler-vegvesen' ); ?></a>
								<?php endif; ?>
								<p class="description">
									<?php
									/* translators: %s: example URL. */
									printf( esc_html__( 'The search field leads here. The address looks like %s. The page can be built in any page builder using the variables.', 'bruktdeler-vegvesen' ), '<code>' . esc_html( BV_Context::url( 'AB12345' ) ) . '</code>' );
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="bv_preview"><?php esc_html_e( 'Preview number for editors', 'bruktdeler-vegvesen' ); ?></label></th>
							<td><input type="text" id="bv_preview" name="<?php echo esc_attr( $name ); ?>[preview_regnr]" value="<?php echo esc_attr( $s['preview_regnr'] ); ?>" class="regular-text code">
								<p class="description"><?php esc_html_e( 'Inside page builders (WPBakery front-end editor, Elementor, Customizer) the variables show this vehicle when there is no number in the address, so you design with real data. On the normal site you see the same as visitors.', 'bruktdeler-vegvesen' ); ?>
									<?php echo wp_kses( __( 'To see the preview on the site, add <code>?bv_preview=1</code> to the address.', 'bruktdeler-vegvesen' ), $code ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="bv_color"><?php esc_html_e( 'Primary colour', 'bruktdeler-vegvesen' ); ?></label></th>
							<td><input type="text" id="bv_color" name="<?php echo esc_attr( $name ); ?>[primary_color]" value="<?php echo esc_attr( $s['primary_color'] ); ?>" class="bv-color code">
								<p class="description"><?php echo wp_kses( __( 'Empty uses the theme\'s primary colour (WoodMart: Theme Settings → Colors). Or enter your own, e.g. <code>#0b6e3a</code>.', 'bruktdeler-vegvesen' ), $code ); ?></p></td>
						</tr>
						<tr>
							<th scope="row"><label for="bv_warn"><?php esc_html_e( 'Inspection warning', 'bruktdeler-vegvesen' ); ?></label></th>
							<td><input type="number" min="0" id="bv_warn" name="<?php echo esc_attr( $name ); ?>[eu_warn_days]" value="<?php echo esc_attr( $s['eu_warn_days'] ); ?>" class="small-text"> <?php esc_html_e( 'days before the deadline – the row turns yellow', 'bruktdeler-vegvesen' ); ?></td>
						</tr>
					</table>
				</section>

				<section class="bv-box">
					<header class="bv-box__head">
						<h2><?php esc_html_e( 'After a search', 'bruktdeler-vegvesen' ); ?></h2>
						<p><?php esc_html_e( 'What the page does when a visitor has searched.', 'bruktdeler-vegvesen' ); ?></p>
					</header>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Scroll to the result', 'bruktdeler-vegvesen' ); ?></th>
							<td>
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[scroll]" value="1" <?php checked( $s['scroll'] ); ?>> <?php esc_html_e( 'Scroll the page smoothly to the result after a search', 'bruktdeler-vegvesen' ); ?></label>
								<p class="description"><?php echo wp_kses( __( 'Target: the element with the class <code>bv-scroll-target</code> (add it in "Extra class name" of a row or element). Without it: the first visible block with <code>bv-if-found</code> / <code>bv-if-notfound</code>, the vehicle card or another plugin block.', 'bruktdeler-vegvesen' ), $code ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="bv_scroll_offset"><?php esc_html_e( 'Space above the result', 'bruktdeler-vegvesen' ); ?></label></th>
							<td><input type="number" min="0" id="bv_scroll_offset" name="<?php echo esc_attr( $name ); ?>[scroll_offset]" value="<?php echo esc_attr( $s['scroll_offset'] ); ?>" class="small-text" placeholder="auto"> px
								<p class="description"><?php esc_html_e( 'Empty = automatic (height of a sticky header + a small gap).', 'bruktdeler-vegvesen' ); ?></p></td>
						</tr>
					</table>
				</section>

				<section class="bv-box">
					<header class="bv-box__head">
						<h2><?php esc_html_e( 'Access', 'bruktdeler-vegvesen' ); ?></h2>
						<p><?php esc_html_e( 'Which users see this plugin in the admin.', 'bruktdeler-vegvesen' ); ?></p>
					</header>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Who can see WP Vegvesen', 'bruktdeler-vegvesen' ); ?></th>
							<td>
								<?php $bv_can_edit_roles = current_user_can( 'manage_options' ); ?>
								<fieldset>
									<legend class="screen-reader-text"><?php esc_html_e( 'Who can see WP Vegvesen', 'bruktdeler-vegvesen' ); ?></legend>
									<?php if ( $bv_can_edit_roles ) : ?>
										<input type="hidden" name="<?php echo esc_attr( $name ); ?>[roles_sent]" value="1">
									<?php endif; ?>
									<?php foreach ( wp_roles()->get_names() as $bv_role => $bv_role_name ) : ?>
										<?php $bv_admin_role = 'administrator' === $bv_role; ?>
										<label class="bv-role">
											<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[roles][]" value="<?php echo esc_attr( $bv_role ); ?>"
												<?php checked( $bv_admin_role || in_array( $bv_role, (array) $s['roles'], true ) ); ?>
												<?php disabled( $bv_admin_role || ! $bv_can_edit_roles ); ?>>
											<?php echo esc_html( translate_user_role( $bv_role_name ) ); ?>
											<?php if ( $bv_admin_role ) : ?>
												<span class="description">(<?php esc_html_e( 'always', 'bruktdeler-vegvesen' ); ?>)</span>
											<?php endif; ?>
										</label>
									<?php endforeach; ?>
								</fieldset>
								<p class="description">
									<?php esc_html_e( 'The chosen roles see the WP Vegvesen menu with Settings and Variables. Visitors and the vehicle pages on the site are not affected.', 'bruktdeler-vegvesen' ); ?>
									<?php if ( ! $bv_can_edit_roles ) : ?>
										<br><?php esc_html_e( 'Only an administrator can change who has access.', 'bruktdeler-vegvesen' ); ?>
									<?php endif; ?>
								</p>
							</td>
						</tr>
					</table>
				</section>

				<section class="bv-box">
					<header class="bv-box__head">
						<h2><?php esc_html_e( 'Brand logos', 'bruktdeler-vegvesen' ); ?></h2>
						<p><?php esc_html_e( 'Logos shown next to the vehicle name.', 'bruktdeler-vegvesen' ); ?></p>
					</header>
					<div class="bv-box__body">
						<?php $bv_dirs = BV_Render::logo_dirs(); ?>
						<p>
							<?php
							/* translators: %d: number of logos bundled with the plugin. */
							printf( esc_html__( '%d logos are bundled with the plugin.', 'bruktdeler-vegvesen' ), (int) BV_Render::logo_count( 'bundled' ) );
							?>
						</p>
						<p class="description"><?php echo wp_kses( __( 'Your own logo: upload an image to the Media Library and set its title to "<strong>Make logo</strong>", e.g. "Volkswagen logo". It is used instead of the bundled logo.', 'bruktdeler-vegvesen' ), array( 'strong' => array() ) ); ?></p>
						<p class="description">
							<?php
							/* translators: 1: folder path, 2: number of files in it. */
							printf( esc_html__( 'Or put files in %1$s (now: %2$d), named after the make: volkswagen.png, mercedes-benz.png, land-rover.svg. They also take priority and survive plugin updates.', 'bruktdeler-vegvesen' ), '<code>' . esc_html( str_replace( wp_normalize_path( ABSPATH ), '', wp_normalize_path( $bv_dirs['custom']['path'] ) ) ) . '</code>', (int) BV_Render::logo_count( 'custom' ) );
							?>
						</p>
					</div>
				</section>

				<div class="bv-savebar">
					<?php submit_button( __( 'Save', 'bruktdeler-vegvesen' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>
		<style>
			.bv-settings { max-width: 980px; }
			.bv-overview { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0; margin: 16px 0; padding: 0; background: #fff; border: 1px solid #c3c4c7; }
			.bv-overview li { margin: 0; padding: 12px 16px; border-left: 1px solid #f0f0f1; }
			.bv-overview li:first-child { border-left: 0; }
			.bv-overview__label { display: block; color: #646970; font-size: 12px; }
			.bv-overview__value { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; }
			.bv-overview__value::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: #00a32a; }
			.bv-overview__value--warn::before { background: #dba617; }
			.bv-box { margin: 0 0 16px; background: #fff; border: 1px solid #c3c4c7; }
			.bv-box__head { padding: 14px 20px 12px; border-bottom: 1px solid #f0f0f1; }
			.bv-box__head h2 { margin: 0; font-size: 15px; }
			.bv-box__head p { margin: 2px 0 0; color: #646970; font-size: 11px; }
			.bv-settings .description, .bv-settings .description code { font-size: 11px; }
			.bv-box .form-table { margin: 0; }
			.bv-box .form-table th { padding-left: 20px; width: 200px; }
			.bv-box .form-table td { padding-right: 20px; }
			.bv-box__body { padding: 4px 20px 8px; }
			.bv-box__more { margin: 0; padding: 0 20px; border-top: 1px solid #f0f0f1; }
			.bv-box__more summary { padding: 12px 0; cursor: pointer; color: #2271b1; }
			.bv-box__more > .description { margin: 0 0 4px; }
			.bv-box__more .form-table th { padding-left: 0; }
			.bv-box__more .form-table td { padding-right: 0; }
			.bv-settings .form-table fieldset label.bv-role { display: block; margin: 0 0 6px !important; }
			.bv-hint { margin: 6px 0 0; color: #2271b1; font-size: 11px; }
			.bv-hint .dashicons { font-size: 16px; width: 16px; height: 16px; vertical-align: -3px; }
			.bv-savebar { position: sticky; bottom: 0; z-index: 10; margin: 0; padding: 12px 20px; background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 -2px 6px rgba(0, 0, 0, .04); }
			@media (max-width: 782px) { .bv-box .form-table th { padding-left: 16px; width: auto; } .bv-box .form-table td { padding: 0 16px 16px; } .bv-overview li { border-left: 0; border-top: 1px solid #f0f0f1; } .bv-overview li:first-child { border-top: 0; } }
		</style>
		<?php
	}

	public static function variables_page() {
		$regnr = isset( $_GET['regnr'] ) ? sanitize_text_field( wp_unslash( $_GET['regnr'] ) ) : BV_Settings::get( 'preview_regnr' ); // phpcs:ignore WordPress.Security.NonceVerification
		$fresh = ! empty( $_GET['fresh'] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification
		$l     = $regnr ? BV_API::lookup( $regnr, array( 'fresh' => $fresh, 'rate_limit' => false ) ) : null;
		$found = $l && 'funnet' === $l['status'];

		$groups = array();
		foreach ( BV_Fields::all() as $key => $f ) {
			$groups[ $f['group'] ][ $key ] = $f;
		}

		$blocks = array(
			'[bv_search]'                => __( 'Search field. Attributes: layout="hero|compact", title, title_tag="h2", subtitle, image (ID or URL), placeholder, button, max_width', 'bruktdeler-vegvesen' ),
			'[bv_card]'                  => __( 'The whole vehicle card. Attributes: tiles, general, dates, miljo (field lists), print="0", image="0", title_tag="h1" (vehicle name), table_title_tag="h4"', 'bruktdeler-vegvesen' ),
			'[bv_plate]'                 => __( 'Number plate. size="small|large"', 'bruktdeler-vegvesen' ),
			'[bv_status]'                => __( '"Vehicle found" + last updated date. found="your text", updated="0"', 'bruktdeler-vegvesen' ),
			'[bv_logo size="64"]'        => __( 'Brand logo: a media library image titled "Make logo" (e.g. "Volkswagen logo"), otherwise the bundled logo. Nothing is shown if there is no logo.', 'bruktdeler-vegvesen' ),
			'[bv_image]'                 => __( 'Vehicle picture: a media library image titled "Make Model" (e.g. "Volkswagen Passat"), otherwise a body type silhouette in the vehicle colour', 'bruktdeler-vegvesen' ),
			'[bv_print]'                 => __( 'Print button. label="your text"', 'bruktdeler-vegvesen' ),
			'[bv_tiles fields="registrert_aar,drivstoff,effekt,slagvolum"]' => __( 'Tiles with icons. columns="4"', 'bruktdeler-vegvesen' ),
			/* translators: %s: list of icon names. */
			'[bv_table fields="merke,modell,farge" title="…" icon="info"]' => sprintf( __( 'Table. highlight="neste_eu", labels="merke:Brand", hide_empty="0", title_tag="h2". Icons: %s', 'bruktdeler-vegvesen' ), implode( ', ', BV_Icons::names() ) ),
			'[bv_label field="egenvekt"]' => __( 'The field\'s label (e.g. Kerb weight)', 'bruktdeler-vegvesen' ),
		);
		$attrs = array(
			'suffix=" kg"'     => __( 'text after the value', 'bruktdeler-vegvesen' ),
			'before="Make: "'  => __( 'text before the value', 'bruktdeler-vegvesen' ),
			'unit="1"'         => __( 'add the standard unit (kg, kW, g/km…)', 'bruktdeler-vegvesen' ),
			'format="j M Y"'   => __( 'date format (month names follow the site language)', 'bruktdeler-vegvesen' ),
			'number="0"'       => __( 'number without thousands separator (1615 instead of 1 615)', 'bruktdeler-vegvesen' ),
			'decimals="1"'     => __( 'number of decimals', 'bruktdeler-vegvesen' ),
			'empty="—"'        => __( 'what to show when there is no data', 'bruktdeler-vegvesen' ),
			'regnr="AB12345"'  => __( 'show a specific vehicle instead of the one in the address', 'bruktdeler-vegvesen' ),
		);
		$conds = array(
			'[bv_if field="co2"]…[/bv_if]'                          => __( 'show if the field has a value', 'bruktdeler-vegvesen' ),
			'[bv_if field="co2" empty="1"]…[/bv_if]'                => __( 'show if the field is empty', 'bruktdeler-vegvesen' ),
			'[bv_if field="eu_status" is="utlopt,snart"]…[/bv_if]'  => __( 'the value is one of the list', 'bruktdeler-vegvesen' ),
			'[bv_if field="drivstoff" not="Elektrisk"]…[/bv_if]'    => __( 'the value is not', 'bruktdeler-vegvesen' ),
			'[bv_if status="funnet"]…[/bv_if]'                      => __( 'search status: funnet (found), ikke_funnet (not found), ugyldig (invalid), feil (error), ingen (no number entered)', 'bruktdeler-vegvesen' ),
		);
		$code = array( 'code' => array(), 'strong' => array() );
		?>
		<div class="wrap bv-admin">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Variables for pages', 'bruktdeler-vegvesen' ); ?></h1>
			<?php echo self::guide_link(); // phpcs:ignore -- escaped inside ?>
			<hr class="wp-header-end">
			<p><?php esc_html_e( 'On the site, variables are replaced with data for the vehicle whose number is in the page address. There are two ways to insert them:', 'bruktdeler-vegvesen' ); ?></p>
			<ul style="list-style:disc;margin-left:20px">
				<li><?php echo wp_kses( __( '<strong>Shortcode <code>[bv field="merke"]</code></strong> – in large text fields with an editor: Text Block, Info Box content, HTML Blocks, Gutenberg.', 'bruktdeler-vegvesen' ), $code ); ?></li>
				<li><?php echo wp_kses( __( '<strong>Token <code>{bv:merke}</code></strong> – in one-line fields where a shortcode does not work: WoodMart Title, Info Box title, button text, List items. Also works in text blocks.', 'bruktdeler-vegvesen' ), $code ); ?>
					<?php echo wp_kses( __( 'Variants: <code>{bv:egenvekt|unit}</code> → with unit, <code>{bv:neste_eu|j M Y}</code> → custom date format, <code>{bv:label:egenvekt}</code> → field label.', 'bruktdeler-vegvesen' ), $code ); ?></li>
			</ul>

			<form method="get" class="bv-admin__test">
				<input type="hidden" name="page" value="bv-variables">
				<label><strong><?php esc_html_e( 'Test number:', 'bruktdeler-vegvesen' ); ?></strong> <input type="text" name="regnr" value="<?php echo esc_attr( $regnr ); ?>" class="regular-text code"></label>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<label><input type="checkbox" name="fresh" value="1"> <?php esc_html_e( 'skip cache', 'bruktdeler-vegvesen' ); ?></label>
				<?php endif; ?>
				<?php submit_button( __( 'Check', 'bruktdeler-vegvesen' ), 'secondary', '', false ); ?>
				<?php if ( $l ) : ?>
					<span class="bv-admin__status bv-admin__status--<?php echo esc_attr( $l['status'] ); ?>">
						<?php echo esc_html( $l['status'] . ( $l['error'] ? ': ' . $l['error'] : '' ) . ( $l['demo'] ? ' (' . __( 'demo', 'bruktdeler-vegvesen' ) . ')' : '' ) ); ?>
					</span>
				<?php endif; ?>
			</form>

			<h2><?php esc_html_e( 'Field values', 'bruktdeler-vegvesen' ); ?></h2>
			<table class="widefat striped bv-admin__table">
				<thead><tr>
					<th class="bv-iconcell"><span title="<?php esc_attr_e( 'Icon in tiles. Click to change.', 'bruktdeler-vegvesen' ); ?>"><?php esc_html_e( 'Icon', 'bruktdeler-vegvesen' ); ?></span></th>
					<th><?php esc_html_e( 'Shortcode (text blocks)', 'bruktdeler-vegvesen' ); ?></th>
					<th><?php esc_html_e( 'Token (titles, buttons, lists)', 'bruktdeler-vegvesen' ); ?></th>
					<th><?php esc_html_e( 'Description', 'bruktdeler-vegvesen' ); ?></th>
					<th><?php esc_html_e( 'Example', 'bruktdeler-vegvesen' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $groups as $group => $fields ) : ?>
					<tr class="bv-admin__group"><th colspan="5"><?php echo esc_html( $group ); ?></th></tr>
					<?php foreach ( $fields as $key => $f ) : ?>
						<?php $bv_icon = self::icon_button_state( $key ); ?>
						<tr>
							<td class="bv-iconcell">
								<button type="button" class="bv-iconbtn<?php echo $bv_icon['changed'] ? ' is-changed' : ''; ?><?php echo $bv_icon['generic'] ? ' is-generic' : ''; ?><?php echo 'none' === $bv_icon['icon'] ? ' is-none' : ''; ?>"
									data-field="<?php echo esc_attr( $key ); ?>" data-icon="<?php echo esc_attr( $bv_icon['icon'] ); ?>"
									title="<?php echo esc_attr( sprintf( /* translators: %s: field label. */ __( 'Icon for "%s" – click to change', 'bruktdeler-vegvesen' ), $f['label'] ) ); ?>">
									<?php echo $bv_icon['html']; // phpcs:ignore -- built-in SVG ?>
								</button>
							</td>
							<td><code class="bv-copy" title="<?php esc_attr_e( 'Click to copy', 'bruktdeler-vegvesen' ); ?>"><?php echo esc_html( '[bv field="' . $key . '"]' ); ?></code></td>
							<td><code class="bv-copy" title="<?php esc_attr_e( 'Click to copy', 'bruktdeler-vegvesen' ); ?>"><?php echo esc_html( '{bv:' . $key . '}' ); ?></code></td>
							<td><?php echo esc_html( $f['desc'] ?? '' ); ?> <span class="description">— <?php echo esc_html( $f['label'] ); ?></span></td>
							<td><?php
								if ( 'status' === $key && $l ) {
									echo esc_html( $l['status'] );
								} elseif ( $found && isset( $l['data'][ $key ] ) ) {
									echo esc_html( BV_Fields::format( $key, $l['data'][ $key ] ) );
								} else {
									echo '<span class="description">—</span>';
								}
							?></td>
						</tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php echo wp_kses( __( 'Attributes for <code>[bv]</code>', 'bruktdeler-vegvesen' ), $code ); ?></h2>
			<p><?php esc_html_e( 'Example:', 'bruktdeler-vegvesen' ); ?> <code class="bv-copy">[bv field="egenvekt" suffix=" kg" empty="—"]</code></p>
			<table class="widefat striped bv-admin__table"><tbody>
				<?php foreach ( $attrs as $c => $desc ) : ?>
					<tr><td><code><?php echo esc_html( $c ); ?></code></td><td><?php echo esc_html( $desc ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>

			<h2><?php esc_html_e( 'Conditions', 'bruktdeler-vegvesen' ); ?></h2>
			<table class="widefat striped bv-admin__table"><tbody>
				<?php foreach ( $conds as $c => $desc ) : ?>
					<tr><td><code class="bv-copy"><?php echo esc_html( $c ); ?></code></td><td><?php echo esc_html( $desc ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>

			<h2><?php esc_html_e( 'Show or hide elements', 'bruktdeler-vegvesen' ); ?></h2>
			<p><?php esc_html_e( 'Add one of these classes to "Extra class name" of any row, column or element (e.g. WoodMart List). Works for whole sections, unlike [bv_if], which only works inside one text field.', 'bruktdeler-vegvesen' ); ?></p>
			<table class="widefat striped bv-admin__table"><tbody>
				<tr><td><code class="bv-copy">bv-if-found</code></td><td><?php esc_html_e( 'visible only when a vehicle is found', 'bruktdeler-vegvesen' ); ?></td></tr>
				<tr><td><code class="bv-copy">bv-if-notfound</code></td><td><?php esc_html_e( 'visible only when the search found nothing or failed', 'bruktdeler-vegvesen' ); ?></td></tr>
				<tr><td><code class="bv-copy">bv-if-empty</code></td><td><?php esc_html_e( 'visible only before a search (no number in the address)', 'bruktdeler-vegvesen' ); ?></td></tr>
				<tr><td><code class="bv-copy">bv-if-searched</code></td><td><?php esc_html_e( 'visible after any search, found or not', 'bruktdeler-vegvesen' ); ?></td></tr>
				<tr><td><code class="bv-copy">bv-scroll-target</code></td><td><?php esc_html_e( 'after a search the page scrolls to this element (see Settings → After a search)', 'bruktdeler-vegvesen' ); ?></td></tr>
			</tbody></table>
			<p class="description"><?php esc_html_e( 'In the WPBakery front-end editor all elements stay visible, so they can be edited.', 'bruktdeler-vegvesen' ); ?></p>

			<h2><?php esc_html_e( 'Ready-made blocks', 'bruktdeler-vegvesen' ); ?></h2>
			<p><?php echo wp_kses( __( 'Also available in WPBakery (category <strong>Vegvesen</strong>) with settings and a Design Options tab.', 'bruktdeler-vegvesen' ), $code ); ?></p>
			<table class="widefat striped bv-admin__table"><tbody>
				<?php foreach ( $blocks as $c => $desc ) : ?>
					<tr><td><code class="bv-copy"><?php echo esc_html( $c ); ?></code></td><td><?php echo esc_html( $desc ); ?></td></tr>
				<?php endforeach; ?>
			</tbody></table>

			<h2><?php esc_html_e( 'Design', 'bruktdeler-vegvesen' ); ?></h2>
			<p><?php esc_html_e( 'Colours and fonts come from the theme (WoodMart: Theme Settings). You can change them in the theme\'s Custom CSS, for example:', 'bruktdeler-vegvesen' ); ?></p>
			<pre class="bv-admin__pre">.bv {
  --bv-primary: #0b6e3a;   /* buttons, icons */
  --bv-radius: 14px;       /* card corner radius */
  --bv-surface: #f5f6f7;   /* tile background */
}</pre>
			<p><?php echo wp_kses( __( 'Templates can be overridden by copying a file from <code>wp-content/plugins/bruktdeler-vegvesen/templates/</code> to <code>wp-content/themes/your-child-theme/bruktdeler-vegvesen/</code>.', 'bruktdeler-vegvesen' ), $code ); ?></p>

			<?php if ( $l && current_user_can( 'manage_options' ) && ! empty( $l['raw'] ) ) : ?>
				<details class="bv-admin__raw">
					<summary><?php esc_html_e( 'Raw API response (for checking fields)', 'bruktdeler-vegvesen' ); ?></summary>
					<pre class="bv-admin__pre"><?php echo esc_html( wp_json_encode( $l['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></pre>
				</details>
			<?php endif; ?>
		</div>
		<dialog class="bv-iconpick" data-nonce="<?php echo esc_attr( wp_create_nonce( 'bv_field_icon' ) ); ?>" data-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">
			<form method="dialog">
				<header class="bv-iconpick__head">
					<strong class="bv-iconpick__title"></strong>
					<button type="submit" class="bv-iconpick__close" aria-label="<?php esc_attr_e( 'Close', 'bruktdeler-vegvesen' ); ?>"><span class="dashicons dashicons-no-alt"></span></button>
				</header>
				<div class="bv-iconpick__body">
					<?php foreach ( BV_Icons::groups() as $bv_group => $bv_names ) : ?>
						<?php if ( $bv_names ) : ?>
							<p class="bv-iconpick__group"><?php echo esc_html( $bv_group ); ?></p>
							<div class="bv-iconpick__grid">
								<?php foreach ( $bv_names as $bv_name ) : ?>
									<button type="button" data-icon="<?php echo esc_attr( $bv_name ); ?>" title="<?php echo esc_attr( $bv_name ); ?>"><?php echo BV_Icons::get( $bv_name ); // phpcs:ignore ?></button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<footer class="bv-iconpick__foot">
					<button type="button" class="button" data-icon="none"><?php esc_html_e( 'No icon', 'bruktdeler-vegvesen' ); ?></button>
					<button type="button" class="button" data-icon=""><?php esc_html_e( 'Reset to default', 'bruktdeler-vegvesen' ); ?></button>
					<span class="bv-iconpick__msg" role="status"></span>
				</footer>
			</form>
		</dialog>
		<style>
			.bv-iconcell{width:44px;text-align:center}
			.bv-iconbtn{position:relative;display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;padding:0;background:#fff;border:1px solid #dcdcde;border-radius:4px;color:#1d2327;cursor:pointer}
			.bv-iconbtn:hover,.bv-iconbtn:focus{border-color:#2271b1;color:#2271b1}
			.bv-iconbtn .bv-icon{width:20px;height:20px}
			.bv-iconbtn.is-generic{color:#a7aaad}
			.bv-iconbtn.is-none{border-style:dashed}
			.bv-iconbtn.is-changed::after{content:"";position:absolute;top:-3px;right:-3px;width:7px;height:7px;border-radius:50%;background:#2271b1;border:1px solid #fff}
			.bv-iconpick{width:min(560px,92vw);padding:0;border:1px solid #c3c4c7;border-radius:4px;box-shadow:0 8px 30px rgba(0,0,0,.15)}
			.bv-iconpick::backdrop{background:rgba(0,0,0,.35)}
			.bv-iconpick__head,.bv-iconpick__foot{display:flex;align-items:center;gap:8px;padding:10px 16px;border-bottom:1px solid #f0f0f1}
			.bv-iconpick__foot{border-top:1px solid #f0f0f1;border-bottom:0}
			.bv-iconpick__title{flex:1;font-size:14px}
			.bv-iconpick__close{background:none;border:0;cursor:pointer;color:#646970}
			.bv-iconpick__body{max-height:60vh;overflow:auto;padding:4px 16px 12px}
			.bv-iconpick__group{margin:12px 0 6px;color:#646970;font-size:11px;text-transform:uppercase;letter-spacing:.04em}
			.bv-iconpick__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(40px,1fr));gap:6px}
			.bv-iconpick__grid button{display:flex;align-items:center;justify-content:center;height:40px;background:#fff;border:1px solid #dcdcde;border-radius:4px;color:#1d2327;cursor:pointer}
			.bv-iconpick__grid button:hover,.bv-iconpick__grid button:focus{border-color:#2271b1;color:#2271b1}
			.bv-iconpick__grid button.is-current{border-color:#2271b1;box-shadow:inset 0 0 0 1px #2271b1;color:#2271b1}
			.bv-iconpick__grid .bv-icon{width:22px;height:22px}
			.bv-iconpick__msg{margin-left:auto;color:#646970;font-size:11px}
			.bv-admin__test{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin:16px 0 8px;padding:12px 16px;background:#fff;border:1px solid #c3c4c7}
			.bv-admin__status{padding:3px 10px;border-radius:12px;background:#f0f0f1}
			.bv-admin__status--funnet{background:#d7f0dd;color:#14532d}
			.bv-admin__status--feil,.bv-admin__status--ugyldig{background:#fbe0e0;color:#8a1f1f}
			.bv-admin__table{max-width:1200px;margin-bottom:8px}
			.bv-admin__group th{background:#f6f7f7;font-weight:600}
			.bv-copy{cursor:pointer;user-select:all}
			.bv-copy.is-copied{background:#d7f0dd}
			.bv-toast{position:fixed;z-index:100000;padding:5px 10px;background:#1d2327;color:#fff;font-size:12px;line-height:1.4;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.2);pointer-events:none;opacity:0;transform:translate(-50%,4px);transition:opacity .15s,transform .15s;white-space:nowrap}
			.bv-toast.is-visible{opacity:1;transform:translate(-50%,0)}
			.bv-toast::after{content:"";position:absolute;left:50%;top:100%;margin-left:-5px;border:5px solid transparent;border-top-color:#1d2327}
			.bv-admin__pre{max-width:1200px;padding:12px 16px;background:#fff;border:1px solid #c3c4c7;overflow:auto;max-height:520px}
			.bv-admin__raw{margin-top:24px}
		</style>
		<script>
			// Click a shortcode or token to copy it; a small "Copied" bubble appears above it.
			(function () {
				var toast = null, timer = null;
				var texts = <?php echo wp_json_encode( array( 'ok' => __( 'Copied', 'bruktdeler-vegvesen' ), 'fail' => __( 'Press Ctrl+C to copy', 'bruktdeler-vegvesen' ) ) ); ?>;

				function show(el, text) {
					if (!toast) {
						toast = document.createElement('div');
						toast.className = 'bv-toast';
						toast.setAttribute('role', 'status');
						document.body.appendChild(toast);
					}
					var r = el.getBoundingClientRect();
					toast.textContent = text;
					toast.style.left = (r.left + r.width / 2) + 'px';
					toast.style.top = (r.top - 34) + 'px';
					toast.classList.add('is-visible');
					clearTimeout(timer);
					timer = setTimeout(function () { toast.classList.remove('is-visible'); }, 1200);
				}

				// The Clipboard API only works on https (and localhost); on plain http use the older way.
				function copy(text) {
					if (navigator.clipboard && window.isSecureContext) {
						return navigator.clipboard.writeText(text);
					}
					return new Promise(function (resolve, reject) {
						var ta = document.createElement('textarea');
						ta.value = text;
						ta.setAttribute('readonly', '');
						ta.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
						document.body.appendChild(ta);
						ta.select();
						var ok = false;
						try { ok = document.execCommand('copy'); } catch (err) {}
						document.body.removeChild(ta);
						ok ? resolve() : reject();
					});
				}

				document.addEventListener('click', function (e) {
					var el = e.target.closest('.bv-copy');
					if (!el) return;
					copy(el.textContent).then(function () {
						el.classList.add('is-copied');
						setTimeout(function () { el.classList.remove('is-copied'); }, 900);
						show(el, texts.ok);
					}, function () {
						// Copying blocked: the text is selected (user-select: all), so Ctrl+C works.
						show(el, texts.fail);
					});
				});
				window.addEventListener('scroll', function () { if (toast) toast.classList.remove('is-visible'); }, { passive: true });
			})();

			// Field icons: open the picker from a row's icon button, save the choice at once.
			(function () {
				var dlg = document.querySelector('.bv-iconpick');
				if (!dlg || !dlg.showModal) return;
				var title = dlg.querySelector('.bv-iconpick__title'), msg = dlg.querySelector('.bv-iconpick__msg'), current = null;
				var titleTpl = <?php echo wp_json_encode( __( 'Icon for "%s"', 'bruktdeler-vegvesen' ) ); ?>;
				document.addEventListener('click', function (e) {
					var btn = e.target.closest('.bv-iconbtn');
					if (!btn) return;
					current = btn;
					title.textContent = titleTpl.replace('%s', btn.closest('tr').querySelector('td:nth-child(4) .description').textContent.replace(/^—\s*/, ''));
					msg.textContent = '';
					dlg.querySelectorAll('.bv-iconpick__grid button').forEach(function (b) {
						b.classList.toggle('is-current', b.dataset.icon === btn.dataset.icon);
					});
					dlg.showModal();
				});
				dlg.addEventListener('click', function (e) {
					var b = e.target.closest('button[data-icon]');
					if (!b || !current) return;
					var body = new URLSearchParams({ action: 'bv_field_icon', _ajax_nonce: dlg.dataset.nonce, field: current.dataset.field, icon: b.dataset.icon });
					msg.textContent = <?php echo wp_json_encode( __( 'Saving…', 'bruktdeler-vegvesen' ) ); ?>;
					fetch(dlg.dataset.ajax, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) { return r.json(); }).then(function (res) {
						if (!res || !res.success) throw new Error();
						var s = res.data;
						current.innerHTML = s.html;
						current.dataset.icon = s.icon;
						current.classList.toggle('is-changed', s.changed);
						current.classList.toggle('is-generic', s.generic);
						current.classList.toggle('is-none', s.icon === 'none');
						dlg.close();
						current.focus();
					}).catch(function () {
						msg.textContent = <?php echo wp_json_encode( __( 'Could not save. Reload the page and try again.', 'bruktdeler-vegvesen' ) ); ?>;
					});
				});
			})();
		</script>
		<?php
	}
}
