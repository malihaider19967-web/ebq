/**
 * Lightweight primitives for the EBQ sidebar — tailored to look great inside
 * the Gutenberg editor without inheriting WP's default form styling.
 */
import { useId, useState, useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	IconCheck,
	IconCross,
	IconDot,
	IconWarn,
	IconArrowDown,
	IconArrowUp,
	IconSparkle,
} from './icons';

/* ─── Section card ───────────────────────────────────────────── */

export function Section({ title, icon, aside, children, flush = false, plain = false }) {
	return (
		<section className={`ebq-section${flush ? ' ebq-section--flush' : ''}${plain ? ' ebq-section--plain' : ''}`}>
			<div className="ebq-section__head">
				{icon ? <span className="ebq-section__icon">{icon}</span> : null}
				<h3 className="ebq-section__title">{title}</h3>
				{aside ? <span className="ebq-section__aside">{aside}</span> : null}
			</div>
			<div className="ebq-section__body">{children}</div>
		</section>
	);
}

/* ─── Field primitives ──────────────────────────────────────── */

export function TextField({ label, hint, value, onChange, placeholder, type = 'text', maxHint }) {
	const id = useId();
	return (
		<div className="ebq-field">
			<label className="ebq-label" htmlFor={id}>
				<span>{label}</span>
				{maxHint != null ? <span className="ebq-label__hint">{maxHint}</span> : null}
			</label>
			<input
				id={id}
				className="ebq-input"
				type={type}
				value={value ?? ''}
				placeholder={placeholder || ''}
				onChange={(e) => onChange(e.target.value)}
				spellCheck={type === 'text'}
			/>
			{hint ? <p className="ebq-help">{hint}</p> : null}
		</div>
	);
}

export function TextArea({ label, hint, value, onChange, placeholder, rows = 3, maxHint }) {
	const id = useId();
	const ref = useRef(null);

	// Auto-grow: keeps the field height aligned with its content so the user
	// always sees what they're writing without an inner scrollbar.
	useEffect(() => {
		const el = ref.current;
		if (!el) return;
		el.style.height = 'auto';
		// Cap to a reasonable max so a 10kB paste doesn't take over the panel.
		el.style.height = Math.min(el.scrollHeight, 320) + 'px';
	}, [value]);

	return (
		<div className="ebq-field">
			<label className="ebq-label" htmlFor={id}>
				<span>{label}</span>
				{maxHint != null ? <span className="ebq-label__hint">{maxHint}</span> : null}
			</label>
			<textarea
				ref={ref}
				id={id}
				className="ebq-textarea"
				rows={rows}
				value={value ?? ''}
				placeholder={placeholder || ''}
				onChange={(e) => onChange(e.target.value)}
			/>
			{hint ? <p className="ebq-help">{hint}</p> : null}
		</div>
	);
}

export function Toggle({ label, checked, onChange }) {
	const id = useId();
	return (
		<label className={`ebq-toggle${checked ? ' is-on' : ''}`} htmlFor={id}>
			<span className="ebq-toggle__track">
				<span className="ebq-toggle__thumb" />
			</span>
			<span className="ebq-toggle__label">{label}</span>
			<input id={id} type="checkbox" checked={!!checked} onChange={(e) => onChange(e.target.checked)} />
		</label>
	);
}

/* ─── Char gauge ─────────────────────────────────────────────── */

export function CharGauge({ length = 0, goodMin = 0, goodMax, hardMax }) {
	const pct = hardMax > 0 ? Math.min(100, (length / hardMax) * 100) : 0;
	let level = 'good';
	if (length === 0)              level = 'bad';
	else if (length > hardMax)     level = 'bad';
	else if (length > goodMax)     level = 'warn';
	else if (length < goodMin)     level = 'warn';
	return (
		<div className="ebq-gauge">
			<div className="ebq-gauge__bar">
				<div className={`ebq-gauge__fill ebq-gauge__fill--${level}`} style={{ width: `${pct}%` }} />
			</div>
			<div className="ebq-gauge__meta">
				<span>{length}</span>
				<span>
					{goodMin > 0 ? `${goodMin}–${goodMax}` : `${goodMax}`} {__('chars', 'ebq-seo')}
				</span>
			</div>
		</div>
	);
}

