/**
 * Pure tokenization helpers shared by the SEO and readability analyses.
 * Designed to be cheap enough to run on every keystroke after debounce.
 */

/** Strip Gutenberg block comments and HTML, collapse whitespace. */
export function htmlToPlain(serializedContent) {
	if (!serializedContent) {
		return '';
	}
	return String(serializedContent)
		.replace(/<!--[\s\S]*?-->/g, ' ')
		.replace(/<script\b[\s\S]*?<\/script>/gi, ' ')
		.replace(/<style\b[\s\S]*?<\/style>/gi, ' ')
		.replace(/<[^>]+>/g, ' ')
		.replace(/&nbsp;/g, ' ')
		.replace(/&amp;/g, '&')
		.replace(/\s+/g, ' ')
		.trim();
}

export function firstParagraph(serializedContent) {
	if (!serializedContent) {
		return '';
	}
	const html = String(serializedContent).replace(/<!--[\s\S]*?-->/g, ' ');
	const match = html.match(/<p[^>]*>([\s\S]*?)<\/p>/i);
	if (match) {
		return htmlToPlain(match[1]);
	}
	// Fallback: first ~300 chars of body text.
	return htmlToPlain(html).slice(0, 300);
}

export function extractHeadings(serializedContent) {
	if (!serializedContent) {
		return [];
	}
	const headings = [];
	const re = /<h([1-6])[^>]*>([\s\S]*?)<\/h\1>/gi;
	let m;
	while ((m = re.exec(serializedContent)) !== null) {
		headings.push({ level: Number(m[1]), text: htmlToPlain(m[2]) });
	}
	return headings;
}

export function extractLinks(serializedContent, homeUrl) {
	if (!serializedContent) {
		return { internal: 0, external: 0 };
	}
	const home = parseHost(homeUrl);
	let internal = 0;
	let external = 0;
	const re = /<a\s[^>]*href=["']([^"']+)["']/gi;
	let m;
	while ((m = re.exec(serializedContent)) !== null) {
		const href = m[1];
		if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('javascript:')) {
			continue;
		}
		const host = parseHost(href);
		if (!host || (home && host === home)) {
			internal += 1;
		} else {
			external += 1;
		}
	}
	return { internal, external };
}

export function extractImages(serializedContent) {
	if (!serializedContent) {
		return [];
	}
	const out = [];
	const re = /<img\s[^>]*>/gi;
	let m;
	while ((m = re.exec(serializedContent)) !== null) {
		const altMatch = m[0].match(/\balt=["']([^"']*)["']/i);
		out.push({ alt: altMatch ? altMatch[1] : '' });
	}
	return out;
}

export function tokenize(text) {
	if (!text) {
		return [];
	}
	// Split into word-ish tokens (Unicode letters and digits).
	return String(text)
		.toLowerCase()
		.split(/[^\p{L}\p{N}'\-]+/u)
		.filter(Boolean);
}

export function splitSentences(text) {
	if (!text) {
		return [];
	}
	return String(text)
		.replace(/\s+/g, ' ')
		.split(/(?<=[.!?])\s+/)
		.map((s) => s.trim())
		.filter(Boolean);
}

export function splitWords(text) {
	if (!text) {
		return [];
	}
	return String(text)
		.split(/\s+/)
		.filter(Boolean);
}

export function countSyllables(word) {
	if (!word) return 0;
	const w = String(word).toLowerCase().replace(/[^a-z]/g, '');
	if (!w) return 0;
	if (w.length <= 3) return 1;
	const groups = w
		.replace(/(?:[^laeiouy]es|ed|[^laeiouy]e)$/, '')
		.replace(/^y/, '')
		.match(/[aeiouy]{1,2}/g);
	return groups ? groups.length : 1;
}

export function escapeRegExp(s) {
	return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/** Loose phrase-match: case-insensitive, allow inflectional s/es/ies endings. */
export function containsPhrase(haystack, phrase) {
	if (!haystack || !phrase) return false;
	const escaped = escapeRegExp(phrase.trim());
	const re = new RegExp('(^|[^\\p{L}])' + escaped + '(s|es|ies|\'s)?($|[^\\p{L}])', 'iu');
	return re.test(haystack);
}

export function parseHost(urlLike) {
	if (!urlLike) return '';
	try {
		const u = new URL(urlLike, 'https://example.com');
		return u.hostname.replace(/^www\./, '');
	} catch {
		return '';
	}
}
