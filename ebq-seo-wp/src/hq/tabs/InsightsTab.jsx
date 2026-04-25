import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Api } from '../api';
import { Card, ErrorState, EmptyState, Pill, Button, SkeletonRows } from '../components/primitives';

const TYPES = [
	{ key: 'striking', label: __('Striking distance', 'ebq-seo'), insight: 'striking_distance', desc: __('Keywords ranking position 5–20 — small lift = first-page win.', 'ebq-seo') },
	{ key: 'cannibalization', label: __('Cannibalizations', 'ebq-seo'), insight: 'cannibalization', desc: __('Multiple URLs ranking for the same query — you compete with yourself.', 'ebq-seo') },
	{ key: 'decay', label: __('Content decay', 'ebq-seo'), insight: 'content_decay', desc: __('Pages losing clicks vs the prior period.', 'ebq-seo') },
	{ key: 'index_fails', label: __('Index fails', 'ebq-seo'), insight: 'indexing_fails', desc: __('URLs not indexed but generating impressions.', 'ebq-seo') },
	{ key: 'quick_wins', label: __('Quick wins', 'ebq-seo'), insight: 'quick_wins', desc: __('High-volume, low-competition keywords you don\'t rank for yet.', 'ebq-seo') },
];

export default function InsightsTab() {
	const [type, setType] = useState('striking');
	const [data, setData] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);

	const load = useCallback(async () => {
		setLoading(true);
		setError(null);
		const res = await Api.insights(type, 50);
		if (res?.ok === false || res?.error) {
			setError(res);
			setData(null);
		} else {
			setData(res);
		}
		setLoading(false);
	}, [type]);

	useEffect(() => { load(); }, [load]);

	const openInEbq = useCallback(async () => {
		const tp = TYPES.find((t) => t.key === type);
		const res = await Api.iframeUrl(tp?.insight || 'cannibalization');
		if (res?.url) window.open(res.url, '_blank', 'noopener');
	}, [type]);

	const rows = data?.payload?.rows || data?.payload?.pages || [];

	return (
		<div className="ebq-hq-page">
			<div className="ebq-hq-page__head">
				<h2 className="ebq-hq-page__title">{__('Insights', 'ebq-seo')}</h2>
				<Button variant="ghost" onClick={openInEbq}>{__('Open full report in EBQ', 'ebq-seo')} →</Button>
			</div>

			<div className="ebq-hq-tabsbar" role="tablist">
				{TYPES.map((t) => (
					<button
						key={t.key}
						type="button"
						role="tab"
						aria-selected={t.key === type}
						className={`ebq-hq-tabsbar__btn${t.key === type ? ' is-active' : ''}`}
						onClick={() => setType(t.key)}
					>
						{t.label}
					</button>
				))}
			</div>

			<Card title={TYPES.find((t) => t.key === type)?.label}>
				<p className="ebq-hq-help">{TYPES.find((t) => t.key === type)?.desc}</p>
				{loading ? <SkeletonRows rows={6} /> : error ? <ErrorState error={error} retry={load} /> : (
					rows.length === 0 ? (
						<EmptyState title={__('No items in this category right now', 'ebq-seo')} sub={__('A great problem to have. Check back after the next sync.', 'ebq-seo')} />
					) : (
						<InsightTable type={type} rows={rows} />
					)
				)}
			</Card>
		</div>
	);
}

