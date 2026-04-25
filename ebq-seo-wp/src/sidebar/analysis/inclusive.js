/**
 * Inclusive language analysis.
 *
 * Curated wordlist grouped by category and severity. Match is case-insensitive
 * with word boundaries (so "ward" doesn't match "stewardess"). Returns a list
 * of items the writer might want to reconsider, with one or more suggested
 * alternatives per term.
 *
 * Tone matters: every prompt is framed as "consider", never "wrong". Severity
 * is tuned so only clearly-charged terms surface as `high` (red dot); broader
 * "rethink this" cases are `medium` or `low`.
 *
 * Site owners can extend the list by filtering window.ebqInclusiveTerms before
 * the editor mounts, or by passing extra entries to analyzeInclusive().
 */
import { htmlToPlain, escapeRegExp } from './text';

const CATEGORIES = {
	ability:     'Disability',
	gender:      'Gender',
	race:        'Race & ethnicity',
	age:         'Age',
	'mental-health': 'Mental health',
	colloquial:  'Dated or colloquial',
	other:       'Other',
};

/**
 * One row = { term, replacements:[], category, severity, match? }
 *
 * - `term` is matched case-insensitively with word boundaries.
 * - `match` (optional) overrides the regex; useful for multi-word phrases.
 *
 * Tuned conservatively — only flagging terms the writer almost certainly
 * benefits from re-examining. Avoids partial / homograph matches.
 */
