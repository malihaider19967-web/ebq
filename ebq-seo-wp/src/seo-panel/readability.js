/**
 * English-focused readability heuristics (Flesch Reading Ease, etc.).
 */

const TRANSITIONS = new Set(
	[
		'however',
		'therefore',
		'furthermore',
		'moreover',
		'consequently',
		'meanwhile',
		'nevertheless',
		'otherwise',
		'additionally',
		'besides',
		'accordingly',
		'thus',
		'hence',
		'although',
		'because',
		'unless',
		'while',
		'whereas',
		'for example',
		'for instance',
		'in conclusion',
		'in summary',
		'as a result',
		'on the other hand',
		'in addition',
		'first',
		'second',
		'finally',
		'next',
		'then',
		'also',
		'still',
		'yet',
		'indeed',
		'instead',
		'likewise',
		'similarly',
		'namely',
		'particularly',
		'especially',
		'clearly',
		'obviously',
		'generally',
		'specifically',
	].map((s) => s.toLowerCase())
);

function countSyllables(word) {
	const w = word.toLowerCase().replace(/[^a-z]/g, '');
	if (w.length <= 3) {
		return 1;
	}
	const groups = w.match(/[aeiouy]+/g);
	return groups ? groups.length : 1;
}

function splitSentences(text) {
	if (!text || !text.trim()) {
		return [];
	}
	const rough = text.replace(/\s+/g, ' ').trim();
	const parts = rough.split(/(?<=[.!?])\s+/);
	return parts.map((s) => s.trim()).filter(Boolean);
}

/**
 * Rough passive-voice detector (advisory).
 */
function passiveRatio(sentences) {
	if (!sentences.length) {
		return 0;
	}
	const passiveRe =
		/\b(am|is|are|was|were|been|being)\s+(\w+ed|built|made|given|taken|shown|found|done|sent|known|called|used|seen|based|set|put|told|held|led|left|met|paid|said|sold|told)\b/i;
	let hits = 0;
	for (const s of sentences) {
		if (passiveRe.test(s)) {
			hits++;
		}
	}
	return Math.round((hits / sentences.length) * 1000) / 10;
}

function transitionRatio(sentences) {
	if (!sentences.length) {
		return 0;
	}
	let withTr = 0;
	for (const s of sentences) {
		const lower = s.toLowerCase();
		for (const t of TRANSITIONS) {
			if (lower.includes(t)) {
				withTr++;
				break;
			}
		}
	}
	return Math.round((withTr / sentences.length) * 1000) / 10;
}

function longSentenceRatio(sentences, maxWords = 20) {
	if (!sentences.length) {
		return 0;
	}
	let long = 0;
	for (const s of sentences) {
		const wc = s.split(/\s+/).filter(Boolean).length;
		if (wc > maxWords) {
			long++;
		}
	}
	return Math.round((long / sentences.length) * 1000) / 10;
}

/**
 * @param {string} plainText
 * @param {string} locale
 */
export function analyzeReadability(plainText, locale = '') {
	const lang = (locale || '').toLowerCase();
	const isEnglish = !lang || lang.startsWith('en');

	const sentences = splitSentences(plainText);
	const words = plainText.split(/\s+/).filter(Boolean);
	const nS = sentences.length;
	const nW = words.length;

	if (!isEnglish || nS < 2 || nW < 10) {
		return {
			available: false,
			reason: isEnglish ? 'too_short' : 'not_english',
		};
	}

	let syllables = 0;
	for (const w of words) {
		syllables += countSyllables(w);
	}

	const score = Math.max(
		0,
		Math.min(100, Math.round(206.835 - 1.015 * (nW / nS) - 84.6 * (syllables / nW)))
	);

	return {
		available: true,
		flesch: score,
		longSentencePercent: longSentenceRatio(sentences, 20),
		passiveVoicePercent: passiveRatio(sentences),
		transitionPercent: transitionRatio(sentences),
		sentenceCount: nS,
	};
}
