<?php
/**
 * Homepage "Main Content Area" column (col-lg-9): ad rail, the "IN OTHER
 * NEWS" grid, and the Columnists/Opinion row. Expects $data in scope.
 *
 * Caller owns the surrounding <div class="row"> — this renders just the
 * col-lg-9 column so it can sit beside template-parts/homepage/partials/
 * sidebar.php's col-lg-3 in the same row (real Bootstrap grid math, unlike
 * promo-and-premium.php's col-lg-12 blocks which don't need to share a row).
 */
?>
                <!-- Main Content Area -->
                <div class="col-lg-9">
                    <!-- Desktop Ad -->
                    <div class="ad-container desktop-only d-none d-md-block">
                        <?php bd_gam_slot( 'div-gpt-ad-1731238848673-0', 300, 50, 'd-flex justify-content-around' ); ?>
                        <?php bd_direct_ad_slot( 'https://www.flyaero.com/', 'https://cdn.businessday.ng/wp-content/uploads/2025/11/Aero.jpg', 'Aero Contractors', 970, 250 ); ?>
                    </div>

                    <!-- Mobile Ad -->
                    <div class="ad-container mobile-only d-sm-block d-md-none">
                        <?php
                        // NOTE: div-gpt-ad-1731239712211-0 is also rendered in the hero
                        // partial for the same page load — a pre-existing duplicate DOM id
                        // from before the ad-consolidation pass. Left as-is (not reassigned
                        // to a different slot) since guessing a replacement mapping risks
                        // misattributing ad revenue; flagging for whoever owns the GAM slot
                        // inventory to resolve.
                        bd_gam_slot( 'div-gpt-ad-1731239712211-0', 300, 50 );
                        ?>
                    </div>

                    <!-- Other News Section -->
                    <div class="col-lg-12 other-news-section" style="background-color: #F8F9FA !important;">
    <div class="section-heading">
        <a href="<?= category_url('news') ?>">
            <span>IN OTHER NEWS</span>
        </a>
    </div>
    <div class="news-type-2">
        <div class="row">
            <?php
                if ( ! empty( $data['news1'] ) ) :
                    foreach( $data['news1'] as $post ) :
            ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <article>
                    <span class="category">
                        <a href="<?= get_category_link(get_cat_ID('news')) ?>">News</a>
                    </span>
                    <figure class="post-thumbnail-wrapper" style="overflow: hidden; margin-bottom: 10px;">
                        <a href="<?= get_the_permalink( $post->ID ); ?>" style="display: block;">
                            <?php
                                // Output the thumbnail with a specific class for CSS control
                                $thumb = get_thumbnail(['post_id'=>$post->ID, 'size'=>'medium_rectangle']);
                                echo str_replace('<img ', '<img class="img-fluid w-100" style="object-fit: cover; height: 200px;" ', $thumb);
                            ?>
                        </a>
                    </figure>
                    <div class="post-info">
                        <h2 class="post-title" style="font-size: 1.2rem; line-height: 1.3;">
                            <a href="<?= get_the_permalink( $post->ID ); ?>"><?= $post->post_title; ?></a>
                        </h2>
                        <div class="post-meta">
                            <span class="post-author">
                                <a href="<?= get_author_posts_url(get_post_field('post_author', $post->ID)) ?>">
                                    <?= get_the_author_meta('display_name', get_post_field('post_author', $post->ID)) ?>
                                </a>
                            </span>
                            <span class="post-time"><?= timeAgo($post->post_date) ?></span>
                        </div>
                        <p class="post-excerpt"><?= wp_trim_words(get_the_excerpt($post->ID), 15) ?>...</p>
                    </div>
                </article>
            </div>
            <?php
                    endforeach;
                endif;
            ?>
        </div>
    </div>
</div>

                    <!-- Desktop Ad -->
                    <div class="ad-container desktop-only d-none d-md-block">
                        <?php // Dochase slot — /23043164651,21781351181/businessday_top2 ?>
                        <?php bd_gam_slot( 'div-gpt-ad-1783084673395-0', 300, 50 ); ?>
                        <?php // FIX (2026-07-18): remapped to the unused businessday_body2 slot ?>
                        <?php bd_gam_slot( 'div-gpt-ad-1783097109737-0', 250, 50 ); ?>
                    </div>

                    <!-- Mobile Ad -->
                    <div class="ad-container mobile-only d-sm-block d-md-none">
                        <?php // FIX (2026-07-18): remapped to the unused businessday_body3 slot — /23043164651,21781351181/businessday_body3 ?>
                        <?php bd_gam_slot( 'div-gpt-ad-1783098103568-0', 300, 60 ); ?>
                        <?php bd_gam_slot( 'div-gpt-ad-1731239786872-0', 300, 50 ); ?>
                    </div>

                    <?php if ( bd_page_allows_ads() ) : ?>
                        <?= do_shortcode('[admanager ad_id="mobile_tenancy_1" placement="mobile" lazy="false" ]'); ?>
                    <?php endif; ?>

                    <!-- Columnists and Opinion Section -->
                    <div class="col-lg-12">
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="columnist-news" style="background-color: #F8F9FA !important;">
                                    <div class="section-heading">
                                        <a href="<?= category_url('columnist') ?>">
                                            <span>COLUMNISTS</span>
                                        </a>
                                    </div>
                                    <div class="row">
                                    <?php
                                        if ( ! empty( $data['column'] ) ) :
                                            foreach( $data['column'] as $post ) :
                                    ?>
                                        <div class="col-lg-6">
                                            <article>
                                                <figure>
                                                    <?= get_avatar( get_the_author_meta( 'ID', get_post_field( 'post_author', $post->ID ) ), 32 ); ?>
                                                </figure>
                                                <div class="post-info">
                                                    <h2 class="post-title">
                                                        <a href="<?= get_the_permalink( $post->ID ); ?>"><?= $post->post_title; ?></a>
                                                    </h2>
                                                    <div class="post-meta">
                                                        <span class="post-author">
                                                            <a href="<?= get_author_posts_url(get_the_author_meta('ID', get_post_field( 'post_author', $post->ID ))) ?>">
                                                                <?= get_the_author_meta( 'display_name', get_post_field( 'post_author', $post->ID ) ) ?>
                                                            </a>
                                                        </span>
                                                        <span class="post-time"><?= custom_time_format($post->post_date, 'full') ?></span>
                                                    </div>
                                                </div>
                                            </article>
                                        </div>
                                    <?php
                                            endforeach;
                                        endif;
                                    ?>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-4 opinion-news">
                                <div class="news-lists">
                                    <div class="section-heading">
                                        <a href="<?= category_url('opinion') ?>">
                                            <span>OPINION</span>
                                        </a>
                                    </div>
                                    <?php
                                        if ( ! empty( $data['opinion'] ) ) :
                                            foreach( $data['opinion'] as $post ) :
                                    ?>
                                    <article>
                                        <span class="category">
                                            <a href="<?= get_category_link(get_cat_ID('opinion')) ?>">OPINION</a>
                                        </span>
                                        <p class="post-title">
                                            <a href="<?= get_the_permalink( $post->ID ); ?>"><?= $post->post_title; ?></a>
                                        </p>
                                        <span class="post-time"><?= custom_time_format($post->post_date, 'full') ?></span>
                                    </article>
                                    <?php
                                            endforeach;
                                        endif;
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
