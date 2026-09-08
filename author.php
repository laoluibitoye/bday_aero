<?php
/**
 * Previously showed only WordPress core's bare archive title — none of
 * addons/author-profile's own data (uploaded byline photo, bio) ever
 * appeared here, even though that same addon's get_avatar() filter and
 * "description" meta already power the identical-looking bio card on
 * every article (template-parts/single-default.php). Reusing that same
 * .bday-author-bio markup here, once, at profile-page scale, instead of
 * the small per-article inline card.
 */
get_header();

$bday_author = get_queried_object();
?>
<header class="bday-container">
	<h1 class="bday-archive-title"><?php echo get_the_archive_title(); // phpcs:ignore ?></h1>
	<?php if ( $bday_author instanceof WP_User ) :
		$bday_author_bio = get_the_author_meta( 'description', $bday_author->ID );
		?>
		<div class="bday-author-bio bday-author-bio--profile">
			<?php echo get_avatar( $bday_author->ID, 64, '', '', array( 'class' => 'bday-author-bio__avatar' ) ); ?>
			<?php if ( $bday_author_bio ) : ?>
				<div><p><?php echo esc_html( $bday_author_bio ); ?></p></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</header>
<?php get_template_part( 'template-parts/archive/listing' ); ?>
<?php get_footer(); ?>
