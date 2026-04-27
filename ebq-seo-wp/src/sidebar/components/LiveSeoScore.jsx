import { useEffect, useState, useRef, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { createPortal } from 'react-dom';
import { ScoreBadge, CheckCard, NeedsSetup } from './primitives';
import TrackKeywordButton from './TrackKeywordButton';
import { liveScoreUnavailable } from './dependencyMessages';
import { publicConfig } from '../hooks/useEditorContext';

/**
 * EBQ live score card. Mirrors the offline `ScoreBadge` shape so the two
 * read as a matched pair at the top of the SEO tab. Clicking the card
 * opens a portal-mounted popover with the per-factor breakdown.
 *
 * Two fetch paths:
 *   - Debounced 1.2s on (postId, focusKeyword) for normal use
 *   - Polled every 12s while the server reports an audit is queued/running,
 *     so the score auto-refreshes once the background audit finishes (no
 *     user action needed). Polling stops as soon as `audit.status` is
 *     `ready`, `failed`, or `unavailable`.
 *
 * Audits never re-run automatically once a `ready` report exists; the user
 * can trigger a fresh audit from HQ → Page Audits.
 */
// Polled while the audit is in flight. 6s = at most one extra round-trip
// of latency between audit completion and the breakdown updating in the
// editor. Audit-side: editor-triggered audits run in lite mode (~15–30s).
const POLL_INTERVAL_MS = 6000;

export default function LiveSeoScore({ postId, focusKeyword, isConnected, onSetupChange }) {
	const [state, setState] = useState({ status: 'idle', data: null, error: null });
	const [open, setOpen] = useState(false);
	const debounceRef = useRef(null);
	const pollRef = useRef(null);
	const anchorRef = useRef(null);
	const popRef = useRef(null);
	const [anchor, setAnchor] = useState({ top: 0, left: 0, width: 360 });

	const fetchScore = useCallback((isPoll = false) => {
		if (!isConnected || !postId) return;
		if (!isPoll) setState((s) => ({ ...s, status: 'loading' }));
		// POST (not GET) so LiteSpeed / Cloudflare / browser never cache
		// the response. Header-based opt-outs aren't reliable on real
		// LSCache installs — using POST is the unconditional escape hatch
		// because no spec-compliant cache stores POST responses. Keeps
		// _cb anyway as a defense for any non-compliant proxy.
		const params = new URLSearchParams();
		if (focusKeyword) params.set('focus_keyword', focusKeyword);
		params.set('_cb', String(Date.now()));
		const path = `/ebq/v1/seo-score/${postId}?${params.toString()}`;
		apiFetch({ path, method: 'POST' }).then((res) => {
			if (res?.ok === false || res?.error) {
				setState({ status: 'error', data: null, error: res?.message || res?.error || 'Fetch failed' });
			} else {
				setState({ status: 'ready', data: res?.live || null, error: null });
			}
		}).catch((err) => {
			setState({ status: 'error', data: null, error: err?.message || 'Network error' });
		});
	}, [postId, focusKeyword, isConnected]);

	// Debounced fetch on edit. Cancels any in-flight poll so we don't double-fetch.
	useEffect(() => {
		if (!isConnected || !postId) {
			setState({ status: 'idle', data: null, error: null });
			return;
		}
		clearTimeout(debounceRef.current);
		debounceRef.current = setTimeout(() => fetchScore(false), 1200);
		return () => clearTimeout(debounceRef.current);
	}, [postId, focusKeyword, isConnected, fetchScore]);

	// Auto-poll while the server reports the audit is still in flight, so the
	// breakdown swaps from "running…" to full data without the user reloading.
	useEffect(() => {
		clearInterval(pollRef.current);
		const auditStatus = state.data?.audit?.status;
		const pending = auditStatus === 'queued' || auditStatus === 'running' || auditStatus === 'refreshing';
		if (state.status === 'ready' && pending) {
			pollRef.current = setInterval(() => fetchScore(true), POLL_INTERVAL_MS);
		}
		return () => clearInterval(pollRef.current);
	}, [state.status, state.data, fetchScore]);

	useEffect(() => {
		if (!open) return;
		const reposition = () => {
			const r = anchorRef.current?.getBoundingClientRect();
			if (!r) return;
			const margin = 12;
			const vw = window.innerWidth;
			const vh = window.innerHeight;
			const popW = Math.min(380, vw - margin * 2);
			let left = Math.max(margin, Math.min(r.left, vw - popW - margin));
			let top = r.bottom + 6;
			const popH = popRef.current?.offsetHeight || 320;
			if (top + popH + margin > vh && r.top - popH - 6 > margin) {
				top = r.top - popH - 6;
			}
			setAnchor({ top, left, width: popW });
		};
		reposition();
		const raf = requestAnimationFrame(reposition);
		const onResize = () => reposition();
		const onScroll = () => reposition();
		const onKey = (e) => { if (e.key === 'Escape') setOpen(false); };
		const onClick = (e) => {
			if (popRef.current?.contains(e.target)) return;
			if (anchorRef.current?.contains(e.target)) return;
			setOpen(false);
		};
		window.addEventListener('resize', onResize);
		window.addEventListener('scroll', onScroll, true);
		document.addEventListener('keydown', onKey, true);
		document.addEventListener('mousedown', onClick, true);
		return () => {
			cancelAnimationFrame(raf);
			window.removeEventListener('resize', onResize);
			window.removeEventListener('scroll', onScroll, true);
			document.removeEventListener('keydown', onKey, true);
			document.removeEventListener('mousedown', onClick, true);
		};
	}, [open]);

	const cfg = publicConfig();
	const badgeText = __('EBQ', 'ebq-seo');
	const labelText = __('Live SEO score', 'ebq-seo');

	let score = 0;
	let displayScore = '—';
	let caption = __('Loading…', 'ebq-seo');
	let canOpen = false;
	let data = null;
	let auditStatus = null;
	// `setupCard` (when populated) renders a friendly NeedsSetup card
	// BELOW the score badge with what / why / fix / action — replaces
	// the previous one-line cryptic caption that said "no GSC data" without
	// telling the user what to do about it.
	let setupCard = null;

	if (!isConnected) {
		caption = __('Connect to see live data', 'ebq-seo');
		setupCard = {
			feature: __('Live SEO score', 'ebq-seo'),
			why: __('This site isn\'t connected to an EBQ workspace yet, so the score has no data to compute from.', 'ebq-seo'),
			fix: __('Open EBQ SEO settings and click Connect — it\'s a one-click flow, no API keys to copy.', 'ebq-seo'),
			action: cfg.settingsUrl ? { label: __('Open EBQ SEO settings', 'ebq-seo'), url: cfg.settingsUrl, target: '_top' } : null,
			tone: 'info',
		};
	} else if (state.status === 'loading' || state.status === 'idle') {
		caption = __('Fetching live signals…', 'ebq-seo');
	} else if (state.status === 'error') {
		caption = __('Live score unavailable', 'ebq-seo');
		setupCard = {
			feature: __('Live SEO score', 'ebq-seo'),
			why: state.error || __('We couldn\'t reach EBQ to compute the score.', 'ebq-seo'),
			fix: __('This is usually a network blip. Reload the editor; if it persists, check that the EBQ connection is still active in plugin settings.', 'ebq-seo'),
			tone: 'warn',
		};
	} else if (state.status === 'ready') {
		data = state.data;
		auditStatus = data?.audit?.status || null;
		if (!data || data.available === false) {
			caption = __('No live data yet', 'ebq-seo');
			setupCard = liveScoreUnavailable(data?.reason || null, cfg);
		} else {
			score = Number(data.score) || 0;
			displayScore = score;
			canOpen = true;
			if (auditStatus === 'queued' || auditStatus === 'running') {
				caption = auditStatus === 'running'
					? __('Auditing page…', 'ebq-seo')
					: __('Audit queued…', 'ebq-seo');
			} else if (auditStatus === 'refreshing') {
				caption = __('Re-auditing — post was updated', 'ebq-seo');
			} else if (data.partial) {
				// Freshly-published URL: GSC factors are pending. The
				// score is still composed from audit + indexing +
				// backlinks; surface that here so the user knows
				// what it represents and that it'll fill in.
				caption = __('Provisional · audit-only signals', 'ebq-seo');
			} else {
				caption = data.label || '';
			}
		}
	}

	const setRef = (el) => { anchorRef.current = el; };
	const isAuditPending = auditStatus === 'queued' || auditStatus === 'running' || auditStatus === 'refreshing';

	// Forward the setup card to the parent (SeoTab) so it can render
	// below the score-stack as a full-width row. We deliberately don't
	// render it inside this component's wrapper because the wrapper is
	// a flex item in the score-stack — adding content here makes the
	// live card taller than the offline card, which breaks the matched-
	// pair design contract.
	useEffect(() => {
		if (typeof onSetupChange === 'function') {
			onSetupChange(setupCard || null);
		}
		// We intentionally serialize setupCard to a stable key so React
		// doesn't re-fire the callback on every render with the same data.
	}, [onSetupChange, setupCard?.feature, setupCard?.why, setupCard?.tone]);

	return (
		<>
			<div ref={setRef} style={{ width: '100%', position: 'relative' }}>
				<ScoreBadge
					kind="live"
					score={score}
					displayScore={displayScore}
					badge={badgeText}
					label={labelText}
					caption={caption}
					onClick={canOpen ? () => setOpen((v) => !v) : undefined}
					ariaExpanded={open}
					trailing={isAuditPending ? <span className="ebq-spinner" aria-hidden /> : null}
				/>
				{/* NeedsSetup renders OUTSIDE the score-stack flex item — see
				    the useEffect below, which forwards setupCard to the parent
				    via onSetupChange. Keeping it inside this wrapper made the
				    live cell taller than the offline cell, breaking the
				    "matched-pair" visual contract those two cards have. */}
			</div>

			{open && data && data.available !== false ? createPortal(
				<div
					ref={popRef}
					className="ebq-live-score__pop"
					role="dialog"
					aria-label={__('EBQ live score breakdown', 'ebq-seo')}
					style={{ top: anchor.top, left: anchor.left, width: anchor.width }}
				>
					<header className="ebq-live-score__pop-head">
						<div className="ebq-live-score__pop-titlewrap">
							<strong>{__('Live SEO score breakdown', 'ebq-seo')}</strong>
							{data.audited_url ? (
								<a
									href={data.audited_url}
									target="_blank"
									rel="noopener noreferrer"
									className="ebq-live-score__pop-url"
									title={data.audited_url}
								>{shortenUrl(data.audited_url)}</a>
							) : null}
						</div>
						<button
							type="button"
							className="ebq-live-score__pop-close"
							onClick={() => setOpen(false)}
							aria-label={__('Close', 'ebq-seo')}
						>×</button>
					</header>

					{isAuditPending ? (
						<div className={`ebq-audit-banner ebq-audit-banner--${auditStatus}`}>
							<span className="ebq-spinner" aria-hidden />
							<div>
								<strong>
									{auditStatus === 'running'
										? __('Auditing this page now', 'ebq-seo')
										: auditStatus === 'refreshing'
											? __('Re-auditing — post was updated', 'ebq-seo')
											: __('Audit queued', 'ebq-seo')}
								</strong>
								<p>{data.audit?.message || __('EBQ is preparing the on-page audit. This score will refresh automatically when it finishes — usually 30–90s.', 'ebq-seo')}</p>
							</div>
						</div>
					) : null}

					{data.audit?.status === 'failed' && data.audit?.message ? (
						<div className="ebq-audit-banner ebq-audit-banner--failed">
							<div>
								<strong>{__('Last audit failed', 'ebq-seo')}</strong>
								<p>{data.audit.message}</p>
							</div>
						</div>
					) : null}

					{data.partial && data.partial_reason ? (
						<div className="ebq-audit-banner ebq-audit-banner--partial">
							<div>
								<strong>{__('Provisional score', 'ebq-seo')}</strong>
								<p>{data.partial_reason}</p>
							</div>
						</div>
					) : null}

					{data.explanation ? (
						<p className="ebq-live-score__pop-explanation">{data.explanation}</p>
					) : null}

					<div className="ebq-check-list">
						{(data.factors || []).map((f) => {
							const isPending = !!f.pending;
							const fLevel = isPending
								? 'mute'
								: (f.score >= 65 ? 'good' : f.score >= 45 ? 'warn' : 'bad');
							let actionEl = null;
							if (f.action?.kind === 'track-keyword' && f.action.keyword) {
								actionEl = <TrackKeywordButton keyword={f.action.keyword} />;
							}
							return (
								<CheckCard
									key={f.key}
									kind="live"
									level={fLevel}
									label={isPending ? `${f.label} · ${__('pending', 'ebq-seo')}` : f.label}
									score={isPending ? null : f.score}
									detail={f.detail}
									recommendation={f.recommendation || null}
									items={Array.isArray(f.items) ? f.items : null}
									action={actionEl}
								/>
							);
						})}
					</div>
					<footer className="ebq-live-score__pop-foot">
						{__('Live score blends Google Search Console data, indexing status, backlinks, and the EBQ on-page audit. Once an audit completes, EBQ doesn\'t re-run it automatically — re-trigger from HQ → Page Audits when you want fresh CWV / performance numbers.', 'ebq-seo')}
					</footer>
				</div>,
				document.body
			) : null}
		</>
	);
}

/**
 * Shorten a permalink for display in the popover header. Keep the
 * pathname (which the user actually recognizes) and drop the protocol +
 * host. Falls back to the raw string if URL parsing fails (e.g. classic
 * editor handing us a relative permalink draft).
 */
function shortenUrl(url) {
	try {
		const u = new URL(url);
		const path = (u.pathname || '/') + (u.search || '') + (u.hash || '');
		return path.length > 1 ? path : u.host;
	} catch (_) {
		return String(url || '');
	}
}
