/**
 * Classic-editor side meta box hydrator. Calls /ebq/v1/post-insights-html and
 * replaces the skeleton with the server-rendered summary. Only matters in the
 * classic editor — the block-editor sidebar covers this UI on its own.
 */
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { __ } from '@wordpress/i18n';

domReady(() => {
	const target = document.querySelector('[data-ebq-mb][data-post]');
	if (!target) return;
	const id = target.dataset.post;
	if (!id) return;

	apiFetch({ path: `/ebq/v1/post-insights-html/${encodeURIComponent(id)}` })
		.then((res) => {
			if (res && res.ok && typeof res.html === 'string') {
				target.classList.remove('ebq-mb-loader');
				target.innerHTML = res.html;
			} else {
				target.innerHTML = `<p class="ebq-mb__label">${__('No insights available yet.', 'ebq-seo')}</p>`;
			}
		})
		.catch(() => {
			target.innerHTML = `<p class="ebq-mb__label">${__('Could not load EBQ insights.', 'ebq-seo')}</p>`;
		});
});
