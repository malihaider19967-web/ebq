import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Api, HQ_CONFIG } from '../api';
import { Card, ErrorState, Pill, Button } from '../components/primitives';
import { DataTable } from '../components/DataTable';
import AddKeywordModal from '../components/AddKeywordModal';

export default function KeywordsTab() {
	const [rows, setRows] = useState([]);
	const [meta, setMeta] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [sort, setSort] = useState('current_position');
	const [dir, setDir] = useState('asc');
	const [page, setPage] = useState(1);
	const [search, setSearch] = useState('');
	const [history, setHistory] = useState({ id: null, series: null, loading: false });
	const [addOpen, setAddOpen] = useState(false);
	const [toast, setToast] = useState(null);

	const load = useCallback(async () => {
		setLoading(true);
		setError(null);
		const res = await Api.keywords({ sort, dir, page, search, per_page: 25 });
		if (res?.ok === false || res?.error) {
			setError(res);
			setRows([]);
		} else {
			setRows(res?.data || []);
			setMeta(res?.meta || null);
		}
		setLoading(false);
	}, [sort, dir, page, search]);

	useEffect(() => { load(); }, [load]);

	const handleSort = (key) => {
		if (sort === key) {
			setDir(dir === 'asc' ? 'desc' : 'asc');
		} else {
			setSort(key);
			setDir('asc');
		}
		setPage(1);
	};

	const showHistory = useCallback(async (id) => {
		setHistory({ id, series: null, loading: true });
		const res = await Api.keywordHistory(id);
		setHistory({ id, series: res?.series || [], loading: false, keyword: res?.keyword });
	}, []);

	const flashToast = useCallback((msg, tone = 'good') => {
		setToast({ msg, tone });
		setTimeout(() => setToast(null), 3500);
	}, []);

	const handleRecheck = useCallback(async (row) => {
		const res = await Api.recheckKeyword(row.id);
		if (res?.ok === false || res?.error) flashToast(res?.message || res?.error || 'Re-check failed', 'bad');
		else { flashToast(__('Re-check queued. New position arrives in a few minutes.', 'ebq-seo'), 'good'); load(); }
	}, [flashToast, load]);

	const handleTogglePause = useCallback(async (row) => {
		const next = !(row._pausing ? row.is_active : true);
		const res = await Api.updateKeyword(row.id, { is_active: !next ? false : true });
		if (res?.ok === false || res?.error) flashToast(res?.message || res?.error || 'Update failed', 'bad');
		else { flashToast(__('Keyword updated.', 'ebq-seo'), 'good'); load(); }
	}, [flashToast, load]);

	const handleDelete = useCallback(async (row) => {
		// eslint-disable-next-line no-alert
		if (!window.confirm(__('Stop tracking this keyword? Historical positions are preserved on EBQ.', 'ebq-seo').replace('EBQ', 'EBQ.io'))) return;
		const res = await Api.deleteKeyword(row.id);
		if (res?.ok === false || res?.error) flashToast(res?.message || res?.error || 'Delete failed', 'bad');
		else { flashToast(__('Keyword removed.', 'ebq-seo'), 'good'); load(); }
	}, [flashToast, load]);

	const columns = [
		{
			key: 'keyword',
			label: __('Keyword', 'ebq-seo'),
			sortable: true,
			render: (row) => (
				<div className="ebq-hq-kw-cell">
					<button type="button" className="ebq-hq-kw-cell__btn" onClick={() => showHistory(row.id)}>
						<strong>{row.keyword}</strong>
					</button>
					{row.target_url ? <a className="ebq-hq-kw-cell__url" href={row.target_url} target="_blank" rel="noopener noreferrer">{row.target_url}</a> : null}
					{row.tags?.length ? <div className="ebq-hq-kw-cell__tags">{row.tags.slice(0, 3).map((t, i) => <Pill key={i} tone="neutral">{t}</Pill>)}</div> : null}
				</div>
			),
		},
		{
			key: 'current_position',
			label: __('Pos', 'ebq-seo'),
			align: 'right',
			sortable: true,
			render: (row) => row.current_position !== null ? (
				<span className={positionToneCls(row.current_position)}>{Math.round(row.current_position)}</span>
			) : (
				<span className="ebq-hq-pos ebq-hq-pos--pending" title={__('Awaiting first SERP check (usually 1–5 min)', 'ebq-seo')}>{__('pending', 'ebq-seo')}</span>
			),
		},
		{
			key: 'best_position',
			label: __('Best', 'ebq-seo'),
			align: 'right',
			sortable: true,
			render: (row) => row.best_position !== null ? Math.round(row.best_position) : '—',
		},
		{
			key: 'position_change',
			label: __('Δ', 'ebq-seo'),
			align: 'right',
			sortable: true,
			render: (row) => {
				if (row.position_change === null || row.position_change === 0) return <span className="ebq-hq-muted">—</span>;
				const up = row.position_change > 0;
				return <span className={`ebq-hq-delta ebq-hq-delta--${up ? 'up' : 'down'}`}>{up ? '▲' : '▼'} {Math.abs(row.position_change).toFixed(0)}</span>;
			},
		},
		{
			key: 'gsc_clicks',
			label: __('Clicks 30d', 'ebq-seo'),
			align: 'right',
			render: (row) => (row.gsc_clicks || 0).toLocaleString(),
		},
		{
			key: 'gsc_impressions',
			label: __('Impr 30d', 'ebq-seo'),
			align: 'right',
			render: (row) => (row.gsc_impressions || 0).toLocaleString(),
		},
		{
			key: 'last_checked_at',
			label: __('Checked', 'ebq-seo'),
			sortable: true,
			align: 'right',
			render: (row) => relTime(row.last_checked_at),
		},
		{
			key: '_actions',
			label: '',
			align: 'right',
			render: (row) => (
				<div className="ebq-hq-row-actions">
					<button type="button" className="ebq-hq-row-action" title={__('Re-check now', 'ebq-seo')} onClick={() => handleRecheck(row)}>↻</button>
					<button type="button" className="ebq-hq-row-action ebq-hq-row-action--danger" title={__('Stop tracking', 'ebq-seo')} onClick={() => handleDelete(row)}>×</button>
				</div>
			),
		},
	];

	return (
		<div className="ebq-hq-page">
			<div className="ebq-hq-page__head">
				<h2 className="ebq-hq-page__title">{__('Keywords', 'ebq-seo')}</h2>
				<div className="ebq-hq-page__head-actions">
					<input
						className="ebq-hq-search"
						placeholder={__('Search keywords…', 'ebq-seo')}
						value={search}
						onChange={(e) => { setSearch(e.target.value); setPage(1); }}
					/>
					<Button variant="primary" onClick={() => setAddOpen(true)}>+ {__('Add keyword', 'ebq-seo')}</Button>
				</div>
			</div>

			{toast ? <div className={`ebq-hq-toast ebq-hq-toast--${toast.tone}`} role="status">{toast.msg}</div> : null}

			<Card padding="flush">
				{error ? <ErrorState error={error} retry={load} /> : (
					<DataTable
						columns={columns}
						rows={rows}
						loading={loading}
						meta={meta}
						sort={sort}
						dir={dir}
						onSort={handleSort}
						onPage={setPage}
						emptyTitle={__('No tracked keywords yet', 'ebq-seo')}
						emptySub={__('Add keywords in the Rank Tracker on EBQ.io to see them here.', 'ebq-seo')}
					/>
				)}
			</Card>

			{history.id ? (
				<HistoryDrawer history={history} onClose={() => setHistory({ id: null, series: null, loading: false })} />
			) : null}

			<AddKeywordModal
				open={addOpen}
				onClose={() => setAddOpen(false)}
				onCreated={() => { flashToast(__('Keyword added — first check queued.', 'ebq-seo'), 'good'); load(); }}
				defaultDomain={HQ_CONFIG.workspaceDomain}
			/>
		</div>
	);
}

