import { useState, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Phase 3 #10 — Backlink prospecting.
 *
 * User pastes a few competitor domains, EBQ returns referring domains
 * that link to those competitors but NOT to the user's site, ranked by
 * domain authority. Pro-tier users can click "Draft outreach" to get an
 * AI-written personalized email (cached 7 days per prospect).
 *
 * Network-effect: every other EBQ user's audits enrich the
 * `competitor_backlinks` set, so a first-day Pro user can already
 * prospect against any competitor that's been audited by anyone on the
 * platform.
 */
export default function ProspectsTab() {
	const [competitors, setCompetitors] = useState('');
	const [data, setData] = useState(null);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);
	const [draftId, setDraftId] = useState(null);
	const [draft, setDraft] = useState(null);

	const fetchProspects = useCallback(() => {
		const list = competitors
			.split(/[\n,]/)
			.map((s) => s.trim())
			.filter(Boolean);
		if (list.length === 0) return;
		setLoading(true);
		setError(null);
		setData(null);
		apiFetch({
			path: '/ebq/v1/hq/backlink-prospects',
			method: 'POST',
			data: { competitors: list },
		})
			.then((res) => { setData(res); setLoading(false); })
			.catch((err) => { setError(err?.message || 'Failed to load'); setLoading(false); });
	}, [competitors]);

	const draftOutreach = useCallback((prospect) => {
		setDraftId(prospect.domain);
		setDraft(null);
		apiFetch({
			path: '/ebq/v1/hq/backlink-prospects/draft',
			method: 'POST',
			data: {
				prospect: { domain: prospect.domain, linked_to: prospect.linked_to },
				our_page_url: window.location.origin,
				our_page_title: '',
				our_page_summary: '',
			},
		})
			.then((res) => {
				if (res?.ok) {
					setDraft({ domain: prospect.domain, subject: res.subject, body: res.body, cached: res.cached });
				} else if (res?.error === 'tier_required') {
					setDraft({ domain: prospect.domain, error: __('AI outreach drafting requires Pro.', 'ebq-seo'), upgrade: true });
				} else {
					setDraft({ domain: prospect.domain, error: res?.message || res?.error || 'Failed' });
				}
				setDraftId(null);
			})
			.catch((err) => {
				setDraft({ domain: prospect.domain, error: err?.message || 'Network error' });
				setDraftId(null);
			});
	}, []);

	return (
		<div className="ebq-hq-tab">
			<header className="ebq-hq-tab__head">
				<h2>{__('Backlink prospects', 'ebq-seo')}</h2>
				<p className="ebq-hq-tab__sub">
					{__('Find domains that link to your competitors but NOT to you. Paste competitor domains (one per line or comma-separated).', 'ebq-seo')}
				</p>
			</header>

			<textarea
				className="ebq-hq-prospects__input"
				rows={3}
				placeholder={__('competitor1.com\ncompetitor2.com', 'ebq-seo')}
				value={competitors}
				onChange={(e) => setCompetitors(e.target.value)}
			/>
			<div className="ebq-hq-toolbar">
				<button
					type="button"
					className="ebq-hq-btn ebq-hq-btn--primary"
					onClick={fetchProspects}
					disabled={loading || !competitors.trim()}
				>
					{loading ? __('Searching…', 'ebq-seo') : __('Find prospects', 'ebq-seo')}
				</button>
			</div>

			{error ? <p className="ebq-hq-empty ebq-hq-empty--error">{error}</p> : null}

			{!loading && data ? (
				<>
					<p className="ebq-hq-meta">
						{sprintf(
							__('%d prospects found across %d competitors. You already have backlinks from %d domains.', 'ebq-seo'),
							data.summary?.prospect_count ?? 0,
							data.summary?.competitors_analyzed ?? 0,
							data.summary?.your_existing_links ?? 0,
						)}
						{data.cached ? <em> · {__('cached', 'ebq-seo')}</em> : null}
					</p>

					{(data.prospects || []).length ? (
						<table className="ebq-hq-table ebq-hq-table--prospects">
							<thead>
								<tr>
									<th>{__('Domain', 'ebq-seo')}</th>
									<th>{__('DA', 'ebq-seo')}</th>
									<th>{__('Linked to', 'ebq-seo')}</th>
									<th>{__('Anchor examples', 'ebq-seo')}</th>
									<th>{__('Action', 'ebq-seo')}</th>
								</tr>
							</thead>
							<tbody>
								{data.prospects.map((p) => (
									<>
										<tr key={p.domain}>
											<td>
												<a href={`https://${p.domain}`} target="_blank" rel="noopener noreferrer">{p.domain}</a>
											</td>
											<td>{p.domain_authority ?? '—'}</td>
											<td>{(p.linked_to || []).join(', ') || '—'}</td>
											<td className="ebq-hq-prospects__anchors">{(p.anchor_examples || []).slice(0, 2).join(' · ') || '—'}</td>
											<td>
												<button
													type="button"
													className="ebq-hq-btn ebq-hq-btn--sm"
													disabled={draftId === p.domain}
													onClick={() => draftOutreach(p)}
												>
													{draftId === p.domain ? __('Drafting…', 'ebq-seo') : __('Draft outreach', 'ebq-seo')}
												</button>
											</td>
										</tr>
										{draft?.domain === p.domain ? (
											<tr key={`${p.domain}-draft`}>
												<td colSpan={5} className="ebq-hq-prospects__draft-cell">
													{draft.error ? (
														<div className="ebq-hq-prospects__draft-err">
															{draft.error}
															{draft.upgrade ? <a href="#" target="_blank" rel="noopener noreferrer"> · {__('Upgrade', 'ebq-seo')}</a> : null}
														</div>
													) : (
														<div className="ebq-hq-prospects__draft">
															<div className="ebq-hq-prospects__draft-subject">
																<strong>{__('Subject:', 'ebq-seo')}</strong> {draft.subject}
															</div>
															<pre className="ebq-hq-prospects__draft-body">{draft.body}</pre>
															{draft.cached ? <span className="ebq-hq-muted">{__('cached', 'ebq-seo')}</span> : null}
														</div>
													)}
												</td>
											</tr>
										) : null}
									</>
								))}
							</tbody>
						</table>
					) : (
						<p className="ebq-hq-empty">
							{__('No prospects found. Either the competitors haven\'t been audited on EBQ yet (try running a page audit on one of their pages first), or every domain that links to them already links to you too.', 'ebq-seo')}
						</p>
					)}
				</>
			) : null}
		</div>
	);
}
