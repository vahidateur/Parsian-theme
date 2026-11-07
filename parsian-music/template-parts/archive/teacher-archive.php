<?php
/**
 * Teacher archive content.
 *
 * @package Parsian_Music
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pm_archive_url = pm_get_teacher_archive_url();
$pm_terms       = get_terms(
	array(
		'taxonomy'   => 'pm_department',
		'hide_empty' => true,
	)
);
$pm_terms       = is_wp_error( $pm_terms ) ? array() : $pm_terms;
$pm_active      = isset( $_GET['department'] ) ? sanitize_title( wp_unslash( $_GET['department'] ) ) : 'all';
if ( is_tax( 'pm_department' ) ) {
	$pm_term   = get_queried_object();
	$pm_active = $pm_term instanceof WP_Term ? $pm_term->slug : 'all';
}

$pm_order = array(
	'strings' => 0,
	'piano'   => 1,
	'persian' => 2,
	'theory'  => 3,
	'vocal'   => 4,
	'kids'    => 5,
);
usort(
	$pm_terms,
	function ( $pm_a, $pm_b ) use ( $pm_order ) {
		$pm_a_order = isset( $pm_order[ $pm_a->slug ] ) ? $pm_order[ $pm_a->slug ] : 99;
		$pm_b_order = isset( $pm_order[ $pm_b->slug ] ) ? $pm_order[ $pm_b->slug ] : 99;
		return $pm_a_order <=> $pm_b_order;
	}
);
?>
<section class="pm-cv-hero">
	<div class="pm-container">
		<h1>اساتید تراز اول آکادمی موسیقی</h1>
	</div>
</section>

<section class="pm-section pm-archive-content">
	<div class="pm-container">
		<?php if ( ! empty( $pm_terms ) ) : ?>
			<nav class="pm-chips pm-chips--links" aria-label="فیلتر اساتید بر اساس دپارتمان">
				<a class="pm-chip<?php echo 'all' === $pm_active ? ' is-on' : ''; ?>" href="<?php echo esc_url( $pm_archive_url ); ?>">همه</a>
				<?php foreach ( $pm_terms as $pm_term ) : ?>
					<a class="pm-chip<?php echo $pm_active === $pm_term->slug ? ' is-on' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'department', $pm_term->slug, $pm_archive_url ) ); ?>"><?php echo esc_html( $pm_term->name ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<h2 class="pm-sr-only">لیست اساتید</h2>
			<div class="pm-teachers pm-teachers--archive">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/cards/teacher-card', null, array( 'post' => get_post() ) );
				endwhile;
				?>
			</div>

			<?php pm_pagination(); ?>
		<?php else : ?>
			<div class="pm-empty">
				<?php echo pm_icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<h2>استادی در این دپارتمان پیدا نشد</h2>
				<p>می‌توانید همهٔ اساتید را ببینید یا از فرم مشاوره برای انتخاب استاد مناسب کمک بگیرید.</p>
				<div class="pm-empty__actions">
					<a class="pm-btn pm-btn--gold" href="<?php echo esc_url( $pm_archive_url ); ?>">همهٔ اساتید</a>
					<a class="pm-btn pm-btn--ghost" href="<?php echo esc_url( pm_get_contact_url() ); ?>">رزرو مشاوره</a>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>

<?php get_template_part( 'template-parts/sections/kids-banner' ); ?>
<?php get_template_part( 'template-parts/sections/contact', null, array( 'compact' => true ) ); ?>
