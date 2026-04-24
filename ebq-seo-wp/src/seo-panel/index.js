/**
 * EBQ SEO — Gutenberg document panel (SEO fields, SERP preview, content analysis).
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect, useMemo, useCallback, Fragment } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import {
	TextControl,
	TextareaControl,
	ToggleControl,
	SelectControl,
	PanelBody,
	Spinner,
} from '@wordpress/components';
import { analyzeSeoContent } from './contentAnalysis';
import { analyzeReadability } from './readability';

function truncate(str, max) {
	if (!str) {
		return '';
	}
	const s = String(str);
	return s.length <= max ? s : s.slice(0, max - 1) + '…';
}

function useDebounced(value, delayMs) {
	const [debounced, setDebounced] = useState(value);
	useEffect(() => {
		const t = setTimeout(() => setDebounced(value), delayMs);
		return () => clearTimeout(t);
	}, [value, delayMs]);
	return debounced;
}

function resolveTitleTemplate(template, { postTitle, sep, siteName }) {
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

function SerpPreview({ url, title, description }) {
	return (
		<div
			style={{
				border: '1px solid #e2e8f0',
				borderRadius: 8,
				padding: 12,
				background: '#fff',
			}}
		>
			<p
				style={{
					margin: '0 0 6px',
					fontSize: 10,
					color: '#64748b',
					textTransform: 'uppercase',
					letterSpacing: '.08em',
				}}
			>
				{__('Google preview', 'ebq-seo')}
			</p>
			<p style={{ margin: 0, fontSize: 12, color: '#3c4043' }}>{truncate(url, 90)}</p>
			<p style={{ margin: '4px 0', fontSize: 16, color: '#1a0dab', lineHeight: 1.2, fontWeight: 500 }}>
				{truncate(title || __('(SEO title)', 'ebq-seo'), 60)}
			</p>
			<p style={{ margin: 0, fontSize: 13, color: '#4d5156', lineHeight: 1.45 }}>
				{truncate(description || __('(meta description)', 'ebq-seo'), 160)}
			</p>
		</div>
	);
}

function CompetitorSerp({ postId, query }) {
	const debouncedQuery = useDebounced(query, 400);
	const [state, setState] = useState({ loading: false, data: null, error: null });

	useEffect(() => {
		if (!postId || !debouncedQuery) {
			setState({ loading: false, data: null, error: null });
			return;
		}
		let cancelled = false;
		setState({ loading: true, data: null, error: null });
		apiFetch({
			path: `/ebq/v1/serp-preview/${postId}?query=${encodeURIComponent(debouncedQuery)}`,
		})
			.then((data) => {
				if (!cancelled) {
					setState({ loading: false, data, error: null });
				}
			})
			.catch((err) => {
				if (!cancelled) {
					setState({ loading: false, data: null, error: err?.message || 'fetch_failed' });
				}
			});
		return () => {
			cancelled = true;
		};
	}, [postId, debouncedQuery]);

	if (!query) {
		return null;
	}
	if (state.loading) {
		return (
			<div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 11, color: '#64748b', padding: 8 }}>
				<Spinner />
				{__('Loading competitor SERP…', 'ebq-seo')}
			</div>
		);
	}
	if (state.error) {
		return null;
	}
	const data = state.data || {};
	if (!data.matched) {
		return (
			<p style={{ fontSize: 11, color: '#64748b', marginTop: 8 }}>
				{__('Add this keyword to EBQ Rank Tracking to see who ranks for it.', 'ebq-seo')}
			</p>
		);
	}
	if (!data.results || !data.results.length) {
		return (
			<p style={{ fontSize: 11, color: '#64748b', marginTop: 8 }}>
				{__('No snapshot yet — run the rank tracker to populate competitor results.', 'ebq-seo')}
			</p>
		);
	}
	return (
		<div style={{ marginTop: 10, border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, background: '#f8fafc' }}>
			<p
				style={{
					margin: '0 0 6px',
					fontSize: 10,
					color: '#64748b',
					textTransform: 'uppercase',
					letterSpacing: '.08em',
				}}
			>
				{__('Actual competitors', 'ebq-seo')} · &quot;{debouncedQuery}&quot;
			</p>
			<ol style={{ paddingLeft: 18, margin: 0 }}>
				{data.results.map((r, i) => (
					<li key={i} style={{ marginBottom: 6, fontSize: 12 }}>
						<div style={{ color: '#1a0dab', fontWeight: 500 }}>{truncate(r.title, 80)}</div>
						<div style={{ color: '#3c4043', fontSize: 11 }}>{truncate(r.url, 80)}</div>
					</li>
				))}
			</ol>
		</div>
	);
}

function SocialPreview({ style, title, description, image }) {
	return (
		<div style={{ border: '1px solid #e2e8f0', borderRadius: 8, overflow: 'hidden', background: '#fff' }}>
			{image ? (
				<div
					style={{
						background: '#f1f5f9',
						height: 120,
						backgroundImage: `url(${image})`,
						backgroundSize: 'cover',
						backgroundPosition: 'center',
					}}
				/>
			) : (
				<div
					style={{
						background: '#e2e8f0',
						height: 60,
						display: 'flex',
						alignItems: 'center',
						justifyContent: 'center',
						fontSize: 10,
						color: '#94a3b8',
					}}
				>
					{__('(no image)', 'ebq-seo')}
				</div>
			)}
			<div style={{ padding: 10 }}>
				<p
					style={{
						margin: '0 0 2px',
						fontSize: 10,
						color: '#64748b',
						textTransform: 'uppercase',
						letterSpacing: '.06em',
					}}
				>
					{style === 'twitter' ? __('Twitter / X', 'ebq-seo') : __('Facebook / LinkedIn', 'ebq-seo')}
				</p>
				<p style={{ margin: 0, fontSize: 13, fontWeight: 600, color: '#0f172a' }}>{truncate(title, 70)}</p>
				<p style={{ margin: '2px 0 0', fontSize: 11, color: '#475569' }}>{truncate(description, 120)}</p>
			</div>
		</div>
	);
}

const suggestionsCache = {};

function FocusKeywordSelect({ postId, value, onChange }) {
	const [state, setState] = useState(() => {
		const cached = suggestionsCache[postId];
		return cached ? { loading: false, suggestions: cached, error: null } : { loading: true, suggestions: [], error: null };
	});

	useEffect(() => {
		if (!postId || suggestionsCache[postId]) {
			return;
		}
		let cancelled = false;
		apiFetch({ path: `/ebq/v1/focus-keyword-suggestions/${postId}` })
			.then((data) => {
				if (cancelled) {
					return;
				}
				const list = (data && data.suggestions) || [];
				suggestionsCache[postId] = list;
				setState({ loading: false, suggestions: list, error: null });
			})
			.catch((err) => {
				if (cancelled) {
					return;
				}
				setState({ loading: false, suggestions: [], error: err?.message || 'fetch_failed' });
			});
		return () => {
			cancelled = true;
		};
	}, [postId]);

	const options = useMemo(() => {
		const opts = [{ label: __('— select a query —', 'ebq-seo'), value: '' }];
		state.suggestions.forEach((row) => {
			opts.push({
				label: `${row.query} (#${row.position || '?'} · ${row.impressions} impr)`,
				value: row.query,
			});
		});
		if (value && !state.suggestions.some((r) => r.query === value)) {
			opts.push({ label: `${value} (custom)`, value });
		}
		return opts;
	}, [state.suggestions, value]);

	return (
		<Fragment>
			<SelectControl
				label={__('Focus keyword (from your real GSC data)', 'ebq-seo')}
				value={value || ''}
				options={options}
				onChange={onChange}
				help={
					state.loading
						? __('Loading queries from EBQ…', 'ebq-seo')
						: state.suggestions.length === 0
							? __('No GSC queries yet for this URL. Type a target keyword below.', 'ebq-seo')
							: __('Sorted by opportunity score (impressions × rank headroom).', 'ebq-seo')
				}
			/>
			<TextControl
				label={__('Or type a custom keyword', 'ebq-seo')}
				value={value || ''}
				onChange={onChange}
			/>
		</Fragment>
	);
}

function CheckRow({ ok, label }) {
	const color = ok ? '#047857' : '#b45309';
	return (
		<li style={{ fontSize: 12, marginBottom: 4, color }}>
			{ok ? '✓ ' : '○ '}
			{label}
		</li>
	);
}

function ContentAnalysisBlock({ ctx, get, homeUrl }) {
	const rawContent = useSelect((select) => select('core/editor').getEditedPostContent(), []);
	const debouncedContent = useDebounced(rawContent, 400);

	const sep =
		(typeof window !== 'undefined' && window.ebqSeoPublic && window.ebqSeoPublic.titleSep) || '–';
	const siteName =
		(typeof window !== 'undefined' && window.ebqSeoPublic && window.ebqSeoPublic.siteName) || '';

	const seoTitleRaw = get('_ebq_title', '');
	const seoTitleResolved = resolveTitleTemplate(seoTitleRaw, {
		postTitle: ctx.postTitle,
		sep,
		siteName,
	});
	const effectiveSeoTitle = seoTitleResolved || ctx.postTitle;

	const metaDesc = get('_ebq_description', '');
	const focusKw = get('_ebq_focus_keyword', '');
	const analysis = useMemo(
		() =>
			analyzeSeoContent({
				serializedContent: debouncedContent,
				postTitle: ctx.postTitle,
				seoTitleResolved: effectiveSeoTitle,
				metaDescription: metaDesc,
				slug: ctx.slug,
				focusKeyword: focusKw,
				homeUrl: homeUrl || (typeof window !== 'undefined' ? window.location.origin : ''),
			}),
		[debouncedContent, ctx.postTitle, ctx.slug, effectiveSeoTitle, metaDesc, focusKw, homeUrl]
	);

	const kw = focusKw;
	if (!kw) {
		return <p style={{ fontSize: 12, color: '#64748b' }}>{__('Set a focus keyword to run on-page checks.', 'ebq-seo')}</p>;
	}

	return (
		<ul style={{ margin: '8px 0 0', paddingLeft: 18 }}>
			<CheckRow ok={analysis.inTitle} label={__('Keyphrase in SEO title', 'ebq-seo')} />
			<CheckRow ok={analysis.inMetaDescription} label={__('Keyphrase in meta description', 'ebq-seo')} />
			<CheckRow ok={analysis.inFirstParagraph} label={__('Keyphrase in first paragraph', 'ebq-seo')} />
			<CheckRow ok={analysis.inHeading} label={__('Keyphrase in an H2 or H3', 'ebq-seo')} />
			<CheckRow ok={analysis.inSlug} label={__('Keyphrase in URL slug', 'ebq-seo')} />
			<CheckRow ok={analysis.internalLinks >= 1} label={__('At least one internal link', 'ebq-seo')} />
			<CheckRow ok={analysis.externalLinks >= 1} label={__('At least one external link', 'ebq-seo')} />
			<CheckRow ok={analysis.imageAltHasKeyphrase} label={__('Image alt includes keyphrase', 'ebq-seo')} />
			<CheckRow ok={analysis.meetsMinWords} label={sprintf(__('At least 300 words (have %d)', 'ebq-seo'), analysis.wordCount)} />
			<li style={{ fontSize: 11, color: '#64748b', marginTop: 6, listStyle: 'none', marginLeft: -18 }}>
				{sprintf(__('Estimated density: %s%%', 'ebq-seo'), String(analysis.densityPercent))}
			</li>
		</ul>
	);
}

function ReadabilityPanel({ plainText, locale }) {
	return (
		<PanelBody title={__('Readability', 'ebq-seo')} initialOpen={false}>
			<ReadabilityBody plainText={plainText} locale={locale} />
		</PanelBody>
	);
}

function ReadabilityBody({ plainText, locale }) {
	const res = useMemo(() => analyzeReadability(plainText, locale), [plainText, locale]);
	if (!res.available) {
		return (
			<p style={{ fontSize: 12, color: '#64748b' }}>
				{res.reason === 'not_english'
					? __('Readability scoring is optimized for English.', 'ebq-seo')
					: __('Add more sentences for a readability score.', 'ebq-seo')}
			</p>
		);
	}
	return (
		<ul style={{ margin: 0, paddingLeft: 18, fontSize: 12 }}>
			<li>{sprintf(__('Flesch Reading Ease: %s', 'ebq-seo'), String(res.flesch))}</li>
			<li>{sprintf(__('Sentences over 20 words: %s%%', 'ebq-seo'), String(res.longSentencePercent))}</li>
			<li>{sprintf(__('Passive voice (approx): %s%%', 'ebq-seo'), String(res.passiveVoicePercent))}</li>
			<li>{sprintf(__('Sentences with transition words: %s%%', 'ebq-seo'), String(res.transitionPercent))}</li>
		</ul>
	);
}

function Panel() {
	const ctx = useSelect((select) => {
		const editor = select('core/editor');
		const meta = editor.getEditedPostAttribute('meta') || {};
		return {
			postId: editor.getCurrentPostId(),
			postTitle: editor.getEditedPostAttribute('title') || '',
			postLink: editor.getEditedPostAttribute('link') || '',
			slug: editor.getEditedPostAttribute('slug') || '',
			meta,
			locale: editor.getCurrentPost()?.lang || '',
		};
	}, []);

	const editPost = useDispatch('core/editor').editPost;

	const setMeta = useCallback(
		(key, value) => {
			const patch = {};
			patch[key] = value;
			editPost({ meta: patch });
		},
		[editPost]
	);

	const get = useCallback((key, fallback) => {
		const v = ctx.meta && ctx.meta[key];
		return v === undefined || v === '' || v === null ? (fallback !== undefined ? fallback : '') : v;
	}, [ctx.meta]);

	const sep =
		(typeof window !== 'undefined' && window.ebqSeoPublic && window.ebqSeoPublic.titleSep) || '–';
	const siteName =
		(typeof window !== 'undefined' && window.ebqSeoPublic && window.ebqSeoPublic.siteName) || '';

	const seoTitleRaw = get('_ebq_title', '');
	const effectiveTitle =
		resolveTitleTemplate(seoTitleRaw, { postTitle: ctx.postTitle, sep, siteName }) || ctx.postTitle;
	const effectiveDescription = get('_ebq_description', '');
	const effectiveUrl = get('_ebq_canonical', ctx.postLink);
	const effectiveOgTitle = get('_ebq_og_title', effectiveTitle);
	const effectiveOgDesc = get('_ebq_og_description', effectiveDescription);
	const effectiveOgImage = get('_ebq_og_image', '');
	const effectiveTwTitle = get('_ebq_twitter_title', effectiveOgTitle);
	const effectiveTwDesc = get('_ebq_twitter_description', effectiveOgDesc);
	const effectiveTwImage = get('_ebq_twitter_image', effectiveOgImage);

	const focusKeyword = get('_ebq_focus_keyword', '');

	const plainForReadability = useSelect((select) => {
		const html = select('core/editor').getEditedPostContent() || '';
		return html.replace(/<!--[\s\S]*?-->/g, ' ').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
	}, []);

	const homeUrl =
		(typeof window !== 'undefined' && window.ebqSeoPublic && window.ebqSeoPublic.homeUrl) || '';

	return (
		<Fragment>
			<PanelBody title={__('Search', 'ebq-seo')} initialOpen={true}>
				<FocusKeywordSelect
					postId={ctx.postId}
					value={focusKeyword}
					onChange={(v) => setMeta('_ebq_focus_keyword', v)}
				/>
				<TextControl
					label={__('SEO title', 'ebq-seo')}
					value={get('_ebq_title', '')}
					onChange={(v) => setMeta('_ebq_title', v)}
					help={
						<>
							{String(get('_ebq_title', '')).length}/60 {__('characters', 'ebq-seo')}
							<br />
							{__('Variables:', 'ebq-seo')} <code>%%title%%</code> <code>%%sep%%</code> <code>%%sitename%%</code>
						</>
					}
				/>
				<TextareaControl
					label={__('Meta description', 'ebq-seo')}
					value={get('_ebq_description', '')}
					onChange={(v) => setMeta('_ebq_description', v)}
					help={`${String(get('_ebq_description', '')).length}/160 ${__('characters', 'ebq-seo')}`}
				/>
				<TextControl
					label={__('Canonical URL', 'ebq-seo')}
					value={get('_ebq_canonical', '')}
					onChange={(v) => setMeta('_ebq_canonical', v)}
					placeholder={ctx.postLink}
				/>
				<p style={{ fontSize: 11, color: '#64748b', margin: '4px 0 8px' }}>
					{sprintf(__('URL slug: %s', 'ebq-seo'), ctx.slug || '—')}
				</p>
				<div style={{ display: 'flex', gap: 16, margin: '8px 0', flexWrap: 'wrap' }}>
					<ToggleControl
						label={__('noindex', 'ebq-seo')}
						checked={!!get('_ebq_robots_noindex', false)}
						onChange={(v) => setMeta('_ebq_robots_noindex', v)}
					/>
					<ToggleControl
						label={__('nofollow', 'ebq-seo')}
						checked={!!get('_ebq_robots_nofollow', false)}
						onChange={(v) => setMeta('_ebq_robots_nofollow', v)}
					/>
				</div>
				<TextControl
					label={__('Advanced robots (comma-separated)', 'ebq-seo')}
					value={get('_ebq_robots_advanced', '')}
					onChange={(v) => setMeta('_ebq_robots_advanced', v)}
					placeholder="noarchive, nosnippet"
					help={__('Merged into the robots meta tag after index/noindex.', 'ebq-seo')}
				/>
				<div style={{ marginTop: 10 }}>
					<SerpPreview title={effectiveTitle} description={effectiveDescription} url={effectiveUrl} />
				</div>
				<CompetitorSerp postId={ctx.postId} query={focusKeyword} />
			</PanelBody>
			<PanelBody title={__('Content analysis', 'ebq-seo')} initialOpen={false}>
				<ContentAnalysisBlock ctx={ctx} get={get} homeUrl={homeUrl} />
			</PanelBody>
			<ReadabilityPanel plainText={plainForReadability} locale={ctx.locale} />
			<PanelBody title={__('Social', 'ebq-seo')} initialOpen={false}>
				<TextControl label={__('OG title', 'ebq-seo')} value={get('_ebq_og_title', '')} onChange={(v) => setMeta('_ebq_og_title', v)} />
				<TextareaControl
					label={__('OG description', 'ebq-seo')}
					value={get('_ebq_og_description', '')}
					onChange={(v) => setMeta('_ebq_og_description', v)}
				/>
				<TextControl label={__('OG image URL', 'ebq-seo')} value={get('_ebq_og_image', '')} onChange={(v) => setMeta('_ebq_og_image', v)} />
				<div style={{ marginTop: 8 }}>
					<SocialPreview style="og" title={effectiveOgTitle} description={effectiveOgDesc} image={effectiveOgImage} />
				</div>
				<hr style={{ margin: '12px 0', border: 'none', borderTop: '1px solid #e2e8f0' }} />
				<TextControl
					label={__('Twitter title', 'ebq-seo')}
					value={get('_ebq_twitter_title', '')}
					onChange={(v) => setMeta('_ebq_twitter_title', v)}
				/>
				<TextareaControl
					label={__('Twitter description', 'ebq-seo')}
					value={get('_ebq_twitter_description', '')}
					onChange={(v) => setMeta('_ebq_twitter_description', v)}
				/>
				<TextControl
					label={__('Twitter image URL', 'ebq-seo')}
					value={get('_ebq_twitter_image', '')}
					onChange={(v) => setMeta('_ebq_twitter_image', v)}
				/>
				<div style={{ marginTop: 8 }}>
					<SocialPreview style="twitter" title={effectiveTwTitle} description={effectiveTwDesc} image={effectiveTwImage} />
				</div>
			</PanelBody>
		</Fragment>
	);
}

registerPlugin('ebq-seo-editor', {
	render: () => (
		<PluginDocumentSettingPanel name="ebq-seo-editor" title={__('EBQ SEO', 'ebq-seo')} className="ebq-seo-editor-panel">
			<Panel />
		</PluginDocumentSettingPanel>
	),
});
