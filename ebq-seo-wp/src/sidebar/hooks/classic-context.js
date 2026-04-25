/**
 * DOM-based editor context for the Classic Editor screen (post.php /
 * post-new.php without `is_block_editor()`). Same shape as the Gutenberg
 * adapter so the React app doesn't have to know which editor it's in.
 *
 * Reads:
 *   - Title  → #title.value
 *   - Slug   → #post_name.value (or fall back to permalink-display)
 *   - Permalink → #sample-permalink a (text shown under the title)
 *   - Content → tinymce.activeEditor.getContent() if visual mode is up,
 *               otherwise #content.value
 *
 * Writes (meta):
 *   - Each meta key has a corresponding hidden input rendered by PHP with
 *     name="ebq_<short>". setMeta() updates the input's value so the form
 *     POST picks it up — same save handler that the previous classic
 *     metabox used (EBQ_Seo_Fields_Meta_Box::save).
 */

import { useEffect, useState, useCallback } from '@wordpress/element';

/* ─── Singleton store ─────────────────────────────────────────── */

const state = {
	postId: 0,
	postTitle: '',
	postLink: '',
	slug: '',
	content: '',
	lang: '',
	meta: {},
	featuredImageUrl: '',
};

const listeners = new Set();
function notify() { listeners.forEach((fn) => fn()); }
function subscribe(fn) { listeners.add(fn); return () => listeners.delete(fn); }

let initialised = false;

/* ─── Hidden-input contract: meta key → form field name ──────── */

const META_TO_FIELD = {
	_ebq_title:               'ebq_title',
	_ebq_description:         'ebq_description',
	_ebq_canonical:           'ebq_canonical',
	_ebq_robots_noindex:      'ebq_robots_noindex',
	_ebq_robots_nofollow:     'ebq_robots_nofollow',
	_ebq_robots_advanced:     'ebq_robots_advanced',
	_ebq_focus_keyword:       'ebq_focus_keyword',
	_ebq_og_title:            'ebq_og_title',
	_ebq_og_description:      'ebq_og_description',
	_ebq_og_image:            'ebq_og_image',
	_ebq_twitter_title:       'ebq_twitter_title',
	_ebq_twitter_description: 'ebq_twitter_description',
	_ebq_twitter_image:       'ebq_twitter_image',
	_ebq_twitter_card:        'ebq_twitter_card',
	_ebq_schema_type:         'ebq_schema_type',
	_ebq_schema_disabled:     'ebq_schema_disabled',
	_ebq_schemas:             'ebq_schemas',
};

function findInput(metaKey) {
	const fieldName = META_TO_FIELD[metaKey];
	if (!fieldName) return null;
	return document.querySelector(`form#post input[name="${fieldName}"], form#post textarea[name="${fieldName}"]`);
}

function writeInput(metaKey, value) {
	const input = findInput(metaKey);
	if (!input) return;
	const next = typeof value === 'boolean' ? (value ? '1' : '') : (value == null ? '' : String(value));
	if (input.value !== next) {
		input.value = next;
	}
}

/* ─── Initial seed + DOM listeners ───────────────────────────── */

function readPostId() {
	const el = document.getElementById('post_ID');
	return el ? Number(el.value) || 0 : 0;
}

function readSlug() {
	const el = document.getElementById('post_name');
	if (el && el.value) return el.value;
	const display = document.getElementById('editable-post-name-full');
	if (display) return display.textContent.trim();
	return '';
}

function readPermalink() {
	const el = document.querySelector('#sample-permalink a');
	if (el) return el.getAttribute('href') || el.textContent.trim();
	const slug = readSlug();
	if (slug && typeof window !== 'undefined' && window.ebqSeoPublic?.homeUrl) {
		const home = String(window.ebqSeoPublic.homeUrl).replace(/\/$/, '');
		return `${home}/${slug}/`;
	}
	return '';
}

function readContent() {
	const tm = window.tinymce;
	if (tm && tm.activeEditor && !tm.activeEditor.isHidden && !tm.activeEditor.isHidden()) {
		try { return tm.activeEditor.getContent({ format: 'html' }) || ''; } catch { /* fallthrough */ }
	}
	const ta = document.getElementById('content');
	return ta ? ta.value : '';
}

function readTitle() {
	const el = document.getElementById('title');
	return el ? el.value : '';
}

function readFeaturedImage() {
	// WP renders the chosen featured image as `<img>` inside #set-post-thumbnail
	// (or #postimagediv .inside img). Pull whichever is present.
	const img = document.querySelector('#postimagediv #set-post-thumbnail img, #postimagediv .inside img');
	if (img && img.src) return img.src;
	return '';
}

function bootstrapState() {
	state.postId = readPostId();
	state.postTitle = readTitle();
	state.slug = readSlug();
	state.postLink = readPermalink();
	state.content = readContent();
	state.lang = (document.documentElement && document.documentElement.lang) || '';
	state.meta = (typeof window !== 'undefined' && window.ebqClassicMeta) ? { ...window.ebqClassicMeta } : {};
	state.featuredImageUrl = readFeaturedImage();
}

