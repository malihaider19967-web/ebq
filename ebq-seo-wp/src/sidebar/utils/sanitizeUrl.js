/**
 * Strip out URL schemes that could execute code (`javascript:`, `vbscript:`,
 * `data:`) before we drop a user-supplied URL into href / src / background-image.
 *
 * Returns an empty string for anything unsafe so the caller can fall back to a
 * placeholder. Doesn't validate — just rejects the obviously dangerous shapes.
 */
const UNSAFE_SCHEMES = /^\s*(javascript|vbscript|data|file)\s*:/i;

export function safeUrl(url) {
	if (typeof url !== 'string') return '';
	const trimmed = url.trim();
	if (!trimmed) return '';
	if (UNSAFE_SCHEMES.test(trimmed)) return '';
	return trimmed;
}

/**
 * For values that go into a CSS background-image() literal — same as above
 * but also strips line breaks and quotes so the value can't escape the rule.
 */
export function safeCssUrl(url) {
	const safe = safeUrl(url);
	if (!safe) return '';
	if (/[\r\n"']/.test(safe)) return '';
	return safe;
}
