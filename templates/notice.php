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
$texts = array(
	/* translators: %s: the registration number or VIN that was searched for. */
	'ikke_funnet' => sprintf( __( 'We found no vehicle with “%s”. Check that the number is correct.', 'bruktdeler-vegvesen' ), $l['query'] ),
	'ugyldig'     => __( 'Enter a registration number (e.g. AB12345) or a 17-character VIN.', 'bruktdeler-vegvesen' ),
	'feil'        => $l['error'],
);
?>
<div class="<?php echo $class; // phpcs:ignore ?> bv-notice bv-notice--<?php echo esc_attr( $l['status'] ); ?>">
	<?php echo BV_Icons::get( 'alert', 'bv-notice__icon' ); // phpcs:ignore ?>
	<div>
		<h3 class="bv-notice__title"><?php echo esc_html( $titles[ $l['status'] ] ?? '' ); ?></h3>
		<p><?php echo esc_html( $texts[ $l['status'] ] ?? '' ); ?></p>
	</div>
</div>
