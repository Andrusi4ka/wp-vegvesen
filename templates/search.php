<?php
/**
 * Search form. Override: {theme}/bruktdeler-vegvesen/search.php
 *
 * @var array $a Shortcode attributes (+ action, value, image_url, class).
 */
defined( 'ABSPATH' ) || exit;
$vars = array();
if ( $a['image_url'] ) {
	$vars[] = '--bv-hero-image:url(' . esc_url( $a['image_url'] ) . ')';
}
if ( ! empty( $a['max_width'] ) ) {
	$vars[] = '--bv-search-max:' . $a['max_width']; // validated in the shortcode.
}
$style = $vars ? ' style="' . esc_attr( implode( ';', $vars ) ) . '"' : '';
?>
<div class="<?php echo $a['class']; // phpcs:ignore -- escaped in BV_Render::classes ?>"<?php echo $style; // phpcs:ignore ?>>
	<div class="bv-search__inner">
		<?php if ( $a['title'] ) : ?>
			<?php $bv_tag = BV_Render::title_tag( $a['title_tag'] ?? '', 'h1' ); ?>
			<<?php echo $bv_tag; // phpcs:ignore -- whitelisted ?> class="bv-search__title"><?php echo esc_html( $a['title'] ); ?></<?php echo $bv_tag; // phpcs:ignore ?>>
		<?php endif; ?>
		<?php if ( $a['subtitle'] ) : ?>
			<p class="bv-search__subtitle"><?php echo esc_html( $a['subtitle'] ); ?></p>
		<?php endif; ?>
		<form class="bv-search__form" method="get" action="<?php echo esc_url( $a['action'] ); ?>" role="search" data-bv-search>
			<?php foreach ( (array) ( $a['hidden'] ?? array() ) as $bv_name => $bv_val ) : ?>
				<?php if ( is_scalar( $bv_val ) ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $bv_name ); ?>" value="<?php echo esc_attr( $bv_val ); ?>">
				<?php endif; ?>
			<?php endforeach; ?>
			<label class="bv-search__field">
				<?php echo BV_Icons::get( 'car', 'bv-search__icon' ); // phpcs:ignore ?>
				<span class="screen-reader-text"><?php echo esc_html( $a['placeholder'] ); ?></span>
				<input type="text" name="regnr" value="<?php echo esc_attr( $a['value'] ); ?>" placeholder="<?php echo esc_attr( $a['placeholder'] ); ?>" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="20" required>
			</label>
			<button type="submit" class="bv-search__button">
				<?php echo BV_Icons::get( 'search' ); // phpcs:ignore ?>
				<span><?php echo esc_html( $a['button'] ); ?></span>
			</button>
		</form>
		<?php if ( ! empty( $a['message_text'] ) ) : ?>
			<p class="bv-search__message bv-search__message--<?php echo esc_attr( $a['message_status'] ); ?>" role="status">
				<?php echo BV_Icons::get( 'alert' ); // phpcs:ignore ?>
				<span><?php echo esc_html( $a['message_text'] ); ?></span>
			</p>
		<?php endif; ?>
	</div>
</div>
