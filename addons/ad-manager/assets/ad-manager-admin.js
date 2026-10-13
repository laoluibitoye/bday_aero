(function () {
	'use strict';

	var units = document.querySelector('[data-bday-units]');

	/* Show only the sections that belong to a unit's chosen partner. */
	function syncPartner(card) {
		var sel = card.querySelector('select[name$="[partner]"]');
		if (!sel) { return; }
		card.querySelectorAll('[data-partner-section]').forEach(function (sec) {
			sec.classList.toggle('is-on', sec.getAttribute('data-partner-section') === sel.value);
		});
	}

	function nextIndex(container, attr) {
		var max = -1;
		container.querySelectorAll('[name]').forEach(function (el) {
			var m = el.name.match(/\[(\d+)\]/);
			if (m) { max = Math.max(max, parseInt(m[1], 10)); }
		});
		return max + 1;
	}

	document.querySelectorAll('[data-bday-unit]').forEach(syncPartner);

	document.addEventListener('change', function (e) {
		if (e.target.matches('select[name$="[partner]"]')) { syncPartner(e.target.closest('[data-bday-unit]')); }
	});

	document.addEventListener('click', function (e) {
		var t = e.target;

		if (t.matches('[data-add-unit]')) {
			e.preventDefault();
			var tpl = document.querySelector('[data-unit-template]');
			var html = tpl.innerHTML.replace(/__i__/g, nextIndex(units));
			units.insertAdjacentHTML('beforeend', html);
			var card = units.lastElementChild;
			card.open = true;
			syncPartner(card);
			card.scrollIntoView({ behavior: 'smooth', block: 'center' });
		}

		if (t.matches('[data-remove-unit]')) {
			e.preventDefault();
			if (window.confirm('Delete this ad unit? Placements using it will stop showing it once you save.')) {
				t.closest('[data-bday-unit]').remove();
			}
		}

		if (t.matches('[data-add-creative]')) {
			e.preventDefault();
			var card2 = t.closest('[data-bday-unit]');
			var list = card2.querySelector('[data-creatives]');
			var ctpl = card2.querySelector('[data-creative-template]');
			list.insertAdjacentHTML('beforeend', ctpl.innerHTML.replace(/__j__/g, nextIndex(list)));
		}

		if (t.matches('[data-remove-creative]')) {
			e.preventDefault();
			t.closest('[data-creative]').remove();
		}

		if (t.matches('[data-media-pick]')) {
			e.preventDefault();
			var field = t.closest('[data-media-field]');
			var frame = wp.media({ title: 'Select banner image', multiple: false, library: { type: 'image' } });
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				field.querySelector('[data-media-input]').value = a.id;
				var url = field.querySelector('[data-media-url]');
				if (url) { url.value = ''; }
				var src = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : a.url;
				field.querySelector('.bday-creative__preview').innerHTML =
					'<img src="' + src + '" alt=""><small>' + a.width + '×' + a.height + '</small>';
			});
			frame.open();
		}

		if (t.matches('[data-media-clear]')) {
			e.preventDefault();
			var f = t.closest('[data-media-field]');
			f.querySelector('[data-media-input]').value = '';
			var u = f.querySelector('[data-media-url]');
			if (u) { u.value = ''; }
			f.querySelector('.bday-creative__preview').innerHTML = '';
		}
	});
})();