function attachListeners() {
	const titleEl = document.getElementById('title');
	if (titleEl) {
		titleEl.addEventListener('input', () => {
			state.postTitle = titleEl.value;
			notify();
		});
	}

	const slugEl = document.getElementById('post_name');
	if (slugEl) {
		slugEl.addEventListener('change', () => {
			state.slug = slugEl.value;
			state.postLink = readPermalink();
			notify();
		});
	}

	// Watch the displayed permalink — WP updates this when the user edits the
	// inline permalink editor. A MutationObserver is the only reliable signal.
	const permalinkEl = document.querySelector('#edit-slug-box');
	if (permalinkEl && typeof MutationObserver !== 'undefined') {
		const mo = new MutationObserver(() => {
			state.slug = readSlug();
			state.postLink = readPermalink();
			notify();
		});
		mo.observe(permalinkEl, { childList: true, subtree: true, characterData: true });
	}

	// Watch the featured image metabox — picking / clearing an image swaps
	// the inner HTML of #postimagediv via WP's media frame.
	const featuredEl = document.getElementById('postimagediv');
	if (featuredEl && typeof MutationObserver !== 'undefined') {
		const mo = new MutationObserver(() => {
			const next = readFeaturedImage();
			if (next !== state.featuredImageUrl) {
				state.featuredImageUrl = next;
				notify();
			}
		});
		mo.observe(featuredEl, { childList: true, subtree: true, attributes: true, attributeFilter: ['src'] });
	}

	// Content: TinyMCE in visual mode, textarea in HTML mode.
	const textarea = document.getElementById('content');
	if (textarea) {
		textarea.addEventListener('input', () => {
			state.content = textarea.value;
			notify();
		});
	}

	let debounceTimer = null;
	const tinymceContentChanged = () => {
		if (debounceTimer) clearTimeout(debounceTimer);
		debounceTimer = setTimeout(() => {
			state.content = readContent();
			notify();
		}, 250);
	};

	const wireTinymce = (tm) => {
		if (!tm) return;
		const wireEditor = (ed) => {
			if (!ed || ed.id !== 'content') return;
			ed.on('NodeChange Change KeyUp Undo Redo SetContent', tinymceContentChanged);
		};
		// Already-mounted editors first.
		if (Array.isArray(tm.editors)) {
			tm.editors.forEach(wireEditor);
		}
		// Future ones (mode toggle, tabbed editor, etc.).
		try { tm.on('AddEditor', (e) => wireEditor(e.editor)); } catch { /* old TinyMCE — skip */ }
	};

	if (window.tinymce) {
		wireTinymce(window.tinymce);
	} else {
		// TinyMCE loads after the metabox in some setups — poll briefly.
		let attempts = 0;
		const waitTm = setInterval(() => {
			if (window.tinymce) {
				wireTinymce(window.tinymce);
				clearInterval(waitTm);
			} else if (++attempts > 40) {
				clearInterval(waitTm);
			}
		}, 200);
	}
}

function ensureInitialised() {
	if (initialised) return;
	initialised = true;
	bootstrapState();
	attachListeners();
}

/* ─── React hooks ─────────────────────────────────────────────── */

function useSnapshot() {
	const [snap, setSnap] = useState(() => {
		ensureInitialised();
		return { ...state };
	});
	useEffect(() => subscribe(() => setSnap({ ...state })), []);
	return snap;
}

export function useEditorContext() {
	return useSnapshot();
}

export function usePostMeta() {
	const snap = useSnapshot();

	const get = useCallback(
		(key, fallback = '') => {
			const v = snap.meta?.[key];
			if (v === undefined || v === null || v === '') return fallback;
			return v;
		},
		[snap.meta]
	);

	const set = useCallback((key, value) => {
		state.meta = { ...state.meta, [key]: value };
		writeInput(key, value);
		notify();
	}, []);

	return { meta: snap.meta, get, set };
}

export function publicConfig() {
	if (typeof window === 'undefined') {
		return { sep: '–', siteName: '', appBase: '', homeUrl: '', isConnected: true, settingsUrl: '', workspaceDomain: '' };
	}
	const cfg = window.ebqSeoPublic || {};
	return {
		sep: cfg.titleSep || '–',
		siteName: cfg.siteName || '',
		appBase: (cfg.appBase || '').replace(/\/$/, ''),
		homeUrl: cfg.homeUrl || '',
		isConnected: cfg.isConnected !== false,
		settingsUrl: cfg.settingsUrl || '',
		workspaceDomain: cfg.workspaceDomain || '',
	};
}

export function resolveTitleTemplate(template, { postTitle, sep, siteName }) {
	if (!template || !String(template).includes('%%')) {
		return template || '';
	}
	return String(template)
		.replace(/%%title%%/g, postTitle || '')
		.replace(/%%sep%%/g, sep || '–')
		.replace(/%%sitename%%/g, siteName || '')
		.replace(/%%page%%/g, '')
		.replace(/\s+/g, ' ')
		.trim();
}
