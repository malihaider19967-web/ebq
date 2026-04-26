import { useState, useEffect, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Phase 3 #6 — Live SERP-feature presence per tracked keyword.
 *
 * Two cuts:
 *   - Top summary: % of tracked keywords currently showing answer-box,
 *     PAA, image-pack — the "is the SERP feature-heavy?" signal.
 *   - Per-keyword table: which features each keyword shows TODAY plus a
 *     30-day mini-timeline so you can spot volatility (a feature that
 *     comes and goes is an outreach opportunity).
 *
 * "Owned" badge highlights keywords where YOUR domain appears inside
 * a feature block — the highest-value signal the agency clientele
 * stares at every morning.
 */
export default function SerpFeaturesTab() {
	const [days, setDays] = useState(30);
	const [data, setData] = useState(null);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);

	useEffect(() => {
		setLoading(true);
		setError(null);
		apiFetch({ path: `/ebq/v1/hq/serp-features?days=${days}` })
			.then((res) => {
				setData(res || null);
				setLoading(false);
			})
			.catch((err) => {
				setError(err?.message || 'Failed to load');
				setLoading(false);
			});
	}, [days]);

	const summaryPct = useMemo(() => {
		if (!data?.summary?.total) return null;
		const t = data.summary.total;
		return {
			ab: Math.round((data.summary.with_answer_box / t) * 100),
			paa: Math.round((data.summary.with_paa / t) * 100),
			img: Math.round((data.summary.with_image_pack / t) * 100),
			any: Math.round((data.summary.with_any_feature / t) * 100),
		};
	}, [data]);

	return (
		<div className="ebq-hq-tab">
			<header className="ebq-hq-tab__head">
				<h2>{__('SERP features', 'ebq-seo')}</h2>
				<p className="ebq-hq-tab__sub">
					{__('Which Google features (answer box, People Also Ask, image pack, sitelinks) appear for your tracked keywords — and which ones YOU own. Tracked daily by EBQ rank tracker; no extra credits to view.', 'ebq-seo')}
				</p>
			</header>

			<div className="ebq-hq-toolbar">
				{[7, 30, 90].map((d) => (
					<button
						key={d}
						type="button"
						className={`ebq-hq-toolbar__filter${d === days ? ' is-active' : ''}`}
						onClick={() => setDays(d)}
					>
						{sprintf(__('%d days', 'ebq-seo'), d)}
					</button>
				))}
			</div>

			{loading ? <p className="ebq-hq-empty">{__('Loading…', 'ebq-seo')}</p> : null}
			{error ? <p className="ebq-hq-empty ebq-hq-empty--error">{error}</p> : null}

			{!loading && !error && data ? (
				<>
					{summaryPct ? (
						<div className="ebq-serpf-summary">
							<SummaryStat label={__('with answer box', 'ebq-seo')} pct={summaryPct.ab} />
							<SummaryStat label={__('with PAA', 'ebq-seo')} pct={summaryPct.paa} />
							<SummaryStat label={__('with image pack', 'ebq-seo')} pct={summaryPct.img} />
							<SummaryStat label={__('any feature', 'ebq-seo')} pct={summaryPct.any} />
						</div>
					) : null}

					{data.keywords?.length ? (
						<table className="ebq-hq-table ebq-hq-table--serpf">
							<thead>
								<tr>
									<th>{__('Keyword', 'ebq-seo')}</th>
									<th>{__('Country', 'ebq-seo')}</th>
									<th>{__('Features today', 'ebq-seo')}</th>
									<th>{__('You own', 'ebq-seo')}</th>
									<th>{__('Days seen (30d)', 'ebq-seo')}</th>
								</tr>
							</thead>
							<tbody>
								{data.keywords.map((kw) => (
									<tr key={kw.id}>
										<td>{kw.keyword}</td>
										<td>{kw.country?.toUpperCase() || '—'}</td>
										<td>{(kw.features_today || []).map((f) => (
											<span key={f} className="ebq-serpf-pill">{prettyFeature(f)}</span>
										))}</td>
										<td>{(kw.features_owned || []).length ? (
											(kw.features_owned).map((f) => (
												<span key={f} className="ebq-serpf-pill ebq-serpf-pill--owned">{prettyFeature(f)}</span>
											))
										) : <span className="ebq-hq-muted">—</span>}</td>
										<td>{(kw.timeline || []).length}</td>
									</tr>
								))}
							</tbody>
						</table>
					) : (
						<p className="ebq-hq-empty">{__('No tracked keywords yet — add some from Rank Tracker.', 'ebq-seo')}</p>
					)}
				</>
			) : null}
		</div>
	);
}

function SummaryStat({ label, pct }) {
	const tone = pct >= 60 ? 'good' : pct >= 30 ? 'warn' : 'bad';
	return (
		<div className={`ebq-serpf-stat ebq-serpf-stat--${tone}`}>
			<span className="ebq-serpf-stat__num">{pct}%</span>
			<span className="ebq-serpf-stat__label">{label}</span>
		</div>
	);
}

function prettyFeature(key) {
	switch (key) {
		case 'answer_box':      return 'Answer Box';
		case 'people_also_ask': return 'PAA';
		case 'image_pack':      return 'Images';
		case 'sitelinks':       return 'Sitelinks';
		case 'video':           return 'Video';
		case 'top_stories':     return 'Top Stories';
		case 'knowledge_panel': return 'Knowledge';
		case 'shopping':        return 'Shopping';
		default:                 return key;
	}
}
