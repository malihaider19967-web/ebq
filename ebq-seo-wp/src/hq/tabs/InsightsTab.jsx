import { useEffect, useState, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Api, HQ_CONFIG } from '../api';
import { Card, ErrorState, EmptyState, Pill, Button, SkeletonRows, SourceTag } from '../components/primitives';
import AddKeywordModal from '../components/AddKeywordModal';

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
	const [trackKeyword, setTrackKeyword] = useState('');
	const [toast, setToast] = useState(null);

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

	const onTrack = useCallback((kw) => setTrackKeyword(kw || ''), []);

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

			{toast ? <div className={`ebq-hq-toast ebq-hq-toast--${toast.tone}`} role="status">{toast.msg}</div> : null}

			<Card title={TYPES.find((t) => t.key === type)?.label}>
				<p className="ebq-hq-help">{TYPES.find((t) => t.key === type)?.desc}</p>
				{loading ? <SkeletonRows rows={6} /> : error ? <ErrorState error={error} retry={load} /> : (
					rows.length === 0 ? (
						<EmptyState title={__('No items in this category right now', 'ebq-seo')} sub={__('A great problem to have. Check back after the next sync.', 'ebq-seo')} />
					) : (
						<InsightTable type={type} rows={rows} onTrack={onTrack} />
					)
				)}
			</Card>

			<AddKeywordModal
				open={!!trackKeyword}
				onClose={() => setTrackKeyword('')}
				onCreated={() => {
					setToast({ msg: __('Now tracking — first SERP check queued.', 'ebq-seo'), tone: 'good' });
					setTimeout(() => setToast(null), 3500);
				}}
				defaultDomain={HQ_CONFIG.workspaceDomain}
				seedKeyword={trackKeyword}
			/>
		</div>
	);
}

function InsightTable({ type, rows, onTrack }) {
	const config = useMemo(() => INSIGHT_TABLES[type], [type]);
	const [sort, setSort] = useState(config?.defaultSort || null);
	const [dir, setDir] = useState(config?.defaultDir || 'desc');

	useEffect(() => {
		setSort(INSIGHT_TABLES[type]?.defaultSort || null);
		setDir(INSIGHT_TABLES[type]?.defaultDir || 'desc');
	}, [type]);

	const sortedRows = useMemo(() => {
		if (!sort) return rows;
		const accessor = (config?.columns || []).find((c) => c.key === sort)?.sortValue
			?? ((row) => row[sort]);
		const sorted = rows.slice().sort((a, b) => {
			const av = accessor(a);
			const bv = accessor(b);
			if (av == null && bv == null) return 0;
			if (av == null) return 1;
			if (bv == null) return -1;
			if (typeof av === 'number' && typeof bv === 'number') return av - bv;
			return String(av).localeCompare(String(bv), undefined, { numeric: true });
		});
		return dir === 'desc' ? sorted.reverse() : sorted;
	}, [rows, sort, dir, config]);

	if (!config) return null;

	const onSort = (key) => {
		if (sort === key) setDir(dir === 'asc' ? 'desc' : 'asc');
		else { setSort(key); setDir(key === 'query' || key === 'keyword' || key === 'page' ? 'asc' : 'desc'); }
	};

	return (
		<table className="ebq-hq-table">
			<thead>
				<tr>
					{config.columns.map((c) => {
						const isSorted = sort === c.key;
						const arrow = isSorted ? (dir === 'desc' ? '▼' : '▲') : '';
						const cls = `ebq-hq-table__th${c.align === 'right' ? ' ebq-hq-table__th--right' : ''}${c.sortable !== false ? ' is-sortable' : ''}${isSorted ? ' is-sorted' : ''}`;
						return (
							<th key={c.key} className={cls} onClick={c.sortable !== false ? () => onSort(c.key) : undefined}>
								{c.label}
								{arrow ? <span className="ebq-hq-table__arrow">{arrow}</span> : null}
							</th>
						);
					})}
				</tr>
			</thead>
			<tbody>
				{sortedRows.map((r, i) => (
					<tr key={i}>
						{config.columns.map((c) => (
							<td key={c.key} className={`ebq-hq-table__td${c.align === 'right' ? ' ebq-hq-table__td--right' : ''}`}>
								{c.render ? c.render(r, { onTrack }) : r[c.key]}
							</td>
						))}
					</tr>
				))}
			</tbody>
		</table>
	);
}

function TrackInlineBtn({ keyword, onTrack }) {
	if (!keyword || !onTrack) return null;
	return (
		<button
			type="button"
			className="ebq-hq-btn ebq-hq-btn--ghost ebq-hq-btn--sm"
			style={{ marginLeft: 8, padding: '2px 8px', fontSize: 11 }}
			onClick={(e) => { e.stopPropagation(); onTrack(keyword); }}
			title={__('Track this keyword in Rank Tracker', 'ebq-seo')}
		>
			+ {__('Track', 'ebq-seo')}
		</button>
	);
}

