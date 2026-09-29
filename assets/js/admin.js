(function () {
	'use strict';

	const cfg = window.wpfvAdmin;
	const list = document.getElementById('wpfv-rows');
	if (!cfg || !list) return;

	const form = list.closest('form');
	const savers = new Set();
	let seq = 0;

	const h = (tag, props, ...kids) => {
		const node = document.createElement(tag);
		Object.entries(props || {}).forEach(([k, v]) => {
			if (k === 'class') node.className = v;
			else if (k.startsWith('on')) node.addEventListener(k.slice(2), v);
			else if (v !== false && v != null) node.setAttribute(k, v);
		});
		kids.flat().forEach((kid) => kid != null && node.append(kid));
		return node;
	};

	const cleanSlug = (v) =>
		v.toLowerCase().replace(/^\[?wpfv-/, '').replace(/[^a-z0-9_-]+/g, '-').replace(/^[-_]+|[-_]+$/g, '').slice(0, 60);

	const coerce = (type, v) => {
		if (type === 'image') return /^\d+$/.test(v) ? v : '';
		if (type === 'number') return isNaN(parseFloat(v)) ? '' : v;
		if (type === 'boolean') return v === '1' ? '1' : '0';
		if (type === 'color') return /^#[0-9a-f]{6}$/i.test(v) ? v : '#000000';
		return v;
	};

	function imageField(name, state) {
		const input = h('input', { type: 'hidden', name, value: state.value, 'data-val': '1' });
		const thumb = h('img', { class: 'wpfv-thumb', alt: '' });
		const remove = h('button', { type: 'button', class: 'button-link', onclick: () => {
			input.value = '';
			state.preview = '';
			sync();
		} }, cfg.i18n.remove);
		const pick = h('button', { type: 'button', class: 'button', onclick: () => {
			const frame = wp.media({ title: cfg.i18n.chooseImage, multiple: false, library: { type: 'image' } });
			frame.on('select', () => {
				const file = frame.state().get('selection').first().toJSON();
				input.value = file.id;
				state.preview = file.sizes && file.sizes.thumbnail ? file.sizes.thumbnail.url : file.url;
				sync();
			});
			frame.open();
		} }, cfg.i18n.select);

		function sync() {
			const has = !!input.value && !!state.preview;
			if (has) thumb.src = state.preview;
			thumb.style.display = has ? '' : 'none';
			remove.style.display = input.value ? '' : 'none';
		}
		sync();

		return h('div', { class: 'wpfv-image' }, input, thumb, pick, remove);
	}

	function buildField(type, state, name, id) {
		const attrs = { name, 'data-val': '1' };
		switch (type) {
			case 'textarea':
				return h('textarea', { ...attrs, rows: 4 }, state.value);
			case 'html':
				return h('textarea', { ...attrs, id: `wpfv-html-${id}`, rows: 8 }, state.value);
			case 'code':
				return h('textarea', { ...attrs, rows: 8 }, state.value);
			case 'image':
				return imageField(name, state);
			case 'url':
				return h('input', { ...attrs, type: 'url', value: state.value, placeholder: 'https://' });
			case 'number':
				return h('input', { ...attrs, type: 'number', step: 'any', value: state.value });
			case 'color':
				return h('input', { ...attrs, type: 'color', value: state.value || '#000000' });
			case 'boolean':
				return h('select', attrs,
					h('option', { value: '1', selected: state.value === '1' }, cfg.i18n.yes),
					h('option', { value: '0', selected: state.value !== '1' }, cfg.i18n.no));
			default:
				return h('input', { ...attrs, type: 'text', value: state.value });
		}
	}

	function addRow(data) {
		const id = ++seq;
		const field = (f) => `wpfv_variables[${id}][${f}]`;
		const state = { value: data.value || '', preview: data.preview || '' };
		let active = null;

		const slug = h('input', { type: 'text', name: field('slug'), value: data.slug || '', placeholder: 'myvariable', autocomplete: 'off', onblur: () => { slug.value = cleanSlug(slug.value); } });
		const type = h('select', { name: field('type'), onchange: () => mount(true) },
			Object.entries(cfg.types).map(([k, label]) => h('option', { value: k, selected: k === (data.type || 'text') }, label)));
		const copy = h('button', { type: 'button', class: 'button button-small', onclick: () => {
			navigator.clipboard.writeText(`[wpfv-${cleanSlug(slug.value)}]`).then(() => {
				copy.textContent = cfg.i18n.copied;
				setTimeout(() => { copy.textContent = cfg.i18n.copy; }, 1200);
			});
		} }, cfg.i18n.copy);
		const del = h('button', { type: 'button', class: 'button-link wpfv-del', onclick: () => {
			teardown();
			savers.delete(row);
			row.remove();
			emptyNote();
		} }, cfg.i18n.remove);
		const cell = h('div', { class: 'wpfv-value' });
		const row = h('div', { class: 'wpfv-row' },
			h('div', { class: 'wpfv-head' }, h('span', { class: 'wpfv-name' }, '[wpfv-', slug, ']'), type, copy, del),
			cell);

		const read = () => {
			const el = cell.querySelector('[data-val]');
			return el ? el.value : state.value;
		};

		function teardown() {
			if (!active) return;
			active.save();
			state.value = read();
			active.destroy();
			active = null;
		}

		function mount(typeChanged) {
			if (cell.firstChild && !active) state.value = read();
			teardown();
			if (typeChanged) state.value = coerce(type.value, state.value);

			cell.replaceChildren(buildField(type.value, state, field('value'), id));

			if (type.value === 'html') {
				const eid = `wpfv-html-${id}`;
				wp.editor.initialize(eid, { tinymce: { wpautop: true, height: 220 }, quicktags: true, mediaButtons: true });
				active = { save: () => window.tinymce && window.tinymce.triggerSave(), destroy: () => wp.editor.remove(eid) };
			} else if (type.value === 'code' && cfg.codeSettings) {
				const cm = wp.codeEditor.initialize(cell.querySelector('textarea'), cfg.codeSettings).codemirror;
				active = { save: () => cm.save(), destroy: () => cm.toTextArea() };
			}
		}

		row.wpfvSave = () => active && active.save();
		savers.add(row);
		list.append(row);
		emptyNote();
		mount(false);
		return { row, slug };
	}

	function emptyNote() {
		const note = list.querySelector('.wpfv-empty');
		const hasRows = list.querySelector('.wpfv-row');
		if (hasRows && note) note.remove();
		if (!hasRows && !note) list.append(h('p', { class: 'wpfv-empty' }, cfg.i18n.empty));
	}

	cfg.rows.forEach(addRow);
	emptyNote();

	document.getElementById('wpfv-add').addEventListener('click', () => addRow({}).slug.focus());
	form.addEventListener('submit', () => savers.forEach((row) => row.wpfvSave()));

	if (cfg.focus) {
		let target = [...list.querySelectorAll('.wpfv-row')].find((r) => r.querySelector('.wpfv-name input').value === cfg.focus);
		if (!target) {
			const created = addRow({ slug: cfg.focus });
			target = created.row;
		}
		target.classList.add('is-focus');
		target.scrollIntoView({ behavior: 'smooth', block: 'center' });
	}
})();
