<?php

	get_header();
	if (have_posts()) : 
        the_post(); 

        // $category = get_the_category();
?> 
<style>
    .read-also {
        background-color: #fdedd7;
        border-top: 2px solid black;
        padding: 1em;
        border-bottom: 1px solid black;
        margin-bottom: 1em;
    }
    .read-also li {
        list-style-type: circle;
        margin-bottom: 10px;
    }
    .read-also header {
        font-weight: 900;
        margin-bottom: 0.5em;
    }
    .read-also a {
        color: #000 !important;
    }
    .read-also a:hover {
        color: #ba141a;
    }
</style>
<!-- <div id="show-ads"> </div> -->
<?php
    

    $cats = wp_get_post_categories($post->ID, array( 'fields' => 'slugs' ) );
    if(in_array('e-edition', $cats)){
        get_template_part( 'template-parts/single', 'edition', $args = [] );
    }
    // The 'pro' category used to branch into template-parts/single-pro.php
    // under the "Legacy Premium Redirect" system (a theme-level content
    // gate that redirected 'pro'-category posts to premium.businessday.ng).
    // Removed: AeroPaywall is now the sole system responsible for gating —
    // every post renders through the same template regardless of category.
    get_template_part( 'template-parts/single', 'default', $args = [] );

?>
  

<?php 
	endif;
 	get_footer(); 
?>