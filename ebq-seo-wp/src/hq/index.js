/**
 * EBQ Head Quarter — top-level WP-admin React entry point.
 *
 * Mounts a single App into #ebq-hq-root, which is rendered by
 * EBQ_Hq_Page::render(). All data flows through the WP REST proxy at
 * /wp-json/ebq/v1/hq/* — the proxy forwards to EBQ.io with the per-site
 * bearer token, keeping the token out of browser JS.
 */

import { createRoot } from '@wordpress/element';
import App from './App';
import AiWriterStandalone from './AiWriterStandalone';
import './hq.css';

document.addEventListener('DOMContentLoaded', () => {
	// EBQ HQ — full dashboard with the tab sidebar.
	const hqMount = document.getElementById('ebq-hq-root');
	if (hqMount) {
		createRoot(hqMount).render(<App />);
	}

	// AI Writer — standalone top-level admin page. Same React bundle,
	// different mount point so we don't load the AI Writer's TinyMCE
	// chrome on every HQ pageview.
	const aiwMount = document.getElementById('ebq-aiwriter-root');
	if (aiwMount) {
		createRoot(aiwMount).render(<AiWriterStandalone />);
	}
});
