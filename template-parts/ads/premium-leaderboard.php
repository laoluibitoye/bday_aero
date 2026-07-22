<?php
/**
 * Homepage-only "premium leaderboard" promo slider.
 *
 * A rotating sponsor image/link banner, admin-configured via the Premium
 * Leaderboard settings page (functions/features.php — up to N image/url
 * pairs stored in the 'premium_leaderboard' option). One random image
 * shows on page load; the inline script below then re-randomizes it on an
 * interval. Moved out of header.php (where this used to live inline) as
 * part of the ad-system consolidation — same markup/behavior, just no
 * longer mixed into the page-shell template.
 */

if ( ! is_front_page() || ! bd_page_allows_ads() ) {
	return;
}

$premiums      = [];
$premium_urls  = [];
$max_premium   = 0;
$slider_speed  = 20000;

$premium_lead  = get_option( 'premium_leaderboard' );
$count         = isset( $premium_lead['leaderboard_count'] ) && $premium_lead['leaderboard_count'] !== '' ? intval( $premium_lead['leaderboard_count'] ) : 4;
$slider_speed  = isset( $premium_lead['slider_speed'] ) && $premium_lead['slider_speed'] !== '' ? intval( $premium_lead['slider_speed'] ) : 20000;

if ( is_array( $premium_lead ) ) {
	for ( $i = 1; $i <= $count; $i++ ) {
		$img = $premium_lead[ 'image' . $i ] ?? '';
		$url = $premium_lead[ 'url' . $i ] ?? '';
		if ( ! empty( $img ) ) {
			$premiums[]     = $img;
			$premium_urls[] = $url;
			$max_premium++;
		}
	}
}

if ( $max_premium > 0 ) {
	$rand_index     = rand( 0, $max_premium - 1 );
	$selected_image = $premiums[ $rand_index ];
	$selected_url   = $premium_urls[ $rand_index ];
	echo '<a id="premium_lederboard_url" href="' . esc_url( $selected_url ) . '" target="_blank"> <img id="premium_leaderboard" class="premium_leaderboard" src="' . esc_url( $selected_image ) . '" alt="premium_leaderboard_ads" max-width="100%" height="auto"/> </a>';
}
?>
<script>
    var premiums = <?= json_encode( $premiums ) ?>;
    var premium_urls = <?= json_encode( $premium_urls ) ?>;
    var slider_speed = <?= $slider_speed ?>;
    if (premiums.length > 0) {
        var max_premium = <?= $max_premium ?>;
        var img = document.getElementById("premium_leaderboard");
        setInterval(function() {
            var premium_rand = Math.floor(Math.random() * max_premium);
            img.src = premiums[premium_rand];
            var href = document.getElementById('premium_lederboard_url');
            href.onclick = function(event) {
                event.preventDefault();
                window.location.href = premium_urls[premium_rand];
            };
        }, slider_speed);
    }
</script>
