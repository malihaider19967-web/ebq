import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Api, RANGES } from '../api';
import { Card, ErrorState, RangePicker } from '../components/primitives';
import { DataTable } from '../components/DataTable';

export default function PagesTab() {
	const [range, setRange] = useState('30d');
	const [rows, setRows] = useState([]);
	const [meta, setMeta] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const [sort, setSort] = useState('clicks');
	const [dir, setDir] = useState('desc');
	const [page, setPage] = useState(1);
	const [search, setSearch] = useState('');

	const load = useCallback(async () => {
		setLoading(true);
		setError(null);
		const res = await Api.pages({ range, sort, dir, page, search, per_page: 25 });
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
		if (sort === key) {
			setDir(dir === 'asc' ? 'desc' : 'asc');
		} else {
			setSort(key);
			setDir('desc');
		}
		setPage(1);
	};

	const columns = [
		{
			key: 'page',
			label: __('URL', 'ebq-seo'),
			render: (row) => (
				<a className="ebq-hq-pages-url" href={row.page} target="_blank" rel="noopener noreferrer" title={row.page}>
					{shortUrl(row.page)}
				</a>
			),
		},
		{
			key: 'clicks',
			label: __('Clicks', 'ebq-seo'),
			sortable: true,
			align: 'right',
			render: (row) => (row.clicks || 0).toLocaleString(),
		},
		{
			key: 'impressions',
			label: __('Impressions', 'ebq-seo'),
			sortable: true,
			align: 'right',
			render: (row) => (row.impressions || 0).toLocaleString(),
		},
		{
			key: 'ctr',
			label: __('CTR', 'ebq-seo'),
			sortable: true,
			align: 'right',
			render: (row) => `${(row.ctr || 0).toFixed(2)}%`,
		},
		{
			key: 'position',
			label: __('Avg pos', 'ebq-seo'),
			sortable: true,
			align: 'right',
			render: (row) => row.position !== null ? row.position.toFixed(1) : '—',
		},
	];

	return (
		<div className="ebq-hq-page">
			<div className="ebq-hq-page__head">
				<h2 className="ebq-hq-page__title">{__('Pages', 'ebq-seo')}</h2>
				<div className="ebq-hq-page__head-actions">
					<RangePicker value={range} options={RANGES} onChange={(v) => { setRange(v); setPage(1); }} />
					<input
						className="ebq-hq-search"
						placeholder={__('Filter URL…', 'ebq-seo')}
						value={search}
						onChange={(e) => { setSearch(e.target.value); setPage(1); }}
					/>
				</div>
			</div>

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
						emptyTitle={__('No GSC pages in this range', 'ebq-seo')}
						emptySub={__('Pages appear once Google has impressions for them.', 'ebq-seo')}
					/>
				)}
			</Card>
		</div>
	);
}

function shortUrl(url) {
	try {
		const u = new URL(url);
		return u.pathname + u.search + u.hash;
	} catch {
		return url;
	}
}
