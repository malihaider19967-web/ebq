/**
 * Client-side mirror of EBQ_Schema_Variables — resolves %var% tokens inside
 * a stored data tree using the current editor context. Used for the live
 * JSON-LD preview in the SchemaForm. The PHP path is still the source of
 * truth at render time; this is a "what your data will look like" preview,
 * not a byte-for-byte renderer.
 */

const STATIC_TOKENS = ['%title%', '%excerpt%', '%url%', '%featured_image%', '%author%', '%date%', '%modified%', '%sitename%'];

export function resolveVariables(value, ctx) {
	if (Array.isArray(value)) {
		return value.map((v) => resolveVariables(v, ctx));
	}
	if (value && typeof value === 'object') {
		const out = {};
		for (const k of Object.keys(value)) {
			out[k] = resolveVariables(value[k], ctx);
		}
		return out;
	}
	if (typeof value !== 'string' || value.indexOf('%') === -1) {
		return value;
	}

	let out = value;
	out = out.replace(/%post_meta\(([A-Za-z0-9_\-]+)\)%/g, (_m, key) => String(ctx.meta?.[key] || ''));

	const map = {
		'%title%':          ctx.postTitle || '',
		'%excerpt%':        ctx.excerpt || '',
		'%url%':            ctx.postLink || '',
		'%featured_image%': ctx.featuredImageUrl || '',
		'%author%':         ctx.authorName || '',
		'%date%':           ctx.publishDate || '',
		'%modified%':       ctx.modifiedDate || '',
		'%sitename%':       ctx.siteName || '',
	};

	for (const tok of STATIC_TOKENS) {
		if (out.indexOf(tok) !== -1) {
			out = out.split(tok).join(map[tok]);
		}
	}
	return out;
}

/**
 * Build a preview JSON-LD object from a stored entry. Approximate — does not
 * apply per-template wrapping (Offer / PostalAddress / etc.) the PHP renderer
 * does. Good enough to confirm the user's input + variable resolution.
 */
export function buildPreview(entry, ctx) {
	const data = resolveVariables(entry.data || {}, ctx);
	// Strip falsy values so the preview doesn't show empty fields.
	const stripped = {};
	for (const k of Object.keys(data)) {
		const v = data[k];
		if (v === '' || v === null || v === undefined) continue;
		if (Array.isArray(v) && v.length === 0) continue;
		stripped[k] = v;
	}
	return {
		'@context': 'https://schema.org',
		'@type': entry.type || 'Thing',
		...stripped,
	};
}
