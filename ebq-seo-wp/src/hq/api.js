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
	return url.toString();
}

async function get(path, query) {
	const url = buildUrl(path, query);
	const res = await apiFetch({ url, method: 'GET', parse: true }).catch((err) => ({ ok: false, error: 'fetch_failed', message: err?.message }));
	return res;
}

export const Api = {
	overview: (range = '30d') => get('/hq/overview', { range }),
	performance: (range = '30d') => get('/hq/performance', { range }),
	keywords: (params) => get('/hq/keywords', params),
	keywordHistory: (id) => get(`/hq/keywords/${id}/history`),
	pages: (params) => get('/hq/pages', params),
	indexStatus: (params) => get('/hq/index-status', params),
	insights: (type, limit = 25) => get(`/hq/insights/${encodeURIComponent(type)}`, { limit }),
	iframeUrl: (insight) => get('/hq/iframe-url', { insight }),
};
