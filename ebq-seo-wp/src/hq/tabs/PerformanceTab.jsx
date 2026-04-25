import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Api, RANGES } from '../api';
import { Card, ErrorState, SkeletonRows, RangePicker, Pill } from '../components/primitives';
import { LineChart } from '../components/charts';

const METRICS = [
	{ key: 'clicks', label: 'Clicks', color: '#4f46e5' },
	{ key: 'impressions', label: 'Impressions', color: '#0891b2' },
	{ key: 'position', label: 'Avg position', color: '#dc2626' },
	{ key: 'ctr', label: 'CTR (%)', color: '#16a34a' },
];

export default function PerformanceTab() {
	const [range, setRange] = useState('30d');
	const [metric, setMetric] = useState('clicks');
	const [data, setData] = useState(null);
	const [error, setError] = useState(null);
	const [loading, setLoading] = useState(true);

	const load = useCallback(async () => {
		setLoading(true);
		setError(null);
		const res = await Api.performance(range);
		if (res?.ok === false || res?.error) {
			setError(res);
			setData(null);
		} else {
			setData(res);
		}
		setLoading(false);
	}, [range]);

	useEffect(() => { load(); }, [load]);

	const totals = (data?.series || []).reduce((acc, r) => ({
		clicks: acc.clicks + (r.clicks || 0),
		impressions: acc.impressions + (r.impressions || 0),
		position: acc.position + (r.position || 0),
		positionN: acc.positionN + (r.position ? 1 : 0),
		ctr: acc.ctr + (r.ctr || 0),
		ctrN: acc.ctrN + (r.ctr ? 1 : 0),
	}), { clicks: 0, impressions: 0, position: 0, positionN: 0, ctr: 0, ctrN: 0 });

	return (
		<div className="ebq-hq-page">
			<div className="ebq-hq-page__head">
				<h2 className="ebq-hq-page__title">{__('SEO Performance', 'ebq-seo')}</h2>
				<RangePicker value={range} options={RANGES} onChange={setRange} />
			</div>

			<Card
				title={METRICS.find((m) => m.key === metric)?.label}
				action={(
					<div className="ebq-hq-metric-pills">
						{METRICS.map((m) => (
							<button
								key={m.key}
								type="button"
								className={`ebq-hq-metric-pill${metric === m.key ? ' is-active' : ''}`}
								style={metric === m.key ? { borderColor: m.color, color: m.color } : null}
								onClick={() => setMetric(m.key)}
							>
								<span className="ebq-hq-metric-pill__dot" style={{ background: m.color }} />
								{m.label}
							</button>
						))}
					</div>
				)}
			>
				{loading ? <SkeletonRows rows={6} /> : error ? <ErrorState error={error} retry={load} /> : (
					data?.series?.length > 0 ? (
						<LineChart series={data.series} metric={metric} color={METRICS.find((m) => m.key === metric)?.color} />
					) : (
						<p className="ebq-hq-help">{__('No data for this range.', 'ebq-seo')}</p>
					)
				)}
			</Card>

			<div className="ebq-hq-grid ebq-hq-grid--4">
				<Card title={__('Total clicks', 'ebq-seo')}>
					<div className="ebq-hq-bignum">{totals.clicks.toLocaleString()}</div>
				</Card>
				<Card title={__('Total impressions', 'ebq-seo')}>
					<div className="ebq-hq-bignum">{totals.impressions.toLocaleString()}</div>
				</Card>
				<Card title={__('Avg position', 'ebq-seo')}>
					<div className="ebq-hq-bignum">{totals.positionN ? (totals.position / totals.positionN).toFixed(1) : '—'}</div>
				</Card>
				<Card title={__('Avg CTR', 'ebq-seo')}>
					<div className="ebq-hq-bignum">{totals.ctrN ? (totals.ctr / totals.ctrN).toFixed(2) : '—'}<span className="ebq-hq-bignum__suffix">%</span></div>
				</Card>
			</div>
		</div>
	);
}
