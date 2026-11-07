<?php
/**
 * Comments.
 *
 * @package Parsian_Music
 */

if ( post_password_required() ) {
	return;
}
?>
<div id="comments" class="pm-comments">
	<?php if ( have_comments() ) : ?>
		<h2 class="pm-comments__title">
			<?php
			$pm_count = get_comments_number();
			echo esc_html( pm_fa( $pm_count ) . ' دیدگاه' );
			?>
		</h2>

		<ol class="pm-comments__list">
			<?php
			wp_list_comments(
				array(
					'style'      => 'ol',
					'short_ping' => true,
					'avatar_size' => 56,
				)
			);
			?>
		</ol>

		<?php the_comments_pagination( array( 'prev_text' => 'قبلی', 'next_text' => 'بعدی' ) ); ?>
	<?php endif; ?>

	<?php
	comment_form(
		array(
			'title_reply'         => 'دیدگاه خود را بنویسید',
			'title_reply_before'  => '<h2 id="reply-title" class="pm-comments__title">',
			'title_reply_after'   => '</h2>',
			'class_submit'        => 'pm-btn pm-btn--gold',
			'comment_notes_before' => '<p class="pm-comments__notes">ایمیل شما منتشر نمی‌شود.</p>',
		)
	);
	?>
</div>