function HistoryDrawer({ history, onClose }) {
	return (
		<div className="ebq-hq-drawer" role="dialog" aria-label="Keyword position history">
			<div className="ebq-hq-drawer__head">
				<h3>{history.keyword || '…'}</h3>
				<button type="button" className="ebq-hq-drawer__close" onClick={onClose} aria-label="Close">×</button>
			</div>
			<div className="ebq-hq-drawer__body">
				{history.loading ? <p>{__('Loading history…', 'ebq-seo')}</p> : (
					history.series?.length > 0 ? (
						<>
							<p className="ebq-hq-help">{__('Position history (lower is better)', 'ebq-seo')}</p>
							<svg viewBox="0 0 600 200" className="ebq-hq-chart" role="img">
								{(() => {
									const max = Math.max(20, ...history.series.map((s) => s.position || 0));
									const stepX = 600 / Math.max(1, history.series.length - 1);
									const yFor = (v) => (v / max) * 180 + 10;
									const path = history.series.map((s, i) => `${i === 0 ? 'M' : 'L'} ${(i * stepX).toFixed(1)} ${yFor(s.position || 0).toFixed(1)}`).join(' ');
									return (
										<>
											<line x1="0" y1={yFor(10)} x2="600" y2={yFor(10)} stroke="#cbd5f5" strokeDasharray="3,3" />
											<text x="6" y={yFor(10) - 4} fontSize="10" fill="#94a3b8">Top 10</text>
											<path d={path} fill="none" stroke="#dc2626" strokeWidth="2" />
										</>
									);
								})()}
							</svg>
						</>
					) : <p>{__('No history captured for this keyword yet.', 'ebq-seo')}</p>
				)}
			</div>
		</div>
	);
}

function positionToneCls(pos) {
	if (pos == null) return 'ebq-hq-muted';
	if (pos <= 3) return 'ebq-hq-pos ebq-hq-pos--top3';
	if (pos <= 10) return 'ebq-hq-pos ebq-hq-pos--top10';
	if (pos <= 20) return 'ebq-hq-pos ebq-hq-pos--striking';
	return 'ebq-hq-pos ebq-hq-pos--deep';
}

function relTime(iso) {
	if (!iso) return '—';
	const d = new Date(iso);
	const diff = (Date.now() - d.getTime()) / 1000;
	if (diff < 3600) return `${Math.round(diff / 60)}m ago`;
	if (diff < 86400) return `${Math.round(diff / 3600)}h ago`;
	if (diff < 86400 * 30) return `${Math.round(diff / 86400)}d ago`;
	return d.toLocaleDateString();
}
