<?php
/**
 * Homepage sidebar column (col-lg-3): today's e-paper thumbnail, a GAM
 * sidebar slot, and the homepage_sidebar widget area. Expects $data in
 * scope. Caller owns the surrounding <div class="row"> — see main-content.php.
 */
?>
                <!-- Sidebar -->
                <div class="col-lg-3">
                    <div class="widget">
                        <div class="section-heading" style="margin-top: 1em; margin-bottom: 1em;">
                            <span>Today's E-paper</span>
                        </div>
                        <?php
                            if ( ! empty( $data['e_paper'] ) ) :
                                foreach( $data['e_paper'] as $post ) :
                        ?>
                        <figure>
                            <a href="https://businessday.ng/today-e-paper/">
                                <?= get_thumbnail(['post_id'=>$post->ID, 'size'=>'pdf_thumbnail']) ?>
                            </a>
                        </figure>
                        <?php
                                endforeach;
                            endif;
                        ?>
                    </div>
                    <aside class="desktop-only">
                        <?php if ( bd_page_allows_ads() ) : ?>
                            <?= do_shortcode('[admanager ad_id="sidebar_1" placement="desktop" lazy="false"]'); ?>
                        <?php endif; ?>
                        <?php
                            if (is_active_sidebar('homepage_sidebar')) {
                                dynamic_sidebar('homepage_sidebar');
                            }
                        ?>
                    </aside>
                </div>