const INSIGHT_TABLES = {
	striking: {
		defaultSort: 'impressions', defaultDir: 'desc',
		columns: [
			{ key: 'query', label: __('Query', 'ebq-seo'), sortValue: (r) => (r.query || r.keyword || '').toLowerCase(), render: (r, ctx) => (<><strong>{r.query || r.keyword}</strong><TrackInlineBtn keyword={r.query || r.keyword} onTrack={ctx?.onTrack} /></>) },
			{ key: 'page', label: __('Page', 'ebq-seo'), sortValue: (r) => (r.page || '').toLowerCase(), render: (r) => <a href={r.page} target="_blank" rel="noopener noreferrer">{shortUrl(r.page)}</a> },
			{ key: 'position', label: <span>{__('GSC pos', 'ebq-seo')} <SourceTag source="gsc" /></span>, align: 'right', render: (r) => r.position?.toFixed?.(1) ?? r.position },
			{ key: 'impressions', label: __('Impressions', 'ebq-seo'), align: 'right', render: (r) => (r.impressions || 0).toLocaleString() },
			{ key: 'clicks', label: __('Clicks', 'ebq-seo'), align: 'right', render: (r) => (r.clicks || 0).toLocaleString() },
		],
	},
	cannibalization: {
		defaultSort: 'clicks', defaultDir: 'desc',
		columns: [
			{ key: 'query', label: __('Query', 'ebq-seo'), sortValue: (r) => (r.query || '').toLowerCase(), render: (r, ctx) => (<><strong>{r.query}</strong><TrackInlineBtn keyword={r.query} onTrack={ctx?.onTrack} /></>) },
			{ key: 'pages_count', label: __('Pages', 'ebq-seo'), sortValue: (r) => (r.pages || []).length, align: 'right', render: (r) => (
				<>
					<span style={{ marginRight: 6 }}><Pill tone="warn">{(r.pages || []).length}</Pill></span>
					{(r.pages || []).slice(0, 2).map((p, j) => (
						<a key={j} className="ebq-hq-canib-link" href={p.page} target="_blank" rel="noopener noreferrer">{shortUrl(p.page)}</a>
					))}
					{(r.pages || []).length > 2 ? <span className="ebq-hq-muted"> +{r.pages.length - 2} more</span> : null}
				</>
			) },
			{ key: 'clicks', label: __('Total clicks', 'ebq-seo'), align: 'right', render: (r) => (r.clicks || 0).toLocaleString() },
		],
	},
	decay: {
		defaultSort: 'change_pct', defaultDir: 'asc',
		columns: [
			{ key: 'page', label: __('Page', 'ebq-seo'), sortValue: (r) => (r.page || '').toLowerCase(), render: (r) => <a href={r.page} target="_blank" rel="noopener noreferrer">{shortUrl(r.page)}</a> },
			{ key: 'current_clicks', label: __('Clicks now', 'ebq-seo'), align: 'right', render: (r) => (r.current_clicks || 0).toLocaleString() },
			{ key: 'previous_clicks', label: __('Clicks prev', 'ebq-seo'), align: 'right', render: (r) => (r.previous_clicks || 0).toLocaleString() },
			{ key: 'change_pct', label: __('Δ', 'ebq-seo'), align: 'right', render: (r) => <span className="ebq-hq-delta ebq-hq-delta--down">{r.change_pct ? `${r.change_pct.toFixed(0)}%` : '—'}</span> },
		],
	},
	index_fails: {
		defaultSort: 'impressions', defaultDir: 'desc',
		columns: [
			{ key: 'page', label: __('Page', 'ebq-seo'), sortValue: (r) => (r.page || '').toLowerCase(), render: (r) => <a href={r.page} target="_blank" rel="noopener noreferrer">{shortUrl(r.page)}</a> },
			{ key: 'verdict', label: __('Verdict', 'ebq-seo'), sortValue: (r) => r.verdict || '', render: (r) => <Pill tone={r.verdict === 'PASS' ? 'good' : r.verdict === 'FAIL' ? 'bad' : 'warn'}>{r.verdict || '—'}</Pill> },
			{ key: 'impressions', label: __('Impressions', 'ebq-seo'), align: 'right', render: (r) => (r.impressions || 0).toLocaleString() },
		],
	},
	quick_wins: {
		defaultSort: 'upside_value', defaultDir: 'desc',
		columns: [
			{ key: 'keyword', label: __('Keyword', 'ebq-seo'), sortValue: (r) => (r.keyword || '').toLowerCase(), render: (r, ctx) => (<><strong>{r.keyword}</strong><TrackInlineBtn keyword={r.keyword} onTrack={ctx?.onTrack} /></>) },
			{ key: 'search_volume', label: __('Volume', 'ebq-seo'), align: 'right', render: (r) => (r.search_volume || 0).toLocaleString() },
			{ key: 'current_position', label: <span>{__('Best GSC pos', 'ebq-seo')} <SourceTag source="gsc" /></span>, align: 'right', render: (r) => r.current_position?.toFixed?.(1) ?? '—' },
			{ key: 'upside_value', label: __('Upside', 'ebq-seo'), align: 'right', render: (r) => <strong>${(r.upside_value || 0).toFixed(0)}</strong> },
		],
	},
};

function shortUrl(url) {
	if (!url) return '';
	try {
		const u = new URL(url);
		return u.pathname + u.search + u.hash;
	} catch {
		return url;
	}
}
