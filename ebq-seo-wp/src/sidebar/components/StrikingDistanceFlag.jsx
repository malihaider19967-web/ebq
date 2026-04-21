import { __ } from '@wordpress/i18n';

export default function StrikingDistanceFlag({ items }) {
    if (!Array.isArray(items) || items.length === 0) return null;

    return (
        <div style={{ border: '1px solid #c7d2fe', background: '#eef2ff', borderRadius: 6, padding: 10 }}>
            <p style={{ fontSize: 11, fontWeight: 700, textTransform: 'uppercase', color: '#3730a3', margin: 0, letterSpacing: '.06em' }}>
                {__('Striking distance', 'ebq-seo')}
            </p>
            <p style={{ fontSize: 11, color: '#312e81', margin: '4px 0 6px' }}>
                {__('Queries this post ranks for at positions 5–20 with below-curve CTR. Tighten title, meta, headings, and internal links.', 'ebq-seo')}
            </p>
            <ul style={{ fontSize: 11, paddingLeft: 16, margin: 0 }}>
                {items.slice(0, 5).map((row, i) => (
                    <li key={i} style={{ marginBottom: 2 }}>
                        <strong>"{row.query}"</strong>
                        <span style={{ marginLeft: 6, color: '#64748b' }}>#{row.position} · {row.impressions} impr · {row.ctr}%</span>
                    </li>
                ))}
            </ul>
        </div>
    );
}
