import { useState, useCallback, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { createPortal } from 'react-dom';

function labelText(status) {
	if (status === 'busy')  return __('Tracking…', 'ebq-seo');
	if (status === 'done')  return __('Tracking', 'ebq-seo');
	if (status === 'error') return __('Retry', 'ebq-seo');
	return __('Track', 'ebq-seo');
}

/**
 * "+ Track keyphrase" — opens a compact options popover (country, device,
 * language) anchored next to the button so the user can confirm targeting
 * before the keyword goes into Rank Tracker. POSTs to /wp-json/ebq/v1/track-keyword
 * which is gated on edit_posts (so authors with no admin cap can still use
 * this from the editor).
 *
 * Anchored popover (not a centered modal) so the user keeps context with
 * what they were typing. Click-outside / ESC closes it.
 *
 * Visual states:
 *   idle   → "+ Track"
 *   open   → popover with form
 *   busy   → "Tracking…"
 *   done   → "Tracking ✓" (auto-resets after 4s)
 *   error  → "Retry track"
 */
export default function TrackKeywordButton({ keyword, size = 'sm', className }) {
	const [open, setOpen] = useState(false);
	const [state, setState] = useState({ status: 'idle', error: null });
	const [opts, setOpts] = useState(() => ({ country: 'us', language: 'en', device: 'desktop' }));
	const btnRef = useRef(null);
	const popRef = useRef(null);
	const [anchor, setAnchor] = useState({ top: 0, left: 0 });

	useEffect(() => {
		if (!open) return;
		// Position popover relative to the viewport (position:fixed) and clamp
		// it to stay fully on-screen — the Gutenberg right-rail sidebar has
		// overflow:hidden, so an absolutely-positioned popover would get
		// clipped against its right edge. Fixed + viewport math sidesteps
		// every parent's overflow.
		const reposition = () => {
			const r = btnRef.current?.getBoundingClientRect();
			if (!r) return;
			const popW = 320;
			const popH = popRef.current?.offsetHeight || 280;
			const margin = 8;
			const vw = window.innerWidth;
			const vh = window.innerHeight;

			let left = r.left;
			// Prefer right-aligning so the popover stays inside the sidebar
			// when the button sits near the right edge (typical case).
			if (left + popW + margin > vw) {
				left = Math.max(margin, vw - popW - margin);
			}
			let top = r.bottom + 6;
			// Flip above if there's no room below.
			if (top + popH + margin > vh && r.top - popH - 6 > margin) {
				top = r.top - popH - 6;
			}
			setAnchor({ top, left });
		};
		reposition();
		// Reposition after mount so we can measure the actual popover height.
		const raf = requestAnimationFrame(reposition);
		window.addEventListener('resize', reposition);
		window.addEventListener('scroll', reposition, true);

		const onKey = (e) => { if (e.key === 'Escape') setOpen(false); };
		const onClick = (e) => {
			if (popRef.current?.contains(e.target)) return;
			if (btnRef.current?.contains(e.target)) return;
			setOpen(false);
		};
		document.addEventListener('keydown', onKey, true);
		document.addEventListener('mousedown', onClick, true);
		return () => {
			cancelAnimationFrame(raf);
			window.removeEventListener('resize', reposition);
			window.removeEventListener('scroll', reposition, true);
			document.removeEventListener('keydown', onKey, true);
			document.removeEventListener('mousedown', onClick, true);
		};
	}, [open]);

	const submit = useCallback(async () => {
		const kw = String(keyword || '').trim();
		if (!kw) return;
		setState({ status: 'busy', error: null });
		try {
			const res = await apiFetch({
				path: '/ebq/v1/track-keyword',
				method: 'POST',
				data: { keyword: kw, ...opts },
			});
			if (res?.ok === false || res?.error) {
				setState({ status: 'error', error: res?.message || res?.error || 'Failed' });
				return;
			}
			setState({ status: 'done', error: null });
			setOpen(false);
			setTimeout(() => setState({ status: 'idle', error: null }), 4000);
		} catch (err) {
			setState({ status: 'error', error: err?.message || 'Network error' });
		}
	}, [keyword, opts]);

	const disabled = !keyword || String(keyword).trim().length < 2 || state.status === 'done';

	const onClick = () => {
		if (state.status === 'done') return;
		setOpen((v) => !v);
	};

	const stateCls = state.status === 'done' ? ' ebq-track-btn--done'
		: state.status === 'busy' ? ' ebq-track-btn--busy'
		: state.status === 'error' ? ' ebq-track-btn--error'
		: '';

	return (
		<>
			<button
				ref={btnRef}
				type="button"
				onClick={onClick}
				disabled={disabled}
				title={state.error || __('Add this keyphrase to Rank Tracker', 'ebq-seo')}
				className={`ebq-track-btn${stateCls}${className ? ' ' + className : ''}`}
				aria-haspopup="dialog"
				aria-expanded={open}
			>
				<span className="ebq-track-btn__plus" aria-hidden="true">
					{state.status === 'done' ? '✓' : state.status === 'busy' ? '…' : state.status === 'error' ? '⚠' : '+'}
				</span>
				<span className="ebq-track-btn__label">{labelText(state.status)}</span>
			</button>

			{open ? createPortal((
				<div
					ref={popRef}
					className="ebq-track-pop"
					role="dialog"
					aria-label={__('Track this keyphrase', 'ebq-seo')}
					style={{ top: anchor.top, left: anchor.left }}
				>
					<div className="ebq-track-pop__head">
						<strong>{__('Track this keyphrase', 'ebq-seo')}</strong>
						<button type="button" className="ebq-track-pop__close" onClick={() => setOpen(false)} aria-label={__('Close', 'ebq-seo')}>×</button>
					</div>
					<div className="ebq-track-pop__kw" title={keyword}>"{keyword}"</div>

					<div className="ebq-track-pop__row">
						<label>{__('Country', 'ebq-seo')}</label>
						<select value={opts.country} onChange={(e) => setOpts({ ...opts, country: e.target.value })}>
							{COUNTRIES.map((c) => <option key={c.code} value={c.code}>{c.label}</option>)}
						</select>
					</div>
					<div className="ebq-track-pop__row">
						<label>{__('Language', 'ebq-seo')}</label>
						<select value={opts.language} onChange={(e) => setOpts({ ...opts, language: e.target.value })}>
							{LANGUAGES.map((l) => <option key={l.code} value={l.code}>{l.label}</option>)}
						</select>
					</div>
					<div className="ebq-track-pop__row">
						<label>{__('Device', 'ebq-seo')}</label>
						<select value={opts.device} onChange={(e) => setOpts({ ...opts, device: e.target.value })}>
							<option value="desktop">{__('Desktop', 'ebq-seo')}</option>
							<option value="mobile">{__('Mobile', 'ebq-seo')}</option>
						</select>
					</div>

					{state.error ? <div className="ebq-track-pop__err">{state.error}</div> : null}

					<div className="ebq-track-pop__foot">
						<button type="button" className="ebq-btn ebq-btn--ghost ebq-btn--sm" onClick={() => setOpen(false)}>
							{__('Cancel', 'ebq-seo')}
						</button>
						<button type="button" className="ebq-btn ebq-btn--primary ebq-btn--sm" onClick={submit} disabled={state.status === 'busy'}>
							{state.status === 'busy' ? __('Adding…', 'ebq-seo') : __('Add to Rank Tracker', 'ebq-seo')}
						</button>
					</div>
				</div>
			), document.body) : null}
		</>
	);
}

const COUNTRIES = [
	{ code: 'us', label: 'United States' },
	{ code: 'gb', label: 'United Kingdom' },
	{ code: 'in', label: 'India' },
	{ code: 'ca', label: 'Canada' },
	{ code: 'au', label: 'Australia' },
	{ code: 'de', label: 'Germany' },
	{ code: 'fr', label: 'France' },
	{ code: 'es', label: 'Spain' },
	{ code: 'it', label: 'Italy' },
	{ code: 'nl', label: 'Netherlands' },
	{ code: 'br', label: 'Brazil' },
	{ code: 'mx', label: 'Mexico' },
	{ code: 'jp', label: 'Japan' },
	{ code: 'kr', label: 'South Korea' },
	{ code: 'sg', label: 'Singapore' },
	{ code: 'ae', label: 'United Arab Emirates' },
	{ code: 'sa', label: 'Saudi Arabia' },
	{ code: 'pk', label: 'Pakistan' },
	{ code: 'bd', label: 'Bangladesh' },
	{ code: 'id', label: 'Indonesia' },
	{ code: 'tr', label: 'Turkey' },
	{ code: 'ph', label: 'Philippines' },
	{ code: 'vn', label: 'Vietnam' },
	{ code: 'th', label: 'Thailand' },
	{ code: 'eg', label: 'Egypt' },
	{ code: 'za', label: 'South Africa' },
	{ code: 'ng', label: 'Nigeria' },
	{ code: 'pl', label: 'Poland' },
	{ code: 'se', label: 'Sweden' },
	{ code: 'no', label: 'Norway' },
];

const LANGUAGES = [
	{ code: 'en', label: 'English' },
	{ code: 'es', label: 'Spanish' },
	{ code: 'fr', label: 'French' },
	{ code: 'de', label: 'German' },
	{ code: 'it', label: 'Italian' },
	{ code: 'pt', label: 'Portuguese' },
	{ code: 'nl', label: 'Dutch' },
	{ code: 'ru', label: 'Russian' },
	{ code: 'ja', label: 'Japanese' },
	{ code: 'ko', label: 'Korean' },
	{ code: 'zh', label: 'Chinese' },
	{ code: 'ar', label: 'Arabic' },
	{ code: 'hi', label: 'Hindi' },
	{ code: 'ur', label: 'Urdu' },
	{ code: 'tr', label: 'Turkish' },
	{ code: 'pl', label: 'Polish' },
	{ code: 'sv', label: 'Swedish' },
	{ code: 'no', label: 'Norwegian' },
	{ code: 'da', label: 'Danish' },
	{ code: 'fi', label: 'Finnish' },
	{ code: 'th', label: 'Thai' },
	{ code: 'vi', label: 'Vietnamese' },
	{ code: 'id', label: 'Indonesian' },
];
