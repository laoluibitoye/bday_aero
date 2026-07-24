<?php
/** Mounts the premium-leaderboard add-on's render hook — a no-op if that add-on is disabled. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
do_action( 'bday_homepage_leaderboard_zone' );
