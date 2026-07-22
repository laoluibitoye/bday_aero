<?php
/**
 * Bottom-of-homepage widget rows: 4 dynamic_sidebar widget areas
 * interleaved with the video/magazine/custom/events widget shortcodes and
 * their ad slots. Self-contained (each row is its own <div class="container">).
 */
?>
<!-- Bottom Content Sections -->
<div class="container">
    <?php
        if (is_active_sidebar('homepage_section_1')) {
            dynamic_sidebar('homepage_section_1');
        }
    ?>
</div>

<div class="container ad-container mobile-only d-sm-block d-md-none">
    <?php // /21781351181/bd_mobile_4 ?>
    <?php bd_gam_slot( 'div-gpt-ad-1731239857708-0', 300, 50 ); ?>
</div>

<!-- Video Widget -->
<?php echo do_shortcode('[new_homepage_video_widget posts=8]'); ?>

<div class="container">
    <?php
        if (is_active_sidebar('homepage_section_2')) {
            dynamic_sidebar('homepage_section_2');
        }
    ?>
</div>
<!-- New Ad-->
<div class="ad-container desktop-only d-none d-md-block">
    <?php bd_gam_slot( 'div-gpt-ad-1731239152173-0', 300, 90, 'd-flex justify-content-around' ); ?>
</div>

<!-- Magazine Widget -->
<?php echo do_shortcode('[homepage_magazine_widget]'); ?>

<div class="container">
    <?php
        if (is_active_sidebar('homepage_section_3')) {
            dynamic_sidebar('homepage_section_3');
        }
    ?>
</div>

<!-- Custom Widget -->
<?php echo do_shortcode('[homepage_widget_custom]'); ?>

<div class="container">
    <?php
        if (is_active_sidebar('homepage_section_4')) {
            dynamic_sidebar('homepage_section_4');
        }
    ?>
</div>

<!-- Events Widget -->
<?= do_shortcode('[events_widget]') ?>
