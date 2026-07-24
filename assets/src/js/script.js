/**
 * BusinessDay Premium — theme JS. Dependency-free vanilla JS by design:
 * the previous theme's script was 100% jQuery syntax while a separate
 * PHP hook deregistered jQuery after enqueueing it, so none of it ran.
 * Nothing here needs a library.
 */

document.addEventListener('DOMContentLoaded', function () {
	// Sticky main menu
	var menu = document.querySelector('.main-menu');
	if (menu) {
		var menuOffset = menu.offsetTop;
		window.addEventListener(
			'scroll',
			function () {
				menu.classList.toggle('fixed', window.scrollY > menuOffset);
			},
			{ passive: true }
		);
	}

	// Desktop dropdown menus on hover
	document.querySelectorAll('.main-menu .dropdown').forEach(function (item) {
		item.addEventListener('mouseenter', function () {
			var dd = item.querySelector('.dropdown-menu');
			if (dd) dd.classList.add('show');
		});
		item.addEventListener('mouseleave', function () {
			var dd = item.querySelector('.dropdown-menu');
			if (dd) dd.classList.remove('show');
		});
	});

	// Lazy-loaded images (post_thumbnail_html filter emits data-src)
	var lazyImages = document.querySelectorAll('img.img-lazy-load');
	if ('IntersectionObserver' in window && lazyImages.length) {
		var imageObserver = new IntersectionObserver(function (entries, observer) {
			entries.forEach(function (entry) {
				if (!entry.isIntersecting) return;
				var img = entry.target;
				if (img.dataset.src) img.src = img.dataset.src;
				if (img.dataset.srcset) img.srcset = img.dataset.srcset;
				img.classList.remove('img-lazy-load');
				observer.unobserve(img);
			});
		});
		lazyImages.forEach(function (img) {
			imageObserver.observe(img);
		});
	}

	// Premium leaderboard rotation (addons/premium-leaderboard)
	document.querySelectorAll('.bday-leaderboard').forEach(function (board) {
		var slides = board.querySelectorAll('.bday-leaderboard__slide');
		if (slides.length < 2) return;
		var speed = parseInt(board.dataset.sliderSpeed, 10) || 20000;
		var current = 0;
		setInterval(function () {
			slides[current].style.display = 'none';
			current = (current + 1) % slides.length;
			slides[current].style.display = '';
		}, speed);
	});

	// News carousel (addons/news-carousel)
	document.querySelectorAll('.bday-news-carousel').forEach(function (carousel) {
		var track = carousel.querySelector('.bday-news-carousel__track');
		var prev = carousel.querySelector('.bday-news-carousel__prev');
		var next = carousel.querySelector('.bday-news-carousel__next');
		if (!track) return;

		function step() {
			var item = track.querySelector('.bday-news-carousel__item');
			return item ? item.offsetWidth + 20 : 300;
		}
		function scrollNext() {
			var atEnd = track.scrollLeft + track.offsetWidth >= track.scrollWidth - 10;
			if (atEnd) track.scrollTo({ left: 0, behavior: 'smooth' });
			else track.scrollBy({ left: step(), behavior: 'smooth' });
		}

		if (next) next.addEventListener('click', scrollNext);
		if (prev)
			prev.addEventListener('click', function () {
				if (track.scrollLeft <= 10) track.scrollTo({ left: track.scrollWidth, behavior: 'smooth' });
				else track.scrollBy({ left: -step(), behavior: 'smooth' });
			});

		var autoMs = parseInt(carousel.dataset.autoScroll, 10);
		if (autoMs) {
			var timer = setInterval(scrollNext, autoMs);
			carousel.addEventListener('mouseenter', function () {
				clearInterval(timer);
			});
			carousel.addEventListener('mouseleave', function () {
				timer = setInterval(scrollNext, autoMs);
			});
		}
	});

	// Deferred Disqus: only load once the reader scrolls halfway down an article
	var disqusMount = document.getElementById('disqus_thread');
	if (disqusMount) {
		var disqusLoaded = false;
		window.addEventListener(
			'scroll',
			function () {
				if (disqusLoaded) return;
				var max = document.documentElement.scrollHeight - document.documentElement.clientHeight;
				if (max > 0 && window.scrollY / max >= 0.5) {
					disqusLoaded = true;
					var s = document.createElement('script');
					s.id = 'disqus';
					s.src = 'https://businessday-ng.disqus.com/embed.js';
					s.setAttribute('data-timestamp', String(Date.now()));
					(document.head || document.body).appendChild(s);
				}
			},
			{ passive: true }
		);
	}
});
