/**
 * SEO assessments. Each assessment returns:
 *   { id, level: 'good' | 'ok' | 'bad' | 'mute', score, label, hint? }
 * Aggregate score is computed in `aggregate()`.
 */
import {
	htmlToPlain,
	firstParagraph,
	extractHeadings,
	extractLinks,
	extractImages,
	containsPhrase,
	tokenize,
	parseHost,
} from './text';

const LEVEL_SCORE = { good: 9, ok: 6, bad: 3, mute: 0 };

function lev(level, score, label, hint) {
	return { level, score: score ?? LEVEL_SCORE[level], label, hint };
}

export function analyzeSeo(input) {
	const {
		serializedContent,
		postTitle,
		seoTitleResolved,
		metaDescription,
		slug,
		focusKeyword,
		additionalKeywords = [],
		homeUrl,
	} = input;

	const focus = (focusKeyword || '').trim();
	const additional = (Array.isArray(additionalKeywords) ? additionalKeywords : [])
		.map((s) => String(s || '').trim())
		.filter(Boolean);
	const plain = htmlToPlain(serializedContent);
	const intro = firstParagraph(serializedContent);
	const headings = extractHeadings(serializedContent);
	const links = extractLinks(serializedContent, homeUrl);
	const images = extractImages(serializedContent);
	const words = tokenize(plain);
	const wordCount = words.length;
	const titleForCheck = (seoTitleResolved || postTitle || '').trim();

	const out = [];

	// ─── Focus keyphrase guard ─────────────────────────────────
	if (!focus) {
		out.push(lev('bad', 0, 'Set a focus keyphrase', 'Pick the main phrase you want this page to rank for.'));
		return {
			assessments: out,
			score: 0,
			scoreLabel: 'Needs work',
			meta: { wordCount, links, images: images.length, density: 0 },
		};
	}

	const focusLower = focus.toLowerCase();

	// ─── Keyphrase in SEO title ────────────────────────────────
	{
		const inTitle = containsPhrase(titleForCheck, focus);
		const earlyTitle = titleForCheck.toLowerCase().indexOf(focusLower) >= 0
			&& titleForCheck.toLowerCase().indexOf(focusLower) <= titleForCheck.length / 2;
		out.push(
			inTitle
				? earlyTitle
					? lev('good', 9, 'Keyphrase in SEO title (good — appears early)')
					: lev('ok', 6, 'Keyphrase in SEO title', 'Move it closer to the start for stronger weight.')
				: lev('bad', 3, 'Keyphrase missing from SEO title', 'Add the focus keyphrase to your SEO title.')
		);
	}

	// ─── Keyphrase in meta description ─────────────────────────
	{
		const inDesc = containsPhrase(metaDescription || '', focus);
		const len = (metaDescription || '').length;
		if (!metaDescription) {
			out.push(lev('bad', 3, 'Write a meta description', 'Add a 130–155 character summary that uses the focus keyphrase.'));
		} else if (inDesc) {
			out.push(lev('good', 9, 'Keyphrase in meta description'));
		} else {
			out.push(lev('bad', 4, 'Keyphrase missing from meta description'));
		}
		// Length feedback as a separate row.
		if (metaDescription) {
			if (len < 70) {
				out.push(lev('bad', 4, `Meta description too short (${len} chars)`, 'Aim for 130–155 characters.'));
			} else if (len > 170) {
				out.push(lev('bad', 4, `Meta description too long (${len} chars)`, 'Trim to under 155 characters.'));
			} else if (len < 130 || len > 155) {
				out.push(lev('ok', 6, `Meta description length: ${len}`, 'Sweet spot is 130–155 characters.'));
			} else {
				out.push(lev('good', 9, `Meta description length: ${len} chars`));
			}
		}
	}

	// ─── Keyphrase in introduction (first paragraph) ───────────
	{
		const inIntro = containsPhrase(intro, focus);
		out.push(
			inIntro
				? lev('good', 9, 'Keyphrase in introduction')
				: lev('bad', 4, 'Keyphrase missing from the first paragraph', 'Mention it within the opening 100 words.')
		);
	}

	// ─── Keyphrase in subheadings (H2/H3) ──────────────────────
	{
		const subs = headings.filter((h) => h.level === 2 || h.level === 3);
		if (!subs.length) {
			out.push(lev('ok', 5, 'No H2/H3 subheadings yet', 'Break long content into sections with H2s.'));
		} else {
			const inSub = subs.some((h) => containsPhrase(h.text, focus));
			out.push(
				inSub
					? lev('good', 9, 'Keyphrase appears in a subheading')
					: lev('ok', 5, 'Keyphrase missing from H2/H3', 'Use it (or a close variant) in at least one subheading.')
			);
		}
	}

	// ─── Keyphrase in slug ─────────────────────────────────────
	{
		const slugLower = (slug || '').toLowerCase();
		const inSlug = focusLower.split(/\s+/).every((tok) => slugLower.includes(tok));
		out.push(
			inSlug
				? lev('good', 9, 'Keyphrase in URL slug')
				: lev('ok', 5, 'Keyphrase missing from slug', 'Use the keyphrase (lowercase, hyphenated) in the URL.')
		);
	}

	// ─── Keyphrase density ─────────────────────────────────────
	{
		const occurrences = (plain.toLowerCase().match(new RegExp(focusLower.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g')) || []).length;
		const densityPct = wordCount ? (occurrences / wordCount) * 100 : 0;
		const densityRounded = Math.round(densityPct * 100) / 100;
		if (wordCount < 100) {
			// Density meaningless on short drafts.
			out.push(lev('mute', 0, `Keyphrase density: ${densityRounded}%`, 'Add more content to evaluate density.'));
		} else if (densityPct < 0.5) {
			out.push(lev('ok', 5, `Low keyphrase density: ${densityRounded}%`, 'Aim for 0.5%–2.5% across the article.'));
		} else if (densityPct > 3) {
			out.push(lev('bad', 4, `Keyphrase density too high: ${densityRounded}%`, 'Soften repetition; risks looking spammy.'));
		} else {
			out.push(lev('good', 9, `Keyphrase density: ${densityRounded}%`));
		}
		input._density = densityRounded;
	}

	// ─── Internal & external links ─────────────────────────────
	{
		out.push(
			links.internal >= 1
				? lev('good', 9, `Internal links (${links.internal})`)
				: lev('ok', 5, 'No internal links', 'Add at least one link to a related post on this site.')
		);
		out.push(
			links.external >= 1
				? lev('good', 9, `Outbound links (${links.external})`)
				: lev('ok', 5, 'No outbound links', 'Cite at least one authoritative external source.')
		);
	}

	// ─── Images & alt text ─────────────────────────────────────
	{
		if (!images.length) {
			out.push(lev('ok', 5, 'No images yet', 'Add at least one relevant image with alt text.'));
		} else {
			const missingAlt = images.filter((i) => !i.alt || !i.alt.trim()).length;
			const altWithKeyphrase = images.some((i) => containsPhrase(i.alt, focus));
			if (missingAlt) {
				out.push(lev('bad', 4, `${missingAlt} of ${images.length} images missing alt text`));
			} else if (altWithKeyphrase) {
				out.push(lev('good', 9, `All ${images.length} images have alt text (keyphrase present)`));
			} else {
				out.push(lev('ok', 6, `All ${images.length} images have alt text`, 'Add the keyphrase to one image alt.'));
			}
		}
	}

	// ─── Word count ────────────────────────────────────────────
	{
		if (wordCount < 300) {
			out.push(lev('bad', 4, `Text length: ${wordCount} words`, 'Aim for at least 300 words.'));
		} else if (wordCount < 600) {
			out.push(lev('ok', 6, `Text length: ${wordCount} words`, 'Longer pieces (700+) tend to rank better.'));
		} else {
			out.push(lev('good', 9, `Text length: ${wordCount} words`));
		}
	}

	// ─── SEO title length ──────────────────────────────────────
	{
		const tLen = titleForCheck.length;
		if (!tLen) {
			out.push(lev('bad', 3, 'No SEO title set', 'Compose a title that uses the focus keyphrase.'));
		} else if (tLen < 30) {
			out.push(lev('ok', 6, `SEO title is short (${tLen})`, 'Use 50–60 characters for full snippet width.'));
		} else if (tLen > 70) {
			out.push(lev('bad', 4, `SEO title is too long (${tLen})`, 'Trim to under 60 chars to avoid truncation.'));
		} else if (tLen > 60) {
			out.push(lev('ok', 6, `SEO title length: ${tLen}`, 'Trim to ≤60 chars for full SERP width.'));
		} else {
			out.push(lev('good', 9, `SEO title length: ${tLen}`));
		}
	}

	// ─── Topical coverage (additional keyphrases) ─────────────
	// Important: the title, H1, slug, and meta description are scored
	// against the FOCUS keyphrase only. Additional keyphrases share the
	// body, subheadings, intro paragraphs, and image alts — so we judge
	// them on those surfaces, not on title/H1.
	let coveragePct = null;
	let coverageRows = [];
	if (additional.length) {
		const altsText = images.map((i) => i.alt || '').filter(Boolean).join('\n');

		coverageRows = additional.map((kw) => {
			const inBody = containsPhrase(plain, kw);
			const inSub = headings.some(
				(h) => (h.level === 2 || h.level === 3) && containsPhrase(h.text, kw)
			);
			const inIntro = containsPhrase(intro, kw);
			const inAlt = altsText !== '' && containsPhrase(altsText, kw);
			// Body presence is the gate. Bonuses are intro / subheading / alt.
			// Body alone → 5 (mediocre). +1 bonus → 7. +2 bonuses → 9.
			const bonuses = [inSub, inIntro, inAlt].filter(Boolean).length;
			const score = !inBody ? 0 : bonuses === 0 ? 5 : bonuses === 1 ? 7 : 9;
			return { kw, inBody, inSub, inIntro, inAlt, score };
		});

		// First, call out anything not mentioned at all — that's a hard miss.
		const missing = coverageRows.filter((r) => !r.inBody).map((r) => r.kw);
		if (missing.length) {
			const sample = missing.slice(0, 3).map((k) => `"${k}"`).join(', ');
			const more = missing.length > 3 ? `, +${missing.length - 3} more` : '';
			out.push(
				lev(
					'bad',
					3,
					`${missing.length} additional keyphrase${missing.length === 1 ? '' : 's'} not in the content`,
					`Mention ${sample}${more} somewhere in the body. Title and H1 are reserved for the focus keyphrase.`
				)
			);
		}

		// Then the unified topical-coverage score.
		const avg = coverageRows.reduce((acc, r) => acc + r.score, 0) / coverageRows.length;
		coveragePct = Math.round((avg / 9) * 100);
		const level = avg >= 8 ? 'good' : avg >= 5 ? 'ok' : 'bad';
		out.push(
			lev(
				level,
				Math.round(avg),
				`Topical coverage: ${coveragePct}% across ${additional.length} additional keyphrase${additional.length === 1 ? '' : 's'}`,
				level === 'good'
					? undefined
					: 'Aim for each additional keyphrase to appear in the body, ideally in a subheading, intro, or image alt.'
			)
		);
	}

	const score = aggregate(out);
	const scoreLabel = labelForScore(score);

	return {
		assessments: out,
		score,
		scoreLabel,
		meta: {
			wordCount,
			links,
			images: images.length,
			density: input._density ?? 0,
			coveragePct,
			coverageRows,
		},
	};
}

export function aggregate(assessments) {
	const real = assessments.filter((a) => a.level !== 'mute');
	if (!real.length) return 0;
	const max = real.length * 9;
	const sum = real.reduce((acc, a) => acc + (a.score || 0), 0);
	return Math.round((sum / max) * 100);
}

export function labelForScore(score) {
	if (score >= 80) return 'Excellent';
	if (score >= 65) return 'Good';
	if (score >= 45) return 'Needs work';
	if (score > 0)   return 'Poor';
	return 'Not analyzed';
}

export function levelForScore(score) {
	if (score >= 65) return 'good';
	if (score >= 45) return 'warn';
	return 'bad';
}
