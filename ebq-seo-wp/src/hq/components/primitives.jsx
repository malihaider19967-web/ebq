import { __ } from '@wordpress/i18n';

/**
 * Headquarter-tab UI primitives. Standalone (not shared with the Gutenberg
 * sidebar) so the admin-page styling can drift independently.
 */

export function Card({ title, action, children, padding = 'md', className = '' }) {
	const padCls = padding === 'tight' ? ' ebq-hq-card--tight' : padding === 'flush' ? ' ebq-hq-card--flush' : '';
	return (
		<section className={`ebq-hq-card${padCls} ${className}`.trim()}>
			{(title || action) && (
				<header className="ebq-hq-card__head">
					{title ? <h3 className="ebq-hq-card__title">{title}</h3> : null}
					{action ? <div className="ebq-hq-card__action">{action}</div> : null}
				</header>
			)}
			<div className="ebq-hq-card__body">{children}</div>
		</section>
	);
}

export function KpiCard({ label, value, change, suffix, sub, sparkline, tone = 'neutral' }) {
	const arrow = change?.direction === 'up' ? '▲' : change?.direction === 'down' ? '▼' : '·';
	const arrowCls = change?.direction === 'up' ? 'ebq-hq-kpi__delta--up' : change?.direction === 'down' ? 'ebq-hq-kpi__delta--down' : 'ebq-hq-kpi__delta--flat';

	const formattedValue = value === null || value === undefined ? '—' : (typeof value === 'number' ? value.toLocaleString() : value);

	return (
		<div className={`ebq-hq-kpi ebq-hq-kpi--${tone}`}>
			<div className="ebq-hq-kpi__label">{label}</div>
			<div className="ebq-hq-kpi__value">
				{formattedValue}
				{suffix ? <span className="ebq-hq-kpi__suffix">{suffix}</span> : null}
			</div>
			{change && change.change_pct !== null && change.change_pct !== undefined ? (
				<div className={`ebq-hq-kpi__delta ${arrowCls}`}>
					<span className="ebq-hq-kpi__arrow">{arrow}</span>
					<span>{Math.abs(change.change_pct)}%</span>
					{change.previous !== undefined && change.previous !== null ? (
						<span className="ebq-hq-kpi__prev">vs {typeof change.previous === 'number' ? change.previous.toLocaleString() : change.previous}</span>
					) : null}
				</div>
			) : sub ? (
				<div className="ebq-hq-kpi__sub">{sub}</div>
			) : null}
			{sparkline ? <div className="ebq-hq-kpi__spark">{sparkline}</div> : null}
		</div>
	);
}

export function Pill({ tone = 'neutral', children }) {
	return <span className={`ebq-hq-pill ebq-hq-pill--${tone}`}>{children}</span>;
}

export function Button({ children, variant = 'ghost', size = 'md', onClick, href, target, disabled }) {
	const cls = `ebq-hq-btn ebq-hq-btn--${variant} ebq-hq-btn--${size}${disabled ? ' is-disabled' : ''}`;
	if (href) {
		return <a className={cls} href={href} target={target || '_self'} rel={target === '_blank' ? 'noopener noreferrer' : undefined}>{children}</a>;
	}
	return <button type="button" className={cls} onClick={onClick} disabled={disabled}>{children}</button>;
}

export function Spinner({ label }) {
	return <span className="ebq-hq-spinner" role="status" aria-live="polite" aria-label={label || __('Loading', 'ebq-seo')} />;
}

export function EmptyState({ title, sub, children }) {
	return (
		<div className="ebq-hq-empty">
			{title ? <p className="ebq-hq-empty__title">{title}</p> : null}
			{sub ? <p className="ebq-hq-empty__sub">{sub}</p> : null}
			{children}
		</div>
	);
}

export function ErrorState({ error, retry }) {
	const msg = error?.error === 'not_connected'
		? __('This site is not connected to EBQ yet.', 'ebq-seo')
		: error?.message || error?.error || __('Could not load this data.', 'ebq-seo');
	return (
		<div className="ebq-hq-error" role="alert">
			<strong>{__('Something went wrong', 'ebq-seo')}</strong>
			<span>{msg}</span>
			{retry ? <Button size="sm" onClick={retry}>{__('Retry', 'ebq-seo')}</Button> : null}
		</div>
	);
}

export function SkeletonRows({ rows = 5 }) {
	return (
		<div className="ebq-hq-skel">
			{Array.from({ length: rows }).map((_, i) => (
				<div key={i} className="ebq-hq-skel__row" />
			))}
		</div>
	);
}

export function RangePicker({ value, options, onChange }) {
	return (
		<div className="ebq-hq-range" role="radiogroup" aria-label={__('Time range', 'ebq-seo')}>
			{options.map((opt) => (
				<button
					key={opt.key}
					type="button"
					role="radio"
					aria-checked={value === opt.key}
					className={`ebq-hq-range__btn${value === opt.key ? ' is-active' : ''}`}
					onClick={() => onChange(opt.key)}
				>
					{opt.label}
				</button>
			))}
		</div>
	);
}
