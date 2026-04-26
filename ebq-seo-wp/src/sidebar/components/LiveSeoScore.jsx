import { useEffect, useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { CheckCard } from './primitives';
import TrackKeywordButton from './TrackKeywordButton';

/**
 * Live SEO score pill — fetches the EBQ-side composite (GSC rank + CTR +
 * audit + cannibalization + coverage) and renders it next to the editor's
 * local self-check. The DELTA between the two is the moat: when the
 * self-check is high but the live score is low, EBQ explains why.
 *
 * Debounced 1.2s on (postId, focusKeyword) so we don't burn calls every
 * keystroke. Renders a clickable factor breakdown popover on the score
 * pill so the user can drill into the why without leaving the sidebar.
 */
export default function LiveSeoScore({ postId, focusKeyword, isConnected }) {
	const [state, setState] = useState({ status: 'idle', data: null, error: null });
	const [open, setOpen] = useState(false);
	const debounceRef = useRef(null);
	const popRef = useRef(null);

	useEffect(() => {
		if (! isConnected || ! postId) {
			setState({ status: 'idle', data: null, error: null });
			return;
		}
		clearTimeout(debounceRef.current);
		debounceRef.current = setTimeout(() => {
			setState((s) => ({ ...s, status: 'loading' }));
			const path = `/ebq/v1/seo-score/${postId}` + (focusKeyword ? `?focus_keyword=${encodeURIComponent(focusKeyword)}` : '');
			apiFetch({ path }).then((res) => {
				if (res?.ok === false || res?.error) {
					setState({ status: 'error', data: null, error: res?.message || res?.error || 'Fetch failed' });
				} else {
					setState({ status: 'ready', data: res?.live || null, error: null });
				}
			}).catch((err) => {
				setState({ status: 'error', data: null, error: err?.message || 'Network error' });
			});
		}, 1200);
		return () => clearTimeout(debounceRef.current);
	}, [postId, focusKeyword, isConnected]);

	useEffect(() => {
		if (! open) return;
		const onClick = (e) => {
			if (popRef.current?.contains(e.target)) return;
			setOpen(false);
		};
		const onKey = (e) => { if (e.key === 'Escape') setOpen(false); };
		document.addEventListener('mousedown', onClick, true);
		document.addEventListener('keydown', onKey, true);
		return () => {
			document.removeEventListener('mousedown', onClick, true);
			document.removeEventListener('keydown', onKey, true);
		};
	}, [open]);

	if (! isConnected) {
		return (
			<div className="ebq-live-score ebq-live-score--disconnected" title={__('Connect this site to EBQ to see the live score.', 'ebq-seo')}>
				<span className="ebq-live-score__badge">EBQ</span>
				<span className="ebq-live-score__num">—</span>
				<span className="ebq-live-score__label">{__('Connect for live', 'ebq-seo')}</span>
			</div>
		);
	}

	if (state.status === 'loading' || state.status === 'idle') {
		return (
			<div className="ebq-live-score ebq-live-score--loading">
				<span className="ebq-live-score__badge">EBQ</span>
				<span className="ebq-live-score__num">…</span>
				<span className="ebq-live-score__label">{__('Live score', 'ebq-seo')}</span>
			</div>
		);
	}

	if (state.status === 'error') {
		return (
			<div className="ebq-live-score ebq-live-score--error" title={state.error || ''}>
				<span className="ebq-live-score__badge">EBQ</span>
				<span className="ebq-live-score__num">!</span>
				<span className="ebq-live-score__label">{__('Live score error', 'ebq-seo')}</span>
			</div>
		);
	}

	const data = state.data;
	if (! data || data.available === false) {
		const reason = data?.explanation || __('Not enough Google Search Console data yet.', 'ebq-seo');
		return (
			<div className="ebq-live-score ebq-live-score--unavailable" title={reason}>
				<span className="ebq-live-score__badge">EBQ</span>
				<span className="ebq-live-score__num">—</span>
				<span className="ebq-live-score__label">{__('No live data yet', 'ebq-seo')}</span>
			</div>
		);
	}

	const tone = data.score >= 65 ? 'good' : data.score >= 45 ? 'warn' : 'bad';

	return (
		<div className="ebq-live-score-wrap" style={{ position: 'relative' }}>
			<button
				type="button"
				className={`ebq-live-score ebq-live-score--${tone} is-clickable`}
				onClick={() => setOpen((v) => !v)}
				aria-expanded={open}
				aria-haspopup="dialog"
				title={__('Click for full breakdown', 'ebq-seo')}
			>
				<span className="ebq-live-score__badge">EBQ</span>
				<span className="ebq-live-score__num">{data.score}</span>
				<span className="ebq-live-score__label">{__('Live', 'ebq-seo')} · {data.label}</span>
			</button>

			{open ? (
				<div ref={popRef} className="ebq-live-score__pop" role="dialog">
					<header className="ebq-live-score__pop-head">
						<strong>{__('Live SEO score breakdown', 'ebq-seo')}</strong>
						<button type="button" className="ebq-live-score__pop-close" onClick={() => setOpen(false)} aria-label={__('Close', 'ebq-seo')}>×</button>
					</header>
					<p className="ebq-live-score__pop-explanation">{data.explanation}</p>
					<div className="ebq-check-list">
						{(data.factors || []).map((f) => {
							const fLevel = f.score >= 65 ? 'good' : f.score >= 45 ? 'warn' : 'bad';
							let actionEl = null;
							if (f.action?.kind === 'track-keyword' && f.action.keyword) {
								actionEl = <TrackKeywordButton keyword={f.action.keyword} />;
							}
							return (
								<CheckCard
									key={f.key}
									kind="live"
									level={fLevel}
									label={f.label}
									score={f.score}
									weight={f.weight}
									detail={f.detail}
									recommendation={f.recommendation || null}
									action={actionEl}
								/>
							);
						})}
					</div>
					<footer className="ebq-live-score__pop-foot">
						{__('Live score uses real Google Search Console data and a Lighthouse audit. The local self-check above only sees what you wrote.', 'ebq-seo')}
					</footer>
				</div>
			) : null}
		</div>
	);
}
