/**
 * @typedef {Object} SeoAnalysisResult
 * @property {number} wordCount
 * @property {number} densityPercent
 * @property {boolean} inTitle
 * @property {boolean} inMetaDescription
 * @property {boolean} inFirstParagraph
 * @property {boolean} inHeading
 * @property {boolean} inSlug
 * @property {number} internalLinks
 * @property {number} externalLinks
 * @property {boolean} imageAltHasKeyphrase
 * @property {boolean} meetsMinWords
 */

import { parse } from '@wordpress/blocks';

function normalizeKeyphrase(kw) {
	if (!kw || typeof kw !== 'string') {
		return '';
	}
	return kw.trim().toLowerCase().replace(/\s+/g, ' ');
}

function countOccurrences(haystack, needle) {
	if (!needle) {
		return 0;
	}
	const esc = needle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
	const re = new RegExp(`\\b${esc}\\b`, 'gi');
	const m = haystack.match(re);
	return m ? m.length : 0;
}

function stripSerializedToText(serialized) {
	if (!serialized) {
		return '';
	}
	return serialized
		.replace(/<!--[\s\S]*?-->/g, ' ')
		.replace(/<[^>]+>/g, ' ')
		.replace(/\s+/g, ' ')
		.trim();
}

function walkBlocks(blocks, visitor) {
	if (!Array.isArray(blocks)) {
		return;
	}
	for (const block of blocks) {
		visitor(block);
		if (block.innerBlocks && block.innerBlocks.length) {
			walkBlocks(block.innerBlocks, visitor);
		}
	}
}

/**
 * @param {Object} opts
 * @param {string} opts.serializedContent
 * @param {string} opts.postTitle
 * @param {string} opts.seoTitleResolved
 * @param {string} opts.metaDescription
 * @param {string} opts.slug
 * @param {string} opts.focusKeyword
 * @param {string} opts.homeUrl
 * @returns {SeoAnalysisResult}
 */
export function analyzeSeoContent({
	serializedContent = '',
	postTitle = '',
	seoTitleResolved = '',
	metaDescription = '',
	slug = '',
	focusKeyword = '',
	homeUrl = '',
}) {
	const kw = normalizeKeyphrase(focusKeyword);
	const plainBody = stripSerializedToText(serializedContent);
	const words = plainBody ? plainBody.split(/\s+/).filter(Boolean) : [];
	const wordCount = words.length;

	let h2h3Text = '';
	let firstParagraphText = '';
	let imageAlts = [];
	const links = [];

	let blocks = [];
	try {
		blocks = parse(serializedContent) || [];
	} catch {
		blocks = [];
	}

	let firstParaCaptured = false;
	walkBlocks(blocks, (block) => {
		const name = block.blockName || '';
		if (name === 'core/heading') {
			const lvl = block.attributes && block.attributes.level ? Number(block.attributes.level) : 2;
			if (lvl === 2 || lvl === 3) {
				const t = stripSerializedToText(block.innerHTML || '');
				h2h3Text += ` ${t}`;
			}
		}
		if (name === 'core/paragraph' && !firstParaCaptured) {
			firstParagraphText = stripSerializedToText(block.innerHTML || '');
			firstParaCaptured = true;
		}
		if (name === 'core/image') {
			imageAlts.push(String((block.attributes && block.attributes.alt) || '').toLowerCase());
		}
		if (name === 'core/button' && block.innerHTML) {
			const inner = String(block.innerHTML);
			const m = inner.match(/href="([^"]+)"/i);
			if (m) {
				links.push(m[1]);
			}
		}
	});

	const hrefRegex = /href="(https?:[^"]+)"/gi;
	const serial = serializedContent || '';
	let hrefMatch;
	while ((hrefMatch = hrefRegex.exec(serial)) !== null) {
		links.push(hrefMatch[1]);
	}

	let host = '';
	try {
		host = new URL(homeUrl || window.location.origin).hostname.replace(/^www\./, '');
	} catch {
		host = '';
	}

	let internal = 0;
	let external = 0;
	for (const href of links) {
		try {
			const u = new URL(href);
			const h = u.hostname.replace(/^www\./, '');
			if (host && (h === host || h.endsWith('.' + host))) {
				internal++;
			} else {
				external++;
			}
		} catch {
			// skip
		}
	}

	const hayFull = `${plainBody} ${postTitle} ${seoTitleResolved} ${metaDescription} ${h2h3Text}`.toLowerCase();
	const occurrencesInBody = kw ? countOccurrences(plainBody.toLowerCase(), kw) : 0;
	const densityPercent = wordCount > 0 && kw ? Math.round((occurrencesInBody / wordCount) * 10000) / 100 : 0;

	const slugNorm = (slug || '').toLowerCase().replace(/-/g, ' ');
	const kwSlug = kw.replace(/\s+/g, '-');

	const imageAltHasKeyphrase =
		kw && imageAlts.some((alt) => alt.includes(kw) || kw.split(' ').every((w) => w && alt.includes(w)));

	return {
		wordCount,
		densityPercent,
		inTitle: kw ? seoTitleResolved.toLowerCase().includes(kw) || postTitle.toLowerCase().includes(kw) : false,
		inMetaDescription: kw ? metaDescription.toLowerCase().includes(kw) : false,
		inFirstParagraph: kw ? firstParagraphText.toLowerCase().includes(kw) : false,
		inHeading: kw ? h2h3Text.toLowerCase().includes(kw) : false,
		inSlug: kw ? slugNorm.includes(kw) || slugNorm.includes(kw.replace(/\s/g, '-')) || (kwSlug && slug.includes(kwSlug)) : false,
		internalLinks: internal,
		externalLinks: external,
		imageAltHasKeyphrase: kw ? imageAltHasKeyphrase : false,
		meetsMinWords: wordCount >= 300,
	};
}
