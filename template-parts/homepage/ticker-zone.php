<?php
/** Mounts the live-match add-on's ticker render hook — a no-op if that add-on is disabled. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
do_action( 'bday_header_ticker_zone' );
