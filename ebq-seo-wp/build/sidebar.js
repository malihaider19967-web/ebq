/*!
 * EBQ SEO — Gutenberg sidebar (no-build, plain JS)
 *
 * Uses the wp.* globals that ship with WordPress, so this file runs as-is
 * without a webpack/@wordpress/scripts build step. If you want the JSX
 * version for future customization, see src/sidebar/*.jsx.
 */
( function ( wp ) {
    if ( ! wp || ! wp.plugins || ! wp.editPost || ! wp.element || ! wp.components || ! wp.data || ! wp.apiFetch || ! wp.i18n ) {
        return;
    }

    var __ = wp.i18n.__;
    var el = wp.element.createElement;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useSelect = wp.data.useSelect;
    var registerPlugin = wp.plugins.registerPlugin;
    var PluginSidebar = wp.editPost.PluginSidebar;
    var PluginSidebarMoreMenuItem = wp.editPost.PluginSidebarMoreMenuItem;
    var PanelBody = wp.components.PanelBody;
    var Spinner = wp.components.Spinner;
    var Button = wp.components.Button;

    var ICON = el( 'svg', {
        xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24',
        width: 20, height: 20, fill: 'none', stroke: 'currentColor', strokeWidth: 1.5,
    }, el( 'path', {
        strokeLinecap: 'round', strokeLinejoin: 'round',
        d: 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z',
    } ) );

    function metric( label, value ) {
        return el( 'div', { style: { border: '1px solid #e2e8f0', borderRadius: 6, padding: '8px 10px', background: '#fff' } },
            el( 'p', { style: { fontSize: 10, textTransform: 'uppercase', letterSpacing: '.08em', color: '#64748b', margin: 0 } }, label ),
            el( 'p', { style: { fontSize: 16, fontWeight: 700, margin: '4px 0 0', fontVariantNumeric: 'tabular-nums' } }, value == null || value === '' ? '—' : value )
        );
    }

    function rankChip( tracked ) {
        if ( ! tracked ) { return null; }
        var pos = tracked.current_position;
        var chipBg = '#f1f5f9', chipColor = '#334155';
        if ( pos ) {
            if ( pos <= 3 ) { chipBg = '#d1fae5'; chipColor = '#047857'; }
            else if ( pos <= 10 ) { chipBg = '#dbeafe'; chipColor = '#1d4ed8'; }
            else if ( pos <= 20 ) { chipBg = '#fef3c7'; chipColor = '#92400e'; }
        }
        var risk = tracked.serp_risk || {};
        return el( 'div', null,
            el( 'div', { style: { display: 'flex', alignItems: 'center', gap: 8, flexWrap: 'wrap' } },
                el( 'span', { style: { background: chipBg, color: chipColor, borderRadius: 16, padding: '3px 10px', fontSize: 12, fontWeight: 700, fontVariantNumeric: 'tabular-nums' } }, pos ? '#' + pos : __( 'Unranked', 'ebq-seo' ) ),
                typeof tracked.position_change === 'number' && tracked.position_change !== 0
                    ? el( 'span', { style: { fontSize: 11, color: tracked.position_change > 0 ? '#047857' : '#b91c1c' } }, tracked.position_change > 0 ? '▲' + tracked.position_change : '▼' + Math.abs( tracked.position_change ) )
                    : null,
                tracked.best_position ? el( 'span', { style: { fontSize: 10, color: '#64748b' } }, __( 'Best', 'ebq-seo' ) + ' #' + tracked.best_position ) : null
            ),
            el( 'p', { style: { fontSize: 11, color: '#64748b', marginTop: 4 } },
                '"' + tracked.keyword + '" · ' + ( tracked.country || '' ).toUpperCase() + ' · ' + ( tracked.device || '' )
            ),
            risk.at_risk ? el( 'p', { style: { fontSize: 11, color: '#b45309', marginTop: 6 } }, __( 'SERP risk', 'ebq-seo' ) + ': ' + ( risk.features_present || [] ).join( ', ' ) ) : null,
            risk.lost_feature ? el( 'p', { style: { fontSize: 11, color: '#b91c1c', marginTop: 4 } }, __( 'Lost SERP feature', 'ebq-seo' ) + ': ' + ( risk.features_lost || [] ).join( ', ' ) ) : null
        );
    }

    function cannibalList( items ) {
        if ( ! items || ! items.length ) { return null; }
        return el( 'div', { style: { border: '1px solid #fde68a', background: '#fffbeb', borderRadius: 6, padding: 10, marginBottom: 10 } },
            el( 'p', { style: { fontSize: 11, fontWeight: 700, textTransform: 'uppercase', color: '#92400e', margin: 0, letterSpacing: '.06em' } }, __( 'Keyword cannibalization', 'ebq-seo' ) ),
            el( 'p', { style: { fontSize: 11, color: '#78350f', margin: '4px 0 6px' } }, __( 'Two or more of your pages split clicks for these queries.', 'ebq-seo' ) ),
            el( 'ul', { style: { fontSize: 11, paddingLeft: 16, margin: 0 } }, items.slice( 0, 3 ).map( function ( r, i ) {
                return el( 'li', { key: i, style: { marginBottom: 4 } },
                    el( 'strong', null, '"' + r.query + '"' ),
                    r.is_primary_this_page
                        ? el( 'span', { style: { marginLeft: 6, color: '#047857' } }, __( '(primary)', 'ebq-seo' ) )
                        : el( 'span', { style: { marginLeft: 6, color: '#b91c1c' } }, __( '(weaker)', 'ebq-seo' ) )
                );
            } ) )
        );
    }

    function strikingList( items ) {
        if ( ! items || ! items.length ) { return null; }
        return el( 'div', { style: { border: '1px solid #c7d2fe', background: '#eef2ff', borderRadius: 6, padding: 10 } },
            el( 'p', { style: { fontSize: 11, fontWeight: 700, textTransform: 'uppercase', color: '#3730a3', margin: 0, letterSpacing: '.06em' } }, __( 'Striking distance', 'ebq-seo' ) ),
            el( 'ul', { style: { fontSize: 11, paddingLeft: 16, margin: '4px 0 0' } }, items.slice( 0, 5 ).map( function ( r, i ) {
                return el( 'li', { key: i, style: { marginBottom: 2 } },
                    el( 'strong', null, '"' + r.query + '"' ),
                    el( 'span', { style: { marginLeft: 6, color: '#64748b' } }, '#' + r.position + ' · ' + r.impressions + ' impr · ' + r.ctr + '%' )
                );
            } ) )
        );
    }

    function clicksSparkline( series ) {
        if ( ! series || series.length < 2 ) { return null; }
        var w = 260, h = 48;
        var max = Math.max.apply( null, [ 1 ].concat( series.map( function ( p ) { return p.clicks || 0; } ) ) );
        var step = series.length > 1 ? w / ( series.length - 1 ) : w;
        var points = series.map( function ( p, i ) {
            var x = i * step;
            var y = h - ( ( p.clicks || 0 ) / max ) * h;
            return x.toFixed( 1 ) + ',' + y.toFixed( 1 );
        } ).join( ' ' );
        var area = 'M 0 ' + h + ' L ' + points.split( ' ' ).join( ' L ' ) + ' L ' + w + ' ' + h + ' Z';
        return el( 'svg', { viewBox: '0 0 ' + w + ' ' + h, width: '100%', height: 48, role: 'img', 'aria-label': 'Clicks trend' },
            el( 'path', { d: area, fill: '#10b981', fillOpacity: 0.15 } ),
            el( 'polyline', { points: points, fill: 'none', stroke: '#10b981', strokeWidth: 1.5, strokeLinejoin: 'round', strokeLinecap: 'round' } )
        );
    }

    function Panel() {
        var postId = useSelect( function ( select ) {
            return select( 'core/editor' ).getCurrentPostId();
        }, [] );

        var s = useState( { loading: true, data: null, error: null } );
        var state = s[0], setState = s[1];
        var rk = useState( 0 );
        var refreshKey = rk[0], setRefreshKey = rk[1];

        useEffect( function () {
            if ( ! postId ) { return; }
            var cancelled = false;
            setState( { loading: true, data: null, error: null } );

            wp.apiFetch( { path: '/ebq/v1/post-insights/' + postId } )
                .then( function ( data ) {
                    if ( cancelled ) { return; }
                    if ( data && data.ok === false ) {
                        setState( { loading: false, data: null, error: data.error || 'unknown_error' } );
                    } else {
                        setState( { loading: false, data: data, error: null } );
                    }
                } )
                .catch( function ( err ) {
                    if ( cancelled ) { return; }
                    setState( { loading: false, data: null, error: ( err && err.message ) || 'fetch_failed' } );
                } );

            return function () { cancelled = true; };
        }, [ postId, refreshKey ] );

        if ( state.loading ) {
            return el( PanelBody, null,
                el( 'div', { style: { display: 'flex', alignItems: 'center', gap: 8 } },
                    el( Spinner, null ),
                    el( 'span', null, __( 'Loading insights…', 'ebq-seo' ) )
                )
            );
        }

        if ( state.error ) {
            return el( PanelBody, null,
                el( 'p', { style: { color: '#b91c1c', fontSize: 12 } },
                    __( 'Could not load insights.', 'ebq-seo' ) + ' (' + state.error + ')'
                ),
                el( Button, { variant: 'secondary', onClick: function () { setRefreshKey( refreshKey + 1 ); } }, __( 'Retry', 'ebq-seo' ) )
            );
        }

        var data = state.data || {};
        var gsc = data.gsc || {};
        var totals30 = gsc.totals_30d || {};
        var tracked = data.tracked_keyword;
        var audit = data.audit;
        var flags = data.flags || {};

        return el( wp.element.Fragment, null,
            el( PanelBody, { title: __( 'Search performance (30d)', 'ebq-seo' ), initialOpen: true },
                el( 'div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 } },
                    metric( __( 'Clicks', 'ebq-seo' ), totals30.clicks ),
                    metric( __( 'Impressions', 'ebq-seo' ), totals30.impressions ),
                    metric( __( 'Avg position', 'ebq-seo' ), totals30.position == null ? '—' : totals30.position ),
                    metric( __( 'CTR', 'ebq-seo' ), ( totals30.ctr == null ? '—' : totals30.ctr + '%' ) )
                ),
                Array.isArray( gsc.click_series_90d ) && gsc.click_series_90d.length > 1
                    ? el( 'div', { style: { marginTop: 12 } },
                        el( 'p', { style: { fontSize: 10, textTransform: 'uppercase', letterSpacing: '.08em', color: '#64748b', margin: 0 } }, __( 'Clicks · last 90 days', 'ebq-seo' ) ),
                        clicksSparkline( gsc.click_series_90d )
                    )
                    : null
            ),
            tracked ? el( PanelBody, { title: __( 'Rank tracking', 'ebq-seo' ), initialOpen: true }, rankChip( tracked ) ) : null,
            ( flags.cannibalized || flags.striking_distance )
                ? el( PanelBody, { title: __( 'Opportunities', 'ebq-seo' ), initialOpen: true },
                    flags.cannibalized ? cannibalList( data.cannibalization || [] ) : null,
                    flags.striking_distance ? strikingList( data.striking_distance || [] ) : null
                )
                : null,
            audit ? el( PanelBody, { title: __( 'Latest audit', 'ebq-seo' ), initialOpen: false },
                el( 'div', { style: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 } },
                    metric( __( 'Perf (mobile)', 'ebq-seo' ), audit.performance_score_mobile == null ? '—' : audit.performance_score_mobile ),
                    metric( __( 'Perf (desktop)', 'ebq-seo' ), audit.performance_score_desktop == null ? '—' : audit.performance_score_desktop ),
                    metric( __( 'LCP (mob)', 'ebq-seo' ), audit.lcp_ms_mobile ? audit.lcp_ms_mobile + 'ms' : '—' ),
                    metric( __( 'CLS (mob)', 'ebq-seo' ), audit.cls_mobile == null ? '—' : audit.cls_mobile )
                )
            ) : null,
            el( PanelBody, null,
                el( Button, { variant: 'secondary', onClick: function () { setRefreshKey( refreshKey + 1 ); } }, __( 'Refresh', 'ebq-seo' ) )
            )
        );
    }

    // Document-settings panel lives inline in the right-hand editor column
    // (always visible, no hunting in the three-dot menu).
    var PluginDocumentSettingPanel =
        ( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
        ( wp.editPost && wp.editPost.PluginDocumentSettingPanel ) ||
        null;

    registerPlugin( 'ebq-seo-sidebar', {
        render: function () {
            return el( wp.element.Fragment, null,
                PluginSidebarMoreMenuItem
                    ? el( PluginSidebarMoreMenuItem, { target: 'ebq-seo-sidebar', icon: ICON }, __( 'EBQ SEO', 'ebq-seo' ) )
                    : null,
                el( PluginSidebar, { name: 'ebq-seo-sidebar', icon: ICON, title: __( 'EBQ SEO', 'ebq-seo' ) },
                    el( Panel, null )
                ),
                PluginDocumentSettingPanel
                    ? el( PluginDocumentSettingPanel, { name: 'ebq-seo-doc-panel', title: __( 'EBQ SEO', 'ebq-seo' ) },
                        el( Panel, null )
                    )
                    : null
            );
        },
    } );
} )( window.wp );
