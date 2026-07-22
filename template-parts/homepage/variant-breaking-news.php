<?php
/**
 * Breaking News homepage variant — scaffold.
 *
 * Single dominant headline block, everything else condensed: just the hero
 * (top stories + the main featured-story carousel + recent) and the
 * sidebar's e-paper/widget content, skipping the World Cup/Premium promo
 * block and the columnists/opinion section entirely so the page stays
 * focused on the breaking story. Meant to be forced on via the admin
 * override during a major story, not day-of-week scheduled. Real structure,
 * built from the same shared partials as every other variant — exact
 * "how condensed is condensed" design is a follow-up to refine with the
 * user, not guessed here.
 */

$data = bd_get_homepage_default_data();

include get_template_directory() . '/template-parts/homepage/partials/hero.php';
?>
<section class="news-block-1">
    <div class="container">
        <div class="col-lg-12">
            <div class="row">
                <?php include get_template_directory() . '/template-parts/homepage/partials/sidebar.php'; ?>
            </div>
        </div>
    </div>
</section>
