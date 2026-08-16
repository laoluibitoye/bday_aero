<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The "E-Editions" homepage section: one card per edition_publication
 * term (get_terms() — real taxonomy, so a new publication an editor adds
 * shows up here with zero code changes), each showing that publication's
 * most recent edition and a link to browse its past editions. Hooks the
 * same bday_homepage_after_bottom_widgets action addons/homepage-modules
 * (Phase 4) already uses for its own new-from-scratch modules (Shorts,
 * promo banners) — same convention, independently toggleable.
 */
add_action(
	'bday_homepage_after_bottom_widgets',
	static function (): void {
		if ( ! post_type_exists( 'bday_edition' ) ) {
			return;
		}

		// Reader-reported: the "Redesign 2026" homepage variant mounts this
		// same shared action (homepage-sections/bottom-widgets-hooks.php)
		// purely to pick up the Shorts/banners modules further down this
		// hook, which meant this callback fired too and rendered a second,
		// duplicate E-Editions row underneath the redesign's own dedicated
		// one (homepage-sections/editions.php, already full-featured with
		// per-card View/Past-editions actions). The classic homepage
		// variants (default.php/weekend.php) have no equivalent of their
		// own, so this stays the real source for them — it just skips
		// itself specifically when the redesign variant is active.
		if ( class_exists( 'Bday_Variant_Registry' ) && 'redesign' === Bday_Variant_Registry::active_slug() ) {
			return;
		}

		$modules = get_option( 'bday_addon_homepage_modules', array() );
		if ( isset( $modules['enable_editions_row'] ) && ! $modules['enable_editions_row'] ) {
			return;
		}

		$publications = get_terms(
			array(
				'taxonomy'   => 'edition_publication',
				'hide_empty' => true,
			)
		);
		if ( is_wp_error( $publications ) || empty( $publications ) ) {
			return;
		}

		$cards = array();
		foreach ( $publications as $publication ) {
			$latest = bday_get_posts(
				array(
					'post_type'      => 'bday_edition',
					'numberposts'    => 1,
					'tax_query'      => array(
						array( 'taxonomy' => 'edition_publication', 'field' => 'term_id', 'terms' => $publication->term_id ),
					),
					'cache_namespace' => 'homepage',
				)
			);
			if ( empty( $latest ) ) {
				continue;
			}
			$cards[] = array( 'publication' => $publication, 'edition' => $latest[0] );
		}
		if ( empty( $cards ) ) {
			return;
		}
		?>
		<section class="bday-editions-row">
			<div class="bday-container">
				<h2 class="bday-section-heading">E-Editions</h2>
				<div class="bday-editions-row__grid">
					<?php foreach ( $cards as $card ) :
						$publication = $card['publication'];
						$edition     = $card['edition'];
						$archive_url = get_term_link( $publication );
						?>
						<article class="bday-edition-card">
							<a href="<?php echo esc_url( get_permalink( $edition ) ); ?>" class="bday-edition-card__media">
								<?php echo bday_get_thumbnail( $edition->ID, 'pdf_thumbnail' ); ?>
							</a>
							<h3 class="bday-edition-card__title"><a href="<?php echo esc_url( get_permalink( $edition ) ); ?>"><?php echo esc_html( $publication->name ); ?></a></h3>
							<span class="bday-edition-card__date"><?php echo esc_html( bday_format_date( $edition->post_date ) ); ?></span>
							<?php if ( ! is_wp_error( $archive_url ) ) : ?>
								<a class="bday-edition-card__past-link" href="<?php echo esc_url( $archive_url ); ?>">View past editions</a>
							<?php endif; ?>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
	}
);
