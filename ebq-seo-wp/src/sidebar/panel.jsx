import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { PanelBody, Spinner, Button } from '@wordpress/components';

import RankChip from './components/RankChip';
import ClicksSparkline from './components/ClicksSparkline';
import CannibalizationWarning from './components/CannibalizationWarning';
import StrikingDistanceFlag from './components/StrikingDistanceFlag';

export default function Panel() {
    const postId = useSelect((select) => select('core/editor').getCurrentPostId(), []);

    const [state, setState] = useState({ loading: true, data: null, error: null });
    const [refreshKey, setRefreshKey] = useState(0);

    useEffect(() => {
        if (!postId) return;

        let cancelled = false;
        setState({ loading: true, data: null, error: null });

        apiFetch({ path: `/ebq/v1/post-insights/${postId}` })
            .then((data) => {
                if (cancelled) return;
                if (data && data.ok === false) {
                    setState({ loading: false, data: null, error: data.error || 'unknown_error' });
                } else {
                    setState({ loading: false, data, error: null });
                }
            })
            .catch((err) => {
                if (cancelled) return;
                setState({ loading: false, data: null, error: err?.message || 'fetch_failed' });
            });

        return () => { cancelled = true; };
    }, [postId, refreshKey]);

    const { loading, data, error } = state;

    if (loading) {
        return (
            <PanelBody>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <Spinner />
                    <span>{__('Loading insights…', 'ebq-seo')}</span>
                </div>
            </PanelBody>
        );
    }

    if (error) {
        return (
            <PanelBody>
                <p style={{ color: '#b91c1c', fontSize: 12 }}>{__('Could not load insights.', 'ebq-seo')} ({error})</p>
                <Button variant="secondary" onClick={() => setRefreshKey((k) => k + 1)}>{__('Retry', 'ebq-seo')}</Button>
            </PanelBody>
        );
    }

    if (!data) return null;

    const gsc = data.gsc || {};
    const totals30 = gsc.totals_30d || {};
    const tracked = data.tracked_keyword;
    const audit = data.audit;

    return (
        <>
            <PanelBody title={__('Search performance (30d)', 'ebq-seo')} initialOpen={true}>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                    <Metric label={__('Clicks', 'ebq-seo')} value={totals30.clicks} />
                    <Metric label={__('Impressions', 'ebq-seo')} value={totals30.impressions} />
                    <Metric label={__('Avg position', 'ebq-seo')} value={totals30.position ?? '—'} />
                    <Metric label={__('CTR', 'ebq-seo')} value={totals30.ctr !== null && totals30.ctr !== undefined ? `${totals30.ctr}%` : '—'} />
                </div>
                {Array.isArray(gsc.click_series_90d) && gsc.click_series_90d.length > 1 && (
                    <div style={{ marginTop: 12 }}>
                        <p style={{ fontSize: 10, textTransform: 'uppercase', letterSpacing: '.08em', color: '#64748b', margin: 0 }}>{__('Clicks · last 90 days', 'ebq-seo')}</p>
                        <ClicksSparkline series={gsc.click_series_90d} />
                    </div>
                )}
            </PanelBody>

            {tracked && (
                <PanelBody title={__('Rank tracking', 'ebq-seo')} initialOpen={true}>
                    <RankChip tracked={tracked} />
                </PanelBody>
            )}

            {(data.flags?.cannibalized || data.flags?.striking_distance) && (
                <PanelBody title={__('Opportunities', 'ebq-seo')} initialOpen={true}>
                    {data.flags?.cannibalized && <CannibalizationWarning items={data.cannibalization || []} />}
                    {data.flags?.striking_distance && <StrikingDistanceFlag items={data.striking_distance || []} />}
                </PanelBody>
            )}

            {audit && (
                <PanelBody title={__('Latest audit', 'ebq-seo')} initialOpen={false}>
                    <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                        <Metric label={__('Perf (mobile)', 'ebq-seo')} value={audit.performance_score_mobile ?? '—'} />
                        <Metric label={__('Perf (desktop)', 'ebq-seo')} value={audit.performance_score_desktop ?? '—'} />
                        <Metric label={__('LCP (mob)', 'ebq-seo')} value={audit.lcp_ms_mobile ? `${audit.lcp_ms_mobile}ms` : '—'} />
                        <Metric label={__('CLS (mob)', 'ebq-seo')} value={audit.cls_mobile ?? '—'} />
                    </div>
                </PanelBody>
            )}

            <PanelBody>
                <Button variant="secondary" onClick={() => setRefreshKey((k) => k + 1)}>{__('Refresh', 'ebq-seo')}</Button>
            </PanelBody>
        </>
    );
}

function Metric({ label, value }) {
    return (
        <div style={{ border: '1px solid #e2e8f0', borderRadius: 6, padding: '8px 10px', background: '#fff' }}>
            <p style={{ fontSize: 10, textTransform: 'uppercase', letterSpacing: '.08em', color: '#64748b', margin: 0 }}>{label}</p>
            <p style={{ fontSize: 16, fontWeight: 700, margin: '4px 0 0', fontVariantNumeric: 'tabular-nums' }}>{value ?? '—'}</p>
        </div>
    );
}
