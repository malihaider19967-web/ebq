/**
 * Pure-SVG charts for the HQ tabs. No external chart library so the bundle
 * stays under 200kb and the visuals match the rest of the admin UI exactly.
 *
 *   Sparkline       inline 60×18 line, used inside KPI cards
 *   LineChart       full-width multi-metric line — performance tab
 *   BarChart        horizontal/vertical bars — position distribution
 *   StackedBar      single-row stacked bar — index-status verdicts
 */

const palette = {
	clicks: '#4f46e5',
	impressions: '#0891b2',
	position: '#dc2626',
	ctr: '#16a34a',
};

export function Sparkline({ data, color = '#4f46e5', height = 22, width = 80 }) {
	if (!data || data.length === 0) return <svg width={width} height={height} aria-hidden="true" />;
	const values = data.map((d) => d.clicks ?? d.value ?? 0);
	const max = Math.max(1, ...values);
	const min = Math.min(0, ...values);
	const range = max - min || 1;
	const stepX = width / Math.max(1, values.length - 1);
	const points = values.map((v, i) => `${(i * stepX).toFixed(1)},${(height - ((v - min) / range) * height).toFixed(1)}`).join(' ');
	return (
		<svg width={width} height={height} viewBox={`0 0 ${width} ${height}`} aria-hidden="true">
			<polyline fill="none" stroke={color} strokeWidth="1.5" points={points} />
		</svg>
	);
}

export function LineChart({ series, metric = 'clicks', height = 220, color }) {
	if (!series || series.length === 0) return null;
	const width = 800;
	const padX = 36;
	const padTop = 18;
	const padBottom = 28;
	const innerW = width - padX * 2;
	const innerH = height - padTop - padBottom;
	const accent = color || palette[metric] || '#4f46e5';

	const values = series.map((d) => d[metric] ?? 0);
	const max = Math.max(1, ...values);
	const min = metric === 'position' ? 0 : 0;
	const range = max - min || 1;

	const stepX = innerW / Math.max(1, series.length - 1);
	const yFor = (v) => padTop + innerH - ((v - min) / range) * innerH;
	const xFor = (i) => padX + i * stepX;

	const linePath = values.map((v, i) => `${i === 0 ? 'M' : 'L'} ${xFor(i).toFixed(1)} ${yFor(v).toFixed(1)}`).join(' ');
	const areaPath = `${linePath} L ${xFor(series.length - 1).toFixed(1)} ${(padTop + innerH).toFixed(1)} L ${xFor(0).toFixed(1)} ${(padTop + innerH).toFixed(1)} Z`;

	// Y-axis labels — 4 evenly-spaced grid lines.
	const yTicks = Array.from({ length: 4 }).map((_, i) => {
		const v = min + (range * i) / 3;
		return { v, y: yFor(v) };
	});

	// X-axis labels — first / middle / last for compactness.
	const xTickIdx = [0, Math.floor(series.length / 2), series.length - 1];

	return (
		<svg className="ebq-hq-chart" viewBox={`0 0 ${width} ${height}`} preserveAspectRatio="none" role="img">
			<defs>
				<linearGradient id={`ebq-hq-grad-${metric}`} x1="0" y1="0" x2="0" y2="1">
					<stop offset="0%" stopColor={accent} stopOpacity="0.18" />
					<stop offset="100%" stopColor={accent} stopOpacity="0.02" />
				</linearGradient>
			</defs>
			{yTicks.map((t, i) => (
				<g key={i}>
					<line x1={padX} y1={t.y} x2={width - padX} y2={t.y} stroke="#e2e8f0" strokeDasharray="3,3" />
					<text x={padX - 6} y={t.y + 4} textAnchor="end" fontSize="11" fill="#94a3b8">{Math.round(t.v).toLocaleString()}</text>
				</g>
			))}
			<path d={areaPath} fill={`url(#ebq-hq-grad-${metric})`} />
			<path d={linePath} fill="none" stroke={accent} strokeWidth="2" />
			{xTickIdx.map((i) => (
				<text key={i} x={xFor(i)} y={height - 8} textAnchor={i === 0 ? 'start' : i === series.length - 1 ? 'end' : 'middle'} fontSize="11" fill="#94a3b8">
					{shortDate(series[i]?.date)}
				</text>
			))}
		</svg>
	);
}

export function BarChart({ items, valueKey = 'value', labelKey = 'label', height = 180 }) {
	if (!items || items.length === 0) return null;
	const max = Math.max(1, ...items.map((it) => it[valueKey] || 0));
	return (
		<div className="ebq-hq-bar" style={{ minHeight: height }}>
			{items.map((it, i) => {
				const pct = ((it[valueKey] || 0) / max) * 100;
				return (
					<div key={i} className="ebq-hq-bar__row">
						<div className="ebq-hq-bar__label">{it[labelKey]}</div>
						<div className="ebq-hq-bar__track">
							<div className="ebq-hq-bar__fill" style={{ width: `${pct}%` }} />
						</div>
						<div className="ebq-hq-bar__value">{(it[valueKey] || 0).toLocaleString()}</div>
					</div>
				);
			})}
		</div>
	);
}

export function StackedBar({ segments }) {
	const total = segments.reduce((sum, s) => sum + (s.value || 0), 0);
	if (total === 0) return <div className="ebq-hq-stacked is-empty">No data</div>;
	return (
		<div className="ebq-hq-stacked">
			{segments.map((s, i) => {
				const pct = ((s.value || 0) / total) * 100;
				if (pct === 0) return null;
				return (
					<span
						key={i}
						className={`ebq-hq-stacked__seg ebq-hq-stacked__seg--${s.tone || 'neutral'}`}
						style={{ width: `${pct}%` }}
						title={`${s.label}: ${s.value}`}
					/>
				);
			})}
		</div>
	);
}

function shortDate(d) {
	if (!d) return '';
	const date = new Date(d);
	if (Number.isNaN(date.getTime())) return d;
	return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}
