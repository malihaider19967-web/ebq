import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Api, RANGES, HQ_CONFIG } from '../api';
import { Card, ErrorState, Button, RangePicker, Pill, SourceTag } from '../components/primitives';
import { DataTable } from '../components/DataTable';
import AddKeywordModal from '../components/AddKeywordModal';

/**
 * Every query the site has GSC impressions for in the chosen range. The
 * universe RankMath calls "All keywords" — distinct from the curated
 * Rank Tracker watchlist on the next-door tab. Each row shows whether it's
 * already tracked; if not, the user can promote it with one click.
 */
export default function GscKeywordsTab() {
	const [range, setRange] = useState('30d');
	const [rows, setRows] = useState([]);
	const [meta, setMeta] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [sort, setSort] = useState('impressions');
	const [dir, setDir] = useState('desc');
	const [page, setPage] = useState(1);
	const [search, setSearch] = useState('');
	const [addOpen, setAddOpen] = useState(false);
	const [seedKeyword, setSeedKeyword] = useState('');
	const [toast, setToast] = useState(null);

	const load = useCallback(async () => {
		setLoading(true);
		setError(null);
		const res = await Api.gscKeywords({ range, sort, dir, page, search, per_page: 25 });
		if (res?.ok === false || res?.error) {
			setError(res);
			setRows([]);
		} else {
			setRows(res?.data || []);
			setMeta(res?.meta || null);
		}
		setLoading(false);
	}, [range, sort, dir, page, search]);

	useEffect(() => { load(); }, [load]);

	const handleSort = (key) => {
		if (sort === key) setDir(dir === 'asc' ? 'desc' : 'asc');
		else { setSort(key); setDir(key === 'query' ? 'asc' : 'desc'); }
		setPage(1);
	};

	const flashToast = (msg, tone = 'good') => {
		setToast({ msg, tone });
		setTimeout(() => setToast(null), 3500);
	};

	const promoteKeyword = (kw) => {
		setSeedKeyword(kw);
		setAddOpen(true);
	};

	const columns = [
		{
			key: 'query',
			label: __('Query', 'ebq-seo'),
			sortable: true,
			render: (row) => (
				<div className="ebq-hq-query-cell">
					<strong>{row.query}</strong>
					{row.is_tracked ? <Pill tone="good">{__('TRACKED', 'ebq-seo')}</Pill> : null}
				</div>
			),
		},
		{
			key: 'clicks',
			label: <>{__('Clicks', 'ebq-seo')} <SourceTag source="gsc" /></>,
			sortable: true,
			align: 'right',
			render: (row) => (row.clicks || 0).toLocaleString(),
		},
		{
			key: 'impressions',
			label: <>{__('Impressions', 'ebq-seo')} <SourceTag source="gsc" /></>,
			sortable: true,
			align: 'right',
			render: (row) => (row.impressions || 0).toLocaleString(),
		},
		{
			key: 'ctr',
			label: <>{__('CTR', 'ebq-seo')} <SourceTag source="gsc" /></>,
			sortable: true,
			align: 'right',
			render: (row) => `${(row.ctr || 0).toFixed(2)}%`,
		},
		{
			key: 'position',
			label: <span title="GSC reported avg position across all impressions for this query.">{__('GSC avg pos', 'ebq-seo')} <SourceTag source="gsc" /></span>,
			sortable: true,
			align: 'right',
			render: (row) => row.position !== null ? row.position.toFixed(1) : '—',
		},
		{
			key: '_actions',
			label: '',
			align: 'right',
			render: (row) => row.is_tracked ? (
				<span className="ebq-hq-muted" style={{ fontSize: 11 }}>—</span>
			) : (
				<button
					type="button"
					className="ebq-hq-btn ebq-hq-btn--ghost ebq-hq-btn--sm"
					onClick={() => promoteKeyword(row.query)}
					title={__('Add to Rank Tracker for live SERP monitoring', 'ebq-seo')}
				>+ {__('Track', 'ebq-seo')}</button>
			),
		},
	];

	return (
		<div className="ebq-hq-page">
			<div className="ebq-hq-page__head">
				<h2 className="ebq-hq-page__title">{__('Keywords', 'ebq-seo')} <SourceTag source="gsc" /></h2>
				<div className="ebq-hq-page__head-actions">
					<RangePicker value={range} options={RANGES} onChange={(v) => { setRange(v); setPage(1); }} />
					<input
						className="ebq-hq-search"
						placeholder={__('Filter query…', 'ebq-seo')}
						value={search}
						onChange={(e) => { setSearch(e.target.value); setPage(1); }}
					/>
				</div>
			</div>

			<div className="ebq-hq-source-banner">
				<span className="ebq-hq-source-banner__icon">i</span>
				<span>
					<strong>{__('Every query Google has shown your site for.', 'ebq-seo')}</strong>{' '}
					{__('Hit', 'ebq-seo')} <Pill tone="neutral">+ {__('Track', 'ebq-seo')}</Pill> {__('on any row to add it to the Rank Tracker for live SERP monitoring with country/device targeting.', 'ebq-seo')}
				</span>
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
						emptyTitle={__('No GSC queries in this range', 'ebq-seo')}
						emptySub={__('Queries appear once Google has impressions for them.', 'ebq-seo')}
					/>
				)}
			</Card>

			<AddKeywordModal
				open={addOpen}
				onClose={() => { setAddOpen(false); setSeedKeyword(''); }}
				onCreated={() => { flashToast(__('Now tracking — first SERP check queued.', 'ebq-seo'), 'good'); load(); }}
				defaultDomain={HQ_CONFIG.workspaceDomain}
				seedKeyword={seedKeyword}
			/>
		</div>
	);
}
