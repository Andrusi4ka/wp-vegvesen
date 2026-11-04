<?php
/**
 * Info panel with label/value rows. Override: {theme}/bruktdeler-vegvesen/table.php
 *
 * @var array  $a     Attributes (title, icon…).
 * @var array  $rows  [ key, label, value, class, color ].
 * @var string $class Wrapper classes (escaped).
 */
defined( 'ABSPATH' ) || exit;
?>
<section class="<?php echo $class; // phpcs:ignore ?>">
	<?php if ( $a['title'] ) : ?>
		<?php $bv_tag = BV_Render::title_tag( $a['title_tag'] ?? '', 'h3' ); ?>
		<<?php echo $bv_tag; // phpcs:ignore -- whitelisted ?> class="bv-panel__title"><?php echo BV_Icons::get( $a['icon'] ? $a['icon'] : 'info' ); // phpcs:ignore ?><span><?php echo esc_html( $a['title'] ); ?></span></<?php echo $bv_tag; // phpcs:ignore ?>>
	<?php endif; ?>
	<dl class="bv-rows">
		<?php foreach ( $rows as $row ) : ?>
			<div class="bv-row <?php echo esc_attr( $row['class'] ); ?>">
				<dt><?php echo esc_html( $row['label'] ); ?></dt>
				<dd>
					<?php if ( $row['color'] ) : ?><span class="bv-swatch" style="background:<?php echo esc_attr( $row['color'] ); ?>"></span><?php endif; ?>
					<?php echo esc_html( $row['value'] ); ?>
				</dd>
			</div>
		<?php endforeach; ?>
	</dl>
</section>
