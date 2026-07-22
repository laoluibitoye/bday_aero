<?php
/**
 * Weekend homepage variant — scaffold.
 *
 * Working structure using the same shared partials as variant-default.php,
 * reordered: the ad-heavy World Cup/Premium promo block moves below the
 * main content + sidebar split instead of above it, so a weekend reader
 * hits the columnists/opinion/other-news content sooner. This phase
 * delivers the switching mechanism and a real, working scaffold — exact
 * editorial content/design for the weekend variant (e.g. a genuinely
 * different lifestyle-leaning section) is a follow-up to refine with the
 * user, not guessed here.
 */

$data = bd_get_homepage_default_data();

include get_template_directory() . '/template-parts/homepage/partials/hero.php';
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
include get_template_directory() . '/template-parts/homepage/partials/promo-and-premium.php';
include get_template_directory() . '/template-parts/homepage/partials/widgets-row.php';
