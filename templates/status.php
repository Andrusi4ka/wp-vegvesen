<?php
/**
 * "Vehicle found" + last updated. Override: {theme}/bruktdeler-vegvesen/status.php
 *
 * @var array  $l     Lookup result.
 * @var array  $a     Attributes.
 * @var string $class Wrapper classes (escaped).
 */
defined( 'ABSPATH' ) || exit;
$found = 'funnet' === $l['status'];
?>
<div class="<?php echo $class; // phpcs:ignore ?> <?php echo $found ? 'bv-status--ok' : 'bv-status--error'; ?>">
	<div class="bv-status__title">
		<?php echo BV_Icons::get( $found ? 'check' : 'alert' ); // phpcs:ignore ?>
		<span><?php echo esc_html( $found ? $a['found'] : ( $l['error'] ? $l['error'] : __( 'Vehicle not found', 'bruktdeler-vegvesen' ) ) ); ?></span>
	</div>
	<?php if ( $found && '1' === $a['updated'] && ! empty( $l['data']['sist_oppdatert'] ) ) : ?>
		<?php
		/* translators: PHP date format for "Last updated", see https://www.php.net/manual/datetime.format.php */
		$bv_date = BV_Fields::format( 'sist_oppdatert', $l['data']['sist_oppdatert'], array( 'format' => __( 'j M Y', 'bruktdeler-vegvesen' ) ) );
		?>
		<?php /* translators: %s: date. */ ?>
		<div class="bv-status__meta"><?php echo esc_html( sprintf( __( 'Last updated: %s', 'bruktdeler-vegvesen' ), $bv_date ) ); ?></div>
	<?php endif; ?>
</div>
