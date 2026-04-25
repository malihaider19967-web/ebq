/**
 * Dashboard widget hydrator — pulls /ebq/v1/dashboard-html and replaces the
 * skeleton block with the server-rendered card grid.
 */
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';

domReady(() => {
	const target = document.querySelector('[data-ebq-dashboard]');
	if (!target) return;

	apiFetch({ path: '/ebq/v1/dashboard-html' })
		.then((res) => {
			if (res && res.ok && typeof res.html === 'string') {
				target.innerHTML = res.html;
			} else {
				target.innerHTML = `<p class="ebq-widget-fallback">${__('Could not load EBQ insights.', 'ebq-seo')}</p>`;
			}
		})
		.catch(() => {
			target.innerHTML = `<p class="ebq-widget-fallback">${__('Could not load EBQ insights.', 'ebq-seo')}</p>`;
		});
});
