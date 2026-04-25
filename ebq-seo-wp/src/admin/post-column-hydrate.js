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
	if (cells.length) {
		const ids = [...new Set(cells.map((c) => c.dataset.post).filter(Boolean))];
		if (ids.length) {
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
		}
	}

	// Inline "+ Track keyphrase" handler — opens a vanilla modal with country
	// / language / device options, POSTs to /ebq/v1/track-keyword on confirm,
	// and updates the row-action link to "✓ Tracking" on success. The link
	// keeps a real href to HQ as a no-JS fallback.
	document.addEventListener('click', (e) => {
		const link = e.target.closest('a.ebq-row-track');
		if (!link || link.dataset.busy === '1' || link.dataset.done === '1') return;
		e.preventDefault();
		openTrackModal(link);
	}, true);
});

function openTrackModal(link) {
	const keyword = link.dataset.ebqKeyword || '';
	const targetUrl = link.dataset.ebqTargetUrl || '';
	if (!keyword) return;

	// Strip any prior modal so repeated clicks don't stack.
	document.querySelectorAll('.ebq-track-modal').forEach((n) => n.remove());

	const modal = document.createElement('div');
	modal.className = 'ebq-track-modal';
	modal.setAttribute('role', 'dialog');
	modal.setAttribute('aria-modal', 'true');
	modal.setAttribute('aria-label', __('Track keyphrase', 'ebq-seo'));
	modal.innerHTML = `
		<div class="ebq-track-modal__panel" role="document">
			<header class="ebq-track-modal__head">
				<h3>${__('Add keyphrase to Rank Tracker', 'ebq-seo')}</h3>
				<button type="button" class="ebq-track-modal__close" aria-label="${__('Close', 'ebq-seo')}">×</button>
			</header>
			<div class="ebq-track-modal__body">
				<div class="ebq-track-modal__kw">
					<span class="ebq-track-modal__kw-label">${__('Keyphrase', 'ebq-seo')}</span>
					<strong></strong>
				</div>
				<div class="ebq-track-modal__row">
					<label>${__('Country', 'ebq-seo')}</label>
					<select name="country">${countryOptions()}</select>
				</div>
				<div class="ebq-track-modal__row">
					<label>${__('Language', 'ebq-seo')}</label>
					<select name="language">${languageOptions()}</select>
				</div>
				<div class="ebq-track-modal__row">
					<label>${__('Device', 'ebq-seo')}</label>
					<select name="device">
						<option value="desktop">${__('Desktop', 'ebq-seo')}</option>
						<option value="mobile">${__('Mobile', 'ebq-seo')}</option>
					</select>
				</div>
				<p class="ebq-track-modal__help">${__('First SERP check runs in 1–5 minutes. View results in EBQ HQ → Rank Tracker.', 'ebq-seo')}</p>
				<div class="ebq-track-modal__err" hidden></div>
			</div>
			<footer class="ebq-track-modal__foot">
				<button type="button" class="ebq-track-modal__btn ebq-track-modal__btn--ghost" data-action="cancel">${__('Cancel', 'ebq-seo')}</button>
				<button type="button" class="ebq-track-modal__btn ebq-track-modal__btn--primary" data-action="submit">${__('Add to Rank Tracker', 'ebq-seo')}</button>
			</footer>
		</div>
	`;
	document.body.appendChild(modal);

	// Inject keyword text safely (no innerHTML interpolation).
	modal.querySelector('.ebq-track-modal__kw strong').textContent = keyword;

	const close = () => {
		document.removeEventListener('keydown', onKey, true);
		modal.remove();
	};
	const onKey = (e) => { if (e.key === 'Escape') close(); };
	document.addEventListener('keydown', onKey, true);

	modal.addEventListener('click', (ev) => {
		if (ev.target === modal) close();
	});
	modal.querySelector('.ebq-track-modal__close').addEventListener('click', close);
	modal.querySelector('[data-action="cancel"]').addEventListener('click', close);

	const submitBtn = modal.querySelector('[data-action="submit"]');
	const errBox = modal.querySelector('.ebq-track-modal__err');

	submitBtn.addEventListener('click', () => {
		const data = {
			keyword,
			target_url: targetUrl,
			country: modal.querySelector('select[name="country"]').value,
			language: modal.querySelector('select[name="language"]').value,
			device: modal.querySelector('select[name="device"]').value,
		};
		submitBtn.disabled = true;
		submitBtn.textContent = __('Adding…', 'ebq-seo');
		errBox.hidden = true;

		apiFetch({
			path: '/ebq/v1/track-keyword',
			method: 'POST',
			data,
		}).then((res) => {
			if (res && res.ok === false) {
				errBox.textContent = res.message || res.error || __('Failed', 'ebq-seo');
				errBox.hidden = false;
				submitBtn.disabled = false;
				submitBtn.textContent = __('Retry', 'ebq-seo');
				return;
			}
			// Success: flip the row action link + close modal.
			link.innerHTML = '<span class="ebq-row-track__plus ebq-row-track__plus--done" aria-hidden="true">✓</span> ' + __('Tracking', 'ebq-seo');
			link.title = __('Added to Rank Tracker. View in EBQ HQ → Rank Tracker.', 'ebq-seo');
			link.dataset.done = '1';
			link.style.pointerEvents = 'none';
			close();
		}).catch((err) => {
			errBox.textContent = (err && err.message) || __('Network error', 'ebq-seo');
			errBox.hidden = false;
			submitBtn.disabled = false;
			submitBtn.textContent = __('Retry', 'ebq-seo');
		});
	});
}

function countryOptions() {
	const list = [
		['us','United States'],['gb','United Kingdom'],['in','India'],['ca','Canada'],['au','Australia'],
		['de','Germany'],['fr','France'],['es','Spain'],['it','Italy'],['nl','Netherlands'],
		['br','Brazil'],['mx','Mexico'],['jp','Japan'],['kr','South Korea'],['sg','Singapore'],
		['ae','United Arab Emirates'],['sa','Saudi Arabia'],['pk','Pakistan'],['bd','Bangladesh'],
		['id','Indonesia'],['tr','Turkey'],['ph','Philippines'],['vn','Vietnam'],['th','Thailand'],
		['eg','Egypt'],['za','South Africa'],['ng','Nigeria'],['pl','Poland'],['se','Sweden'],['no','Norway'],
	];
	return list.map(([c, l]) => `<option value="${c}">${l}</option>`).join('');
}

function languageOptions() {
	const list = [
		['en','English'],['es','Spanish'],['fr','French'],['de','German'],['it','Italian'],
		['pt','Portuguese'],['nl','Dutch'],['ru','Russian'],['ja','Japanese'],['ko','Korean'],
		['zh','Chinese'],['ar','Arabic'],['hi','Hindi'],['ur','Urdu'],['tr','Turkish'],
		['pl','Polish'],['sv','Swedish'],['no','Norwegian'],['da','Danish'],['fi','Finnish'],
		['th','Thai'],['vi','Vietnamese'],['id','Indonesian'],
	];
	return list.map(([c, l]) => `<option value="${c}">${l}</option>`).join('');
}
