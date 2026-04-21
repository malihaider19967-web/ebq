/*!
 * EBQ SEO — post-list column hydrator.
 *
 * One bulk fetch per admin page, populates every EBQ column cell at once.
 * Skeleton bars fade out, numbers + badges replace them.
 */
( function ( wp ) {
    if ( ! wp || ! wp.apiFetch ) { return; }

    injectStyles();

    function injectStyles() {
        var css = '.ebq-col-cell{display:flex;flex-direction:column;gap:2px;font-size:11px;}'
            + '.ebq-col-skeleton{display:flex;flex-direction:column;gap:3px;}'
            + '.ebq-col-shimmer{display:block;height:8px;border-radius:2px;background:linear-gradient(90deg,#f1f5f9 0%,#e2e8f0 50%,#f1f5f9 100%);background-size:200% 100%;animation:ebq-col-shimmer 1.2s infinite;}'
            + '@keyframes ebq-col-shimmer{0%{background-position:200% 0;}100%{background-position:-200% 0;}}'
            + '.ebq-col-badge{display:inline-block;padding:1px 4px;border-radius:4px;font-size:9px;font-weight:600;text-transform:uppercase;margin-right:3px;}'
            + '.ebq-col-badge.cann{background:#fef3c7;color:#92400e;}'
            + '.ebq-col-badge.tracked{background:#e0e7ff;color:#3730a3;}';
        var style = document.createElement( 'style' );
        style.textContent = css;
        document.head.appendChild( style );
    }

    function render( node, data ) {
        var clicks = data && typeof data.clicks_30d === 'number' ? data.clicks_30d : 0;
        var position = data && data.avg_position !== null && data.avg_position !== undefined ? data.avg_position : null;
        var flags = data && data.flags ? data.flags : {};

        var html = '';
        html += '<span><strong style="color:#1d4ed8;">' + clicks.toLocaleString() + '</strong> <span style="color:#64748b;">clicks 30d</span></span>';
        if ( position !== null ) {
            html += '<span style="color:#64748b;">Avg pos <strong>' + position + '</strong></span>';
        }
        var badges = '';
        if ( flags.cannibalized ) { badges += '<span class="ebq-col-badge cann">cannibalized</span>'; }
        if ( flags.tracked ) { badges += '<span class="ebq-col-badge tracked">tracked</span>'; }
        if ( badges ) {
            html += '<span style="margin-top:2px;">' + badges + '</span>';
        }
        node.innerHTML = html;
    }

    function empty( node ) {
        node.innerHTML = '<span style="color:#94a3b8;">—</span>';
    }

    function run() {
        var cells = Array.prototype.slice.call( document.querySelectorAll( '[data-ebq-col]' ) );
        if ( cells.length === 0 ) { return; }

        var ids = cells.map( function ( c ) { return c.getAttribute( 'data-post' ); } ).filter( Boolean );
        var path = '/ebq/v1/bulk-post-insights?' + ids.map( function ( id ) { return 'post_ids[]=' + encodeURIComponent( id ); } ).join( '&' );

        wp.apiFetch( { path: path } )
            .then( function ( res ) {
                var rows = res && res.rows ? res.rows : {};
                cells.forEach( function ( cell ) {
                    var id = cell.getAttribute( 'data-post' );
                    if ( rows[ id ] ) {
                        render( cell, rows[ id ] );
                    } else {
                        empty( cell );
                    }
                } );
            } )
            .catch( function () {
                cells.forEach( empty );
            } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', run );
    } else {
        run();
    }
} )( window.wp || {} );
