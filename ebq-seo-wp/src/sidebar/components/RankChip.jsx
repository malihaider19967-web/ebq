import { __ } from '@wordpress/i18n';

export default function RankChip({ tracked }) {
    if (!tracked) return null;

    const pos = tracked.current_position;
    const best = tracked.best_position;
    const change = tracked.position_change;
    const risk = tracked.serp_risk || {};

    let chipColor = '#334155';
    let chipBg = '#f1f5f9';
    if (pos) {
        if (pos <= 3) { chipColor = '#047857'; chipBg = '#d1fae5'; }
        else if (pos <= 10) { chipColor = '#1d4ed8'; chipBg = '#dbeafe'; }
        else if (pos <= 20) { chipColor = '#92400e'; chipBg = '#fef3c7'; }
    }

    return (
        <div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' }}>
                <span style={{ background: chipBg, color: chipColor, borderRadius: 16, padding: '3px 10px', fontSize: 12, fontWeight: 700, fontVariantNumeric: 'tabular-nums' }}>
                    {pos ? `#${pos}` : __('Unranked', 'ebq-seo')}
                </span>
                {typeof change === 'number' && change !== 0 && (
                    <span style={{ fontSize: 11, color: change > 0 ? '#047857' : '#b91c1c' }}>
                        {change > 0 ? `▲${change}` : `▼${Math.abs(change)}`}
                    </span>
                )}
                {best && <span style={{ fontSize: 10, color: '#64748b' }}>{__('Best', 'ebq-seo')} #{best}</span>}
            </div>
            <p style={{ fontSize: 11, color: '#64748b', marginTop: 4 }}>
                "{tracked.keyword}" · {tracked.country?.toUpperCase()} · {tracked.device}
            </p>
            {risk.at_risk && (
                <p style={{ fontSize: 11, color: '#b45309', marginTop: 6 }}>
                    {__('SERP risk', 'ebq-seo')}: {(risk.features_present || []).join(', ')}
                </p>
            )}
            {risk.lost_feature && (
                <p style={{ fontSize: 11, color: '#b91c1c', marginTop: 4 }}>
                    {__('Lost SERP feature', 'ebq-seo')}: {(risk.features_lost || []).join(', ')}
                </p>
            )}
        </div>
    );
}
