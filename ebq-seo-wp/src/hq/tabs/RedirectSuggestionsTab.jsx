import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * HQ "Redirect suggestions" tab. Lists pending AI-matched redirect
 * proposals sourced from front-end 404 captures shipped by the WP
 * `EBQ_404_Tracker` cron. Each row is one proposed (source → target)
 * mapping with a confidence score and a one-line LLM rationale.
 *
 * Apply: sends the decision to EBQ AND creates a local 301 in
 * EBQ_Redirects so the redirect serves immediately — no waiting on a
 * background sync.
 * Reject: marks dismissed so the matcher won't re-suggest.
 */
export default function RedirectSuggestionsTab() {
	const [status, setStatus] = useState('pending');
	const [rows, setRows] = useState([]);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState(null);
	const [pendingId, setPendingId] = useState(null); // disables the row's buttons during decide() round-trip

	const load = useCallback((withStatus) => {
		setLoading(true);
		setError(null);
		apiFetch({ path: `/ebq/v1/redirect-suggestions?status=${encodeURIComponent(withStatus || status)}` })
			.then((res) => {
				setRows(Array.isArray(res?.suggestions) ? res.suggestions : []);
				setLoading(false);
			})
			.catch((err) => {
				setError(err?.message || 'Failed to load suggestions');
				setLoading(false);
			});
	}, [status]);

	useEffect(() => { load(status); }, [status, load]);

	const decide = useCallback((row, action) => {
		setPendingId(row.id);
		apiFetch({
			path: `/ebq/v1/redirect-suggestions/${row.id}/decide`,
			method: 'POST',
			data: {
				action,
				source_path: row.source_path,
				suggested_destination: row.suggested_destination,
			},
		})
			.then(() => {
				setPendingId(null);
				// Optimistic: drop the decided row from the list immediately.
				setRows((prev) => prev.filter((r) => r.id !== row.id));
			})
			.catch((err) => {
				setPendingId(null);
				setError(err?.message || 'Decision failed');
			});
	}, []);

	return (
		<div className="ebq-hq-tab">
			<header className="ebq-hq-tab__head">
				<h2>{__('AI redirect suggestions', 'ebq-seo')}</h2>
				<p className="ebq-hq-tab__sub">
					{__('EBQ groups recent 404s on this site, asks the model which existing page best replaces each broken URL, and proposes a 301. Apply with one click — the redirect serves immediately from the local rule store.', 'ebq-seo')}
				</p>
			</header>

			<div className="ebq-hq-toolbar">
				{['pending', 'applied', 'rejected', 'all'].map((s) => (
					<button
						key={s}
						type="button"
						className={`ebq-hq-toolbar__filter${s === status ? ' is-active' : ''}`}
						onClick={() => setStatus(s)}
					>
						{s}
					</button>
				))}
				<button type="button" className="ebq-hq-toolbar__refresh" onClick={() => load(status)}>
					{__('Refresh', 'ebq-seo')}
				</button>
			</div>

			{loading ? <p className="ebq-hq-empty">{__('Loading…', 'ebq-seo')}</p> : null}
			{error ? <p className="ebq-hq-empty ebq-hq-empty--error">{error}</p> : null}
			{!loading && !error && rows.length === 0 ? (
				<p className="ebq-hq-empty">
					{status === 'pending'
						? __('No pending suggestions. Either no 404s have been captured yet, or all current 404s have been decided. Front-end 404s are captured continuously and shipped to EBQ hourly.', 'ebq-seo')
						: __('Nothing to show with this filter.', 'ebq-seo')}
				</p>
			) : null}

			{rows.length > 0 ? (
				<table className="ebq-hq-table ebq-hq-table--redirects">
					<thead>
						<tr>
							<th>{__('From', 'ebq-seo')}</th>
							<th>{__('Suggested →', 'ebq-seo')}</th>
							<th>{__('Confidence', 'ebq-seo')}</th>
							<th>{__('Hits/30d', 'ebq-seo')}</th>
							<th>{__('Why', 'ebq-seo')}</th>
							<th>{__('Action', 'ebq-seo')}</th>
						</tr>
					</thead>
					<tbody>
						{rows.map((r) => {
							const tone = r.confidence >= 75 ? 'good' : r.confidence >= 50 ? 'warn' : 'bad';
							return (
								<tr key={r.id}>
									<td><code>{r.source_path}</code></td>
									<td>
										{r.suggested_destination ? (
											<code>{r.suggested_destination}</code>
										) : (
											<span className="ebq-hq-muted">{__('No match found', 'ebq-seo')}</span>
										)}
									</td>
									<td>
										<span className={`ebq-hq-badge ebq-hq-badge--${tone}`}>{r.confidence}%</span>
									</td>
									<td>{r.hits_30d}</td>
									<td className="ebq-hq-rationale">{r.rationale || '—'}</td>
									<td>
										{status === 'pending' && r.suggested_destination ? (
											<>
												<button
													type="button"
													className="ebq-hq-btn ebq-hq-btn--primary ebq-hq-btn--sm"
													disabled={pendingId === r.id}
													onClick={() => decide(r, 'apply')}
												>
													{pendingId === r.id ? __('…', 'ebq-seo') : __('Apply', 'ebq-seo')}
												</button>
												<button
													type="button"
													className="ebq-hq-btn ebq-hq-btn--ghost ebq-hq-btn--sm"
													disabled={pendingId === r.id}
													onClick={() => decide(r, 'reject')}
												>
													{__('Reject', 'ebq-seo')}
												</button>
											</>
										) : (
											<span className="ebq-hq-muted">{r.status}</span>
										)}
									</td>
								</tr>
							);
						})}
					</tbody>
				</table>
			) : null}
		</div>
	);
}
