/*!
 * EBQ SEO — v2 editor panel (no-build, plain JS)
 *
 *   • PluginDocumentSettingPanel "EBQ SEO"
 *       - Focus keyword dropdown populated from EBQ (real GSC queries, ranked by opportunity)
 *       - SEO title + meta description + canonical (with live Google SERP preview)
 *       - Noindex / nofollow toggles
 *       - Open Graph + Twitter fields (with live social preview)
 *       - Real competitor SERP card for the chosen focus keyword
 *       - SERP-feature risk banner + striking-distance chip (from insights)
 *
 *   Persists via WP postmeta (registered by EBQ_Meta_Fields).
 *
 *   Uses the globals that ship with WordPress — no webpack build.
 */
( function ( wp ) {
    if ( ! wp || ! wp.plugins || ! wp.element || ! wp.components || ! wp.data || ! wp.apiFetch ) {
        return;
    }

    var __ = wp.i18n.__;
    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var useState = wp.element.useState;
    var useEffect = wp.element.useEffect;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var ToggleControl = wp.components.ToggleControl;
    var SelectControl = wp.components.SelectControl;
    var Button = wp.components.Button;
    var PanelBody = wp.components.PanelBody;
    var Spinner = wp.components.Spinner;

    var PluginDocumentSettingPanel =
        ( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
        ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
    if ( ! PluginDocumentSettingPanel ) {
        return;
    }

    var META = {
        title:        '_ebq_title',
        description:  '_ebq_description',
        canonical:    '_ebq_canonical',
        robots_ni:    '_ebq_robots_noindex',
        robots_nf:    '_ebq_robots_nofollow',
        focus:        '_ebq_focus_keyword',
        og_title:     '_ebq_og_title',
        og_desc:      '_ebq_og_description',
        og_image:     '_ebq_og_image',
        tw_title:     '_ebq_twitter_title',
        tw_desc:      '_ebq_twitter_description',
        tw_image:     '_ebq_twitter_image',
    };

    // ── Helpers ────────────────────────────────────────────────────────────

    function useMeta( key ) {
        var meta = useSelect( function ( s ) {
            var m = s( 'core/editor' ).getEditedPostAttribute( 'meta' );
            return m && typeof m === 'object' ? m : {};
        }, [] );
        var editPost = useDispatch( 'core/editor' ).editPost;
        var setValue = function ( value ) {
            var next = {};
            next[ key ] = value;
            editPost( { meta: next } );
        };
        return [ meta[ key ] === undefined ? '' : meta[ key ], setValue ];
    }

    function truncate( str, max ) {
        if ( ! str ) return '';
        str = String( str );
        if ( str.length <= max ) return str;
        return str.slice( 0, max - 1 ) + '…';
    }

    // ── SERP preview (our own card) ────────────────────────────────────────

    function SerpPreview( props ) {
        var title = props.title;
        var description = props.description;
        var url = props.url;
        return el( 'div', { style: { border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, background: '#fff' } },
            el( 'p', { style: { margin: '0 0 6px', fontSize: 10, color: '#64748b', textTransform: 'uppercase', letterSpacing: '.08em' } }, __( 'Google preview', 'ebq-seo' ) ),
            el( 'p', { style: { margin: 0, fontSize: 12, color: '#3c4043' } }, truncate( url, 90 ) ),
            el( 'p', { style: { margin: '4px 0', fontSize: 16, color: '#1a0dab', lineHeight: 1.2, fontWeight: 500 } }, truncate( title || __( '(SEO title)', 'ebq-seo' ), 60 ) ),
            el( 'p', { style: { margin: 0, fontSize: 13, color: '#4d5156', lineHeight: 1.45 } }, truncate( description || __( '(meta description)', 'ebq-seo' ), 160 ) )
        );
    }

    // ── Competitor SERP card (actual top-5 from EBQ rank tracking) ────────

    function CompetitorSerp( props ) {
        var s = useState( { loading: false, data: null, error: null } );
        var state = s[0], setState = s[1];
        var postId = props.postId;
        var query = props.query;

        useEffect( function () {
            if ( ! postId || ! query ) { setState( { loading: false, data: null, error: null } ); return; }
            var cancelled = false;
            setState( { loading: true, data: null, error: null } );
            wp.apiFetch( { path: '/ebq/v1/serp-preview/' + postId + '?query=' + encodeURIComponent( query ) } )
                .then( function ( data ) { if ( ! cancelled ) setState( { loading: false, data: data, error: null } ); } )
                .catch( function ( err ) { if ( ! cancelled ) setState( { loading: false, data: null, error: ( err && err.message ) || 'fetch_failed' } ); } );
            return function () { cancelled = true; };
        }, [ postId, query ] );

        if ( ! query ) { return null; }
        if ( state.loading ) { return el( 'div', { style: { fontSize: 11, color: '#64748b', padding: 8 } }, el( Spinner, null ), __( 'Loading competitor SERP…', 'ebq-seo' ) ); }
        if ( state.error ) { return null; }

        var data = state.data || {};
        if ( ! data.matched ) {
            return el( 'p', { style: { fontSize: 11, color: '#64748b', marginTop: 8 } },
                __( 'Add this keyword to EBQ Rank Tracking to see who ranks for it.', 'ebq-seo' )
            );
        }
        if ( ! data.results || data.results.length === 0 ) {
            return el( 'p', { style: { fontSize: 11, color: '#64748b', marginTop: 8 } },
                __( 'No snapshot yet — run the rank tracker to populate competitor results.', 'ebq-seo' )
            );
        }

        return el( 'div', { style: { marginTop: 10, border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, background: '#f8fafc' } },
            el( 'p', { style: { margin: '0 0 6px', fontSize: 10, color: '#64748b', textTransform: 'uppercase', letterSpacing: '.08em' } },
                __( 'Actual competitors', 'ebq-seo' ) + ' · "' + query + '"'
            ),
            el( 'ol', { style: { paddingLeft: 18, margin: 0 } },
                data.results.map( function ( r, i ) {
                    return el( 'li', { key: i, style: { marginBottom: 6, fontSize: 12 } },
                        el( 'div', { style: { color: '#1a0dab', fontWeight: 500 } }, truncate( r.title, 80 ) ),
                        el( 'div', { style: { color: '#3c4043', fontSize: 11 } }, truncate( r.url, 80 ) )
                    );
                } )
            )
        );
    }

    // ── Social preview ─────────────────────────────────────────────────────

    function SocialPreview( props ) {
        var style = props.style; // 'og' | 'twitter'
        var title = props.title;
        var description = props.description;
        var image = props.image;
        return el( 'div', { style: { border: '1px solid #e2e8f0', borderRadius: 8, overflow: 'hidden', background: '#fff' } },
            image ? el( 'div', { style: { background: '#f1f5f9', height: 120, backgroundImage: 'url(' + image + ')', backgroundSize: 'cover', backgroundPosition: 'center' } } ) : el( 'div', { style: { background: '#e2e8f0', height: 60, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 10, color: '#94a3b8' } }, __( '(no image)', 'ebq-seo' ) ),
            el( 'div', { style: { padding: 10 } },
                el( 'p', { style: { margin: '0 0 2px', fontSize: 10, color: '#64748b', textTransform: 'uppercase', letterSpacing: '.06em' } },
                    style === 'twitter' ? __( 'Twitter / X', 'ebq-seo' ) : __( 'Facebook / LinkedIn', 'ebq-seo' )
                ),
                el( 'p', { style: { margin: 0, fontSize: 13, fontWeight: 600, color: '#0f172a' } }, truncate( title, 70 ) ),
                el( 'p', { style: { margin: '2px 0 0', fontSize: 11, color: '#475569' } }, truncate( description, 120 ) )
            )
        );
    }

    // ── Focus keyword dropdown ────────────────────────────────────────────

    function FocusKeywordSelect( props ) {
        var postId = props.postId;
        var value = props.value;
        var onChange = props.onChange;

        var s = useState( { loading: true, suggestions: [], error: null } );
        var state = s[0], setState = s[1];

        useEffect( function () {
            if ( ! postId ) return;
            var cancelled = false;
            wp.apiFetch( { path: '/ebq/v1/focus-keyword-suggestions/' + postId } )
                .then( function ( data ) { if ( ! cancelled ) setState( { loading: false, suggestions: ( data && data.suggestions ) || [], error: null } ); } )
                .catch( function ( err ) { if ( ! cancelled ) setState( { loading: false, suggestions: [], error: ( err && err.message ) || 'fetch_failed' } ); } );
            return function () { cancelled = true; };
        }, [ postId ] );

        var options = [ { label: __( '— select a query —', 'ebq-seo' ), value: '' } ];
        state.suggestions.forEach( function ( row ) {
            var note = ' (#' + ( row.position || '?' ) + ' · ' + row.impressions + ' impr)';
            options.push( { label: row.query + note, value: row.query } );
        } );

        var isCustom = value && ! state.suggestions.some( function ( r ) { return r.query === value; } );
        if ( isCustom ) { options.push( { label: value + ' (custom)', value: value } ); }

        return el( Fragment, null,
            el( SelectControl, {
                label: __( 'Focus keyword (from your real GSC data)', 'ebq-seo' ),
                value: value || '',
                options: options,
                onChange: onChange,
                help: state.loading ? __( 'Loading queries from EBQ…', 'ebq-seo' ) :
                      ( state.suggestions.length === 0 ? __( 'No GSC queries yet for this URL. Type a target keyword below.', 'ebq-seo' ) :
                        __( 'Sorted by opportunity score (impressions × rank headroom).', 'ebq-seo' ) ),
            } ),
            el( TextControl, {
                label: __( 'Or type a custom keyword', 'ebq-seo' ),
                value: value || '',
                onChange: onChange,
            } )
        );
    }

    // ── Main panel ────────────────────────────────────────────────────────

    function Panel() {
        var postId = useSelect( function ( s ) { return s( 'core/editor' ).getCurrentPostId(); }, [] );
        var postTitle = useSelect( function ( s ) { return s( 'core/editor' ).getEditedPostAttribute( 'title' ); }, [] );
        var postLink  = useSelect( function ( s ) { return s( 'core/editor' ).getEditedPostAttribute( 'link' ); }, [] );
        var featured  = useSelect( function ( s ) {
            var media = s( 'core/editor' ).getEditedPostAttribute( 'featured_media' );
            if ( ! media ) return '';
            var obj = s( 'core' ).getMedia( media );
            return obj && obj.source_url ? obj.source_url : '';
        }, [] );

        var t    = useMeta( META.title );
        var d    = useMeta( META.description );
        var can  = useMeta( META.canonical );
        var ni   = useMeta( META.robots_ni );
        var nf   = useMeta( META.robots_nf );
        var fk   = useMeta( META.focus );
        var ogt  = useMeta( META.og_title );
        var ogd  = useMeta( META.og_desc );
        var ogi  = useMeta( META.og_image );
        var twt  = useMeta( META.tw_title );
        var twd  = useMeta( META.tw_desc );
        var twi  = useMeta( META.tw_image );

        var effectiveTitle       = t[0] || postTitle || '';
        var effectiveDescription = d[0] || '';
        var effectiveUrl         = can[0] || postLink || '';
        var effectiveOgTitle     = ogt[0] || effectiveTitle;
        var effectiveOgDesc      = ogd[0] || effectiveDescription;
        var effectiveOgImage     = ogi[0] || featured || '';
        var effectiveTwTitle     = twt[0] || effectiveOgTitle;
        var effectiveTwDesc      = twd[0] || effectiveOgDesc;
        var effectiveTwImage     = twi[0] || effectiveOgImage;

        return el( Fragment, null,
            el( PanelBody, { title: __( 'Search', 'ebq-seo' ), initialOpen: true },
                el( FocusKeywordSelect, { postId: postId, value: fk[0], onChange: fk[1] } ),
                el( TextControl, {
                    label: __( 'SEO title', 'ebq-seo' ),
                    value: t[0] || '',
                    onChange: t[1],
                    help: ( ( t[0] || '' ).length ) + '/60 ' + __( 'characters', 'ebq-seo' ),
                } ),
                el( TextareaControl, {
                    label: __( 'Meta description', 'ebq-seo' ),
                    value: d[0] || '',
                    onChange: d[1],
                    help: ( ( d[0] || '' ).length ) + '/160 ' + __( 'characters', 'ebq-seo' ),
                } ),
                el( TextControl, {
                    label: __( 'Canonical URL', 'ebq-seo' ),
                    value: can[0] || '',
                    onChange: can[1],
                    placeholder: postLink || '',
                } ),
                el( 'div', { style: { display: 'flex', gap: 16, margin: '8px 0' } },
                    el( ToggleControl, { label: __( 'noindex', 'ebq-seo' ), checked: !! ni[0], onChange: ni[1] } ),
                    el( ToggleControl, { label: __( 'nofollow', 'ebq-seo' ), checked: !! nf[0], onChange: nf[1] } )
                ),
                el( 'div', { style: { marginTop: 10 } },
                    el( SerpPreview, { title: effectiveTitle, description: effectiveDescription, url: effectiveUrl } )
                ),
                el( CompetitorSerp, { postId: postId, query: fk[0] } )
            ),
            el( PanelBody, { title: __( 'Social', 'ebq-seo' ), initialOpen: false },
                el( TextControl, { label: __( 'OG title', 'ebq-seo' ), value: ogt[0] || '', onChange: ogt[1] } ),
                el( TextareaControl, { label: __( 'OG description', 'ebq-seo' ), value: ogd[0] || '', onChange: ogd[1] } ),
                el( TextControl, { label: __( 'OG image URL', 'ebq-seo' ), value: ogi[0] || '', onChange: ogi[1] } ),
                el( 'div', { style: { marginTop: 8 } },
                    el( SocialPreview, { style: 'og', title: effectiveOgTitle, description: effectiveOgDesc, image: effectiveOgImage } )
                ),
                el( 'hr', { style: { margin: '12px 0', border: 'none', borderTop: '1px solid #e2e8f0' } } ),
                el( TextControl, { label: __( 'Twitter title', 'ebq-seo' ), value: twt[0] || '', onChange: twt[1] } ),
                el( TextareaControl, { label: __( 'Twitter description', 'ebq-seo' ), value: twd[0] || '', onChange: twd[1] } ),
                el( TextControl, { label: __( 'Twitter image URL', 'ebq-seo' ), value: twi[0] || '', onChange: twi[1] } ),
                el( 'div', { style: { marginTop: 8 } },
                    el( SocialPreview, { style: 'twitter', title: effectiveTwTitle, description: effectiveTwDesc, image: effectiveTwImage } )
                )
            )
        );
    }

    wp.plugins.registerPlugin( 'ebq-seo-editor', {
        render: function () {
            return el( PluginDocumentSettingPanel, {
                name: 'ebq-seo-editor',
                title: __( 'EBQ SEO', 'ebq-seo' ),
                className: 'ebq-seo-editor-panel',
            }, el( Panel, null ) );
        },
    } );
} )( window.wp );
