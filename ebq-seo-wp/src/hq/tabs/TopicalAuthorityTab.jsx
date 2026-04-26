import { useState, useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Phase 3 #4 — Topical authority map.
 *
 * Renders clusters EBQ derived from this site's GSC footprint, ordered
 * by authority score. Each cluster shows the queries it covers, the
 * pages currently ranking for those queries, and (when authority is low
 * but impressions are high) a "content opportunity" gap row.
 *
 * The agency-favorite read: scan the cluster list, find one with low
 * authority + high impressions, that's literally next quarter's content
 * brief.
 */
export default function TopicalAuthorityTab() {
	const [data, setData] = useState(null);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);
	const [openCluster, setOpenCluster] = useState(null);

	useEffect(() => {
		setLoading(true);
		setError(null);
		apiFetch({ path: '/ebq/v1/hq/topical-authority' })
			.then((res) => { setData(res); setLoading(false); })
			.catch((err) => { setError(err?.message || 'Failed to load'); setLoading(false); });
	}, []);

	return (
		<div className="ebq-hq-tab">
			<header className="ebq-hq-tab__head">
				<h2>{__('Topical authority map', 'ebq-seo')}</h2>
				<p className="ebq-hq-tab__sub">
					{__('Clusters of GSC queries you rank for, scored by depth × traffic × position. Low-authority high-impression clusters are explicit content opportunities — write a definitive page for them.', 'ebq-seo')}
				</p>
			</header>

			{loading ? <p className="ebq-hq-empty">{__('Loading…', 'ebq-seo')}</p> : null}
			{error ? <p className="ebq-hq-empty ebq-hq-empty--error">{error}</p> : null}

			{!loading && !error && data ? (
				data.ok === false ? (
					<p className="ebq-hq-empty">
						{data.reason === 'no_gsc_data'
							? __('Not enough GSC data yet to cluster. Once Search Console accumulates queries we\'ll surface the topical map here.', 'ebq-seo')
							: __('Authority map is unavailable right now.', 'ebq-seo')}
					</p>
				) : (
					<>
						{(data.gaps || []).length ? (
							<section className="ebq-topauth-gaps">
								<h3>{__('Content opportunities', 'ebq-seo')}</h3>
								<ul>
									{data.gaps.map((g, i) => (
										<li key={i}>
											<strong>{g.label}</strong>
											<p>{g.suggested_action}</p>
										</li>
									))}
								</ul>
							</section>
						) : null}

						<table className="ebq-hq-table ebq-hq-table--topauth">
							<thead>
								<tr>
									<th>{__('Cluster', 'ebq-seo')}</th>
									<th>{__('Authority', 'ebq-seo')}</th>
									<th>{__('Avg pos', 'ebq-seo')}</th>
									<th>{__('Clicks/90d', 'ebq-seo')}</th>
									<th>{__('Imps/90d', 'ebq-seo')}</th>
									<th>{__('Pages', 'ebq-seo')}</th>
								</tr>
							</thead>
							<tbody>
								{(data.clusters || []).map((c) => {
									const tone = c.authority_score >= 65 ? 'good' : c.authority_score >= 40 ? 'warn' : 'bad';
									const open = openCluster === c.id;
									return (
										<>
											<tr key={c.id} onClick={() => setOpenCluster(open ? null : c.id)} className="is-clickable">
												<td><strong>{c.label}</strong></td>
												<td><span className={`ebq-hq-badge ebq-hq-badge--${tone}`}>{c.authority_score}</span></td>
												<td>{c.avg_position}</td>
												<td>{c.total_clicks}</td>
												<td>{c.total_impressions}</td>
												<td>{c.pages?.length ?? 0}</td>
											</tr>
											{open ? (
												<tr key={`${c.id}-detail`}>
													<td colSpan={6} className="ebq-topauth-detail">
														<div>
															<strong>{__('Queries:', 'ebq-seo')}</strong>{' '}
															{(c.queries || []).map((q, i) => <span key={i} className="ebq-topauth-chip">{q}</span>)}
														</div>
														<div>
															<strong>{__('Ranking pages:', 'ebq-seo')}</strong>
															<ul className="ebq-topauth-pages">
																{(c.pages || []).map((p, i) => (
																	<li key={i}><a href={p} target="_blank" rel="noopener noreferrer">{p}</a></li>
																))}
															</ul>
														</div>
													</td>
												</tr>
											) : null}
										</>
									);
								})}
							</tbody>
						</table>
					</>
				)
			) : null}
		</div>
	);
}
