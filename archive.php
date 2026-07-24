<?php
get_header();
?>
<header class="bday-container"><h1 class="bday-archive-title"><?php echo get_the_archive_title(); // phpcs:ignore ?></h1></header>
<?php get_template_part( 'template-parts/archive/listing' ); ?>
<?php get_footer(); ?>
