import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { publicConfig } from './useEditorContext';

/**
 * Reactive subscription tier. Initial value comes from the page-load
 * `window.ebqSeoPublic` (set by wp_localize_script), then stays in sync
 * with whatever the EBQ backend reports on EVERY API response — no
 * reconnect, no second refresh required.
 *
 * Wiring:
 *   1. We register a one-time apiFetch middleware (the FIRST mounted
 *      component to call useTier installs it) that inspects every
 *      response for a top-level `tier` field. When it differs from the
 *      currently-known value, we mutate `window.ebqSeoPublic.tier` and
 *      dispatch a `ebq:tier-changed` window event.
 *   2. Each `useTier()` hook subscribes to that event and re-renders
 *      its component with the new value.
 *
 * The middleware is idempotent — installs only once per page load — so
 * mounting useTier in 5 places doesn't run the listener 5 times.
 */
let middlewareInstalled = false;

function installMiddlewareOnce() {
	if (middlewareInstalled) return;
	middlewareInstalled = true;
	apiFetch.use((options, next) => {
		const result = next(options);
		if (!result || typeof result.then !== 'function') return result;
		return result.then((res) => {
			if (
				res &&
				typeof res.tier === 'string' &&
				(res.tier === 'pro' || res.tier === 'free')
			) {
				if (typeof window !== 'undefined') {
					window.ebqSeoPublic = window.ebqSeoPublic || {};
					if (window.ebqSeoPublic.tier !== res.tier) {
						window.ebqSeoPublic.tier = res.tier;
						window.dispatchEvent(new CustomEvent('ebq:tier-changed', { detail: res.tier }));
					}
				}
			}
			return res;
		});
	});
}

export function useTier() {
	installMiddlewareOnce();
	const [tier, setTier] = useState(() => publicConfig().tier);
	useEffect(() => {
		if (typeof window === 'undefined') return undefined;
		const onChange = (e) => setTier(e.detail);
		window.addEventListener('ebq:tier-changed', onChange);
		return () => window.removeEventListener('ebq:tier-changed', onChange);
	}, []);
	return tier;
}
