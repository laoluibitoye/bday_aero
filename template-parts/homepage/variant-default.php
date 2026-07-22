<?php
/**
 * Default (weekday) homepage variant — the exact layout templates/
 * masterpage.php always rendered, now composed from the shared partials in
 * template-parts/homepage/partials/ instead of being one 645-line file.
 * See inc/homepage/homepage-variants.php for how a variant gets selected.
 */

$data = bd_get_homepage_default_data();

include get_template_directory() . '/template-parts/homepage/partials/hero.php';
include get_template_directory() . '/template-parts/homepage/partials/promo-and-premium.php';
?>
<section class="news-block-1">
    <div class="container">
        <div class="col-lg-12">
            <div class="row">
                <?php
                include get_template_directory() . '/template-parts/homepage/partials/main-content.php';
                include get_template_directory() . '/template-parts/homepage/partials/sidebar.php';
                ?>
            </div>
        </div>
    </div>
</section>
<?php
include get_template_directory() . '/template-parts/homepage/partials/widgets-row.php';
