<?php
/**
 * Shown by [bv_card] when nothing was found or an error happened.
 * Override: {theme}/bruktdeler-vegvesen/notice.php
 *
 * @var array  $l     Lookup result (status: ikke_funnet | ugyldig | feil).
 * @var string $class Wrapper classes (escaped).
 */
defined( 'ABSPATH' ) || exit;
$titles = array(
	'ikke_funnet' => __( 'Vehicle not found', 'bruktdeler-vegvesen' ),
	'ugyldig'     => __( 'Invalid registration number', 'bruktdeler-vegvesen' ),
	'feil'        => __( 'The search could not be completed', 'bruktdeler-vegvesen' ),
);
?>
<div class="<?php echo $class; // phpcs:ignore ?> bv-notice bv-notice--<?php echo esc_attr( $l['status'] ); ?>">
	<?php echo BV_Icons::get( 'alert', 'bv-notice__icon' ); // phpcs:ignore ?>
	<div>
		<?php $bv_tag = BV_Render::title_tag( $tag ?? '', 'h3' ); ?>
		<<?php echo $bv_tag; // phpcs:ignore -- whitelisted ?> class="bv-notice__title"><?php echo esc_html( $titles[ $l['status'] ] ?? '' ); ?></<?php echo $bv_tag; // phpcs:ignore ?>>
		<p><?php echo esc_html( BV_Render::status_message( $l ) ); ?></p>
	</div>
</div>
