<?php
/**
 * Vehicle picture: media item titled "Merke Modell" (e.g. "Volkswagen Passat") or "Merke", else a silhouette.
 * Override: {theme}/bruktdeler-vegvesen/image.php
 *
 * @var string $url   Image URL ('' = silhouette).
 * @var string $alt   Alt text.
 * @var string $class Wrapper classes (escaped).
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="<?php echo $class; // phpcs:ignore ?><?php echo $url ? '' : ' bv-image--placeholder'; ?>">
	<?php if ( $url ) : ?>
		<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy">
	<?php else : ?>
		<?php echo BV_Render::silhouette(); // phpcs:ignore ?>
	<?php endif; ?>
</div>
