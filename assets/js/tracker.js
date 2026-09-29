(function () {
	'use strict';

	const cfg = window.wpfvTracker;
	if (!cfg) return;

	const SHORTCODE = /\[wpfv-([a-z0-9][a-z0-9_-]*)\]/g;
	const SKIP = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA']);
	const pills = [];
	const attached = new WeakSet();
	let matches = [];
	let info = {};
	let busy = false;
	let queued = false;

	function frames(doc, chain, out) {
		out.push({ doc, chain });
		doc.querySelectorAll('iframe').forEach((frame) => {
			if (frame.contentDocument) frames(frame.contentDocument, chain.concat(frame), out);
		});
		return out;
	}

	function scan({ doc, chain }) {
		const found = [];
		if (!doc.body) return found;

		if (!attached.has(doc)) {
			attached.add(doc);
			doc.addEventListener('scroll', schedule, true);
			(doc.defaultView || window).addEventListener('resize', schedule);
		}

		const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT);
		for (let node = walker.nextNode(); node; node = walker.nextNode()) {
			if (!node.nodeValue.includes('[wpfv-') || SKIP.has(node.parentNode.nodeName)) continue;
			for (const m of node.nodeValue.matchAll(SHORTCODE)) {
				const range = doc.createRange();
				range.setStart(node, m.index);
				range.setEnd(node, m.index + m[0].length);
				found.push({ slug: m[1], range, chain });
			}
		}

		doc.querySelectorAll('input[type=text], textarea').forEach((el) => {
			if (!el.value || !el.value.includes('[wpfv-')) return;
			for (const m of el.value.matchAll(SHORTCODE)) found.push({ slug: m[1], el, chain });
		});

		return found;
	}

	function rectOf(match) {
		const box = match.range ? (match.range.getClientRects()[0] || match.range.getBoundingClientRect()) : match.el.getBoundingClientRect();
		if (!box || (!box.width && !box.height)) return null;

		let left = box.left;
		let top = box.top;
		match.chain.forEach((frame) => {
			const f = frame.getBoundingClientRect();
			left += f.left + frame.clientLeft;
			top += f.top + frame.clientTop;
		});
		return { left, top, bottom: top + box.height };
	}

	function linkFor(slug) {
		const url = new URL(cfg.editUrl, window.location.href);
		url.searchParams.set('wpfv_var', slug);
		return url.toString();
	}

	function render() {
		while (pills.length < matches.length) {
			const pill = document.createElement('a');
			pill.className = 'wpfv-pill';
			pill.target = '_blank';
			pill.rel = 'noopener';
			pill.addEventListener('mousedown', (e) => e.preventDefault());
			document.body.appendChild(pill);
			pills.push(pill);
		}
		while (pills.length > matches.length) pills.pop().remove();

		matches.forEach((match, i) => {
			const data = info[match.slug];
			const exists = !!(data && data.exists);
			const pill = pills[i];
			pill.href = linkFor(match.slug);
			pill.classList.toggle('is-new', !exists);
			pill.textContent = exists
				? `✎ ${cfg.i18n.edit} wpfv-${match.slug}${data.preview ? ' · ' + data.preview : ''}`
				: `+ ${cfg.i18n.create} wpfv-${match.slug}`;
		});
		place();
	}

	function place() {
		queued = false;
		matches.forEach((match, i) => {
			const pill = pills[i];
			const r = rectOf(match);
			if (!pill) return;
			if (!r || r.bottom < 0 || r.top > window.innerHeight) {
				pill.style.display = 'none';
				return;
			}
			const above = r.top > 32;
			pill.style.display = '';
			pill.style.left = Math.max(4, r.left) + 'px';
			pill.style.top = (above ? r.top - 4 : r.bottom + 4) + 'px';
			pill.style.transform = above ? 'translateY(-100%)' : '';
		});
	}

	function schedule() {
		if (queued) return;
		queued = true;
		window.requestAnimationFrame(place);
	}

	async function tick() {
		if (busy || document.hidden) return;
		busy = true;

		matches = frames(document, [], []).flatMap(scan);
		render();

		const slugs = [...new Set(matches.map((m) => m.slug))];
		if (slugs.length) {
			const body = new URLSearchParams({ action: cfg.action, nonce: cfg.nonce });
			slugs.forEach((s) => body.append('slugs[]', s));
			try {
				const res = await fetch(cfg.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' });
				const json = await res.json();
				if (json && json.success) {
					info = json.data;
					render();
				}
			} catch (e) {}
		}

		busy = false;
	}

	setInterval(tick, cfg.interval);
	tick();
})();
