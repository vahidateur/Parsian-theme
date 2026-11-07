<?php
/**
 * 404.
 *
 * @package Parsian_Music
 */

get_header();

pm_page_hero( 'این صفحه پیدا نشد', 'ممکن است آدرس را اشتباه وارد کرده باشید یا صفحه جابه‌جا شده باشد.', array( 'eyebrow' => 'خطای ۴۰۴' ) );
?>
<section class="pm-section">
	<div class="pm-container pm-404">
		<div class="pm-404__number" aria-hidden="true">۴۰۴</div>
		<div class="pm-404__content">
			<h2>مسیر را دوباره پیدا کنیم</h2>
			<p>از جستجو استفاده کنید یا به صفحهٔ اصلی برگردید.</p>
			<div class="pm-404__search"><?php get_search_form(); ?></div>
			<div class="pm-404__actions">
				<a class="pm-btn pm-btn--gold" href="<?php echo esc_url( home_url( '/' ) ); ?>">صفحهٔ اصلی</a>
				<a class="pm-btn pm-btn--ghost" href="<?php echo esc_url( pm_get_teacher_archive_url() ); ?>">اساتید</a>
				<a class="pm-btn pm-btn--ghost" href="<?php echo esc_url( pm_get_contact_url() ); ?>">ارتباط با ما</a>
			</div>
		</div>
	</div>
</section>
<?php
get_footer();
