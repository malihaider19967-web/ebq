/**
 * Readability assessments. Same shape as SEO assessments:
 *   { id, level, score, label, hint? }
 */
import { splitSentences, splitWords, countSyllables, htmlToPlain } from './text';

const TRANSITION_WORDS = new Set([
	'accordingly','additionally','afterward','again','also','although','as','because','before','besides',
	'but','consequently','conversely','despite','equally','finally','first','firstly','for','further',
	'furthermore','hence','however','if','in','indeed','instead','later','likewise','meanwhile',
	'moreover','namely','nevertheless','nonetheless','nor','notwithstanding','of','on','otherwise',
	'overall','rather','regardless','second','secondly','similarly','since','so','specifically','still',
	'subsequently','that','then','therefore','though','thus','too','ultimately','unless','until',
	'whereas','while','yet',
]);

const PASSIVE_AUX = /\b(am|is|are|was|were|be|been|being|got|gotten)\b\s+\b\w+(?:ed|en)\b/i;

const LEVEL_SCORE = { good: 9, ok: 6, bad: 3, mute: 0 };

function lev(level, label, hint, score) {
	return { level, label, hint, score: score ?? LEVEL_SCORE[level] };
}

export function analyzeReadability(input) {
	const { serializedContent, locale } = input;
	const plain = htmlToPlain(serializedContent);
	const sentences = splitSentences(plain);
	const words = splitWords(plain);

	if (sentences.length < 3 || words.length < 50) {
		return {
			assessments: [lev('mute', 'Add more content for a readability score', 'Need at least ~50 words and 3 sentences.', 0)],
			score: 0,
			scoreLabel: 'Not analyzed',
			meta: { sentences: sentences.length, words: words.length },
		};
	}

	// English-only signals (Flesch, transition words, passive voice are calibrated for English).
	const isEnglish = !locale || /^en(-|$)/i.test(String(locale)) || /^en$/i.test(String(locale));

	const out = [];

	// ─── Flesch Reading Ease ───────────────────────────────────
	const syllables = words.reduce((acc, w) => acc + countSyllables(w), 0);
	const asl = words.length / sentences.length;
	const asw = syllables / words.length;
	const flesch = isEnglish
		? Math.max(0, Math.min(120, Math.round((206.835 - 1.015 * asl - 84.6 * asw) * 10) / 10))
		: null;

	if (isEnglish && flesch !== null) {
		if (flesch >= 70) out.push(lev('good', `Flesch Reading Ease: ${flesch} — easy to read`));
		else if (flesch >= 60) out.push(lev('good', `Flesch Reading Ease: ${flesch} — fairly easy`));
		else if (flesch >= 50) out.push(lev('ok', `Flesch Reading Ease: ${flesch}`, 'Aim for ≥60 by shortening sentences.'));
		else out.push(lev('bad', `Flesch Reading Ease: ${flesch} — hard to read`, 'Use shorter sentences and simpler words.'));
	}

	// ─── Long sentences (>20 words) ────────────────────────────
	{
		const long = sentences.filter((s) => splitWords(s).length > 20).length;
		const pct = Math.round((long / sentences.length) * 100);
		if (pct <= 25) out.push(lev('good', `${pct}% of sentences are long (>20 words)`));
		else if (pct <= 40) out.push(lev('ok', `${pct}% of sentences are long`, 'Aim for ≤25%.'));
		else out.push(lev('bad', `${pct}% of sentences are long`, 'Break up long sentences.'));
	}

	// ─── Paragraph length ──────────────────────────────────────
	{
		const paragraphs = (serializedContent || '')
			.split(/<p[\s>]/i)
			.map((s) => htmlToPlain(s))
			.filter((s) => s && s.length > 0);
		const longParas = paragraphs.filter((p) => splitWords(p).length > 150).length;
		if (paragraphs.length === 0) {
			out.push(lev('ok', 'No paragraph blocks detected', 'Use the paragraph block instead of long line breaks.'));
		} else if (!longParas) {
			out.push(lev('good', `${paragraphs.length} paragraphs, all under 150 words`));
		} else {
			out.push(lev('ok', `${longParas} paragraph${longParas === 1 ? '' : 's'} over 150 words`, 'Break very long paragraphs.'));
		}
	}

	// ─── Transition words (English) ────────────────────────────
	if (isEnglish) {
		const sentencesWithT = sentences.filter((s) => {
			const ws = splitWords(s.toLowerCase().replace(/[^\p{L}\s]/gu, ''));
			return ws.some((w) => TRANSITION_WORDS.has(w));
		}).length;
		const pct = Math.round((sentencesWithT / sentences.length) * 100);
		if (pct >= 30) out.push(lev('good', `${pct}% of sentences contain transition words`));
		else if (pct >= 20) out.push(lev('ok', `${pct}% transition words`, 'Aim for ≥30% to improve flow.'));
		else out.push(lev('bad', `Only ${pct}% transition words`, 'Add words like "however", "moreover", "therefore".'));
	}

	// ─── Passive voice (English heuristic) ─────────────────────
	if (isEnglish) {
		const passive = sentences.filter((s) => PASSIVE_AUX.test(s)).length;
		const pct = Math.round((passive / sentences.length) * 100);
		if (pct <= 10) out.push(lev('good', `${pct}% passive voice`));
		else if (pct <= 25) out.push(lev('ok', `${pct}% passive voice`, 'Try to keep below 10%.'));
		else out.push(lev('bad', `${pct}% passive voice`, 'Rewrite passive sentences in the active voice.'));
	}

	// ─── Subheading distribution ───────────────────────────────
	{
		const totalWords = words.length;
		const subheadingMatches = ((serializedContent || '').match(/<h[2-3]\b/gi) || []).length;
		if (totalWords < 300) {
			out.push(lev('mute', 'Subheading distribution: needs more content'));
		} else if (subheadingMatches >= Math.ceil(totalWords / 300)) {
			out.push(lev('good', `Subheading distribution looks good (${subheadingMatches} H2/H3)`));
		} else {
			out.push(lev('ok', `Add more subheadings (${subheadingMatches} for ${totalWords} words)`, 'Aim for one H2/H3 every ~300 words.'));
		}
	}

	const real = out.filter((a) => a.level !== 'mute');
	const score = real.length ? Math.round(real.reduce((a, x) => a + (x.score || 0), 0) / (real.length * 9) * 100) : 0;

	return {
		assessments: out,
		score,
		scoreLabel: scoreLabel(score),
		meta: { sentences: sentences.length, words: words.length, flesch },
	};
}

function scoreLabel(s) {
	if (s >= 80) return 'Excellent';
	if (s >= 65) return 'Good';
	if (s >= 45) return 'Needs work';
	if (s > 0)   return 'Poor';
	return 'Not analyzed';
}
