<?php
/**
 * Search form.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$pm_search_id = wp_unique_id( 'pm-search-' );
?>
<form role="search" method="get" class="pm-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="pm-sr-only" for="<?php echo esc_attr( $pm_search_id ); ?>">جستجو در سایت</label>
	<input type="search" id="<?php echo esc_attr( $pm_search_id ); ?>" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="جستجو در سایت…" />
	<button type="submit" aria-label="جستجو">
		<?php echo pm_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</button>
</form>
