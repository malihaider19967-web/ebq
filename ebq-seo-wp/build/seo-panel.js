/*!
 * EBQ SEO — v2 editor panel (no-build, plain JS)
 *
 *   • PluginDocumentSettingPanel "EBQ SEO"
 *       - Focus keyword dropdown (real GSC queries, ranked by opportunity)
 *       - SEO title + meta description + canonical (live Google SERP preview)
 *       - Noindex / nofollow toggles
 *       - Open Graph + Twitter fields (live social preview)
 *       - Real competitor SERP card for the chosen focus keyword
 *
 *   Production optimizations:
 *     - ONE useSelect consolidates meta + post title/link. Reduces the
 *       re-subscription count from 12 to 1 across all fields.
 *     - SERP preview fetch is debounced 400 ms so typing/selecting doesn't
 *       spam the API.
 *     - Focus-keyword suggestions are cached in-memory per post.
 *     - Featured-image resolution uses the editor store only — no extra
 *       wp.core media fetch on mount.
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
    var useMemo = wp.element.useMemo;
    var useCallback = wp.element.useCallback;
    var useSelect = wp.data.useSelect;
    var useDispatch = wp.data.useDispatch;
    var TextControl = wp.components.TextControl;
    var TextareaControl = wp.components.TextareaControl;
    var ToggleControl = wp.components.ToggleControl;
    var SelectControl = wp.components.SelectControl;
    var PanelBody = wp.components.PanelBody;
    var Spinner = wp.components.Spinner;

    var PluginDocumentSettingPanel =
        ( wp.editor && wp.editor.PluginDocumentSettingPanel ) ||
        ( wp.editPost && wp.editPost.PluginDocumentSettingPanel );
    if ( ! PluginDocumentSettingPanel ) {
        return;
    }

    function truncate( str, max ) {
        if ( ! str ) return '';
        str = String( str );
        return str.length <= max ? str : str.slice( 0, max - 1 ) + '…';
    }

    function useDebounced( value, delayMs ) {
        var s = useState( value );
        useEffect( function () {
            var timer = setTimeout( function () { s[1]( value ); }, delayMs );
            return function () { clearTimeout( timer ); };
        }, [ value, delayMs ] );
        return s[0];
    }

    // ── Preview cards ─────────────────────────────────────────────────────

    function SerpPreview( props ) {
        return el( 'div', { style: { border: '1px solid #e2e8f0', borderRadius: 8, padding: 12, background: '#fff' } },
            el( 'p', { style: { margin: '0 0 6px', fontSize: 10, color: '#64748b', textTransform: 'uppercase', letterSpacing: '.08em' } }, __( 'Google preview', 'ebq-seo' ) ),
            el( 'p', { style: { margin: 0, fontSize: 12, color: '#3c4043' } }, truncate( props.url, 90 ) ),
            el( 'p', { style: { margin: '4px 0', fontSize: 16, color: '#1a0dab', lineHeight: 1.2, fontWeight: 500 } }, truncate( props.title || __( '(SEO title)', 'ebq-seo' ), 60 ) ),
            el( 'p', { style: { margin: 0, fontSize: 13, color: '#4d5156', lineHeight: 1.45 } }, truncate( props.description || __( '(meta description)', 'ebq-seo' ), 160 ) )
        );
    }

    function CompetitorSerp( props ) {
        var postId = props.postId;
        var debouncedQuery = useDebounced( props.query, 400 );

        var s = useState( { loading: false, data: null, error: null } );
        var state = s[0], setState = s[1];

        useEffect( function () {
            if ( ! postId || ! debouncedQuery ) {
                setState( { loading: false, data: null, error: null } );
                return;
            }
            var cancelled = false;
            setState( { loading: true, data: null, error: null } );
            wp.apiFetch( { path: '/ebq/v1/serp-preview/' + postId + '?query=' + encodeURIComponent( debouncedQuery ) } )
                .then( function ( data ) { if ( ! cancelled ) setState( { loading: false, data: data, error: null } ); } )
                .catch( function ( err ) { if ( ! cancelled ) setState( { loading: false, data: null, error: ( err && err.message ) || 'fetch_failed' } ); } );
            return function () { cancelled = true; };
        }, [ postId, debouncedQuery ] );

        if ( ! props.query ) { return null; }
        if ( state.loading ) {
            return el( 'div', { style: { display: 'flex', alignItems: 'center', gap: 6, fontSize: 11, color: '#64748b', padding: 8 } },
                el( Spinner, null ), __( 'Loading competitor SERP…', 'ebq-seo' )
            );
        }
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
                __( 'Actual competitors', 'ebq-seo' ) + ' · "' + debouncedQuery + '"'
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

    function SocialPreview( props ) {
        return el( 'div', { style: { border: '1px solid #e2e8f0', borderRadius: 8, overflow: 'hidden', background: '#fff' } },
            props.image
                ? el( 'div', { style: { background: '#f1f5f9', height: 120, backgroundImage: 'url(' + props.image + ')', backgroundSize: 'cover', backgroundPosition: 'center' } } )
                : el( 'div', { style: { background: '#e2e8f0', height: 60, display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: 10, color: '#94a3b8' } }, __( '(no image)', 'ebq-seo' ) ),
            el( 'div', { style: { padding: 10 } },
                el( 'p', { style: { margin: '0 0 2px', fontSize: 10, color: '#64748b', textTransform: 'uppercase', letterSpacing: '.06em' } },
                    props.style === 'twitter' ? __( 'Twitter / X', 'ebq-seo' ) : __( 'Facebook / LinkedIn', 'ebq-seo' )
                ),
                el( 'p', { style: { margin: 0, fontSize: 13, fontWeight: 600, color: '#0f172a' } }, truncate( props.title, 70 ) ),
                el( 'p', { style: { margin: '2px 0 0', fontSize: 11, color: '#475569' } }, truncate( props.description, 120 ) )
            )
        );
    }

    // ── Focus keyword dropdown (cached in-memory per post) ────────────────

    var suggestionsCache = {};

    function FocusKeywordSelect( props ) {
        var postId = props.postId;
        var value = props.value;
        var onChange = props.onChange;

        var s = useState( function () {
            var cached = suggestionsCache[ postId ];
            return cached ? { loading: false, suggestions: cached, error: null } : { loading: true, suggestions: [], error: null };
        } );
        var state = s[0], setState = s[1];

        useEffect( function () {
            if ( ! postId ) return;
            if ( suggestionsCache[ postId ] ) { return; }
            var cancelled = false;
            wp.apiFetch( { path: '/ebq/v1/focus-keyword-suggestions/' + postId } )
                .then( function ( data ) {
                    if ( cancelled ) return;
                    var list = ( data && data.suggestions ) || [];
                    suggestionsCache[ postId ] = list;
                    setState( { loading: false, suggestions: list, error: null } );
                } )
                .catch( function ( err ) {
                    if ( cancelled ) return;
                    setState( { loading: false, suggestions: [], error: ( err && err.message ) || 'fetch_failed' } );
                } );
            return function () { cancelled = true; };
        }, [ postId ] );

        var options = useMemo( function () {
            var opts = [ { label: __( '— select a query —', 'ebq-seo' ), value: '' } ];
            state.suggestions.forEach( function ( row ) {
                opts.push( {
                    label: row.query + ' (#' + ( row.position || '?' ) + ' · ' + row.impressions + ' impr)',
                    value: row.query,
                } );
            } );
            if ( value && ! state.suggestions.some( function ( r ) { return r.query === value; } ) ) {
                opts.push( { label: value + ' (custom)', value: value } );
            }
            return opts;
        }, [ state.suggestions, value ] );

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
        // ONE selector for everything — prevents re-render storms on keystrokes.
        var ctx = useSelect( function ( s ) {
            var editor = s( 'core/editor' );
            var meta = editor.getEditedPostAttribute( 'meta' ) || {};
            return {
                postId: editor.getCurrentPostId(),
                postTitle: editor.getEditedPostAttribute( 'title' ) || '',
                postLink: editor.getEditedPostAttribute( 'link' ) || '',
                meta: meta,
            };
        }, [] );

        var editPost = useDispatch( 'core/editor' ).editPost;

        var setMeta = useCallback( function ( key, value ) {
            var patch = {};
            patch[ key ] = value;
            editPost( { meta: patch } );
        }, [ editPost ] );

        var get = function ( key, fallback ) {
            var v = ctx.meta && ctx.meta[ key ];
            return v === undefined || v === '' || v === null ? ( fallback !== undefined ? fallback : '' ) : v;
        };

        var effectiveTitle       = get( '_ebq_title', ctx.postTitle );
        var effectiveDescription = get( '_ebq_description', '' );
        var effectiveUrl         = get( '_ebq_canonical', ctx.postLink );
        var effectiveOgTitle     = get( '_ebq_og_title', effectiveTitle );
        var effectiveOgDesc      = get( '_ebq_og_description', effectiveDescription );
        var effectiveOgImage     = get( '_ebq_og_image', '' );
        var effectiveTwTitle     = get( '_ebq_twitter_title', effectiveOgTitle );
        var effectiveTwDesc      = get( '_ebq_twitter_description', effectiveOgDesc );
        var effectiveTwImage     = get( '_ebq_twitter_image', effectiveOgImage );

        var focusKeyword = get( '_ebq_focus_keyword', '' );

        return el( Fragment, null,
            el( PanelBody, { title: __( 'Search', 'ebq-seo' ), initialOpen: true },
                el( FocusKeywordSelect, {
                    postId: ctx.postId,
                    value: focusKeyword,
                    onChange: function ( v ) { setMeta( '_ebq_focus_keyword', v ); },
                } ),
                el( TextControl, {
                    label: __( 'SEO title', 'ebq-seo' ),
                    value: get( '_ebq_title', '' ),
                    onChange: function ( v ) { setMeta( '_ebq_title', v ); },
                    help: ( String( get( '_ebq_title', '' ) ).length ) + '/60 ' + __( 'characters', 'ebq-seo' ),
                } ),
                el( TextareaControl, {
                    label: __( 'Meta description', 'ebq-seo' ),
                    value: get( '_ebq_description', '' ),
                    onChange: function ( v ) { setMeta( '_ebq_description', v ); },
                    help: ( String( get( '_ebq_description', '' ) ).length ) + '/160 ' + __( 'characters', 'ebq-seo' ),
                } ),
                el( TextControl, {
                    label: __( 'Canonical URL', 'ebq-seo' ),
                    value: get( '_ebq_canonical', '' ),
                    onChange: function ( v ) { setMeta( '_ebq_canonical', v ); },
                    placeholder: ctx.postLink,
                } ),
                el( 'div', { style: { display: 'flex', gap: 16, margin: '8px 0' } },
                    el( ToggleControl, {
                        label: __( 'noindex', 'ebq-seo' ),
                        checked: !! get( '_ebq_robots_noindex', false ),
                        onChange: function ( v ) { setMeta( '_ebq_robots_noindex', v ); },
                    } ),
                    el( ToggleControl, {
                        label: __( 'nofollow', 'ebq-seo' ),
                        checked: !! get( '_ebq_robots_nofollow', false ),
                        onChange: function ( v ) { setMeta( '_ebq_robots_nofollow', v ); },
                    } )
                ),
                el( 'div', { style: { marginTop: 10 } },
                    el( SerpPreview, { title: effectiveTitle, description: effectiveDescription, url: effectiveUrl } )
                ),
                el( CompetitorSerp, { postId: ctx.postId, query: focusKeyword } )
            ),
            el( PanelBody, { title: __( 'Social', 'ebq-seo' ), initialOpen: false },
                el( TextControl, { label: __( 'OG title', 'ebq-seo' ), value: get( '_ebq_og_title', '' ), onChange: function ( v ) { setMeta( '_ebq_og_title', v ); } } ),
                el( TextareaControl, { label: __( 'OG description', 'ebq-seo' ), value: get( '_ebq_og_description', '' ), onChange: function ( v ) { setMeta( '_ebq_og_description', v ); } } ),
                el( TextControl, { label: __( 'OG image URL', 'ebq-seo' ), value: get( '_ebq_og_image', '' ), onChange: function ( v ) { setMeta( '_ebq_og_image', v ); } } ),
                el( 'div', { style: { marginTop: 8 } },
                    el( SocialPreview, { style: 'og', title: effectiveOgTitle, description: effectiveOgDesc, image: effectiveOgImage } )
                ),
                el( 'hr', { style: { margin: '12px 0', border: 'none', borderTop: '1px solid #e2e8f0' } } ),
                el( TextControl, { label: __( 'Twitter title', 'ebq-seo' ), value: get( '_ebq_twitter_title', '' ), onChange: function ( v ) { setMeta( '_ebq_twitter_title', v ); } } ),
                el( TextareaControl, { label: __( 'Twitter description', 'ebq-seo' ), value: get( '_ebq_twitter_description', '' ), onChange: function ( v ) { setMeta( '_ebq_twitter_description', v ); } } ),
                el( TextControl, { label: __( 'Twitter image URL', 'ebq-seo' ), value: get( '_ebq_twitter_image', '' ), onChange: function ( v ) { setMeta( '_ebq_twitter_image', v ); } } ),
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
