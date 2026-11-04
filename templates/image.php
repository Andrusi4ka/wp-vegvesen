<?php
/**
 * Vehicle picture: media item titled "Merke Modell" (e.g. "Volkswagen Passat") or "Merke",
 * else a silhouette of the body type (sedan, estate, van…) in the vehicle's colour.
 * Override: {theme}/bruktdeler-vegvesen/image.php
 *
 * @var string $url   Image URL ('' = silhouette).
 * @var string $alt   Alt text.
 * @var string $class Wrapper classes (escaped).
 * @var array  $data  Vehicle data.
 * @var string $type  Silhouette slug (sedan, estate, van…).
 * @var string $color HEX colour from Vegvesen ('' = neutral grey).
 * @var bool   $light Light colour: the silhouette gets an outline.
 */
defined( 'ABSPATH' ) || exit;

$bv_classes = $class;
$bv_style   = '';
if ( ! $url ) {
	$bv_classes .= ' bv-image--placeholder bv-image--' . esc_attr( $type ) . ( $light ? ' bv-image--light' : '' );
	if ( $color ) {
		$bv_style = ' style="--bv-car-color:' . esc_attr( $color ) . '"';
	}
}
?>
<div class="<?php echo $bv_classes; // phpcs:ignore ?>"<?php echo $bv_style; // phpcs:ignore ?>>
	<?php if ( $url ) : ?>
		<img src="<?php echo esc_url( $url ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy">
	<?php else : ?>
		<?php echo BV_Render::silhouette( $data ); // phpcs:ignore ?>
	<?php endif; ?>
</div>
