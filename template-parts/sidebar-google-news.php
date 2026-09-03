<?php
/**
 * Reusable sidebar card: drives readers to follow BusinessDay on Google
 * News. Drop-in via get_template_part( 'template-parts/sidebar-google-news' )
 * from any sidebar widget area.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<style>
	.bday-gnews-card {
		background: #ffffff;
		border: 1px solid #e5e7eb;
		border-radius: 16px;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
		padding: 24px 20px;
		text-align: center;
	}
	.bday-gnews-card__logo {
		max-width: 180px;
		height: auto;
		margin: 0 auto 16px;
		display: block;
	}
	.bday-gnews-card__title {
		font-size: 22px;
		font-weight: 800;
		margin: 0 0 8px;
	}
	.bday-gnews-card__desc {
		font-size: 14px;
		line-height: 1.5;
		color: #4b5563;
		margin: 0 0 20px;
	}
	.bday-gnews-card__cta {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		padding: 10px 20px;
		border-radius: 50px;
		background: #ffffff;
		border: 1px solid #e5e7eb;
		text-decoration: none;
		transition: background-color 0.2s ease, box-shadow 0.2s ease;
	}
	.bday-gnews-card__cta:hover {
		background-color: #f9fafb;
		box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
	}
	.bday-gnews-card__cta-label {
		font-weight: 700;
		color: #b91c1c;
		font-size: 14px;
	}
</style>

<div class="bday-gnews-card">
	<img class="bday-gnews-card__logo" src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/businessday-banner-logo.jpg' ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
	<h3 class="bday-gnews-card__title">Stay Ahead with Us</h3>
	<p class="bday-gnews-card__desc">Follow us on Google News and never miss breaking stories, market insights, and in-depth reporting.</p>
	<a class="bday-gnews-card__cta" href="https://news.google.com/publications/CAAqKQgKIiNDQklTRkFnTWFoQUtEbUoxYzJsdVpYTnpaR0Y1TG01bktBQVAB?hl=en-NG&gl=NG&ceid=NG%3Aen" target="_blank" rel="noopener noreferrer">
		<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
			<path fill="#4285F4" d="M45.12 24.5c0-1.56-.14-3.06-.4-4.5H24v8.51h11.84c-.51 2.75-2.06 5.08-4.39 6.64v5.52h7.11c4.16-3.83 6.56-9.47 6.56-16.17z"/>
			<path fill="#34A853" d="M24 46c5.94 0 10.92-1.97 14.56-5.33l-7.11-5.52c-1.97 1.32-4.49 2.1-7.45 2.1-5.73 0-10.58-3.87-12.31-9.07H4.34v5.7C7.96 41.07 15.4 46 24 46z"/>
			<path fill="#FBBC05" d="M11.69 28.18A12.14 12.14 0 0 1 11.06 24c0-1.45.25-2.86.63-4.18v-5.7H4.34A21.94 21.94 0 0 0 2 24c0 3.55.85 6.91 2.34 9.88l7.35-5.7z"/>
			<path fill="#EA4335" d="M24 10.75c3.23 0 6.13 1.11 8.41 3.29l6.31-6.31C34.91 4.18 29.93 2 24 2 15.4 2 7.96 6.93 4.34 14.12l7.35 5.7c1.73-5.2 6.58-9.07 12.31-9.07z"/>
		</svg>
		<span class="bday-gnews-card__cta-label">Follow us on Google News</span>
	</a>
</div>
