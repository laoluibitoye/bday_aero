<footer class="bday-site-footer py-3 bg-dark">
	<div class="py-3 bg-dark text-white">
		<div class="container">
			<div class="row">
				<div class="col-md-3">
					<img src="<?php echo esc_url( BDAY_THEME_URI . 'assets/build/images/businessday.png' ); ?>" class="w-100" alt="BusinessDay logo">
					<div class="bday-site-footer__about mt-3">
						Business Day, established in 2001, is a daily business newspaper based in Lagos. It is the only Nigerian newspaper with a bureau in Accra, Ghana. It has both daily and Sunday titles. It circulates in Nigeria and Ghana...
						<a class="text-decoration-none text-white" href="https://about.businessday.ng/index.php">Read More...</a>
						Phone: +234-803-322-5506 | +234-802-601-1296 | +234-813-346-4051
					</div>
				</div>

				<div class="col-md-9">
					<div class="row mt-4">
						<div class="col-md-1"></div>
						<div class="col-md-3 d-none d-md-block">
							<h5>The Company</h5>
							<ul class="list-unstyled">
								<li><a href="https://about.businessday.ng/index.php" class="text-decoration-none text-white">About Us</a></li>
								<li><a href="https://pro.businessday.ng/" class="text-decoration-none text-white">BusinessDay Pro</a></li>
								<li><a href="https://businessdayintelligence.ng/" class="text-decoration-none text-white">Research &amp; Insight</a></li>
								<li><a href="https://conferences.businessday.ng/" class="text-decoration-none text-white">Conferences</a></li>
								<li><a href="https://currency.businessday.ng/" class="text-decoration-none text-white">BD Fx</a></li>
							</ul>
						</div>
						<div class="col-md-3 d-none d-md-block">
							<h5>Legal &amp; Privacy</h5>
							<ul class="list-unstyled">
								<li><a href="<?php echo esc_url( home_url( '/app-privacy-policy/' ) ); ?>" class="text-decoration-none text-white">Privacy Policy</a></li>
								<li><a href="<?php echo esc_url( home_url( '/copyright/' ) ); ?>" class="text-decoration-none text-white">Copyright</a></li>
							</ul>
						</div>
						<div class="col-md-3 d-none d-md-block">
							<h5>Quick Links</h5>
							<ul class="list-unstyled">
								<li><a href="<?php echo esc_url( home_url( '/advert-and-rates/' ) ); ?>" class="text-decoration-none text-white">Adverts &amp; Rates</a></li>
								<li><a href="<?php echo esc_url( home_url( '/category/companies/' ) ); ?>" class="text-decoration-none text-white">Companies</a></li>
								<li><a href="<?php echo esc_url( home_url( '/category/markets/' ) ); ?>" class="text-decoration-none text-white">Market</a></li>
								<li><a href="<?php echo esc_url( home_url( '/category/business-economy/' ) ); ?>" class="text-decoration-none text-white">Economy</a></li>
							</ul>
						</div>
						<div class="col-md-2 d-none d-md-block">
							<h5>Support</h5>
							<ul class="list-unstyled">
								<li><a href="mailto:digitalsales@businessday.ng" class="text-decoration-none text-white">digitalsales@businessday.ng</a></li>
								<li><a href="tel:+2348033225506" class="text-decoration-none text-white">+2348033225506</a></li>
								<li><a href="tel:+2348026011296" class="text-decoration-none text-white">+2348026011296</a></li>
								<li><a href="tel:+2348133464051" class="text-decoration-none text-white">+2348133464051</a></li>
							</ul>
						</div>
					</div>
					<div class="bday-site-footer__copyright mt-3 pt-3 border-top border-secondary text-center">
						&copy; BUSINESSDAY MEDIA LTD <?php echo esc_html( date_i18n( 'Y' ) ); ?>.
					</div>
				</div>
			</div>
		</div>
	</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous" defer></script>
<?php wp_footer(); ?>
</body>
</html>
