/**
 * Post list "EBQ" column hydrator.
 *
 * Server-side, every row renders a skeleton (<div data-ebq-col data-post=…>).
 * After load, we collect every post id, hit /ebq/v1/bulk-post-insights once,
 * and render the rich cell content (rank pill + flags + sparkline meta).
 */
import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { __, sprintf } from '@wordpress/i18n';

function rankClass(p) {
	if (p == null) return 'ebq-col-rank--deep';
	const n = Number(p);
	if (n <= 10) return 'ebq-col-rank--top10';
	if (n <= 20) return 'ebq-col-rank--page1';
	if (n <= 30) return 'ebq-col-rank--striking';
	return 'ebq-col-rank--deep';
}

function el(tag, className, text) {
	const e = document.createElement(tag);
	if (className) e.className = className;
	if (text != null) e.textContent = text;
	return e;
}

function renderCell(target, data) {
	target.innerHTML = '';
	const wrap = el('div', 'ebq-col-stack');

	const tracked = data?.tracked_keyword;
	const gsc = data?.gsc || {};
	const flags = data?.flags || {};

	if (tracked && tracked.current_position != null) {
		const row = el('div', 'ebq-col-row');
		const rank = el('span', `ebq-col-rank ${rankClass(tracked.current_position)}`, `#${tracked.current_position}`);
		row.appendChild(rank);
		const change = Number(tracked.position_change || 0);
		if (change !== 0) {
			const arrow = change > 0
				? sprintf(__('▲ +%d', 'ebq-seo'), change)
				: sprintf(__('▼ %d', 'ebq-seo'), change);
			const tone = change > 0 ? 'good' : 'bad';
			row.appendChild(el('span', `ebq-col-meta ebq-col-meta--${tone}`, arrow));
		}
		const kw = el('span', 'ebq-col-meta', tracked.target_keyword || '');
		row.appendChild(kw);
		wrap.appendChild(row);
	} else {
		wrap.appendChild(el('span', 'ebq-col-meta', __('Not tracked', 'ebq-seo')));
	}

	const totals = gsc.totals_30d || {};
	if (totals.clicks != null || totals.impressions != null) {
		const meta = el('div', 'ebq-col-meta', sprintf(
			__('%1$s clicks · %2$s impr · 30d', 'ebq-seo'),
			Number(totals.clicks || 0).toLocaleString(),
			Number(totals.impressions || 0).toLocaleString()
		));
		wrap.appendChild(meta);
	}

	const flagsRow = el('div', 'ebq-col-flags');
	if (flags.cannibalized) flagsRow.appendChild(el('span', 'ebq-col-flag ebq-col-flag--bad', __('Cannibalized', 'ebq-seo')));
	if (flags.striking_distance) flagsRow.appendChild(el('span', 'ebq-col-flag ebq-col-flag--warn', __('Striking', 'ebq-seo')));
	if (flagsRow.children.length) wrap.appendChild(flagsRow);

	target.appendChild(wrap);
}

function renderEmpty(target, message) {
	target.innerHTML = '';
	target.appendChild(el('span', 'ebq-col-meta', message));
}

domReady(() => {
	const cells = Array.from(document.querySelectorAll('[data-ebq-col][data-post]'));
	if (!cells.length) return;

	const ids = [...new Set(cells.map((c) => c.dataset.post).filter(Boolean))];
	if (!ids.length) return;

	const params = ids.map((id) => `post_ids[]=${encodeURIComponent(id)}`).join('&');

	apiFetch({ path: `/ebq/v1/bulk-post-insights?${params}` })
		.then((res) => {
			const rows = (res && res.rows) || {};
			cells.forEach((cell) => {
				const id = cell.dataset.post;
				const data = rows[id];
				if (data && data.ok !== false) {
					renderCell(cell, data);
				} else {
					renderEmpty(cell, __('No data yet', 'ebq-seo'));
				}
			});
		})
		.catch(() => {
			cells.forEach((cell) => renderEmpty(cell, __('—', 'ebq-seo')));
		});
});
