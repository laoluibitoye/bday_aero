<?php
/**
 * World Cup promo block + Terrific embed + Premium section + the
 * "Your News" carousel shortcode. Expects $data in scope (included
 * directly, not get_template_part).
 *
 * Self-contained section/container/row (the original markup nested this
 * inside the same .row as the main-content+sidebar split further down —
 * split into two independent sections here instead, since a col-lg-12
 * block already takes a full row's width on its own regardless of which
 * .row wraps it, so there's no visual difference and each partial becomes
 * genuinely reusable on its own).
 */
?>
<!-- Secondary News Section -->
<section class="news-block-1">
    <div class="container">
        <div class="col-lg-12">
            <div class="row">
<div class="col-lg-12 mb-4">
    <div class="col-lg-12 pro-section" style="background-color: #E7E7E7;">
        <?php
            $running = $data['running'];

            echo '<section class="news-block-2">
            <div class="container">
                <div class="section-heading d-flex justify-content-between">
                    <div class="mt-1">
                        <a href="https://businessday.ng/tag/2026-fifa-world-cup/" target="_blank" style="color: black !important;">
                            <span style="font-weight: 900; font-size: 22px; color: black !important;">
                            2026 World Cup
                            </span>
                        </a>
                    </div>
                    <div class="mt-0">
                        <a href="https://businessday.ng/tag/2026-fifa-world-cup/" class="btn btn-sm btn-danger" target="_blank">
                            View More
                        </a>
                    </div>
                </div>
                <div class="col-lg-12">
                    <div class="row news">';

                        if ( ! empty( $running ) ) :
                            foreach( $running as $post ) :
                            echo '<div class="col-lg-3 mb-3">
                                    <article>
                                        <figure>
                                            <a href="'.get_the_permalink( $post->ID ).'">'.get_thumbnail(['post_id'=>$post->ID, 'size'=>'medium_rectangle']).'</a>
                                        </figure>
                                        <div class="post-info">
                                            <h2 class="post-title"><a href="'.get_the_permalink( $post->ID ).'" style="color: black !important;">'.$post->post_title.'</a></h2>
                                            <div class="post-excerpt" style="color: black !important;">
                                                '.get_the_excerpt( $post ).'
                                            </div>
                                            <div class="post-meta">
                                                <span class="post-date" style="color: black !important;"> '.custom_time_format($post->post_date, 'full').' </span>
                                            </div>
                                        </div>
                                    </article>
                                </div>';
                            endforeach;
                        endif;
                        echo '
                    </div>
                </div>
            </div>
            </section>';
        ?>
    </div>
</div>
              <!--Terrific-->
<div data-source="terrific" embedding-id="NTWXkg1ovwVf9kwd8DPf" class="container"></div>

                <!-- Premium Section -->
                <div class="col-lg-12">
                    <div class="col-lg-12 pro-section" style="background-color: #E7E7E7 !important; color: black">
                        <section class="news-block-2">
                            <div class="container">
                                <div class="section-heading">
                                    <a href="https://premium.businessday.ng/" target="_blank">
                                        <span style="font-weight: 900; font-size: 22px;">PREMIUM</span>
                                    </a>
                                </div>
                                <div class="col-lg-12">
                                    <div class="row news">
                                        <?php
                                            if ( ! empty( $data['premium'] ) ) :
                                                foreach( $data['premium'] as $post ) :
                                        ?>
                                        <div class="col-lg-3 mb-3">
                                            <article>
                                                <figure>
                                                    <a href="<?= get_the_permalink( $post->ID ) ?>">
                                                        <?= get_thumbnail(['post_id'=>$post->ID, 'size'=>'medium_rectangle']) ?>
                                                    </a>
                                                </figure>
                                                <div class="post-info">
                                                    <h2 class="post-title">
                                                        <a href="<?= get_the_permalink( $post->ID ) ?>"><?= $post->post_title ?></a>
                                                    </h2>
                                                    <div class="post-meta">
                                                        <span class="post-date"><?= custom_time_format($post->post_date, 'full') ?></span>
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
                        </section>
                    </div>
                </div>

                <!-- Your News Section -->
                <div class="col-lg-12">
                    <?php echo do_shortcode('[homepage_news_carousel]'); ?>
                </div>
            </div>
        </div>
    </div>
</section>
