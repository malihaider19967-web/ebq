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

export function Section({ title, icon, aside, children, flush = false, plain = false, collapsible = false, defaultOpen = true }) {
	const [open, setOpen] = useState(collapsible ? defaultOpen : true);
	const isCollapsible = !!collapsible;
	const showBody = !isCollapsible || open;

	const className = `ebq-section${flush ? ' ebq-section--flush' : ''}${plain ? ' ebq-section--plain' : ''}${isCollapsible ? ' ebq-section--collapsible' : ''}${isCollapsible && !open ? ' is-collapsed' : ''}`;

	const head = (
		<>
			{icon ? <span className="ebq-section__icon">{icon}</span> : null}
			<h3 className="ebq-section__title">{title}</h3>
			{aside ? <span className="ebq-section__aside">{aside}</span> : null}
			{isCollapsible ? (
				<span className="ebq-section__chevron" aria-hidden>
					<svg viewBox="0 0 16 16" width="10" height="10">
						<path fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" d="M4 6l4 4 4-4" />
					</svg>
				</span>
			) : null}
		</>
	);

	return (
		<section className={className}>
			{isCollapsible ? (
				<button
					type="button"
					className="ebq-section__head ebq-section__head--toggle"
					onClick={() => setOpen((v) => !v)}
					aria-expanded={open}
				>
					{head}
				</button>
			) : (
				<div className="ebq-section__head">{head}</div>
			)}
			{showBody ? <div className="ebq-section__body">{children}</div> : null}
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

/**
 * Both the offline self-check and the EBQ live score render through this
 * one component so they're visually identical. The only differences:
 *
 *   `kind="offline"` → accent-blue tint, ring color is always neutral accent
 *   `kind="live"`    → ring color follows the score level (good / warn / bad)
 *
 * `displayScore` lets the caller render "—" (or any string) instead of 0
 * when there's no score yet, without losing the layout.
 *
 * Pass `onClick` to make the whole card a button (used by the live card so
 * it can toggle a factor-breakdown popover). Without `onClick` the card
 * renders as a static div.
 */
export function ScoreBadge({ kind = 'offline', score = 0, displayScore, label, caption, onClick, ariaExpanded, badge, trailing }) {
	const safeScore = Math.max(0, Math.min(100, Number(score) || 0));
	const level = safeScore >= 65 ? 'good' : safeScore >= 45 ? 'warn' : 'bad';
	// When the caller passes a non-numeric displayScore (e.g. "—" while
	// the audit can't run on a non-public URL), don't color the ring as
	// bad-red just because score is 0 — that misreads as "you're failing"
	// when the truth is "we can't grade this yet". Render a neutral
	// muted ring instead and keep the rest of the layout intact.
	const isPlaceholder = displayScore !== undefined && displayScore !== null && typeof displayScore !== 'number' && isNaN(Number(displayScore));
	const colorVar = isPlaceholder
		? 'var(--ebq-text-soft)'
		: (kind === 'live'
			? (level === 'good' ? 'var(--ebq-good)' : level === 'warn' ? 'var(--ebq-warn)' : 'var(--ebq-bad)')
			: 'var(--ebq-accent)');
	// Empty-state ring should fill all the way (visual: a "muted full
	// circle") rather than partially based on a misleading 0.
	const ringFill = isPlaceholder ? 100 : safeScore;
	const cls = `ebq-scorebadge ebq-scorebadge--${kind}${onClick ? ' is-clickable' : ''}`;
	// Caption is line-clamped to 2 lines visually; use `title` so the full
	// message reveals on hover. Required for explanations like the GSC
	// "give Google a few days to crawl + impressions to accrue" copy that
	// gets truncated at the badge size.
	const captionStr = typeof caption === 'string' ? caption : '';
	const inner = (
		<>
			<div className="ebq-scorebadge__ring" style={{ '--p': ringFill, '--col': colorVar }}>
				<span className="ebq-scorebadge__num">{displayScore ?? safeScore}</span>
			</div>
			<div className="ebq-scorebadge__main">
				<p className="ebq-scorebadge__label">
					{badge ? <span className="ebq-scorebadge__badge">{badge}</span> : null}
					<span>{label}</span>
				</p>
				<p className="ebq-scorebadge__caption" title={captionStr}>{caption}</p>
			</div>
			{trailing ? <span className="ebq-scorebadge__trailing">{trailing}</span> : null}
		</>
	);
	if (onClick) {
		return (
			<button type="button" className={cls} onClick={onClick} aria-expanded={ariaExpanded} aria-haspopup="dialog" title={captionStr}>
				{inner}
			</button>
		);
	}
	return <div className={cls} title={captionStr}>{inner}</div>;
}

/* ─── Check card (unified offline + live factor row) ─────────── */

/**
 * Single row used by both the offline self-check (`AssessmentList`) and the
 * EBQ live-score factor breakdown. Same layout — colored left stripe,
 * label + score chip, detail line, optional recommendation, optional action.
 *
 * `kind` paints the card's background tint ("offline" = neutral surface,
 * "live" = subtle accent tint) so the user can tell at a glance which side
 * of the SEO panel a row came from. The left stripe is always tinted by
 * `level` (good / warn / bad / mute).
 */
export function CheckCard({ kind = 'offline', level = 'mute', label, score, detail, recommendation, action, icon, items }) {
	const cardLevel = level === 'ok' ? 'warn' : level;
	const cls = `ebq-check-card ebq-check-card--${kind} ebq-check-card--${cardLevel}`;
	return (
		<div className={cls}>
			{icon ? <span className={`ebq-check-card__icon ebq-check-card__icon--${cardLevel}`}>{icon}</span> : null}
			<div className="ebq-check-card__body">
				<div className="ebq-check-card__head">
					<span className="ebq-check-card__label">{label}</span>
					{score != null ? (
						<span className={`ebq-check-card__chip ebq-check-card__chip--${cardLevel}`}>
							{score}
						</span>
					) : null}
				</div>
				{detail ? <p className="ebq-check-card__detail">{detail}</p> : null}
				{recommendation ? <p className="ebq-check-card__recommend">{recommendation}</p> : null}
				{Array.isArray(items) && items.length ? (
					<ul className="ebq-check-card__items">
						{items.map((it, i) => {
							const sev = it.severity || 'info';
							return (
								<li key={i} className={`ebq-check-card__item ebq-check-card__item--${sev}`}>
									<div className="ebq-check-card__item-head">
										<span className={`ebq-check-card__item-dot ebq-check-card__item-dot--${sev}`} aria-hidden />
										<strong>{it.title}</strong>
									</div>
									{it.why ? <p className="ebq-check-card__item-why">{it.why}</p> : null}
									{it.fix ? <p className="ebq-check-card__item-fix"><span>Fix:</span> {it.fix}</p> : null}
								</li>
							);
						})}
					</ul>
				) : null}
				{action ? <div className="ebq-check-card__action">{action}</div> : null}
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

/* ─── NeedsSetup — unified dependency-blocked state ─────────── */

/**
 * Used by every feature that has a hard precondition (focus keyphrase,
 * GSC data, completed audit, etc.). Replaces ad-hoc one-line "X is
 * unavailable" copy with a friendly "what this feature does / why it's
 * blocked / how to fix / button to take you there" card.
 *
 * Design intent: the user should never have to ask "what does this error
 * mean and what do I do?". Every NeedsSetup tells them.
 *
 * Props:
 *   - feature: short feature name (used in the title)
 *   - why: one sentence describing the missing dependency in plain terms
 *   - fix: one sentence describing the concrete next action
 *   - action (optional): { label, onClick? OR url? } for the CTA button
 *   - tone: 'info' (default — neutral blue) | 'warn' (something is wrong)
 *           | 'pending' (background work in progress)
 *   - icon (optional): override the default icon
 */
export function NeedsSetup({ feature, why, fix, action, tone = 'info', icon }) {
	const cls = `ebq-needs-setup ebq-needs-setup--${tone}`;
	const button = action ? (
		action.url ? (
			<a
				href={action.url}
				target={action.target || '_blank'}
				rel="noopener noreferrer"
				className="ebq-needs-setup__action"
			>
				{action.label} →
			</a>
		) : (
			<button type="button" className="ebq-needs-setup__action" onClick={action.onClick}>
				{action.label} →
			</button>
		)
	) : null;
	return (
		<div className={cls}>
			<div className="ebq-needs-setup__icon" aria-hidden>
				{icon || (tone === 'warn' ? <IconWarn /> : tone === 'pending' ? <IconDot /> : <IconSparkle />)}
			</div>
			<div className="ebq-needs-setup__body">
				{feature ? <p className="ebq-needs-setup__feature">{feature}</p> : null}
				{why ? <p className="ebq-needs-setup__why">{why}</p> : null}
				{fix ? <p className="ebq-needs-setup__fix">{fix}</p> : null}
				{button}
			</div>
		</div>
	);
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
