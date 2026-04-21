/*!
 * EBQ SEO — meta-box hydrator (post edit screens only).
 *
 * Every `<div class="ebq-mb-loader" data-ebq-mb data-post="NN">` is replaced
 * with real insights HTML fetched from /wp-json/ebq/v1/post-insights-html/NN
 * — after the page is rendered, so the editor is never blocked.
 */
( function ( wp ) {
    if ( ! wp || ! wp.apiFetch ) { return; }

    function hydrate( node ) {
        var postId = parseInt( node.getAttribute( 'data-post' ), 10 );
        if ( ! postId ) { return; }

        wp.apiFetch( { path: '/ebq/v1/post-insights-html/' + postId } )
            .then( function ( res ) {
                if ( ! res || typeof res.html !== 'string' ) {
                    fallback( node, ( res && res.error ) || 'no_response' );
                    return;
                }
                node.classList.remove( 'ebq-mb-loader' );
                node.innerHTML = res.html;
            } )
            .catch( function ( err ) {
                fallback( node, ( err && err.code ) || 'fetch_failed' );
            } );
    }

    function fallback( node, reason ) {
        var skeleton = node.querySelector( '.ebq-mb-skeleton' );
        if ( skeleton ) { skeleton.style.display = 'none'; }
        var p = node.querySelector( '.ebq-mb-fallback' );
        if ( p ) {
            p.style.display = 'block';
            p.textContent = 'EBQ insights unavailable (' + reason + ').';
        }
    }

    function run() {
        document.querySelectorAll( '[data-ebq-mb]' ).forEach( hydrate );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', run );
    } else {
        run();
    }
} )( window.wp || {} );