const TERMS = [
	// ─── Ability ───────────────────────────────────────────────
	{ term: 'crippled',  replacements: ['has a disability', 'is disabled'],            category: 'ability', severity: 'high' },
	{ term: 'crippling', replacements: ['severe', 'overwhelming', 'debilitating'],      category: 'ability', severity: 'medium' },
	{ term: 'lame',      replacements: ['weak', 'unconvincing', 'boring'],              category: 'ability', severity: 'high' },
	{ term: 'dumb',      replacements: ['silent', 'unwise', 'foolish'],                 category: 'ability', severity: 'medium' },
	{ term: 'retarded',  replacements: ['delayed', 'unwise', 'misguided'],              category: 'ability', severity: 'high' },
	{ term: 'handicapped', replacements: ['has a disability', 'disabled'],              category: 'ability', severity: 'high' },
	{ term: 'invalid',   replacements: ['has a disability', 'is disabled'],             category: 'ability', severity: 'medium', match: '\\binvalid(s)?\\b(?!\\s+(input|argument|state|json|date|email|url))' },
	{ term: 'deaf to',   replacements: ['unaware of', 'ignoring'],                      category: 'ability', severity: 'medium', match: '\\bdeaf\\s+to\\b' },
	{ term: 'falls on deaf ears', replacements: ['is ignored', 'goes unanswered'],      category: 'ability', severity: 'medium', match: '\\bfalls?\\s+on\\s+deaf\\s+ears\\b' },
	{ term: 'blind to',  replacements: ['unaware of', 'ignoring'],                      category: 'ability', severity: 'medium', match: '\\bblind\\s+to\\b' },
	{ term: 'tone-deaf', replacements: ['out of touch', 'unaware'],                     category: 'ability', severity: 'medium' },
	{ term: 'cripple',   replacements: ['weaken', 'undermine'],                         category: 'ability', severity: 'medium' },

	// ─── Gender ────────────────────────────────────────────────
	{ term: 'guys',      replacements: ['everyone', 'team', 'folks', 'all'],            category: 'gender', severity: 'medium' },
	{ term: 'mankind',   replacements: ['humanity', 'humankind', 'people'],             category: 'gender', severity: 'medium' },
	{ term: 'manpower',  replacements: ['workforce', 'staffing', 'human effort'],       category: 'gender', severity: 'medium' },
	{ term: 'manmade',   replacements: ['artificial', 'synthetic', 'human-made'],       category: 'gender', severity: 'low' },
	{ term: 'man-made',  replacements: ['artificial', 'synthetic', 'human-made'],       category: 'gender', severity: 'low' },
	{ term: 'middleman', replacements: ['intermediary', 'go-between', 'broker'],        category: 'gender', severity: 'medium' },
	{ term: 'chairman',  replacements: ['chair', 'chairperson'],                        category: 'gender', severity: 'medium' },
	{ term: 'spokesman', replacements: ['spokesperson', 'representative'],              category: 'gender', severity: 'medium' },
	{ term: 'salesman',  replacements: ['salesperson', 'sales rep'],                    category: 'gender', severity: 'medium' },
	{ term: 'fireman',   replacements: ['firefighter'],                                 category: 'gender', severity: 'medium' },
	{ term: 'policeman', replacements: ['police officer'],                              category: 'gender', severity: 'medium' },
	{ term: 'mailman',   replacements: ['mail carrier', 'letter carrier'],              category: 'gender', severity: 'medium' },
	{ term: 'stewardess',replacements: ['flight attendant'],                            category: 'gender', severity: 'medium' },
	{ term: 'waitress',  replacements: ['server', 'waitperson'],                        category: 'gender', severity: 'low' },
	{ term: 'actress',   replacements: ['actor'],                                       category: 'gender', severity: 'low' },
	{ term: 'female engineer', replacements: ['engineer'],                              category: 'gender', severity: 'low', match: '\\bfemale\\s+(engineer|developer|programmer|scientist|doctor|lawyer|founder)s?\\b' },
	{ term: 'opposite sex', replacements: ['different gender', 'another gender'],       category: 'gender', severity: 'medium', match: '\\bopposite\\s+sex\\b' },
	{ term: 'preferred pronouns', replacements: ['pronouns'],                           category: 'gender', severity: 'low', match: '\\bpreferred\\s+pronouns?\\b' },

	// ─── Race & ethnicity / coded language ─────────────────────
	{ term: 'blacklist', replacements: ['blocklist', 'denylist', 'banned list'],        category: 'race', severity: 'high' },
	{ term: 'whitelist', replacements: ['allowlist', 'safe list', 'approved list'],     category: 'race', severity: 'high' },
	{ term: 'master',    replacements: ['main', 'primary', 'leader'],                   category: 'race', severity: 'medium', match: '\\bmaster\\b(?!\\s+(of|degree|class|piece|chef))' },
	{ term: 'slave',     replacements: ['secondary', 'replica', 'follower'],            category: 'race', severity: 'high' },
	{ term: 'master/slave', replacements: ['primary/replica', 'main/secondary'],        category: 'race', severity: 'high', match: '\\bmaster\\s*[\\-/]\\s*slave\\b' },
	{ term: 'gypped',    replacements: ['cheated', 'swindled', 'shortchanged'],         category: 'race', severity: 'high' },
	{ term: 'low man on the totem pole', replacements: ['lowest in seniority', 'most junior'], category: 'race', severity: 'medium', match: '\\blow\\s+man\\s+on\\s+the\\s+totem\\s+pole\\b' },
	{ term: 'powwow',    replacements: ['meeting', 'discussion'],                       category: 'race', severity: 'medium' },
	{ term: 'spirit animal', replacements: ['favorite', 'role model'],                  category: 'race', severity: 'medium', match: '\\bspirit\\s+animal\\b' },
	{ term: 'tribal knowledge', replacements: ['undocumented knowledge', 'institutional knowledge'], category: 'race', severity: 'low', match: '\\btribal\\s+knowledge\\b' },
	{ term: 'grandfathered', replacements: ['legacy', 'pre-existing', 'exempted'],      category: 'race', severity: 'medium' },

	// ─── Age ───────────────────────────────────────────────────
	{ term: 'elderly',   replacements: ['older adults', 'older people', 'seniors'],     category: 'age', severity: 'low' },
	{ term: 'old folks', replacements: ['older adults', 'older people'],                category: 'age', severity: 'medium', match: '\\bold\\s+folks\\b' },
	{ term: 'senile',    replacements: ['has dementia', 'showing memory loss'],         category: 'age', severity: 'medium' },
	{ term: 'kids',      replacements: ['children', 'young people'],                    category: 'age', severity: 'low' },

	// ─── Mental health ─────────────────────────────────────────
	{ term: 'crazy',     replacements: ['surprising', 'wild', 'extreme', 'incredible'], category: 'mental-health', severity: 'high' },
	{ term: 'insane',    replacements: ['extreme', 'wild', 'unbelievable'],             category: 'mental-health', severity: 'high' },
	{ term: 'psycho',    replacements: ['cruel', 'extreme'],                            category: 'mental-health', severity: 'high' },
	{ term: 'schizo',    replacements: ['inconsistent', 'erratic'],                     category: 'mental-health', severity: 'high' },
	{ term: 'bipolar',   replacements: ['volatile', 'inconsistent'],                    category: 'mental-health', severity: 'medium', match: '\\bbipolar\\b(?!\\s+(disorder|depression))' },
	{ term: 'OCD',       replacements: ['detail-oriented', 'meticulous'],               category: 'mental-health', severity: 'medium', match: '\\bOCD\\b(?!\\s+(diagnosis|symptoms))' },
	{ term: 'committed suicide', replacements: ['died by suicide'],                     category: 'mental-health', severity: 'medium', match: '\\bcommit(ted|s|ting)?\\s+suicide\\b' },

	// ─── Dated / colloquial / overused ─────────────────────────
	{ term: 'sanity check', replacements: ['quick check', 'sense check', 'sanity test'], category: 'colloquial', severity: 'low', match: '\\bsanity\\s+check\\b' },
	{ term: 'man hours',  replacements: ['person-hours', 'work hours', 'effort hours'], category: 'colloquial', severity: 'medium', match: '\\bman[\\s-]?hours?\\b' },
	{ term: 'man up',     replacements: ['stay strong', 'be brave'],                    category: 'colloquial', severity: 'medium', match: '\\bman\\s+up\\b' },
	{ term: 'no can do',  replacements: ['can\'t do that', 'unable'],                   category: 'colloquial', severity: 'low', match: '\\bno\\s+can\\s+do\\b' },
	{ term: 'long time no see', replacements: ['hasn\'t been a while', 'been a while'], category: 'colloquial', severity: 'low', match: '\\blong\\s+time\\s+no\\s+see\\b' },
	{ term: 'on the warpath', replacements: ['frustrated', 'on a tirade'],              category: 'colloquial', severity: 'medium', match: '\\bon\\s+the\\s+warpath\\b' },

	// ─── Other ─────────────────────────────────────────────────
	{ term: 'illegal alien', replacements: ['undocumented immigrant'],                  category: 'other', severity: 'high', match: '\\billegal\\s+aliens?\\b' },
	{ term: 'illegals',  replacements: ['undocumented immigrants'],                     category: 'other', severity: 'high' },
	{ term: 'minorities', replacements: ['marginalized groups', 'underrepresented groups'], category: 'other', severity: 'low' },
	{ term: 'normal person', replacements: ['typical person', 'average person'],        category: 'other', severity: 'low', match: '\\bnormal\\s+(person|people|guy|user)s?\\b' },
];

