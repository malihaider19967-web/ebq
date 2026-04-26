import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Phase 3 #10 — Backlink prospecting (persisted workflow + auto-discovery).
 *
 * Auto-discovery flow (the primary path):
 *   - On first mount, if the saved list is empty, the tab fires the
 *     /auto-discover endpoint which pulls competitor domains from this
 *     site's last 30 days of page audits and runs prospect() against them.
 *   - A nightly `ebq:auto-discover-prospects` command does the same for
 *     every connected site, so by morning the kanban already reflects
 *     yesterday's audits.
 *   - "Refresh from audits" button re-runs auto-discovery on demand.
 *
 * Manual entry stays as a fallback under "+ Add custom competitors" for
 * prospecting against domains that haven't been auto-discovered yet
 * (e.g. competitors you know about but haven't audited any page against).
 */
const STATUS_FILTERS = ['all', 'new', 'drafted', 'contacted', 'replied', 'converted', 'declined', 'snoozed'];

export default function ProspectsTab() {
	const [statusFilter, setStatusFilter] = useState('new');
	const [savedRows, setSavedRows] = useState([]);
	const [counts, setCounts] = useState({});
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);

	const [competitors, setCompetitors] = useState('');
	const [searching, setSearching] = useState(false);
	const [autoDiscovering, setAutoDiscovering] = useState(false);
	const [autoBanner, setAutoBanner] = useState(null);   // { discovered, prospect_count, new_in_run } | { reason }
	const [autoTried, setAutoTried] = useState(false);    // ensures we only auto-fire once per mount

	const [draftingId, setDraftingId] = useState(null);
	const [updatingId, setUpdatingId] = useState(null);
	const [expandedId, setExpandedId] = useState(null);
	const [notesDraft, setNotesDraft] = useState({});

	const loadSaved = useCallback((withStatus) => {
		setLoading(true);
		setError(null);
		const status = withStatus || statusFilter;
		const path = status === 'all'
			? '/ebq/v1/hq/outreach-prospects'
			: `/ebq/v1/hq/outreach-prospects?status=${encodeURIComponent(status)}`;
		apiFetch({ path })
			.then((res) => {
				setSavedRows(Array.isArray(res?.prospects) ? res.prospects : []);
				setCounts(res?.counts || {});
				setLoading(false);
			})
			.catch((err) => {
				setError(err?.message || 'Failed to load');
				setLoading(false);
			});
	}, [statusFilter]);

	useEffect(() => { loadSaved(statusFilter); }, [statusFilter, loadSaved]);

	// Auto-discover on first mount if the saved list is empty AND we
	// haven't already tried this session. Single-shot, gated on the
	// global counts being all-zero (so we don't re-fire when the user
	// just filtered to a status with no rows).
	const totalSaved = Object.values(counts).reduce((a, b) => a + b, 0);
	useEffect(() => {
		if (autoTried || loading || totalSaved > 0) return;
		setAutoTried(true);
		setAutoDiscovering(true);
		apiFetch({ path: '/ebq/v1/hq/outreach-prospects/auto-discover', method: 'POST' })
			.then((res) => {
				setAutoDiscovering(false);
				setAutoBanner(res || null);
				loadSaved(statusFilter);
			})
			.catch((err) => {
				setAutoDiscovering(false);
				setError(err?.message || 'Auto-discover failed');
			});
	}, [autoTried, loading, totalSaved, statusFilter, loadSaved]);

	const refreshFromAudits = useCallback(() => {
		setAutoDiscovering(true);
		setError(null);
		apiFetch({ path: '/ebq/v1/hq/outreach-prospects/auto-discover', method: 'POST' })
			.then((res) => {
				setAutoDiscovering(false);
				setAutoBanner(res || null);
				loadSaved(statusFilter);
			})
			.catch((err) => {
				setAutoDiscovering(false);
				setError(err?.message || 'Refresh failed');
			});
	}, [statusFilter, loadSaved]);

	const findProspects = useCallback(() => {
		const list = competitors
			.split(/[\n,]/)
			.map((s) => s.trim())
			.filter(Boolean);
		if (list.length === 0) return;
		setSearching(true);
		setError(null);
		apiFetch({
			path: '/ebq/v1/hq/backlink-prospects',
			method: 'POST',
			data: { competitors: list },
		})
			.then(() => {
				setSearching(false);
				setCompetitors('');
				loadSaved(statusFilter);
			})
			.catch((err) => {
				setError(err?.message || 'Failed to find prospects');
				setSearching(false);
			});
	}, [competitors, statusFilter, loadSaved]);

	const updateProspect = useCallback((id, patch) => {
		setUpdatingId(id);
		apiFetch({
			path: `/ebq/v1/hq/outreach-prospects/${id}`,
			method: 'POST',
			data: patch,
		})
			.then(() => {
				setUpdatingId(null);
				// Reload to reflect filter changes (e.g. moving to a status that's not in the current view).
				loadSaved(statusFilter);
			})
			.catch((err) => {
				setError(err?.message || 'Update failed');
				setUpdatingId(null);
			});
	}, [statusFilter, loadSaved]);

	const draftOutreach = useCallback((row) => {
		setDraftingId(row.id);
		apiFetch({
			path: '/ebq/v1/hq/backlink-prospects/draft',
			method: 'POST',
			data: {
				prospect: { domain: row.domain, linked_to: row.linked_to },
				our_page_url: window.location.origin,
				our_page_title: '',
				our_page_summary: '',
			},
		})
			.then((res) => {
				setDraftingId(null);
				if (res?.error === 'tier_required') {
					setError(__('AI outreach drafting requires Pro.', 'ebq-seo'));
				} else if (res?.ok) {
					loadSaved(statusFilter);
					setExpandedId(row.id);
				} else {
					setError(res?.message || res?.error || 'Draft failed');
				}
			})
			.catch((err) => {
				setError(err?.message || 'Network error');
				setDraftingId(null);
			});
	}, [statusFilter, loadSaved]);

	return (
		<div className="ebq-hq-tab">
			<header className="ebq-hq-tab__head">
				<h2>{__('Outreach prospects', 'ebq-seo')}</h2>
				<p className="ebq-hq-tab__sub">
					{__('Persistent backlink-prospecting kanban. Find new prospects by adding competitor domains; status and notes survive across sessions.', 'ebq-seo')}
				</p>
			</header>

			{/* Auto-discovery is the primary path. Banner shows the result of
			    the most recent auto-discover run; the Refresh button re-fires
			    it on demand. Manual entry stays as a collapsed fallback. */}
			<div className="ebq-hq-prospects__autobar">
				<div className="ebq-hq-prospects__autobar-left">
					{autoDiscovering ? (
						<span><span className="ebq-spinner" /> {__('Auto-discovering competitors from your last 30 days of audits…', 'ebq-seo')}</span>
					) : autoBanner?.ok ? (
						<span>
							{sprintf(
								__('Auto-discovered %d competitor%s from your audits → %d total prospects (%d new this run).', 'ebq-seo'),
								autoBanner.discovered_competitors || 0,
								(autoBanner.discovered_competitors === 1) ? '' : 's',
								autoBanner.prospect_count || 0,
								autoBanner.new_in_run || 0,
							)}
						</span>
					) : autoBanner?.reason === 'no_competitors_in_audits' ? (
						<span>
							{__('No competitors found in your recent audits. Run a page audit from HQ → Page Audits first — the audit identifies your SERP neighbours, then prospects auto-populate.', 'ebq-seo')}
						</span>
					) : (
						<span>
							{__('Prospects auto-populate from your page audits. Run an audit on any page from HQ → Page Audits, then come back here.', 'ebq-seo')}
						</span>
					)}
				</div>
				<button
					type="button"
					className="ebq-hq-btn ebq-hq-btn--ghost ebq-hq-btn--sm"
					onClick={refreshFromAudits}
					disabled={autoDiscovering}
				>
					{autoDiscovering ? __('Refreshing…', 'ebq-seo') : __('Refresh from audits', 'ebq-seo')}
				</button>
			</div>

			<details className="ebq-hq-prospects__add">
				<summary>{__('+ Add custom competitors (manual)', 'ebq-seo')}</summary>
				<p className="ebq-hq-tab__sub" style={{ marginTop: 6, marginBottom: 6 }}>
					{__('For competitors you know about but haven\'t audited any page against. Auto-discovery already covers the SERP neighbours of pages you\'ve audited.', 'ebq-seo')}
				</p>
				<textarea
					className="ebq-hq-prospects__input"
					rows={2}
					placeholder={__('competitor1.com, competitor2.com', 'ebq-seo')}
					value={competitors}
					onChange={(e) => setCompetitors(e.target.value)}
				/>
				<button
					type="button"
					className="ebq-hq-btn ebq-hq-btn--primary ebq-hq-btn--sm"
					onClick={findProspects}
					disabled={searching || !competitors.trim()}
				>
					{searching ? __('Finding…', 'ebq-seo') : __('Find prospects', 'ebq-seo')}
				</button>
			</details>

			{/* Status filter pills with counts. */}
			<div className="ebq-hq-toolbar">
				{STATUS_FILTERS.map((s) => {
					const count = s === 'all'
						? Object.values(counts).reduce((a, b) => a + b, 0)
						: (counts[s] || 0);
					return (
						<button
							key={s}
							type="button"
							className={`ebq-hq-toolbar__filter${s === statusFilter ? ' is-active' : ''}`}
							onClick={() => setStatusFilter(s)}
						>
							{s} {count > 0 ? <em>· {count}</em> : null}
						</button>
					);
				})}
				<button type="button" className="ebq-hq-toolbar__refresh" onClick={() => loadSaved(statusFilter)}>
					{__('Refresh', 'ebq-seo')}
				</button>
			</div>

			{error ? <p className="ebq-hq-empty ebq-hq-empty--error">{error}</p> : null}
			{loading ? <p className="ebq-hq-empty">{__('Loading…', 'ebq-seo')}</p> : null}

			{!loading && !error && savedRows.length === 0 ? (
				<p className="ebq-hq-empty">
					{statusFilter === 'all' || statusFilter === 'new'
						? __('No saved prospects yet. Open the "+ Find more prospects" panel above and paste a couple of competitor domains to seed your outreach list.', 'ebq-seo')
						: sprintf(__('No prospects in "%s" right now.', 'ebq-seo'), statusFilter)}
				</p>
			) : null}

			{!loading && savedRows.length > 0 ? (
				<table className="ebq-hq-table ebq-hq-table--prospects">
					<thead>
						<tr>
							<th>{__('Domain', 'ebq-seo')}</th>
							<th>{__('DA', 'ebq-seo')}</th>
							<th>{__('Linked to', 'ebq-seo')}</th>
							<th>{__('Status', 'ebq-seo')}</th>
							<th>{__('Action', 'ebq-seo')}</th>
						</tr>
					</thead>
					<tbody>
						{savedRows.map((p) => {
							const isExpanded = expandedId === p.id;
							return (
								<>
									<tr key={p.id}>
										<td>
											<a href={`https://${p.domain}`} target="_blank" rel="noopener noreferrer">{p.domain}</a>
											{p.contacted_at ? (
												<div className="ebq-hq-muted" style={{ fontSize: 10 }}>
													{__('contacted', 'ebq-seo')} {new Date(p.contacted_at).toLocaleDateString()}
												</div>
											) : null}
										</td>
										<td>{p.domain_authority ?? '—'}</td>
										<td className="ebq-hq-prospects__anchors">
											{(p.linked_to || []).slice(0, 3).join(', ')}
											{(p.linked_to || []).length > 3 ? ` +${p.linked_to.length - 3}` : ''}
										</td>
										<td>
											<select
												value={p.status}
												disabled={updatingId === p.id}
												onChange={(e) => updateProspect(p.id, { status: e.target.value })}
												className="ebq-hq-prospects__status"
											>
												{STATUS_FILTERS.filter((s) => s !== 'all').map((s) => (
													<option key={s} value={s}>{s}</option>
												))}
											</select>
										</td>
										<td>
											<button
												type="button"
												className="ebq-hq-btn ebq-hq-btn--sm"
												disabled={draftingId === p.id}
												onClick={() => draftOutreach(p)}
											>
												{draftingId === p.id
													? __('Drafting…', 'ebq-seo')
													: (p.latest_draft ? __('Re-draft', 'ebq-seo') : __('Draft', 'ebq-seo'))}
											</button>
											<button
												type="button"
												className="ebq-hq-btn ebq-hq-btn--ghost ebq-hq-btn--sm"
												onClick={() => setExpandedId(isExpanded ? null : p.id)}
											>
												{isExpanded ? __('Close', 'ebq-seo') : __('Open', 'ebq-seo')}
											</button>
										</td>
									</tr>
									{isExpanded ? (
										<tr key={`${p.id}-detail`}>
											<td colSpan={5} className="ebq-hq-prospects__draft-cell">
												{p.latest_draft ? (
													<div className="ebq-hq-prospects__draft">
														<div className="ebq-hq-prospects__draft-subject">
															<strong>{__('Subject:', 'ebq-seo')}</strong> {p.latest_draft.subject}
														</div>
														<pre className="ebq-hq-prospects__draft-body">{p.latest_draft.body}</pre>
														<button
															type="button"
															className="ebq-hq-btn ebq-hq-btn--ghost ebq-hq-btn--sm"
															onClick={() => navigator.clipboard?.writeText(`Subject: ${p.latest_draft.subject}\n\n${p.latest_draft.body}`)}
														>
															{__('Copy email', 'ebq-seo')}
														</button>
													</div>
												) : (
													<p className="ebq-hq-muted" style={{ margin: 0 }}>
														{__('No draft yet — click Draft to generate one.', 'ebq-seo')}
													</p>
												)}
												<div className="ebq-hq-prospects__notes">
													<label>{__('Notes', 'ebq-seo')}</label>
													<textarea
														rows={3}
														value={notesDraft[p.id] ?? p.notes ?? ''}
														onChange={(e) => setNotesDraft({ ...notesDraft, [p.id]: e.target.value })}
														placeholder={__('Outreach context, contact info, follow-up reminders…', 'ebq-seo')}
													/>
													<button
														type="button"
														className="ebq-hq-btn ebq-hq-btn--sm"
														disabled={updatingId === p.id || (notesDraft[p.id] ?? p.notes ?? '') === (p.notes ?? '')}
														onClick={() => updateProspect(p.id, { notes: notesDraft[p.id] ?? '' })}
													>
														{__('Save notes', 'ebq-seo')}
													</button>
												</div>
											</td>
										</tr>
									) : null}
								</>
							);
						})}
					</tbody>
				</table>
			) : null}
		</div>
	);
}
