<?php
defined( 'ABSPATH' ) || exit;

/** Admin: settings page + variables reference with live test lookup. */
class BV_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
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
		add_menu_page( 'Vegvesen', 'Vegvesen', 'manage_options', 'bv-settings', array( __CLASS__, 'settings_page' ), 'dashicons-car', 58 );
		add_submenu_page( 'bv-settings', __( 'Settings', 'bruktdeler-vegvesen' ), __( 'Settings', 'bruktdeler-vegvesen' ), 'manage_options', 'bv-settings', array( __CLASS__, 'settings_page' ) );
		add_submenu_page( 'bv-settings', __( 'Variables', 'bruktdeler-vegvesen' ), __( 'Variables', 'bruktdeler-vegvesen' ), 'edit_posts', 'bv-variables', array( __CLASS__, 'variables_page' ) );
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
		$out['preview_regnr'] = strtoupper( sanitize_text_field( $in['preview_regnr'] ?? '' ) );
		$out['eu_warn_days']  = absint( $in['eu_warn_days'] ?? 60 );
		$out['primary_color'] = sanitize_hex_color( $in['primary_color'] ?? '' ) ?: '';
		$out['scroll']        = empty( $in['scroll'] ) ? 0 : 1;
		$out['scroll_offset'] = isset( $in['scroll_offset'] ) && '' !== trim( (string) $in['scroll_offset'] ) ? (string) absint( $in['scroll_offset'] ) : '';
		return $out;
	}

	public static function settings_page() {
		$s       = BV_Settings::all();
		$name    = BV_Settings::OPTION;
		$const   = defined( 'BV_API_KEY' ) && BV_API_KEY;
		$has_key = '' !== BV_Settings::api_key();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Vegvesen – settings', 'bruktdeler-vegvesen' ); ?></h1>

			<?php if ( BV_Settings::is_demo() ) : ?>
				<div class="notice notice-warning inline"><p>
					<strong><?php esc_html_e( 'Demo mode is on.', 'bruktdeler-vegvesen' ); ?></strong>
					<?php esc_html_e( 'The site shows sample data (Volkswagen Passat) for any number. The number XX00000 simulates "not found". To get real data, add an API key and turn off demo mode.', 'bruktdeler-vegvesen' ); ?>
				</p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'bv' ); ?>

				<h2 class="title"><?php esc_html_e( 'Statens vegvesen API', 'bruktdeler-vegvesen' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bv_key"><?php esc_html_e( 'API key', 'bruktdeler-vegvesen' ); ?></label></th>
						<td>
							<?php if ( $const ) : ?>
								<p><span class="dashicons dashicons-yes" style="color:#1e8a4c"></span> <?php echo wp_kses( __( 'The key is set with the <code>BV_API_KEY</code> constant in <code>wp-config.php</code>.', 'bruktdeler-vegvesen' ), array( 'code' => array() ) ); ?></p>
							<?php else : ?>
								<input type="password" id="bv_key" name="<?php echo esc_attr( $name ); ?>[api_key]" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo $has_key ? esc_attr__( '•••••••• (saved)', 'bruktdeler-vegvesen' ) : ''; ?>">
								<?php if ( $has_key ) : ?>
									<label style="margin-left:8px"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[api_key_clear]" value="1"> <?php esc_html_e( 'remove', 'bruktdeler-vegvesen' ); ?></label>
								<?php endif; ?>
								<p class="description"><?php echo wp_kses( __( 'It is safer to set the key in <code>wp-config.php</code>: <code>define( \'BV_API_KEY\', \'your-key\' );</code> – then it is not stored in the database.', 'bruktdeler-vegvesen' ), array( 'code' => array() ) ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Demo mode', 'bruktdeler-vegvesen' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[demo_mode]" value="1" <?php checked( $s['demo_mode'] ); ?>> <?php esc_html_e( 'Show sample data instead of calling Vegvesen', 'bruktdeler-vegvesen' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="bv_endpoint"><?php esc_html_e( 'API URL', 'bruktdeler-vegvesen' ); ?></label></th>
						<td><input type="url" id="bv_endpoint" name="<?php echo esc_attr( $name ); ?>[endpoint]" value="<?php echo esc_attr( $s['endpoint'] ); ?>" class="large-text code">
							<p class="description"><?php echo wp_kses( __( 'The <code>?kjennemerke=</code> or <code>?understellsnummer=</code> parameter is added automatically.', 'bruktdeler-vegvesen' ), array( 'code' => array() ) ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Authorization header', 'bruktdeler-vegvesen' ); ?></th>
						<td>
							<input type="text" name="<?php echo esc_attr( $name ); ?>[auth_header]" value="<?php echo esc_attr( $s['auth_header'] ); ?>" class="regular-text code" placeholder="SVV-Authorization">
							<input type="text" name="<?php echo esc_attr( $name ); ?>[auth_prefix]" value="<?php echo esc_attr( $s['auth_prefix'] ); ?>" class="small-text code" placeholder="Apikey ">
							<p class="description">
								<?php
								/* translators: %s: the HTTP header that will be sent. */
								printf( esc_html__( 'Sent as: %s', 'bruktdeler-vegvesen' ), '<code>' . esc_html( $s['auth_header'] . ': ' . $s['auth_prefix'] ) . '&lt;key&gt;</code>' );
								?>
							</p>
						</td>
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
				</table>

				<h2 class="title"><?php esc_html_e( 'Page and appearance', 'bruktdeler-vegvesen' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="bv_page"><?php esc_html_e( 'Result page', 'bruktdeler-vegvesen' ); ?></label></th>
						<td>
							<?php wp_dropdown_pages( array( 'name' => $name . '[result_page]', 'id' => 'bv_page', 'selected' => (int) $s['result_page'], 'show_option_none' => __( '— not selected —', 'bruktdeler-vegvesen' ), 'option_none_value' => 0 ) ); ?>
							<?php if ( BV_Settings::result_page_id() ) : ?>
								<a href="<?php echo esc_url( BV_Context::url( $s['preview_regnr'] ? $s['preview_regnr'] : 'AB12345' ) ); ?>" target="_blank" class="button button-small"><?php esc_html_e( 'View', 'bruktdeler-vegvesen' ); ?></a>
								<a href="<?php echo esc_url( get_edit_post_link( BV_Settings::result_page_id() ) ); ?>" class="button button-small"><?php esc_html_e( 'Edit page', 'bruktdeler-vegvesen' ); ?></a>
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
								<?php echo wp_kses( __( 'To see the preview on the site, add <code>?bv_preview=1</code> to the address.', 'bruktdeler-vegvesen' ), array( 'code' => array() ) ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="bv_warn"><?php esc_html_e( 'Inspection warning', 'bruktdeler-vegvesen' ); ?></label></th>
						<td><input type="number" min="0" id="bv_warn" name="<?php echo esc_attr( $name ); ?>[eu_warn_days]" value="<?php echo esc_attr( $s['eu_warn_days'] ); ?>" class="small-text"> <?php esc_html_e( 'days before the deadline – the row turns yellow', 'bruktdeler-vegvesen' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="bv_color"><?php esc_html_e( 'Primary colour', 'bruktdeler-vegvesen' ); ?></label></th>
						<td><input type="text" id="bv_color" name="<?php echo esc_attr( $name ); ?>[primary_color]" value="<?php echo esc_attr( $s['primary_color'] ); ?>" class="regular-text code" placeholder="<?php esc_attr_e( 'empty = theme colour', 'bruktdeler-vegvesen' ); ?>">
							<p class="description"><?php echo wp_kses( __( 'Empty uses the theme\'s primary colour (WoodMart: Theme Settings → Colors). Or enter your own, e.g. <code>#0b6e3a</code>.', 'bruktdeler-vegvesen' ), array( 'code' => array() ) ); ?></p></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'After a search', 'bruktdeler-vegvesen' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Scroll to the result', 'bruktdeler-vegvesen' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[scroll]" value="1" <?php checked( $s['scroll'] ); ?>> <?php esc_html_e( 'Scroll the page smoothly to the result after a search', 'bruktdeler-vegvesen' ); ?></label>
							<p class="description"><?php echo wp_kses( __( 'Target: the element with the class <code>bv-scroll-target</code> (add it in "Extra class name" of a row or element). Without it: the first visible block with <code>bv-if-found</code> / <code>bv-if-notfound</code>, the vehicle card or another plugin block.', 'bruktdeler-vegvesen' ), array( 'code' => array() ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bv_scroll_offset"><?php esc_html_e( 'Space above the result', 'bruktdeler-vegvesen' ); ?></label></th>
						<td><input type="number" min="0" id="bv_scroll_offset" name="<?php echo esc_attr( $name ); ?>[scroll_offset]" value="<?php echo esc_attr( $s['scroll_offset'] ); ?>" class="small-text" placeholder="auto"> px
							<p class="description"><?php esc_html_e( 'Empty = automatic (height of a sticky header + a small gap).', 'bruktdeler-vegvesen' ); ?></p></td>
					</tr>
				</table>

				<h2 class="title"><?php esc_html_e( 'Brand logos', 'bruktdeler-vegvesen' ); ?></h2>
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

				<?php submit_button( __( 'Save', 'bruktdeler-vegvesen' ) ); ?>
			</form>
		</div>
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
			'[bv_search]'                => __( 'Search field. Attributes: layout="hero|compact", title, subtitle, image (ID or URL), placeholder, button, max_width', 'bruktdeler-vegvesen' ),
			'[bv_card]'                  => __( 'The whole vehicle card. Attributes: tiles, general, dates, miljo (field lists), print="0", image="0"', 'bruktdeler-vegvesen' ),
			'[bv_plate]'                 => __( 'Number plate. size="small|large"', 'bruktdeler-vegvesen' ),
			'[bv_status]'                => __( '"Vehicle found" + last updated date. found="your text", updated="0"', 'bruktdeler-vegvesen' ),
			'[bv_logo size="64"]'        => __( 'Brand logo: a media library image titled "Make logo" (e.g. "Volkswagen logo"), otherwise the bundled logo. Nothing is shown if there is no logo.', 'bruktdeler-vegvesen' ),
			'[bv_image]'                 => __( 'Vehicle picture: a media library image titled "Make Model" (e.g. "Volkswagen Passat"), otherwise a body type silhouette in the vehicle colour', 'bruktdeler-vegvesen' ),
			'[bv_print]'                 => __( 'Print button. label="your text"', 'bruktdeler-vegvesen' ),
			'[bv_tiles fields="registrert_aar,drivstoff,effekt,slagvolum"]' => __( 'Tiles with icons. columns="4"', 'bruktdeler-vegvesen' ),
			/* translators: %s: list of icon names. */
			'[bv_table fields="merke,modell,farge" title="…" icon="info"]' => sprintf( __( 'Table. highlight="neste_eu", labels="merke:Brand", hide_empty="0". Icons: %s', 'bruktdeler-vegvesen' ), implode( ', ', BV_Icons::names() ) ),
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
			<h1><?php esc_html_e( 'Variables for pages', 'bruktdeler-vegvesen' ); ?></h1>
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
					<th><?php esc_html_e( 'Shortcode (text blocks)', 'bruktdeler-vegvesen' ); ?></th>
					<th><?php esc_html_e( 'Token (titles, buttons, lists)', 'bruktdeler-vegvesen' ); ?></th>
					<th><?php esc_html_e( 'Description', 'bruktdeler-vegvesen' ); ?></th>
					<th><?php esc_html_e( 'Example', 'bruktdeler-vegvesen' ); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $groups as $group => $fields ) : ?>
					<tr class="bv-admin__group"><th colspan="4"><?php echo esc_html( $group ); ?></th></tr>
					<?php foreach ( $fields as $key => $f ) : ?>
						<tr>
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
		<style>
			.bv-admin__test{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin:16px 0 8px;padding:12px 16px;background:#fff;border:1px solid #c3c4c7}
			.bv-admin__status{padding:3px 10px;border-radius:12px;background:#f0f0f1}
			.bv-admin__status--funnet{background:#d7f0dd;color:#14532d}
			.bv-admin__status--feil,.bv-admin__status--ugyldig{background:#fbe0e0;color:#8a1f1f}
			.bv-admin__table{max-width:1200px;margin-bottom:8px}
			.bv-admin__group th{background:#f6f7f7;font-weight:600}
			.bv-copy{cursor:pointer;user-select:all}
			.bv-copy.is-copied{background:#d7f0dd}
			.bv-admin__pre{max-width:1200px;padding:12px 16px;background:#fff;border:1px solid #c3c4c7;overflow:auto;max-height:520px}
			.bv-admin__raw{margin-top:24px}
		</style>
		<script>
			document.addEventListener('click', function (e) {
				var el = e.target.closest('.bv-copy');
				if (!el || !navigator.clipboard) return;
				navigator.clipboard.writeText(el.textContent).then(function () {
					el.classList.add('is-copied');
					setTimeout(function () { el.classList.remove('is-copied'); }, 900);
				});
			});
		</script>
		<?php
	}
}
