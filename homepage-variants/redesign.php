<?php
/**
 * Variant Name: Redesign 2026
 * Variant Slug: redesign
 * Description: The new modular homepage, built from BusinessDay Homepage.html's design — sections are added, reordered, and toggled from Appearance > BusinessDay Theme > Homepage Sections, not edited here.
 *
 * Ships alongside (not replacing) Default/Weekend so it can be previewed
 * or forced on from the existing Homepage Variants override without any
 * change to how variants are resolved — Bday_Variant_Registry::discover()
 * picks this file up the same way it already picks up default.php and
 * weekend.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = bday_get_redesign_homepage_data();
?>
<div class="bday-rd">
	<?php Bday_Section_Registry::render_active( $data ); ?>
</div>