/* ─── Score badge (overall) ──────────────────────────────────── */

export function ScoreBadge({ score, label, caption }) {
	const level = score >= 65 ? 'good' : score >= 45 ? 'warn' : 'bad';
	const colorVar = level === 'good' ? 'var(--ebq-good)' : level === 'warn' ? 'var(--ebq-warn)' : 'var(--ebq-bad)';
	return (
		<div className="ebq-scorebadge">
			<div className="ebq-scorebadge__ring" style={{ '--p': score, '--col': colorVar }}>
				<span className="ebq-scorebadge__num">{score}</span>
			</div>
			<div className="ebq-scorebadge__main">
				<p className="ebq-scorebadge__label">{label}</p>
				<p className="ebq-scorebadge__caption">{caption}</p>
			</div>
		</div>
	);
}

/* ─── Stat tile ──────────────────────────────────────────────── */

export function StatGrid({ children }) {
	return <div className="ebq-stats">{children}</div>;
}

export function Stat({ label, value, sub, trend }) {
	const trendClass =
		trend === 'up'   ? ' ebq-stat__sub--up'
		: trend === 'down' ? ' ebq-stat__sub--down' : '';
	return (
		<div className="ebq-stat">
			<p className="ebq-stat__label">{label}</p>
			<p className="ebq-stat__value">{value ?? '—'}</p>
			{sub ? <p className={`ebq-stat__sub${trendClass}`}>{sub}</p> : null}
		</div>
	);
}

/* ─── Pill / Score chip ──────────────────────────────────────── */

export function Pill({ tone = 'neutral', children }) {
	const cls = tone === 'neutral' ? '' : ` ebq-pill--${tone}`;
	return <span className={`ebq-pill${cls}`}>{children}</span>;
}

export function ScoreChip({ score, label }) {
	const level = score >= 65 ? 'good' : score >= 45 ? 'warn' : 'bad';
	return (
		<span className={`ebq-score-chip ebq-score-chip--${level}`}>
			<span className="ebq-score-chip__dot" />
			<strong>{score}</strong>
			<span style={{ fontWeight: 500 }}>· {label}</span>
		</span>
	);
}

/* ─── Buttons ────────────────────────────────────────────────── */

export function Button({ children, variant = 'ghost', block = false, size = 'md', ...rest }) {
	const cls = `ebq-btn ebq-btn--${variant}${block ? ' ebq-btn--block' : ''}${size === 'sm' ? ' ebq-btn--sm' : ''}`;
	if (rest.href) {
		return <a className={cls} {...rest}>{children}</a>;
	}
	return <button type="button" className={cls} {...rest}>{children}</button>;
}

/* ─── Empty / loading ────────────────────────────────────────── */

export function EmptyState({ icon, title, sub, children }) {
	return (
		<div className="ebq-empty">
			{icon ? <span className="ebq-empty__icon">{icon}</span> : null}
			{title ? <p className="ebq-empty__title">{title}</p> : null}
			{sub ? <p className="ebq-empty__sub">{sub}</p> : null}
			{children}
		</div>
	);
}

export function Spinner({ label }) {
	return (
		<span className="ebq-spinner" role="status" aria-live="polite"
			aria-label={label || __('Loading', 'ebq-seo')} />
	);
}

export function SkeletonRow({ width = '100%', label }) {
	return (
		<div className="ebq-skeleton" style={{ width }}
			role="status" aria-live="polite"
			aria-label={label || __('Loading', 'ebq-seo')} />
	);
}

/* ─── Trend arrow helpers ────────────────────────────────────── */

export function TrendArrow({ delta }) {
	if (delta == null || delta === 0) return null;
	if (delta > 0) return <span className="ebq-stat__sub--up"><IconArrowUp /> +{delta}</span>;
	return <span className="ebq-stat__sub--down"><IconArrowDown /> {delta}</span>;
}

/* ─── Useful re-exports ──────────────────────────────────────── */
export { IconCheck, IconCross, IconWarn, IconDot, IconSparkle };
