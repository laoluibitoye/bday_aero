<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Google Analytics 4 — supports a primary + optional secondary measurement ID (the previous theme ran two independently, one may be legacy). */
class Bday_Vendor_Ga4 extends Bday_Vendor_Driver {

	public function slug(): string {
		return 'ga4';
	}

	public function is_configured(): bool {
		$opt = $this->option();
		return ! empty( $opt['enabled'] ) && ! empty( $opt['measurement_id'] );
	}

	public function settings_fields(): array {
		return array(
			array( 'key' => 'enabled', 'type' => 'checkbox', 'label' => 'Enable Google Analytics 4', 'default' => true, 'description' => 'Standard pageview/audience analytics — the main traffic-reporting tool most sites already use.' ),
			array( 'key' => 'measurement_id', 'type' => 'text', 'label' => 'Measurement ID', 'default' => 'G-KRZW6E45JP', 'description' => 'Found in the GA4 property\'s Admin → Data Streams settings, formatted like "G-XXXXXXX".' ),
			array( 'key' => 'measurement_id_secondary', 'type' => 'text', 'label' => 'Secondary measurement ID (optional)', 'default' => 'G-BS5YSBR9FP', 'description' => 'Leave blank if only one property is in use.' ),
		);
	}

	public function print_head(): void {
		$opt = $this->option();
		$ids = array_filter( array( $opt['measurement_id'] ?? '', $opt['measurement_id_secondary'] ?? '' ) );
		foreach ( $ids as $id ) {
			printf( '<script async src="https://www.googletagmanager.com/gtag/js?id=%1$s"></script>', esc_attr( $id ) );
			printf(
				'<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config","%1$s");</script>',
				esc_js( $id )
			);
		}
	}
}
