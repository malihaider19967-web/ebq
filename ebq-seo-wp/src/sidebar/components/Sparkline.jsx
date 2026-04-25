/**
 * Tiny inline sparkline. Accepts an array of numbers OR
 * an array of {date, value}. Plots area + line + last-point dot.
 */
export default function Sparkline({ series, height = 36 }) {
	const values = (series || []).map((p) => (typeof p === 'object' ? Number(p.value || p.clicks || 0) : Number(p))).filter(
		(v) => !Number.isNaN(v)
	);
	if (values.length < 2) {
		return <div style={{ height, color: 'var(--ebq-text-faint)', fontSize: 11 }}>—</div>;
	}
	const w = 100;
	const h = 100;
	const min = Math.min(...values);
	const max = Math.max(...values);
	const span = Math.max(1, max - min);
	const step = w / (values.length - 1);
	const points = values.map((v, i) => [i * step, h - ((v - min) / span) * h]);
	const line = points.map((p, i) => (i === 0 ? `M${p[0]},${p[1]}` : `L${p[0]},${p[1]}`)).join(' ');
	const area = `${line} L${w},${h} L0,${h} Z`;
	const last = points[points.length - 1];

	return (
		<svg className="ebq-spark" viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" style={{ height }} aria-hidden>
			<path className="ebq-spark-area" d={area} />
			<path className="ebq-spark-line" d={line} />
			<circle className="ebq-spark-dot" cx={last[0]} cy={last[1]} r="2" />
		</svg>
	);
}
