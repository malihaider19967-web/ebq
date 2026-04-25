/**
 * Tiny fetch wrapper for the /wp-json/ebq/v1/* endpoints. Adds the WP REST
 * nonce, normalizes errors, and short-circuits when the user isn't connected
 * yet (so screens render an "Connect to EBQ" prompt instead of a network
 * error pile).
 */

import apiFetch from '@wordpress/api-fetch';

const config = (typeof window !== 'undefined' && window.ebqHqConfig) || {};

apiFetch.use(apiFetch.createNonceMiddleware(config.nonce || ''));

export const HQ_CONFIG = {
	siteName: config.siteName || '',
	workspaceDomain: config.workspaceDomain || '',
	isConnected: config.isConnected !== false,
	settingsUrl: config.settingsUrl || '',
	connectUrl: config.connectUrl || '',
	pluginVersion: config.pluginVersion || '',
};

export const RANGES = [
	{ key: '7d', label: '7 days' },
	{ key: '30d', label: '30 days' },
	{ key: '90d', label: '90 days' },
	{ key: '180d', label: '180 days' },
];

const BASE = (config.restUrl || '').replace(/\/$/, '');

function buildUrl(path, query) {
	const url = new URL(BASE + path);
	if (query) {
		for (const [k, v] of Object.entries(query)) {
			if (v === undefined || v === null || v === '') continue;
			url.searchParams.set(k, String(v));
		}
	}
	// Cache-buster — every request has a unique URL so no CDN, no LiteSpeed,
	// no browser-disk layer can return a stale response. Server-side we still
	// send no-cache headers; this is the defense-in-depth layer.
	url.searchParams.set('_', Date.now().toString(36));
	return url.toString();
}

async function request(method, path, { query, body } = {}) {
	const url = buildUrl(path, query);
	return apiFetch({
		url,
		method,
		data: body,
		parse: true,
		// Tell the browser fetch layer to never use HTTP cache for these calls.
		headers: { 'Cache-Control': 'no-cache', 'Pragma': 'no-cache' },
	}).catch((err) => ({ ok: false, error: 'fetch_failed', message: err?.message }));
}

export const Api = {
	overview: (range = '30d') => request('GET', '/hq/overview', { query: { range } }),
	performance: (range = '30d') => request('GET', '/hq/performance', { query: { range } }),
	keywords: (params) => request('GET', '/hq/keywords', { query: params }),
	keywordHistory: (id) => request('GET', `/hq/keywords/${id}/history`),
	keywordCandidates: (limit = 25) => request('GET', '/hq/keywords/candidates', { query: { limit } }),
	gscKeywords: (params) => request('GET', '/hq/gsc-keywords', { query: params }),
	createKeyword: (payload) => request('POST', '/hq/keywords', { body: payload }),
	updateKeyword: (id, payload) => request('PATCH', `/hq/keywords/${id}`, { body: payload }),
	deleteKeyword: (id) => request('DELETE', `/hq/keywords/${id}`),
	recheckKeyword: (id) => request('POST', `/hq/keywords/${id}/recheck`),
	pages: (params) => request('GET', '/hq/pages', { query: params }),
	indexStatus: (params) => request('GET', '/hq/index-status', { query: params }),
	insights: (type, limit = 25) => request('GET', `/hq/insights/${encodeURIComponent(type)}`, { query: { limit } }),
	iframeUrl: (insight) => request('GET', '/hq/iframe-url', { query: { insight } }),
};
