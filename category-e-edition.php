<?php
/** E-edition category archive — a PDF-thumbnail grid, not the article listing every other category uses (addons/e-edition/). */
get_header();
?>
<header class="bday-container"><h1 class="bday-archive-title"><?php echo get_the_archive_title(); // phpcs:ignore ?></h1></header>
<?php bday_render_e_edition_grid(); ?>
<?php get_footer(); ?>
