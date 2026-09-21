<?php
/**
 * Comments template.
 *
 * @package GuideGrid_Travel
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<div class="tg-comments" id="comments">
	<?php if ( have_comments() ) : ?>
		<h3>
			<?php
			$count = get_comments_number();
			printf(
				/* translators: %s: number of comments */
				esc_html( _n( '%s comment', '%s comments', $count, 'guidegrid-travel' ) ),
				esc_html( number_format_i18n( $count ) )
			);
			?>
		</h3>
		<ol class="comment-list">
			<?php
			wp_list_comments(
				array(
					'style'       => 'ol',
					'avatar_size' => 48,
				)
			);
			?>
		</ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>

	<?php if ( comments_open() ) : ?>
		<?php
		comment_form(
			array(
				'title_reply'         => __( 'Leave a comment', 'guidegrid-travel' ),
				'label_submit'        => __( 'Post Comment', 'guidegrid-travel' ),
				'comment_notes_before' => '',
			)
		);
		?>
	<?php endif; ?>
</div>
