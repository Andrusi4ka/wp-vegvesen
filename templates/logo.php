<?php
/**
 * Brand logo. Override: {theme}/bruktdeler-vegvesen/logo.php
 *
 * @var string $url   Logo URL.
 * @var string $alt   Make (used as alt text).
 * @var int    $size  Max height/width in px.
 * @var string $class Wrapper classes (escaped).
 */
defined( 'ABSPATH' ) || exit;
?>
<span class="<?php echo $class; // phpcs:ignore ?>" style="--bv-logo-size:<?php echo (int) $size; ?>px">
	<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy">
</span>
