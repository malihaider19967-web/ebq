/**
 * EBQ SEO — Classic Editor mount.
 *
 * IMPORTANT: the `window.__EBQ_CLASSIC__` flag MUST be true before any module
 * that imports `useEditorContext.js` evaluates. The PHP enqueue uses
 * wp_add_inline_script(..., 'before') to set it; this redundant assignment is
 * a belt-and-braces fallback in case enqueue order ever drifts.
 *
 * Note: ES module imports are hoisted above all other top-level code. So
 * `window.__EBQ_CLASSIC__ = true` *here* would actually run AFTER the imports
 * below — that's why we rely on the inline script for ordering. The reassign
 * below is only there to keep the value true even if some later code clears it.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot, StrictMode } from '@wordpress/element';

import App from '../sidebar/App';
import ErrorBoundary from '../sidebar/components/ErrorBoundary';
import '../sidebar/sidebar.css';

if (typeof window !== 'undefined') {
	window.__EBQ_CLASSIC__ = true;
}

function mountClassic() {
	const el = document.getElementById('ebq-classic-root');
	if (!el) {
		// Metabox not on this screen — silent no-op.
		return;
	}
	if (el.dataset.ebqMounted === '1') {
		return;
	}
	el.dataset.ebqMounted = '1';

	try {
		createRoot(el).render(
			<StrictMode>
				<ErrorBoundary>
					<App />
				</ErrorBoundary>
			</StrictMode>
		);
	} catch (err) {
		// Surface render errors so the user gets a hint instead of a silent blank box.
		// eslint-disable-next-line no-console
		console.error('[EBQ SEO] Classic editor mount failed:', err);
		el.innerHTML =
			'<p style="padding:16px;border:1px solid #fecaca;background:#fef2f2;color:#7f1d1d;border-radius:6px;font-size:12px;">' +
			'EBQ SEO failed to render in this editor. Check the browser console for details.' +
			'</p>';
	}
}

domReady(mountClassic);
