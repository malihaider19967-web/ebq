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
import './hq.css';

document.addEventListener('DOMContentLoaded', () => {
	const mount = document.getElementById('ebq-hq-root');
	if (!mount) return;
	const root = createRoot(mount);
	root.render(<App />);
});
