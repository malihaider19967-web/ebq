import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Api } from '../api';
import { Card, ErrorState, Pill } from '../components/primitives';
import { DataTable } from '../components/DataTable';
import { StackedBar } from '../components/charts';

const FILTERS = [
	{ key: '', label: __('All', 'ebq-seo') },
	{ key: 'PASS', label: __('Pass', 'ebq-seo') },
	{ key: 'PARTIAL', label: __('Partial', 'ebq-seo') },
	{ key: 'FAIL', label: __('Fail', 'ebq-seo') },
	{ key: 'NEUTRAL', label: __('Neutral', 'ebq-seo') },
];

export default function IndexStatusTab() {
	const [status, setStatus] = useState('');
	const [rows, setRows] = useState([]);
	const [meta, setMeta] = useState(null);
	const [verdictCounts, setVerdictCounts] = useState(null);
	const [page, setPage] = useState(1);
	const [search, setSearch] = useState('');
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);

	const load = useCallback(async () => {
		setLoading(true);
		setError(null);
		const res = await Api.indexStatus({ status, page, search, per_page: 25 });
		if (res?.ok === false || res?.error) {
			setError(res);
			setRows([]);
		} else {
			setRows(res?.data || []);
			setMeta(res?.meta || null);
			setVerdictCounts(res?.verdict_counts || null);
		}
		setLoading(false);
	}, [status, page, search]);

	useEffect(() => { load(); }, [load]);

	const segments = verdictCounts ? [
		{ label: 'Pass', value: verdictCounts.PASS, tone: 'good' },
		{ label: 'Partial', value: verdictCounts.PARTIAL, tone: 'warn' },
		{ label: 'Fail', value: verdictCounts.FAIL, tone: 'bad' },
		{ label: 'Neutral', value: verdictCounts.NEUTRAL, tone: 'neutral' },
	] : [];

	const columns = [
		{
			key: 'page',
			label: __('URL', 'ebq-seo'),
			render: (row) => (
				<a href={row.page} target="_blank" rel="noopener noreferrer" className="ebq-hq-pages-url" title={row.page}>{shortUrl(row.page)}</a>
			),
		},
		{
			key: 'verdict',
			label: __('Verdict', 'ebq-seo'),
			render: (row) => row.verdict ? <Pill tone={verdictTone(row.verdict)}>{row.verdict}</Pill> : <span className="ebq-hq-muted">—</span>,
		},
		{
			key: 'coverage_state',
			label: __('Coverage', 'ebq-seo'),
			render: (row) => row.coverage_state || <span className="ebq-hq-muted">—</span>,
		},
		{
			key: 'last_crawl_at',
			label: __('Last crawl', 'ebq-seo'),
			align: 'right',
			render: (row) => relTime(row.last_crawl_at),
		},
		{
			key: 'last_checked_at',
			label: __('Checked', 'ebq-seo'),
			align: 'right',
			render: (row) => relTime(row.last_checked_at),
		},
	];

	return (
		<div className="ebq-hq-page">
			<div className="ebq-hq-page__head">
				<h2 className="ebq-hq-page__title">{__('Index Status', 'ebq-seo')}</h2>
				<input
					className="ebq-hq-search"
					placeholder={__('Filter URL…', 'ebq-seo')}
					value={search}
					onChange={(e) => { setSearch(e.target.value); setPage(1); }}
				/>
			</div>

			{verdictCounts ? (
				<Card title={__('Indexing verdicts', 'ebq-seo')}>
					<StackedBar segments={segments} />
					<div className="ebq-hq-legend">
						{segments.map((s) => (
							<button
								key={s.label}
								type="button"
								className={`ebq-hq-legend__item ebq-hq-legend__item--${s.tone}${status === s.label.toUpperCase() ? ' is-active' : ''}`}
								onClick={() => { setStatus(status === s.label.toUpperCase() ? '' : s.label.toUpperCase()); setPage(1); }}
							>
								<span className="ebq-hq-legend__dot" />
								<strong>{s.value.toLocaleString()}</strong>
								<span>{s.label}</span>
							</button>
						))}
					</div>
				</Card>
			) : null}

			<div className="ebq-hq-filter-row">
				{FILTERS.map((f) => (
					<button
						key={f.key || 'all'}
						type="button"
						className={`ebq-hq-filter${status === f.key ? ' is-active' : ''}`}
						onClick={() => { setStatus(f.key); setPage(1); }}
					>{f.label}</button>
				))}
			</div>

			<Card padding="flush">
				{error ? <ErrorState error={error} retry={load} /> : (
					<DataTable
						columns={columns}
						rows={rows}
						loading={loading}
						meta={meta}
						onPage={setPage}
						emptyTitle={__('No URL Inspection data yet', 'ebq-seo')}
						emptySub={__('EBQ syncs Google index status nightly. Once synced, results land here.', 'ebq-seo')}
					/>
				)}
			</Card>
		</div>
	);
}

function verdictTone(v) {
	if (v === 'PASS') return 'good';
	if (v === 'PARTIAL') return 'warn';
	if (v === 'FAIL') return 'bad';
	return 'neutral';
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

function shortUrl(url) {
	try {
		const u = new URL(url);
		return u.pathname + u.search + u.hash;
	} catch {
		return url;
	}
}