const SEVERITY_SCORE = { high: 6, medium: 7, low: 8 }; // worse severity = lower score
const SEVERITY_TO_LEVEL = { high: 'bad', medium: 'ok', low: 'mute' };

/**
 * @typedef {Object} InclusiveItem
 * @property {string} term            The flagged term as written.
 * @property {string} match           The exact substring matched in the text.
 * @property {string} category        Category id from CATEGORIES.
 * @property {string} categoryLabel   Human label.
 * @property {'high'|'medium'|'low'} severity
 * @property {string[]} replacements
 * @property {number} count           Occurrences in the text.
 */

export function analyzeInclusive(serializedContent) {
	const plain = htmlToPlain(serializedContent);
	if (!plain) {
		return { items: [], totalMatches: 0, score: 100, byCategory: {}, scoreLabel: 'Not analyzed' };
	}

	const overrides = (typeof window !== 'undefined' && Array.isArray(window.ebqInclusiveTerms))
		? window.ebqInclusiveTerms
		: [];
	const list = [...TERMS, ...overrides];

	const found = new Map();

	for (const entry of list) {
		const pattern = entry.match
			? entry.match
			: '\\b' + escapeRegExp(entry.term) + '\\b';
		let re;
		try {
			re = new RegExp(pattern, 'gi');
		} catch {
			continue;
		}

		let m;
		let count = 0;
		let firstMatch = '';
		while ((m = re.exec(plain)) !== null) {
			count++;
			if (!firstMatch) firstMatch = m[0];
			if (re.lastIndex === m.index) re.lastIndex++;
		}
		if (count === 0) continue;

		const key = entry.term.toLowerCase();
		if (found.has(key)) {
			const prev = found.get(key);
			prev.count += count;
		} else {
			found.set(key, {
				term: entry.term,
				match: firstMatch,
				category: entry.category,
				categoryLabel: CATEGORIES[entry.category] || 'Other',
				severity: entry.severity,
				replacements: entry.replacements,
				count,
			});
		}
	}

	const items = [...found.values()].sort((a, b) => {
		const orderA = a.severity === 'high' ? 0 : a.severity === 'medium' ? 1 : 2;
		const orderB = b.severity === 'high' ? 0 : b.severity === 'medium' ? 1 : 2;
		if (orderA !== orderB) return orderA - orderB;
		return b.count - a.count;
	});

	const totalMatches = items.reduce((acc, i) => acc + i.count, 0);

	const byCategory = items.reduce((acc, item) => {
		const id = item.category;
		acc[id] = acc[id] || { id, label: item.categoryLabel, items: [] };
		acc[id].items.push(item);
		return acc;
	}, {});

	let score = 100;
	for (const item of items) {
		const penalty = item.severity === 'high' ? 8 : item.severity === 'medium' ? 4 : 1;
		score -= Math.min(penalty * item.count, penalty * 3); // diminishing returns past 3 instances
	}
	score = Math.max(0, score);

	const scoreLabel = score >= 90 ? 'Inclusive' : score >= 70 ? 'Mostly inclusive' : score >= 40 ? 'Needs work' : 'Many concerns';

	return { items, totalMatches, score, byCategory, scoreLabel };
}

/** Helper exposed for tests / reuse. */
export { TERMS, CATEGORIES, SEVERITY_TO_LEVEL };
