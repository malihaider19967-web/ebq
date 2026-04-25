import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';

import { EmptyState, Spinner, Pill } from './primitives';
import { IconSparkle, IconRefresh } from './icons';
import useDebounced from '../hooks/useDebounced';
import { fetchRelatedKeywords } from '../api';

/**
 * Tag → friendly source label.
 * - gsc:     query already ranking on this site (strongest signal)
 * - related: from "Related searches" of a tracked keyword's SERP
 * - paa:     from "People also ask" block
 */
const SOURCE_META = {
	gsc:     { label: __('Search Console', 'ebq-seo'), tone: 'good' },
	related: { label: __('Related search', 'ebq-seo'), tone: 'accent' },
	paa:     { label: __('People also ask', 'ebq-seo'), tone: 'neutral' },
};

const cache = new Map();
const cacheKey = (postId, keyword) => `${postId}|${keyword.trim().toLowerCase()}`;

function formatNumber(n) {
	const v = Number(n || 0);
	if (v >= 10000) return `${Math.round(v / 100) / 10}k`;
	return v.toLocaleString();
}

export default function RelatedKeyphrases({ postId, focusKeyword, onChoose }) {
	const debouncedFocus = useDebounced((focusKeyword || '').trim(), 600);
	const [state, setState] = useState({ loading: false, suggestions: [], diagnostic: null });
	const [reloadKey, setReloadKey] = useState(0);

	useEffect(() => {
		if (!postId || debouncedFocus.length < 3) {
			setState({ loading: false, suggestions: [], diagnostic: null });
			return;
		}

		const key = cacheKey(postId, debouncedFocus);
		if (cache.has(key) && reloadKey === 0) {
			setState({ loading: false, ...cache.get(key) });
			return;
		}

		let cancelled = false;
		setState({ loading: true, suggestions: [], diagnostic: null });
		fetchRelatedKeywords(postId, debouncedFocus)
			.then((res) => {
				if (cancelled) return;
				if (res && res.ok === false) {
					setState({ loading: false, suggestions: [], diagnostic: res.error || 'fetch_failed' });
					return;
				}
				const list = (res && res.suggestions) || [];
				const diagnostic = (res && res.diagnostic) || null;
				cache.set(key, { suggestions: list, diagnostic });
				setState({ loading: false, suggestions: list, diagnostic });
			})
			.catch(() => {
				if (cancelled) return;
				setState({ loading: false, suggestions: [], diagnostic: 'fetch_failed' });
			});

		return () => { cancelled = true; };
	}, [postId, debouncedFocus, reloadKey]);

	const refresh = () => {
		cache.delete(cacheKey(postId, debouncedFocus));
		setReloadKey((k) => k + 1);
	};

	if (debouncedFocus.length < 3) {
		return null;
	}

	return (
		<div className="ebq-stack" style={{ gap: 6 }}>
			<div className="ebq-row ebq-row--between">
				<span className="ebq-text-xs ebq-text-soft" style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
					<IconSparkle /> {__('Related keyphrases', 'ebq-seo')}
				</span>
				<button
					type="button"
					className="ebq-btn ebq-btn--quiet ebq-btn--sm"
					onClick={refresh}
					title={__('Refresh', 'ebq-seo')}
					aria-label={__('Refresh related keyphrases', 'ebq-seo')}
				>
					<IconRefresh />
				</button>
			</div>

			{state.loading ? (
				<div className="ebq-row ebq-text-xs ebq-text-soft">
					<Spinner /> {__('Looking for related queries…', 'ebq-seo')}
				</div>
			) : state.suggestions.length === 0 ? (
				<EmptyState
					icon={<IconSparkle />}
					title={__('Nothing related yet', 'ebq-seo')}
					sub={__('Search Console and rank-tracker SERPs don\'t have queries that match this keyphrase yet. Try a broader phrase, or check back after the next sync.', 'ebq-seo')}
				/>
			) : (
				<ul className="ebq-suggestions">
					{state.suggestions.map((row) => {
						const meta = SOURCE_META[row.source] || SOURCE_META.gsc;
						return (
							<li key={row.keyword}>
								<button
									type="button"
									className="ebq-suggestion ebq-suggestion--related"
									onClick={() => onChoose(row.keyword)}
									title={__('Use this keyphrase', 'ebq-seo')}
								>
									<span className="ebq-suggestion__q">{row.keyword}</span>
									<span className="ebq-suggestion__pos">
										{row.volume != null ? (
											<span title={__('Search volume / month', 'ebq-seo')}>
												{formatNumber(row.volume)}/mo
											</span>
										) : row.impressions > 0 ? (
											<span title={__('Impressions on this site, last 90d', 'ebq-seo')}>
												{formatNumber(row.impressions)} impr
											</span>
										) : (
											<span className="ebq-text-soft">—</span>
										)}
									</span>
									<Pill tone={meta.tone}>{meta.label}</Pill>
								</button>
							</li>
						);
					})}
				</ul>
			)}
		</div>
	);
}
