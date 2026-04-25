import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';

import { Section, Button, EmptyState, Pill, Spinner, SkeletonRow } from '../components/primitives';
import { IconChart, IconExternal, IconRefresh, IconSparkle, IconCheck } from '../components/icons';
import { useEditorContext } from '../hooks/useEditorContext';
import { insertLink } from '../hooks/insertLink';
import { safeUrl } from '../utils/sanitizeUrl';
import { fetchInternalLinkSuggestions } from '../api';

function formatNumber(n) {
	const v = Number(n || 0);
	if (v >= 10000) return `${Math.round(v / 100) / 10}k`;
	return v.toLocaleString();
}

function LinkCard({ suggestion }) {
	const [copied, setCopied] = useState(false);
	const [inserted, setInserted] = useState(false);

	const cleanedUrl = safeUrl(suggestion.url);
	const anchorText = suggestion.title || suggestion.top_query || cleanedUrl;

	const onCopy = useCallback(() => {
		if (!cleanedUrl) return;
		const md = `[${anchorText}](${cleanedUrl})`;
		try {
			navigator.clipboard.writeText(md).then(() => {
				setCopied(true);
				setTimeout(() => setCopied(false), 1600);
			});
		} catch {
			// ignore — older browsers
		}
	}, [anchorText, cleanedUrl]);

	const onInsert = useCallback(async () => {
		if (!cleanedUrl) return;
		const ok = await insertLink({ url: cleanedUrl, anchor: anchorText });
		if (ok) {
			setInserted(true);
			setTimeout(() => setInserted(false), 1600);
		}
	}, [anchorText, cleanedUrl]);

	if (!cleanedUrl) return null; // unsafe url — skip rendering rather than render a dud

	return (
		<div className="ebq-link-card">
			<p className="ebq-link-card__title" title={suggestion.title || cleanedUrl}>{suggestion.title || cleanedUrl}</p>
			<p className="ebq-link-card__url" title={cleanedUrl}>{cleanedUrl}</p>
			<p className="ebq-link-card__reason">
				<span>{__('Ranks for', 'ebq-seo')}</span>
				<strong>"{suggestion.top_query}"</strong>
				<span className="ebq-link-card__metric-pill">
					#{suggestion.position ?? '—'}
				</span>
				<span className="ebq-text-soft">
					{sprintf(__('%s impressions / 90d', 'ebq-seo'), formatNumber(suggestion.impressions))}
				</span>
			</p>
			<div className="ebq-link-card__actions">
				<Button variant="primary" size="sm" onClick={onInsert}
					aria-label={__('Insert link to this post in the editor', 'ebq-seo')}>
					{__('Insert link', 'ebq-seo')}
				</Button>
				<Button variant="ghost" size="sm" onClick={onCopy}
					aria-label={__('Copy as markdown link', 'ebq-seo')}>
					{__('Copy markdown', 'ebq-seo')}
				</Button>
				<Button variant="ghost" size="sm" href={cleanedUrl} target="_blank" rel="noopener noreferrer"
					aria-label={__('Open this post in a new tab', 'ebq-seo')} title={__('Open in new tab', 'ebq-seo')}>
					<IconExternal />
				</Button>
				{copied ? (
					<span className="ebq-link-card__copied" role="status"><IconCheck /> {__('Copied', 'ebq-seo')}</span>
				) : null}
				{inserted ? (
					<span className="ebq-link-card__copied" role="status"><IconCheck /> {__('Inserted', 'ebq-seo')}</span>
				) : null}
			</div>
		</div>
	);
}

export default function LinksTab() {
	const ctx = useEditorContext();
	const focusKw = ctx.meta?._ebq_focus_keyword || '';

	const [state, setState] = useState({ loading: true, data: null, error: null });
	const [reloadKey, setReloadKey] = useState(0);

	useEffect(() => {
		if (!ctx.postId) return;
		let cancelled = false;
		setState({ loading: true, data: null, error: null });
		fetchInternalLinkSuggestions(ctx.postId)
			.then((res) => {
				if (cancelled) return;
				if (res && res.ok === false) {
					setState({ loading: false, data: null, error: res.error || 'unknown' });
				} else {
					setState({ loading: false, data: res, error: null });
				}
			})
			.catch((err) => {
				if (cancelled) return;
				setState({ loading: false, data: null, error: err?.message || 'fetch_failed' });
			});
		return () => { cancelled = true; };
	}, [ctx.postId, reloadKey]);

	if (state.loading) {
		return (
			<div className="ebq-stack">
				<Section title={__('Internal link suggestions', 'ebq-seo')} icon={<IconChart />}>
					<div className="ebq-stack">
						<SkeletonRow />
						<SkeletonRow width="80%" />
						<SkeletonRow width="60%" />
					</div>
				</Section>
			</div>
		);
	}

	if (state.error) {
		const isNotConnected = state.error === 'not_connected';
		return (
			<div className="ebq-stack">
				<EmptyState
					icon={<IconChart />}
					title={isNotConnected ? __('Connect to EBQ for link suggestions', 'ebq-seo') : __('Could not load link suggestions', 'ebq-seo')}
					sub={
						isNotConnected
							? __('Suggestions use Search Console data from your EBQ workspace to surface posts that already rank for relevant queries.', 'ebq-seo')
							: sprintf(__('Reason: %s', 'ebq-seo'), String(state.error))
					}
				>
					<Button variant="ghost" size="sm" onClick={() => setReloadKey((k) => k + 1)}>
						<IconRefresh /> {__('Retry', 'ebq-seo')}
					</Button>
				</EmptyState>
			</div>
		);
	}

	const suggestions = (state.data && state.data.suggestions) || [];

	if (!suggestions.length) {
		return (
			<div className="ebq-stack">
				<EmptyState
					icon={<IconSparkle />}
					title={__('No related posts found yet', 'ebq-seo')}
					sub={
						focusKw
							? __('Once Search Console picks up signals for related queries on your other posts, we\'ll suggest them here.', 'ebq-seo')
							: __('Set a focus keyphrase in the SEO tab to get the strongest suggestions.', 'ebq-seo')
					}
				>
					<Button variant="ghost" size="sm" onClick={() => setReloadKey((k) => k + 1)}>
						<IconRefresh /> {__('Re-check', 'ebq-seo')}
					</Button>
				</EmptyState>
			</div>
		);
	}

	const matchType = suggestions[0]?.match_type || 'focus_keyword';

	return (
		<div className="ebq-stack">
			<Section
				title={__('Suggested internal links', 'ebq-seo')}
				icon={<IconChart />}
				aside={
					<Pill tone="accent">
						{matchType === 'focus_keyword' ? __('Matched by keyphrase', 'ebq-seo') : __('Matched by title', 'ebq-seo')}
					</Pill>
				}
			>
				<p className="ebq-help">
					{__('Posts on your site that already rank for queries related to your focus keyphrase. Linking to them passes context — and they tend to outrank fresh posts.', 'ebq-seo')}
				</p>
				<div className="ebq-stack" style={{ gap: 10 }}>
					{suggestions.map((s) => (
						<LinkCard key={s.url} suggestion={s} />
					))}
				</div>
				<div className="ebq-row" style={{ justifyContent: 'flex-end', marginTop: 8 }}>
					<Button variant="quiet" size="sm" onClick={() => setReloadKey((k) => k + 1)}>
						<IconRefresh /> {__('Refresh', 'ebq-seo')}
					</Button>
				</div>
			</Section>
		</div>
	);
}
