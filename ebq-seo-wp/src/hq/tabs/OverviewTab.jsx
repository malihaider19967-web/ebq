import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Api, RANGES } from '../api';
import { Card, KpiCard, Pill, Button, ErrorState, SkeletonRows, RangePicker, EmptyState } from '../components/primitives';
import { Sparkline, BarChart } from '../components/charts';

export default function OverviewTab() {
	const [range, setRange] = useState('30d');
	const [data, setData] = useState(null);
	const [error, setError] = useState(null);
	const [loading, setLoading] = useState(true);

	const load = useCallback(async () => {
		setLoading(true);
		setError(null);
		const res = await Api.overview(range);
		if (res?.ok === false || res?.error) {
			setError(res);
			setData(null);
		} else {
			setData(res);
		}
		setLoading(false);
	}, [range]);

	useEffect(() => { load(); }, [load]);

	const openInEbq = useCallback(async (insight) => {
		const res = await Api.iframeUrl(insight);
		if (res?.url) {
			window.open(res.url, '_blank', 'noopener');
		}
	}, []);

	if (loading) {
		return (
			<div className="ebq-hq-grid">
				<div className="ebq-hq-kpi-row"><SkeletonRows rows={2} /></div>
			</div>
		);
	}
	if (error) return <ErrorState error={error} retry={load} />;
	if (!data) return null;

	const { kpi, position_distribution, sparkline, insight_counts, top_winning_keywords, top_losing_keywords } = data;

	const slabs = [
		{ label: __('Top 3', 'ebq-seo'), value: position_distribution?.top_3 || 0 },
		{ label: __('4–10', 'ebq-seo'), value: position_distribution?.top_10 || 0 },
		{ label: __('11–50', 'ebq-seo'), value: position_distribution?.top_50 || 0 },
		{ label: __('51–100', 'ebq-seo'), value: position_distribution?.top_100 || 0 },
	];

	return (
		<div className="ebq-hq-page">
			<div className="ebq-hq-page__head">
				<h2 className="ebq-hq-page__title">{__('Overview', 'ebq-seo')}</h2>
				<RangePicker value={range} options={RANGES} onChange={setRange} />
			</div>

			<div className="ebq-hq-kpi-row">
				<KpiCard label={__('Search clicks', 'ebq-seo')} value={kpi.clicks.value} change={kpi.clicks} sparkline={<Sparkline data={sparkline} color="#4f46e5" />} />
				<KpiCard label={__('Impressions', 'ebq-seo')} value={kpi.impressions.value} change={kpi.impressions} sparkline={<Sparkline data={sparkline} color="#0891b2" />} />
				<KpiCard label={__('Avg. position', 'ebq-seo')} value={kpi.avg_position.value} change={kpi.avg_position} />
				<KpiCard label={__('CTR', 'ebq-seo')} value={kpi.ctr.value} suffix="%" change={kpi.ctr} />
				<KpiCard label={__('Ranking keywords', 'ebq-seo')} value={kpi.ranking_keywords.value} sub={__('queries in top 100', 'ebq-seo')} />
				<KpiCard label={__('Tracked keywords', 'ebq-seo')} value={kpi.tracked_keywords.value} sub={__('Rank Tracker', 'ebq-seo')} />
			</div>

			<div className="ebq-hq-grid ebq-hq-grid--2">
				<Card title={__('Keyword position distribution', 'ebq-seo')} action={<Pill tone="neutral">{range}</Pill>}>
					<BarChart items={slabs} valueKey="value" labelKey="label" />
				</Card>

				<Card title={__('Opportunities', 'ebq-seo')}>
					<div className="ebq-hq-insights-counts">
						<InsightCount label={__('Cannibalizations', 'ebq-seo')} count={insight_counts?.cannibalizations} tone="warn" onOpen={() => openInEbq('cannibalization')} />
						<InsightCount label={__('Striking distance', 'ebq-seo')} count={insight_counts?.striking_distance} tone="good" onOpen={() => openInEbq('striking_distance')} />
						<InsightCount label={__('Index fails', 'ebq-seo')} count={insight_counts?.indexing_fails_with_traffic} tone="bad" onOpen={() => openInEbq('indexing_fails')} />
						<InsightCount label={__('Content decay', 'ebq-seo')} count={insight_counts?.content_decay} tone="warn" onOpen={() => openInEbq('content_decay')} />
						<InsightCount label={__('Quick wins', 'ebq-seo')} count={insight_counts?.quick_wins} tone="good" onOpen={() => openInEbq('quick_wins')} />
					</div>
				</Card>
			</div>

			<div className="ebq-hq-grid ebq-hq-grid--2">
				<MoversCard title={__('Top winning keywords', 'ebq-seo')} rows={top_winning_keywords} direction="up" />
				<MoversCard title={__('Top losing keywords', 'ebq-seo')} rows={top_losing_keywords} direction="down" />
			</div>
		</div>
	);
}

function InsightCount({ label, count, tone, onOpen }) {
	const n = count ?? 0;
	return (
		<button type="button" className={`ebq-hq-insight-count ebq-hq-insight-count--${tone}`} onClick={onOpen}>
			<span className="ebq-hq-insight-count__num">{n.toLocaleString()}</span>
			<span className="ebq-hq-insight-count__label">{label}</span>
			<span className="ebq-hq-insight-count__cta">{__('Open in EBQ', 'ebq-seo')} →</span>
		</button>
	);
}

function MoversCard({ title, rows, direction }) {
	if (!rows || rows.length === 0) {
		return (
			<Card title={title}>
				<EmptyState title={__('Not enough data yet', 'ebq-seo')} sub={__('We need at least one full period of GSC history to compare.', 'ebq-seo')} />
			</Card>
		);
	}
	return (
		<Card title={title}>
			<table className="ebq-hq-table ebq-hq-table--compact">
				<thead>
					<tr>
						<th>{__('Keyword', 'ebq-seo')}</th>
						<th className="ebq-hq-table__th--right">{__('Position', 'ebq-seo')}</th>
						<th className="ebq-hq-table__th--right">{__('Δ', 'ebq-seo')}</th>
						<th className="ebq-hq-table__th--right">{__('Clicks', 'ebq-seo')}</th>
					</tr>
				</thead>
				<tbody>
					{rows.map((r, i) => (
						<tr key={i}>
							<td className="ebq-hq-table__td--ellipsis"><strong>{r.keyword}</strong></td>
							<td className="ebq-hq-table__td--right">{r.position}</td>
							<td className={`ebq-hq-table__td--right ebq-hq-delta ebq-hq-delta--${direction === 'up' ? 'up' : 'down'}`}>
								{direction === 'up' ? '▲ ' : '▼ '}{Math.abs(r.delta)}
							</td>
							<td className="ebq-hq-table__td--right">{r.clicks?.toLocaleString?.() || r.clicks}</td>
						</tr>
					))}
				</tbody>
			</table>
		</Card>
	);
}