function InsightTable({ type, rows }) {
	if (type === 'striking') {
		return (
			<table className="ebq-hq-table">
				<thead><tr><th>{__('Query', 'ebq-seo')}</th><th>{__('Page', 'ebq-seo')}</th><th className="ebq-hq-table__th--right">{__('Position', 'ebq-seo')}</th><th className="ebq-hq-table__th--right">{__('Impressions', 'ebq-seo')}</th><th className="ebq-hq-table__th--right">{__('Clicks', 'ebq-seo')}</th></tr></thead>
				<tbody>
					{rows.map((r, i) => (
						<tr key={i}>
							<td><strong>{r.query || r.keyword}</strong></td>
							<td><a href={r.page} target="_blank" rel="noopener noreferrer">{shortUrl(r.page)}</a></td>
							<td className="ebq-hq-table__td--right">{r.position?.toFixed?.(1) ?? r.position}</td>
							<td className="ebq-hq-table__td--right">{(r.impressions || 0).toLocaleString()}</td>
							<td className="ebq-hq-table__td--right">{(r.clicks || 0).toLocaleString()}</td>
						</tr>
					))}
				</tbody>
			</table>
		);
	}
	if (type === 'cannibalization') {
		return (
			<table className="ebq-hq-table">
				<thead><tr><th>{__('Query', 'ebq-seo')}</th><th>{__('Pages', 'ebq-seo')}</th><th className="ebq-hq-table__th--right">{__('Total clicks', 'ebq-seo')}</th></tr></thead>
				<tbody>
					{rows.map((r, i) => (
						<tr key={i}>
							<td><strong>{r.query}</strong></td>
							<td>
								{(r.pages || []).slice(0, 3).map((p, j) => (
									<a key={j} className="ebq-hq-canib-link" href={p.page} target="_blank" rel="noopener noreferrer">{shortUrl(p.page)}</a>
								))}
								{(r.pages || []).length > 3 ? <span className="ebq-hq-muted"> +{r.pages.length - 3} more</span> : null}
							</td>
							<td className="ebq-hq-table__td--right">{(r.clicks || 0).toLocaleString()}</td>
						</tr>
					))}
				</tbody>
			</table>
		);
	}
	if (type === 'decay') {
		return (
			<table className="ebq-hq-table">
				<thead><tr><th>{__('Page', 'ebq-seo')}</th><th className="ebq-hq-table__th--right">{__('Clicks now', 'ebq-seo')}</th><th className="ebq-hq-table__th--right">{__('Clicks prev', 'ebq-seo')}</th><th className="ebq-hq-table__th--right">{__('Δ', 'ebq-seo')}</th></tr></thead>
				<tbody>
					{rows.map((r, i) => (
						<tr key={i}>
							<td><a href={r.page} target="_blank" rel="noopener noreferrer">{shortUrl(r.page)}</a></td>
							<td className="ebq-hq-table__td--right">{(r.current_clicks || 0).toLocaleString()}</td>
							<td className="ebq-hq-table__td--right">{(r.previous_clicks || 0).toLocaleString()}</td>
							<td className="ebq-hq-table__td--right ebq-hq-delta ebq-hq-delta--down">{r.change_pct ? `${r.change_pct.toFixed(0)}%` : '—'}</td>
						</tr>
					))}
				</tbody>
			</table>
		);
	}
	if (type === 'index_fails') {
		return (
			<table className="ebq-hq-table">
				<thead><tr><th>{__('Page', 'ebq-seo')}</th><th>{__('Verdict', 'ebq-seo')}</th><th className="ebq-hq-table__th--right">{__('Impressions', 'ebq-seo')}</th></tr></thead>
				<tbody>
					{rows.map((r, i) => (
						<tr key={i}>
							<td><a href={r.page} target="_blank" rel="noopener noreferrer">{shortUrl(r.page)}</a></td>
							<td><Pill tone={r.verdict === 'PASS' ? 'good' : r.verdict === 'FAIL' ? 'bad' : 'warn'}>{r.verdict || '—'}</Pill></td>
							<td className="ebq-hq-table__td--right">{(r.impressions || 0).toLocaleString()}</td>
						</tr>
					))}
				</tbody>
			</table>
		);
	}
	if (type === 'quick_wins') {
		return (
			<table className="ebq-hq-table">
				<thead><tr><th>{__('Keyword', 'ebq-seo')}</th><th className="ebq-hq-table__th--right">{__('Volume', 'ebq-seo')}</th><th className="ebq-hq-table__th--right">{__('Position', 'ebq-seo')}</th><th className="ebq-hq-table__th--right">{__('Upside', 'ebq-seo')}</th></tr></thead>
				<tbody>
					{rows.map((r, i) => (
						<tr key={i}>
							<td><strong>{r.keyword}</strong></td>
							<td className="ebq-hq-table__td--right">{(r.search_volume || 0).toLocaleString()}</td>
							<td className="ebq-hq-table__td--right">{r.current_position?.toFixed?.(1) ?? '—'}</td>
							<td className="ebq-hq-table__td--right"><strong>${(r.upside_value || 0).toFixed(0)}</strong>/mo</td>
						</tr>
					))}
				</tbody>
			</table>
		);
	}
	return null;
}

function shortUrl(url) {
	if (!url) return '';
	try {
		const u = new URL(url);
		return u.pathname + u.search + u.hash;
	} catch {
		return url;
	}
}
