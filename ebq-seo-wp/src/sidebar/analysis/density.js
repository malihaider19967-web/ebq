/**
 * Keyword density — same shape as the audit's `keyword_density`:
 * one row per term with `term`, `count`, `density` (% of total words).
 *
 * Mirrors HtmlAuditor::keywordDensity() so the editor surface matches what
 * the audit pipeline reports back. Single-term density:
 *   < 0.5%   sparse
 *   0.5–1.5  healthy
 *   1.5–3.0  watch — risk of looking spammy
 *   ≥ 3.0    stuffing — a known penalty signal since Panda
 */
import { htmlToPlain, tokenize } from './text';

/** Same EN stopword set the audit uses, kept in sync with HtmlAuditor.STOPWORDS. */
const STOPWORDS = new Set([
	'a','an','the','and','or','but','if','then','so','of','at','by','for','with','about','to','from','in','on',
	'is','are','was','were','be','been','being','have','has','had','do','does','did','will','would','should',
	'could','can','may','might','this','that','these','those','it','its','as','not','no','yes',
	'i','you','he','she','we','they','them','their','our','your','my','me','us','him','her','his','hers',
	'what','when','where','why','how','which','who','whom','all','any','some','such','than','too','very','just',
	'also','there','here',
]);

/**
 * @param {string} serializedContent
 * @param {string[]} highlight optional list of phrases (focus + additional) to surface
 * @returns {{
 *   wordCount: number,
 *   topTerms: Array<{ term: string, count: number, density: number, isTracked: boolean }>,
 *   trackedTerms: Array<{ term: string, count: number, density: number, found: boolean }>,
 *   stuffingRisk: { term: string, density: number } | null,
 * }}
 */
export function analyzeDensity(serializedContent, highlight = []) {
	const plain = htmlToPlain(serializedContent);
	const allTokens = tokenize(plain);
	const wordCount = allTokens.length;

	if (wordCount === 0) {
		return { wordCount: 0, topTerms: [], trackedTerms: [], stuffingRisk: null };
	}

	const counts = new Map();
	for (const t of allTokens) {
		if (!t || t.length < 3 || STOPWORDS.has(t)) continue;
		counts.set(t, (counts.get(t) || 0) + 1);
	}

	const lowerHighlight = (highlight || [])
		.map((s) => String(s || '').trim())
		.filter(Boolean)
		.map((s) => s.toLowerCase());
	const trackedSet = new Set(
		lowerHighlight.flatMap((p) =>
			p.split(/\s+/).filter((w) => w.length >= 3 && !STOPWORDS.has(w))
		)
	);

	// Top 20 terms by count, density rounded to 2 decimal places.
	const sorted = [...counts.entries()].sort((a, b) => b[1] - a[1]).slice(0, 20);
	const topTerms = sorted.map(([term, count]) => ({
		term,
		count,
		density: Math.round((count * 10000) / wordCount) / 100,
		isTracked: trackedSet.has(term),
	}));

	// Per-phrase tracker rows — even if a phrase is absent we want to show it.
	// Density for multi-word phrases counted by occurrence in plain text.
	const lowerPlain = plain.toLowerCase();
	const trackedTerms = (highlight || [])
		.map((s) => String(s || '').trim())
		.filter(Boolean)
		.map((phrase) => {
			const re = new RegExp(
				'\\b' + phrase.toLowerCase().replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b',
				'g'
			);
			const matches = lowerPlain.match(re);
			const count = matches ? matches.length : 0;
			const phraseWords = Math.max(1, phrase.trim().split(/\s+/).length);
			return {
				term: phrase,
				count,
				density: Math.round((count * phraseWords * 10000) / wordCount) / 100,
				found: count > 0,
			};
		});

	// Stuffing flag — first term over 3% (Panda signal).
	const stuffing = topTerms.find((t) => t.density >= 3) || null;

	return {
		wordCount,
		topTerms,
		trackedTerms,
		stuffingRisk: stuffing ? { term: stuffing.term, density: stuffing.density } : null,
	};
}

export function densityLevel(density) {
	if (density >= 3) return 'bad';        // stuffing
	if (density >= 1.5) return 'warn';     // watch
	if (density >= 0.5) return 'good';     // healthy
	return 'mute';                          // sparse / not used
}
