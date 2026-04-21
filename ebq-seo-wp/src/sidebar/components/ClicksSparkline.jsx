export default function ClicksSparkline({ series }) {
    if (!Array.isArray(series) || series.length < 2) return null;

    const width = 260;
    const height = 48;
    const max = Math.max(1, ...series.map((p) => p.clicks || 0));
    const step = series.length > 1 ? width / (series.length - 1) : width;

    const points = series.map((p, i) => {
        const x = i * step;
        const y = height - ((p.clicks || 0) / max) * height;
        return `${x.toFixed(1)},${y.toFixed(1)}`;
    }).join(' ');

    const areaPath = `M 0 ${height} L ${points.split(' ').join(' L ')} L ${width} ${height} Z`;

    return (
        <svg viewBox={`0 0 ${width} ${height}`} width="100%" height="48" role="img" aria-label="Clicks trend">
            <path d={areaPath} fill="#10b981" fillOpacity="0.15" />
            <polyline points={points} fill="none" stroke="#10b981" strokeWidth="1.5" strokeLinejoin="round" strokeLinecap="round" />
        </svg>
    );
}
