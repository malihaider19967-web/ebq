import { useSelect, useDispatch, useRegistry } from '@wordpress/data';
import { useCallback } from '@wordpress/element';

/** One-stop hook for everything the sidebar needs from the editor store. */
export function useEditorContext() {
	const ctx = useSelect((select) => {
		const editor = select('core/editor');
		const core = select('core');
		const meta = editor.getEditedPostAttribute('meta') || {};
		const featuredId = editor.getEditedPostAttribute('featured_media');
		const media = featuredId ? core.getMedia(featuredId) : null;
		const featuredImageUrl =
			media?.media_details?.sizes?.medium_large?.source_url ||
			media?.media_details?.sizes?.medium?.source_url ||
			media?.source_url ||
			'';
		return {
			postId: editor.getCurrentPostId(),
			postTitle: editor.getEditedPostAttribute('title') || '',
			postLink: editor.getEditedPostAttribute('link') || '',
			slug: editor.getEditedPostAttribute('slug') || '',
			content: editor.getEditedPostContent(),
			meta,
			lang: editor.getCurrentPost()?.lang || '',
			featuredImageUrl,
		};
	}, []);
	return ctx;
}

/** Read/write a single `_ebq_*` meta field. */
export function usePostMeta() {
	const registry = useRegistry();
	const editPost = useDispatch('core/editor').editPost;
	const meta = useSelect((select) => select('core/editor').getEditedPostAttribute('meta') || {}, []);

	const get = useCallback(
		(key, fallback = '') => {
			const v = meta?.[key];
			return v === undefined || v === '' || v === null ? fallback : v;
		},
		[meta]
	);

	const set = useCallback(
		(key, value) => {
			const current = registry.select('core/editor').getEditedPostAttribute('meta') || {};
			editPost({ meta: { ...current, [key]: value } });
		},
		[editPost, registry]
	);

	return { meta, get, set };
}

/** %%title%% / %%sep%% / %%sitename%% / %%page%% resolver — mirrors PHP. */
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
		// `isConnected` defaults to true so the banner doesn't flash on
		// older builds that don't yet localize the flag.
		isConnected: cfg.isConnected !== false,
		settingsUrl: cfg.settingsUrl || '',
		workspaceDomain: cfg.workspaceDomain || '',
	};
}
