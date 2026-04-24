import { useSelect } from '@wordpress/data';
import { useEffect, useState, useMemo } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';
import { PanelBody, Spinner, Button } from '@wordpress/components';

import RankChip from './components/RankChip';
import ClicksSparkline from './components/ClicksSparkline';
import CannibalizationWarning from './components/CannibalizationWarning';
import StrikingDistanceFlag from './components/StrikingDistanceFlag';

export default function Panel() {
    const postId = useSelect((select) => select('core/editor').getCurrentPostId(), []);
    const focusKeyword = useSelect((select) => {
        const meta = select('core/editor').getEditedPostAttribute('meta') || {};
        return (meta._ebq_focus_keyword || '').trim();
    }, []);
    const permalink = useSelect((select) => {
        const post = select('core/editor').getCurrentPost();
        return post?.link || '';
    }, []);

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

    const auditUrl = useMemo(() => {
        const audit = data?.audit;
        if (!audit?.report_id || typeof window === 'undefined') {
            return '';
        }
        const base = (window.ebqSeoPublic && window.ebqSeoPublic.appBase) || '';
        return base ? `${base.replace(/\/$/, '')}/page-audits/${audit.report_id}` : '';
    }, [data]);

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
    const indexing = data.indexing;
    const primaryQuery = (gsc.primary_query || '').trim().toLowerCase();
    const focusNorm = focusKeyword.toLowerCase();
    const intentMismatch = primaryQuery && focusNorm && primaryQuery !== focusNorm;

    return (
        <>
            {indexing?.verdict && (
                <PanelBody title={__('Google indexing', 'ebq-seo')} initialOpen={false}>
                    <p style={{ fontSize: 12, margin: 0, fontWeight: 600 }}>{indexing.verdict}</p>
                    {indexing.coverage_state && (
                        <p style={{ fontSize: 11, color: '#475569', margin: '6px 0 0' }}>{indexing.coverage_state}</p>
                    )}
                </PanelBody>
            )}

            {intentMismatch && (
                <PanelBody title={__('Keyword alignment', 'ebq-seo')} initialOpen={true}>
                    <p style={{ fontSize: 12, color: '#b45309', margin: 0 }}>
                        {sprintf(
                            /* translators: 1: top GSC query, 2: editor focus keyword */
                            __('Top GSC query ("%1$s") differs from your focus keyphrase ("%2$s"). Consider aligning title and H1.', 'ebq-seo'),
                            gsc.primary_query,
                            focusKeyword
                        )}
                    </p>
                </PanelBody>
            )}

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
                    {data.flags?.striking_distance && (
                        <p style={{ fontSize: 11, color: '#475569', marginTop: 8 }}>
                            {__('Tip: tighten the SEO title and add one strong internal link to the primary URL.', 'ebq-seo')}
                        </p>
                    )}
                    {data.flags?.cannibalized && (
                        <p style={{ fontSize: 11, color: '#475569', marginTop: 8 }}>
                            {__('Tip: consolidate competing pages or de-optimize duplicates for this query.', 'ebq-seo')}
                        </p>
                    )}
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
                    {auditUrl && (
                        <p style={{ marginTop: 10 }}>
                            <Button variant="primary" href={auditUrl} target="_blank" rel="noopener noreferrer">
                                {__('Open full audit in EBQ', 'ebq-seo')}
                            </Button>
                        </p>
                    )}
                </PanelBody>
            )}

            <PanelBody title={__('Actions', 'ebq-seo')} initialOpen={false}>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                    {typeof window !== 'undefined' && window.ebqSeoPublic?.appBase && permalink && (
                        <Button
                            variant="secondary"
                            href={`${window.ebqSeoPublic.appBase.replace(/\/$/, '')}/custom-audit?pageUrl=${encodeURIComponent(permalink)}${focusKeyword ? `&targetKeyword=${encodeURIComponent(focusKeyword)}` : ''}`}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {__('Run new audit in EBQ', 'ebq-seo')}
                        </Button>
                    )}
                    <Button variant="secondary" onClick={() => setRefreshKey((k) => k + 1)}>{__('Refresh insights', 'ebq-seo')}</Button>
                </div>
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
