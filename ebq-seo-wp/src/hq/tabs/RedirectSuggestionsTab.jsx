import { useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * HQ "Redirects (AI)" tab — card-based redesign.
 *
 * Each suggestion is a card with:
 *   - Confidence ring (color-coded: green ≥80, amber ≥50, red <50)
 *   - Source path → editable destination path (so the user can override
 *     the AI's pick before applying — the apply endpoint passes whatever
 *     destination the client sends to the local EBQ_Redirects writer)
 *   - Hits / last-seen / status badge metadata
 *   - "Why" rationale in italic
 *   - Apply / Reject buttons per row
 *
 * Toolbar:
 *   - Status filter pills with counts
 *   - Sort dropdown (confidence / hits / recency)
 *   - Path search
 *   - Bulk "Apply all ≥80%" with confirmation
 */
const STATUS_FILTERS = ['pending', 'applied', 'rejected', 'all'];
const SORT_OPTIONS = [
	{ value: 'confidence', label: 'Confidence' },
	{ value: 'hits',       label: 'Hits/30d' },
	{ value: 'recent',     label: 'Most recent' },
];

export default function RedirectSuggestionsTab() {
	const [status, setStatus] = useState('pending');
	const [sort, setSort] = useState('confidence');
	const [search, setSearch] = useState('');
	const [rows, setRows] = useState([]);
	const [counts, setCounts] = useState({ pending: 0, applied: 0, rejected: 0 });
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);
	const [pendingId, setPendingId] = useState(null);
	const [destEdits, setDestEdits] = useState({});   // local override per row before apply
	const [bulkBusy, setBulkBusy] = useState(false);

	const load = useCallback((withStatus) => {
		setLoading(true);
		setError(null);
		const s = withStatus || status;
		apiFetch({ path: `/ebq/v1/redirect-suggestions?status=${encodeURIComponent(s)}` })
			.then((res) => {
				const list = Array.isArray(res?.suggestions) ? res.suggestions : [];
				setRows(list);
				// Compute per-status counts from a separate "all" call so the
				// pill counts stay accurate regardless of current filter.
				if (s !== 'all') {
					apiFetch({ path: '/ebq/v1/redirect-suggestions?status=all' })
						.then((r2) => {
							const all = Array.isArray(r2?.suggestions) ? r2.suggestions : [];
							setCounts(all.reduce((acc, x) => {
								acc[x.status] = (acc[x.status] || 0) + 1;
								return acc;
							}, { pending: 0, applied: 0, rejected: 0 }));
						})
						.catch(() => { /* count fetch is best-effort */ });
				} else {
					setCounts(list.reduce((acc, x) => {
						acc[x.status] = (acc[x.status] || 0) + 1;
						return acc;
					}, { pending: 0, applied: 0, rejected: 0 }));
				}
				setLoading(false);
			})
			.catch((err) => { setError(err?.message || 'Failed to load'); setLoading(false); });
	}, [status]);

	useEffect(() => { load(status); }, [status, load]);

	const decide = useCallback((row, action) => {
		setPendingId(row.id);
		const destination = destEdits[row.id] !== undefined
			? destEdits[row.id]
			: row.suggested_destination;
		apiFetch({
			path: `/ebq/v1/redirect-suggestions/${row.id}/decide`,
			method: 'POST',
			data: {
				action,
				source_path: row.source_path,
				suggested_destination: destination,
			},
		})
			.then(() => {
				setPendingId(null);
				setRows((prev) => prev.filter((r) => r.id !== row.id));
				setDestEdits((prev) => { const n = { ...prev }; delete n[row.id]; return n; });
				// Refresh counts in background
				load(status);
			})
			.catch((err) => { setPendingId(null); setError(err?.message || 'Decision failed'); });
	}, [destEdits, status, load]);

	// Bulk apply: only operates on rows currently visible AND with confidence >= 80.
	const eligibleForBulk = useMemo(
		() => rows.filter((r) => r.status === 'pending' && r.confidence >= 80 && r.suggested_destination),
		[rows],
	);
	const bulkApply = useCallback(async () => {
		if (eligibleForBulk.length === 0) return;
		// eslint-disable-next-line no-alert
		const ok = window.confirm(sprintf(
			__('Apply %d high-confidence (≥80%%) redirects? Each one creates a 301 locally and marks the suggestion as applied on EBQ.', 'ebq-seo'),
			eligibleForBulk.length,
		));
		if (!ok) return;
		setBulkBusy(true);
		for (const r of eligibleForBulk) {
			try {
				await apiFetch({
					path: `/ebq/v1/redirect-suggestions/${r.id}/decide`,
					method: 'POST',
					data: {
						action: 'apply',
						source_path: r.source_path,
						suggested_destination: r.suggested_destination,
					},
				});
			} catch (e) { /* keep going */ }
		}
		setBulkBusy(false);
		load(status);
	}, [eligibleForBulk, status, load]);

	// Apply local sort + search to the displayed list.
	const displayed = useMemo(() => {
		let out = rows.slice();
		if (search.trim() !== '') {
			const q = search.trim().toLowerCase();
			out = out.filter(
				(r) => (r.source_path || '').toLowerCase().includes(q)
				    || (r.suggested_destination || '').toLowerCase().includes(q)
				    || (r.rationale || '').toLowerCase().includes(q),
			);
		}
		out.sort((a, b) => {
			if (sort === 'hits')   return (b.hits_30d ?? 0) - (a.hits_30d ?? 0);
			if (sort === 'recent') return (b.last_seen_at || '').localeCompare(a.last_seen_at || '');
			return (b.confidence ?? 0) - (a.confidence ?? 0);
		});
		return out;
	}, [rows, search, sort]);

	return (
		<div className="ebq-hq-tab">
			<header className="ebq-hq-tab__head">
				<h2>{__('AI redirect suggestions', 'ebq-seo')}</h2>
				<p className="ebq-hq-tab__sub">
					{__('EBQ groups recent 404s, asks the model which existing page best replaces each broken URL, and proposes a 301. Edit the destination if needed, then apply — the rule serves immediately from the local store.', 'ebq-seo')}
				</p>
			</header>

			<div className="ebq-redir-toolbar">
				<div className="ebq-redir-toolbar__left">
					{STATUS_FILTERS.map((s) => {
						const c = s === 'all'
							? Object.values(counts).reduce((a, b) => a + b, 0)
							: (counts[s] || 0);
						return (
							<button
								key={s}
								type="button"
								className={`ebq-hq-toolbar__filter${s === status ? ' is-active' : ''}`}
								onClick={() => setStatus(s)}
							>
								{s} {c > 0 ? <em>· {c}</em> : null}
							</button>
						);
					})}
				</div>
				<div className="ebq-redir-toolbar__right">
					<input
						type="search"
						className="ebq-redir-toolbar__search"
						placeholder={__('Search path or rationale…', 'ebq-seo')}
						value={search}
						onChange={(e) => setSearch(e.target.value)}
					/>
					<label className="ebq-redir-toolbar__sort">
						{__('Sort:', 'ebq-seo')}
						<select value={sort} onChange={(e) => setSort(e.target.value)}>
							{SORT_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
						</select>
					</label>
					{status === 'pending' && eligibleForBulk.length > 0 ? (
						<button
							type="button"
							className="ebq-hq-btn ebq-hq-btn--primary ebq-hq-btn--sm"
							onClick={bulkApply}
							disabled={bulkBusy}
						>
							{bulkBusy
								? __('Applying…', 'ebq-seo')
								: sprintf(__('Apply all ≥80%% (%d)', 'ebq-seo'), eligibleForBulk.length)}
						</button>
					) : null}
					<button type="button" className="ebq-hq-toolbar__refresh" onClick={() => load(status)}>
						{__('Refresh', 'ebq-seo')}
					</button>
				</div>
			</div>

			{loading ? <p className="ebq-hq-empty">{__('Loading…', 'ebq-seo')}</p> : null}
			{error ? <p className="ebq-hq-empty ebq-hq-empty--error">{error}</p> : null}

			{!loading && !error && displayed.length === 0 ? (
				<p className="ebq-hq-empty">
					{status === 'pending'
						? __('No pending suggestions. Front-end 404s are captured continuously and shipped to EBQ hourly — check back after some 404 traffic accumulates.', 'ebq-seo')
						: __('Nothing to show with this filter.', 'ebq-seo')}
				</p>
			) : null}

			<div className="ebq-redir-cards">
				{displayed.map((r) => {
					const confTone = r.confidence >= 80 ? 'good' : r.confidence >= 50 ? 'warn' : 'bad';
					const isStale = !r.suggested_destination;
					const editedDest = destEdits[r.id] !== undefined ? destEdits[r.id] : r.suggested_destination;
					const isDirty = destEdits[r.id] !== undefined && destEdits[r.id] !== r.suggested_destination;
					const isPending = pendingId === r.id;
					return (
						<div key={r.id} className={`ebq-redir-card ebq-redir-card--${r.status} ebq-redir-card--${confTone}`}>
							<div className="ebq-redir-card__conf">
								<div
									className="ebq-redir-card__conf-ring"
									style={{
										'--p': r.confidence,
										'--col': confTone === 'good' ? 'var(--ebq-good, #16a34a)' : confTone === 'warn' ? 'var(--ebq-warn, #d97706)' : 'var(--ebq-bad, #dc2626)',
									}}
								>
									<span className="ebq-redir-card__conf-num">{r.confidence}</span>
								</div>
								<span className="ebq-redir-card__conf-label">{__('confidence', 'ebq-seo')}</span>
							</div>

							<div className="ebq-redir-card__main">
								<div className="ebq-redir-card__flow">
									<div className="ebq-redir-card__flow-from">
										<span className="ebq-redir-card__flow-label">{__('From', 'ebq-seo')}</span>
										<code title={r.source_path}>{r.source_path}</code>
									</div>
									<div className="ebq-redir-card__flow-arrow" aria-hidden>→</div>
									<div className="ebq-redir-card__flow-to">
										<span className="ebq-redir-card__flow-label">{__('To', 'ebq-seo')}</span>
										{r.status === 'pending' ? (
											<input
												type="text"
												className={`ebq-redir-card__dest${isDirty ? ' is-dirty' : ''}`}
												value={editedDest || ''}
												placeholder={__('Enter a destination path…', 'ebq-seo')}
												onChange={(e) => setDestEdits({ ...destEdits, [r.id]: e.target.value })}
											/>
										) : (
											<code title={r.suggested_destination}>{r.suggested_destination || '—'}</code>
										)}
									</div>
								</div>

								{r.rationale ? (
									<p className="ebq-redir-card__why"><em>{r.rationale}</em></p>
								) : null}

								<div className="ebq-redir-card__meta">
									<span><strong>{r.hits_30d}</strong> {__('hits/30d', 'ebq-seo')}</span>
									{r.last_seen_at ? (
										<span>
											{__('last seen', 'ebq-seo')} {new Date(r.last_seen_at).toLocaleDateString()}
										</span>
									) : null}
									{r.matched_at ? (
										<span>
											{__('matched', 'ebq-seo')} {new Date(r.matched_at).toLocaleDateString()}
										</span>
									) : null}
									<span className={`ebq-redir-card__status-badge ebq-redir-card__status-badge--${r.status}`}>
										{r.status}
									</span>
								</div>
							</div>

							<div className="ebq-redir-card__actions">
								{r.status === 'pending' ? (
									<>
										<button
											type="button"
											className="ebq-hq-btn ebq-hq-btn--primary ebq-hq-btn--sm"
											disabled={isPending || !editedDest || isStale}
											onClick={() => decide(r, 'apply')}
										>
											{isPending ? __('…', 'ebq-seo') : (isDirty ? __('Apply edited', 'ebq-seo') : __('Apply', 'ebq-seo'))}
										</button>
										<button
											type="button"
											className="ebq-hq-btn ebq-hq-btn--ghost ebq-hq-btn--sm"
											disabled={isPending}
											onClick={() => decide(r, 'reject')}
										>
											{__('Reject', 'ebq-seo')}
										</button>
									</>
								) : (
									<span className="ebq-hq-muted ebq-redir-card__actions-applied">
										{r.status === 'applied' ? '✓' : '✗'} {r.status}
									</span>
								)}
							</div>
						</div>
					);
				})}
			</div>
		</div>
	);
}
