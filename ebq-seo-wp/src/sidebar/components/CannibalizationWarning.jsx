import { __ } from '@wordpress/i18n';

export default function CannibalizationWarning({ items }) {
    if (!Array.isArray(items) || items.length === 0) return null;

    return (
        <div style={{ border: '1px solid #fde68a', background: '#fffbeb', borderRadius: 6, padding: 10, marginBottom: 10 }}>
            <p style={{ fontSize: 11, fontWeight: 700, textTransform: 'uppercase', color: '#92400e', margin: 0, letterSpacing: '.06em' }}>
                {__('Keyword cannibalization', 'ebq-seo')}
            </p>
            <p style={{ fontSize: 11, color: '#78350f', margin: '4px 0 6px' }}>
                {__('This post competes with other pages for the same queries. Consolidate, re-target, or redirect the weaker URLs.', 'ebq-seo')}
            </p>
            <ul style={{ fontSize: 11, paddingLeft: 16, margin: 0 }}>
                {items.slice(0, 3).map((row, i) => (
                    <li key={i} style={{ marginBottom: 4 }}>
                        <strong>"{row.query}"</strong>
                        {row.is_primary_this_page ? (
                            <span style={{ marginLeft: 6, color: '#047857' }}>{__('(primary)', 'ebq-seo')}</span>
                        ) : (
                            <span style={{ marginLeft: 6, color: '#b91c1c' }}>{__('(weaker)', 'ebq-seo')}</span>
                        )}
                        {row.competing_pages?.length > 0 && (
                            <div style={{ color: '#64748b', marginTop: 2 }}>
                                vs {row.competing_pages.slice(0, 2).map((p) => p.page).join(', ')}
                            </div>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}
