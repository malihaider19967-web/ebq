import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { TextField, EmptyState, Spinner, Button } from './primitives';
import { IconChart, IconRefresh } from './icons';
import RelatedKeyphrases from './RelatedKeyphrases';
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

/**
 * Expandable details block that turns the empty state into a real debugging
 * surface: shows what URL was queried, what GSC has for the site overall,
 * and a few similar URLs in case the post lives under a different shape.
 */
function Diagnostics({ debug }) {
	if (!debug || typeof debug !== 'object') return null;
	const total = debug.gsc_rows_total_all_time;
	const last = debug.gsc_last_sync_date;
	const queried = debug.queried_url;
	const similar = Array.isArray(debug.similar_urls_in_gsc) ? debug.similar_urls_in_gsc : [];
	const variants = Array.isArray(debug.tried_variants) ? debug.tried_variants : [];
	const strictRows = debug.strict_match_rows;
	const strictPages = Array.isArray(debug.strict_match_pages) ? debug.strict_match_pages : [];
	const codeVersion = debug.code_version || '?';

	const dtStyle = { color: 'var(--ebq-text-soft)' };
	const ddStyle = { margin: 0, wordBreak: 'break-all' };

	return (
		<details style={{ marginTop: 8, width: '100%', textAlign: 'left' }} open>
			<summary style={{
				cursor: 'pointer', fontSize: 10, color: 'var(--ebq-text-soft)',
				textTransform: 'uppercase', letterSpacing: '.06em', fontWeight: 600,
			}}>
				{__('Why is this empty?', 'ebq-seo')}
			</summary>
			<div style={{ marginTop: 6, padding: 8, background: 'var(--ebq-bg-emboss)', borderRadius: 4, fontSize: 11, color: 'var(--ebq-text-muted)', lineHeight: 1.4 }}>
				<dl style={{ margin: 0, display: 'grid', gridTemplateColumns: 'max-content 1fr', gap: '2px 8px' }}>
					<dt style={dtStyle}>{__('Backend code', 'ebq-seo')}</dt>
					<dd style={ddStyle}><code style={{ fontSize: 10 }}>{codeVersion}</code></dd>

					<dt style={dtStyle}>{__('Site GSC rows', 'ebq-seo')}</dt>
					<dd style={{ ...ddStyle, fontVariantNumeric: 'tabular-nums' }}>
						{total != null ? Number(total).toLocaleString() : '—'}
						{total === 0 ? ' ' + __('(GSC may not be connected yet)', 'ebq-seo') : ''}
					</dd>

					<dt style={dtStyle}>{__('Last sync date', 'ebq-seo')}</dt>
					<dd style={ddStyle}>{last || __('never', 'ebq-seo')}</dd>

					<dt style={dtStyle}>{__('We queried', 'ebq-seo')}</dt>
					<dd style={ddStyle}>{queried || '—'}</dd>

					{strictRows != null ? (
						<>
							<dt style={dtStyle}>{__('Direct match rows', 'ebq-seo')}</dt>
							<dd style={{ ...ddStyle, fontVariantNumeric: 'tabular-nums', fontWeight: 600,
								color: strictRows > 0 ? 'var(--ebq-good-text)' : 'var(--ebq-bad-text)' }}>
								{strictRows}
							</dd>
						</>
					) : null}
				</dl>

				{strictRows === 0 && strictPages.length === 0 && variants.length > 0 ? (
					<>
						<p style={{ margin: '8px 0 3px', fontSize: 10, color: 'var(--ebq-text-soft)', textTransform: 'uppercase', letterSpacing: '.06em', fontWeight: 600 }}>
							{__('Variants we tried', 'ebq-seo')}
						</p>
						<ul style={{ margin: 0, padding: 0, listStyle: 'none' }}>
							{variants.map((u) => (
								<li key={u} style={{ wordBreak: 'break-all', padding: '1px 0', fontSize: 11, fontFamily: 'var(--ebq-font-mono)' }}>{u}</li>
							))}
						</ul>
					</>
				) : null}

				{similar.length > 0 ? (
					<>
						<p style={{ margin: '8px 0 3px', fontSize: 10, color: 'var(--ebq-text-soft)', textTransform: 'uppercase', letterSpacing: '.06em', fontWeight: 600 }}>
							{__('Similar URLs in GSC', 'ebq-seo')}
						</p>
						<ul style={{ margin: 0, padding: 0, listStyle: 'none' }}>
							{similar.map((u) => (
								<li key={u} style={{ wordBreak: 'break-all', padding: '1px 0', fontSize: 11, fontFamily: 'var(--ebq-font-mono)' }}>{u}</li>
							))}
						</ul>
						<p style={{ margin: '8px 0 0', fontSize: 11, lineHeight: 1.45 }}>
							{__('Compare these to the URL above. If they differ, the canonical or permalink doesn\'t match what Google indexed.', 'ebq-seo')}
						</p>
					</>
				) : strictRows === 0 && total > 0 ? (
					<p style={{ margin: '8px 0 0', fontSize: 11, lineHeight: 1.45 }}>
						{__('GSC has data for this site but nothing matched the URL we tried. Either the canonical / permalink differs from what Google indexed, or backend code is stale (clear PHP opcache and the WP transient cache).', 'ebq-seo')}
					</p>
				) : null}
			</div>
		</details>
	);
}

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
					setState({ loading: false, suggestions: [], diagnostic: data.error || 'fetch_failed', debug: null });
					return;
				}
				const list = (data && data.suggestions) || [];
				const diagnostic = (data && data.diagnostic) || null;
				const debug = (data && data.debug) || null;
				cache.set(postId, { suggestions: list, diagnostic, debug });
				setState({ loading: false, suggestions: list, diagnostic, debug });
			})
			.catch(() => {
				if (!cancelled) {
					setState({ loading: false, suggestions: [], diagnostic: 'fetch_failed', debug: null });
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
					<Diagnostics debug={state.debug} />
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

			{value && value.trim().length >= 3 ? (
				<>
					<div className="ebq-divider" />
					<RelatedKeyphrases postId={postId} focusKeyword={value} onChoose={onChange} />
				</>
			) : null}
		</div>
	);
}
