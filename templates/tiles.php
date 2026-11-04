<?php
/**
 * Key figure tiles. Override: {theme}/bruktdeler-vegvesen/tiles.php
 *
 * @var array  $tiles   [ label, value, icon ].
 * @var int    $columns Columns on desktop.
 * @var string $class   Wrapper classes (escaped).
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="<?php echo $class; // phpcs:ignore ?>" style="--bv-cols:<?php echo (int) $columns; ?>">
	<?php foreach ( $tiles as $t ) : ?>
		<div class="bv-tile">
			<?php echo BV_Icons::get( $t['icon'], 'bv-tile__icon' ); // phpcs:ignore ?>
			<div>
				<div class="bv-tile__label"><?php echo esc_html( $t['label'] ); ?></div>
				<div class="bv-tile__value"><?php echo esc_html( $t['value'] ); ?></div>
			</div>
		</div>
	<?php endforeach; ?>
</div>
