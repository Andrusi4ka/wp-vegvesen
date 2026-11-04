<?php
/**
 * Full vehicle card (the whole layout). Built from the other shortcodes, so every part
 * can also be placed separately in a page builder. Override: {theme}/bruktdeler-vegvesen/card.php
 *
 * @var array  $l     Lookup result.
 * @var array  $a     Attributes (tiles, general, dates, miljo, print, image).
 * @var string $class Wrapper classes (escaped).
 */
defined( 'ABSPATH' ) || exit;
$r = $a['regnr'] ? ' regnr="' . esc_attr( $a['regnr'] ) . '"' : '';
?>
<article class="<?php echo $class; // phpcs:ignore ?>">
	<header class="bv-card__head">
		<?php echo do_shortcode( '[bv_plate' . $r . ']' ); // phpcs:ignore ?>
		<?php echo do_shortcode( '[bv_status' . $r . ']' ); // phpcs:ignore ?>
		<?php if ( '1' === $a['print'] ) : ?>
			<?php echo do_shortcode( '[bv_print]' ); // phpcs:ignore ?>
		<?php endif; ?>
	</header>

	<div class="bv-card__hero">
		<?php if ( '1' === $a['image'] ) : ?>
			<?php echo do_shortcode( '[bv_image' . $r . ']' ); // phpcs:ignore ?>
		<?php endif; ?>
		<div class="bv-card__name">
			<?php if ( '1' === $a['logo'] ) : ?>
				<?php echo do_shortcode( '[bv_logo size="64"' . $r . ']' ); // phpcs:ignore ?>
			<?php endif; ?>
			<h2 class="bv-card__title"><?php echo esc_html( $l['data']['merke_modell'] ?? '' ); ?></h2>
			<p class="bv-card__sub"><?php echo esc_html( implode( ' · ', array_filter( array( $l['data']['registrert_aar'] ?? '', $l['data']['kategori_navn'] ?? '' ) ) ) ); ?></p>
		</div>
	</div>

	<?php echo do_shortcode( '[bv_tiles fields="' . esc_attr( $a['tiles'] ) . '"' . $r . ']' ); // phpcs:ignore ?>

	<div class="bv-card__grid">
		<div class="bv-card__col">
			<?php echo do_shortcode( '[bv_table title="' . esc_attr__( 'General information', 'bruktdeler-vegvesen' ) . '" icon="info" fields="' . esc_attr( $a['general'] ) . '"' . $r . ']' ); // phpcs:ignore ?>
		</div>
		<div class="bv-card__col">
			<?php echo do_shortcode( '[bv_table title="' . esc_attr__( 'Important dates', 'bruktdeler-vegvesen' ) . '" icon="calendar" highlight="neste_eu" fields="' . esc_attr( $a['dates'] ) . '"' . $r . ']' ); // phpcs:ignore ?>
			<?php echo do_shortcode( '[bv_table title="' . esc_attr__( 'Environment and consumption', 'bruktdeler-vegvesen' ) . '" icon="leaf" fields="' . esc_attr( $a['miljo'] ) . '"' . $r . ']' ); // phpcs:ignore ?>
		</div>
	</div>
</article>
