/*!
 * EBQ SEO — dashboard widget hydrator.
 * Replaces the skeleton with real insight-count cards once the remote fetch
 * completes, so /wp-admin/ never waits on EBQ.
 */
( function ( wp ) {
    if ( ! wp || ! wp.apiFetch ) { return; }

    function run() {
        var node = document.querySelector( '[data-ebq-dashboard]' );
        if ( ! node ) { return; }

        wp.apiFetch( { path: '/ebq/v1/dashboard-html' } )
            .then( function ( res ) {
                if ( ! res || ! res.ok || typeof res.html !== 'string' ) {
                    fallback( node, ( res && res.error ) || 'no_response' );
                    return;
                }
                node.innerHTML = res.html;
            } )
            .catch( function ( err ) {
                fallback( node, ( err && err.code ) || 'fetch_failed' );
            } );
    }

    function fallback( node, reason ) {
        var skeleton = node.querySelector( '.ebq-widget-skeleton' );
        if ( skeleton ) { skeleton.style.display = 'none'; }
        var p = node.querySelector( '.ebq-widget-fallback' );
        if ( p ) {
            p.style.display = 'block';
            p.textContent = 'EBQ insights unavailable (' + reason + ').';
        }
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', run );
    } else {
        run();
    }
} )( window.wp || {} );
