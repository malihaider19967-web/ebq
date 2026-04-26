import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Phase 3 #7 — "You vs the EBQ network" benchmarks.
 *
 * Aggregates GSC averages across the entire EBQ network anonymously
 * (min cohort 5 sites). Reports your avg position + percentile rank
 * against global, with optional country cohort. The percentile is the
 * single most powerful number on this screen — agencies use it to
 * prove progress to clients without leaking competitor data.
 */
export default function BenchmarksTab() {
	const [country, setCountry] = useState('');
	const [data, setData] = useState(null);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);

	useEffect(() => {
		setLoading(true);
		setError(null);
		const path = country
			? `/ebq/v1/hq/benchmarks?country=${encodeURIComponent(country)}`
			: '/ebq/v1/hq/benchmarks';
		apiFetch({ path })
			.then((res) => { setData(res || null); setLoading(false); })
			.catch((err) => { setError(err?.message || 'Failed to load'); setLoading(false); });
	}, [country]);

	return (
		<div className="ebq-hq-tab">
			<header className="ebq-hq-tab__head">
				<h2>{__('Network benchmarks', 'ebq-seo')}</h2>
				<p className="ebq-hq-tab__sub">
					{__('How your site compares to other EBQ-connected sites — fully anonymized aggregate stats. The percentile is "what % of network sites rank worse than you on average".', 'ebq-seo')}
				</p>
			</header>

			<div className="ebq-hq-toolbar">
				<label className="ebq-hq-toolbar__label">{__('Country cohort:', 'ebq-seo')}</label>
				<select value={country} onChange={(e) => setCountry(e.target.value)}>
					<option value="">{__('Global only', 'ebq-seo')}</option>
					<option value="us">United States</option>
					<option value="gb">United Kingdom</option>
					<option value="ca">Canada</option>
					<option value="au">Australia</option>
					<option value="in">India</option>
					<option value="de">Germany</option>
					<option value="fr">France</option>
					<option value="pk">Pakistan</option>
				</select>
			</div>

			{loading ? <p className="ebq-hq-empty">{__('Loading…', 'ebq-seo')}</p> : null}
			{error ? <p className="ebq-hq-empty ebq-hq-empty--error">{error}</p> : null}

			{!loading && !error && data ? (
				data.ok === false ? (
					<p className="ebq-hq-empty">
						{data.reason === 'cohort_too_small'
							? __('Network cohort is still building (min 5 sites). Check back as more sites connect.', 'ebq-seo')
							: __('Benchmarks are not available right now.', 'ebq-seo')}
					</p>
				) : (
					<>
						{data.percentile != null ? (
							<div className="ebq-bench-percentile">
								<div className="ebq-bench-percentile__num">{data.percentile}<small>{__('th percentile', 'ebq-seo')}</small></div>
								<div className="ebq-bench-percentile__caption">
									{sprintf(
										__('Your site ranks better than %d%% of the %d sites in the network cohort.', 'ebq-seo'),
										data.percentile,
										data.global?.sample_size ?? 0,
									)}
								</div>
							</div>
						) : null}

						<div className="ebq-bench-grid">
							<BenchCard label={__('You', 'ebq-seo')} stats={data.your} />
							<BenchCard label={__('Network avg', 'ebq-seo')} stats={data.global} highlight />
							{data.country ? (
								<BenchCard label={sprintf(__('%s cohort', 'ebq-seo'), (data.country.country || '').toUpperCase())} stats={data.country} />
							) : null}
						</div>
					</>
				)
			) : null}
		</div>
	);
}

function BenchCard({ label, stats, highlight }) {
	if (!stats) return null;
	return (
		<div className={`ebq-bench-card${highlight ? ' is-highlight' : ''}`}>
			<div className="ebq-bench-card__label">{label}</div>
			<div className="ebq-bench-card__row">
				<span>{__('Avg position', 'ebq-seo')}</span>
				<strong>{stats.avg_position ?? '—'}</strong>
			</div>
			{stats.p50_position != null ? (
				<div className="ebq-bench-card__row">
					<span>{__('Median (p50)', 'ebq-seo')}</span>
					<strong>{stats.p50_position}</strong>
				</div>
			) : null}
			{stats.p90_position != null ? (
				<div className="ebq-bench-card__row">
					<span>{__('Top 10% (p90)', 'ebq-seo')}</span>
					<strong>{stats.p90_position}</strong>
				</div>
			) : null}
			<div className="ebq-bench-card__row">
				<span>{__('Avg CTR', 'ebq-seo')}</span>
				<strong>{stats.ctr_pct != null ? `${stats.ctr_pct}%` : '—'}</strong>
			</div>
			{stats.queries_30d != null ? (
				<div className="ebq-bench-card__row">
					<span>{__('Queries / 30d', 'ebq-seo')}</span>
					<strong>{stats.queries_30d}</strong>
				</div>
			) : null}
			{stats.sample_size != null ? (
				<div className="ebq-bench-card__sample">{sprintf(__('n=%d sites', 'ebq-seo'), stats.sample_size)}</div>
			) : null}
		</div>
	);
}
