import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { TextField, EmptyState, Spinner, Button } from './primitives';
import { IconChart, IconRefresh } from './icons';
import { fetchFocusKeywordSuggestions } from '../api';

const cache = new Map();

const DIAG_MESSAGES = {
	missing_url: {
		title: __('Save the post first', 'ebq-seo'),
		sub: __('We need a saved permalink to ask EBQ for ranking queries.', 'ebq-seo'),
	},
	url_not_for_website: {
		title: __('Domain mismatch', 'ebq-seo'),
		sub: __("This post's URL isn't on the domain connected to EBQ. Open Settings → EBQ SEO and reconnect to the right workspace.", 'ebq-seo'),
	},
	no_gsc_data: {
		title: __('No queries yet for this URL', 'ebq-seo'),
		sub: __('Search Console may not have impressions for this exact URL yet. Type a keyphrase above, or wait for the next GSC sync.', 'ebq-seo'),
	},
	not_connected: {
		title: __('Not connected to EBQ', 'ebq-seo'),
		sub: __('Open Settings → EBQ SEO and connect this site.', 'ebq-seo'),
	},
	fetch_failed: {
		title: __('Could not load suggestions', 'ebq-seo'),
		sub: __('Network error reaching EBQ. Try Refresh.', 'ebq-seo'),
	},
};

export default function KeyphraseInput({ postId, value, onChange }) {
	const [state, setState] = useState(() => {
		const cached = cache.get(postId);
		return cached
			? { loading: false, suggestions: cached.suggestions, diagnostic: cached.diagnostic }
			: { loading: true, suggestions: [], diagnostic: null };
	});
	const [reloadKey, setReloadKey] = useState(0);

	useEffect(() => {
		if (!postId) return;
		if (cache.has(postId) && reloadKey === 0) return;

		let cancelled = false;
		setState((s) => ({ ...s, loading: true }));
		fetchFocusKeywordSuggestions(postId)
			.then((data) => {
				if (cancelled) return;
				if (data && data.ok === false) {
					setState({ loading: false, suggestions: [], diagnostic: data.error || 'fetch_failed' });
					return;
				}
				const list = (data && data.suggestions) || [];
				const diagnostic = (data && data.diagnostic) || null;
				cache.set(postId, { suggestions: list, diagnostic });
				setState({ loading: false, suggestions: list, diagnostic });
			})
			.catch(() => {
				if (!cancelled) {
					setState({ loading: false, suggestions: [], diagnostic: 'fetch_failed' });
				}
			});

		return () => {
			cancelled = true;
		};
	}, [postId, reloadKey]);

	const refresh = () => {
		cache.delete(postId);
		setReloadKey((k) => k + 1);
	};

	const diagnosticMessage = state.diagnostic ? DIAG_MESSAGES[state.diagnostic] : null;

	return (
		<div className="ebq-stack">
			<TextField
				label={__('Focus keyphrase', 'ebq-seo')}
				value={value}
				onChange={onChange}
				placeholder={__('e.g. "best running shoes"', 'ebq-seo')}
				hint={__('The main phrase you want this page to rank for. Analysis below updates in real time.', 'ebq-seo')}
			/>

			<div className="ebq-row ebq-row--between" style={{ marginTop: 4 }}>
				<span className="ebq-text-xs ebq-text-soft" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
					<IconChart /> {__('From Google Search Console', 'ebq-seo')}
				</span>
				<button
					type="button"
					className="ebq-btn ebq-btn--quiet ebq-btn--sm"
					onClick={refresh}
					title={__('Refresh', 'ebq-seo')}
				>
					<IconRefresh />
				</button>
			</div>

			{state.loading ? (
				<div className="ebq-row ebq-text-xs ebq-text-soft">
					<Spinner /> {__('Loading top queries…', 'ebq-seo')}
				</div>
			) : state.suggestions.length === 0 ? (
				<EmptyState
					icon={<IconChart />}
					title={diagnosticMessage ? diagnosticMessage.title : __('No queries yet', 'ebq-seo')}
					sub={diagnosticMessage ? diagnosticMessage.sub : __('Search Console hasn\'t recorded queries for this URL yet.', 'ebq-seo')}
				>
					<Button variant="ghost" size="sm" onClick={refresh}>
						<IconRefresh /> {__('Retry', 'ebq-seo')}
					</Button>
				</EmptyState>
			) : (
				<ul className="ebq-suggestions">
					{state.suggestions.slice(0, 8).map((row) => (
						<li key={row.query}>
							<button
								type="button"
								className="ebq-suggestion"
								onClick={() => onChange(row.query)}
								title={__('Use this keyphrase', 'ebq-seo')}
							>
								<span className="ebq-suggestion__q">{row.query}</span>
								<span className="ebq-suggestion__pos">#{row.position ?? '—'}</span>
								<span className="ebq-suggestion__impr">
									{Number(row.impressions || 0).toLocaleString()} impr
								</span>
							</button>
						</li>
					))}
				</ul>
			)}
		</div>
	);
}
