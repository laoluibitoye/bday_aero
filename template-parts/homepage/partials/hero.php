<?php
/**
 * Homepage hero: Top Stories column, the main featured-story carousel, and
 * the Recent column (with the optional BDay Live video takeover). Expects
 * $data from bd_get_homepage_default_data() to already be in scope —
 * included directly (not get_template_part) so that scope-sharing works.
 */
?>
<section class="news-block-1">
    <div class="container">
        <div class="col-lg-12">
            <div class="row">
                <div class="col-lg-12">
                    <div class="row top-row" style="padding-bottom: 1.5em;">
                        <!-- Top Stories Left Column -->
                        <div class="col-lg-3 top_stories mb-1 py-2">
                            <div class="section-heading">
                                <a href="">
                                    <span class="text-whitex fw-bolder">TOP NEWS</span>
                                </a>
                            </div>
                            <div class="news">
                                <?php
                                    if ( ! empty( $data['top_post'] ) ) :
                                        foreach( $data['top_post'] as $post ) :
                                ?>
                                    <article>
                                        <div class="inner">
                                            <h2 class="post-title">
                                                <a href="<?= get_the_permalink( $post->ID ); ?>"><?= $post->post_title; ?></a>
                                            </h2>
                                            <div class="post-meta">
                                                <span class="time"><?= timeAgo($post->post_date) ?></span>
                                            </div>
                                        </div>
                                    </article>
                                <?php
                                    endforeach;
                                    endif;
                                ?>
                            </div>
                            <a class="btn btn-sm btn-danger" href="https://businessday.ng/tag/bdlead/">Read more >></a>
                        </div>

                       <!-- Main Featured Story Center Column -->
<div class="col-lg-6 main mb-1">
    <div class="top-stories-new owl-carouselx">
        <?php if ( ! empty( $data['main'] ) ) : ?>
            <?php foreach( $data['main'] as $post ) : ?>
                <article>
                    <figure>
                        <a href="<?= get_the_permalink( $post->ID ); ?>">
                            <?= get_thumbnail(['post_id'=>$post->ID, 'size'=>'featured']) ?>
                        </a>
                    </figure>
                    <div class="post-info">
                        <h2 style="font-size: 2rem; line-height: 1; font-weight: 700;">
                            <a href="<?= get_the_permalink( $post->ID ); ?>"><?= $post->post_title; ?></a>
                        </h2>
                        <div class="post-meta">
                            <span class="post-author">
                                <a href="<?= get_author_posts_url(get_the_author_meta('ID', get_post_field( 'post_author', $post->ID ))) ?>">
                                    <?= get_the_author_meta( 'display_name', get_post_field( 'post_author', $post->ID ) ) ?>
                                </a>
                            </span>
                            <span class="post-time"><?= timeAgo($post->post_date) ?></span>
                        </div>

                        <?php
                            // Get raw data to bypass global excerpt filters
                            $text_source = !empty($post->post_excerpt) ? $post->post_excerpt : $post->post_content;
                            // Clean up tags and shortcodes
                            $clean_text = wp_strip_all_tags(strip_shortcodes($text_source));
                            // Trim to ~65 words to fill roughly 4 lines on desktop
                            $forced_excerpt = wp_trim_words($clean_text, 65, '...');
                        ?>

                        <p style="font-size: 16px; line-height: 1.5; height: 6em; overflow: hidden; margin-top: 10px;">
                            <?= $forced_excerpt; ?>
                        </p>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

                        <!-- Recent News Right Column -->
                        <div class="col-lg-3 recent mt-2">
                            <?php
                                $bday_live = get_option('bday_live_meta');

                                if( $bday_live['bday_live_verify'] == 'on'){
                                    $latest = custom_get_posts(
                                        array(
                                            'tag' => 'bdrecent',
                                            'numberposts'   => 2
                                        )
                                    );
                            ?>
                            <div class="mb-3">
                                <div class="ring-container">
                                    <div class="ringring"></div>
                                    <div class="circle"><span>LIVE</span></div>
                                </div>
                                <div class="top-stories-new owl-carouselx">
                                    <article>
                                        <iframe style="width: 100%; height: 200px;"
                                                src="https://www.youtube.com/embed/<?= $bday_live['bday_live_ID'] ?>?autoplay=1&mute=1"
                                                frameborder="0"
                                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                                allowfullscreen>
                                        </iframe>
                                        <div class="post-info">
                                            <h2 style="font-size: 1.5em; font-weight: 700; margin-top: 0.2em; line-height: 1em;">
                                                <a href="#"><?= $bday_live['bday_live_title'] ?></a>
                                            </h2>
                                            <div class="post-meta">
                                                <span class="post-author"><a href="#">BusinessDay</a></span>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            </div>
                            <?php }else{
                                $latest = custom_get_posts(
                                    array(
                                        'tag' => 'bdrecent',
                                        'numberposts'   => 4
                                    )
                                );
                            } ?>

                            <!-- Mobile Ad -->
                            <div class="ad-container mobile-only d-sm-block d-md-none">
                                <?php bd_direct_ad_slot( 'https://bit.ly/3PgGCB7', 'https://cdn.businessday.ng/wp-content/uploads/2026/03/Mixta.jpg', 'MIXTA Africa', 970, 250 ); ?>
                                <?php bd_gam_slot( 'div-gpt-ad-1731239712211-0', 300, 50 ); ?>
                            </div>

                            <div class="section-heading">
                                <a href="https://businessday.ng/tag/bdrecent/">
                                    <span>Recent</span>
                                </a>
                            </div>
                            <div class="news">
                                <?php
                                    if ( ! empty( $latest ) ) :
                                        foreach( $latest as $post ) :
                                ?>
                                    <article>
                                        <div class="inner">
                                            <h2 class="post-title">
                                                <a href="<?= get_the_permalink( $post->ID ); ?>"><?= $post->post_title; ?></a>
                                            </h2>
                                            <div class="post-meta">
                                                <span class="time"><?= timeAgo($post->post_date) ?></span>
                                            </div>
                                        </div>
                                    </article>
                                <?php
                                    endforeach;
                                    endif;
                                ?>
                            </div>
                            <a class="btn btn-sm btn-danger" href="https://businessday.ng/tag/bdrecent/">Read more >></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mobile Sidebar -->
<div class="mobile-only">
    <?php
        if (is_active_sidebar('homepage_mobile_1')) {
            dynamic_sidebar('homepage_mobile_1');
        }
    ?>
</div>

<!-- Desktop Ad -->
<div class="container ad-container desktop-only d-none d-md-block">
      <?php bd_direct_ad_slot( 'https://bit.ly/47LzvXF', 'https://cdn.businessday.ng/wp-content/uploads/2026/03/728-x-90.png', 'IDICE', 728, 90 ); ?>

<?php // Dochase slot — /23043164651,21781351181/businessday_top ?>
<?php bd_gam_slot( 'div-gpt-ad-1783084250687-0', 300, 50 ); ?>

</div>

<!-- Mobile Ad -->
<div class="container ad-container mobile-only d-sm-block d-md-none mt-3 mb-3">
    <?php bd_gam_slot( 'div-gpt-ad-1731239615531-0', 300, 50 ); ?>

    <?php // FIX (2026-07-18): remapped to the unused businessday_body1 slot — /23043164651,21781351181/businessday_body1 ?>
    <?php bd_gam_slot( 'div-gpt-ad-1783096747143-0', 300, 60 ); ?>
</div>
