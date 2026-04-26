import { __, sprintf } from '@wordpress/i18n';
import { useMemo, Fragment } from '@wordpress/element';

import { IconCheck, IconCross } from './icons';
import { Section, NeedsSetup, Pill } from './primitives';
import { IconSparkle } from './icons';
import { containsPhrase, htmlToPlain, firstParagraph, extractHeadings, extractImages } from '../analysis/text';

/**
 * Topical coverage scores how completely the article covers the set of
 * keyphrases (focus + additional). For each keyphrase we check four
 * surfaces — title, intro paragraph, any heading, image alt text.
 *
 *   covered   ≥ 3 / 4 surfaces hit
 *   partial   1–2 / 4
 *   missing   0 / 4
 *
 * Overall % = average per-keyphrase coverage. Mirrors how Yoast computes
 * its "Topic" / inclusive-coverage signal — fast enough to run on every
 * keystroke after debounce.
 */
export default function TopicalCoverage({ focusKeyword, additional, postTitle, seoTitle, content }) {
	const phrases = useMemo(() => {
		const raw = [focusKeyword, ...additional]
			.map((s) => String(s || '').trim())
			.filter(Boolean);
		// Dedupe case-insensitively
		const seen = new Map();
		for (const p of raw) {
			const k = p.toLowerCase();
			if (!seen.has(k)) seen.set(k, p);
		}
		return [...seen.values()];
	}, [focusKeyword, additional]);

	const ctx = useMemo(() => {
		const intro = firstParagraph(content);
		const headings = extractHeadings(content).map((h) => h.text).join('\n');
		const alts = extractImages(content).map((i) => i.alt || '').join('\n');
		const titleSurface = (seoTitle && seoTitle.trim()) || postTitle || '';
		const plain = htmlToPlain(content);
		return { titleSurface, intro, headings, alts, plain };
	}, [content, postTitle, seoTitle]);

	const rows = useMemo(() => phrases.map((kw) => {
		const inTitle    = containsPhrase(ctx.titleSurface, kw);
		const inIntro    = containsPhrase(ctx.intro, kw);
		const inHeading  = containsPhrase(ctx.headings, kw);
		const inAlt      = containsPhrase(ctx.alts, kw);
		const inBody     = containsPhrase(ctx.plain, kw);
		const hits = [inTitle, inIntro, inHeading, inAlt].filter(Boolean).length;
		const status = hits >= 3 ? 'covered' : hits >= 1 ? 'partial' : 'missing';
		return { keyword: kw, inTitle, inIntro, inHeading, inAlt, inBody, hits, status };
	}), [phrases, ctx]);

	const score = phrases.length === 0
		? 0
		: Math.round((rows.reduce((acc, r) => acc + r.hits, 0) / (rows.length * 4)) * 100);

	const statusCounts = rows.reduce(
		(acc, r) => ({ ...acc, [r.status]: (acc[r.status] || 0) + 1 }),
		{ covered: 0, partial: 0, missing: 0 }
	);

	if (phrases.length === 0) {
		return (
			<Section title={__('Topical coverage', 'ebq-seo')} icon={<IconSparkle />}>
				<NeedsSetup
					feature={__('Topical coverage', 'ebq-seo')}
					why={__('Add additional keyphrases to expand topical coverage. Each one factors into the overall SEO score — title and H1 are reserved for the focus keyphrase, additional ones score on body, subheadings, intro, and image alt.', 'ebq-seo')}
					fix={__('Add at least one additional keyphrase in the section above, then come back here.', 'ebq-seo')}
					tone="info"
				/>
			</Section>
		);
	}

	const overallTone = score >= 75 ? 'good' : score >= 45 ? 'warn' : 'bad';

	return (
		<Section
			title={__('Topical coverage', 'ebq-seo')}
			icon={<IconSparkle />}
			aside={<Pill tone={overallTone}>{score}%</Pill>}
		>
			<div>
				<div className="ebq-coverage__bar" aria-hidden>
					<div className="ebq-coverage__fill" style={{ width: `${score}%` }} />
				</div>
				<p className="ebq-help" style={{ margin: 0 }}>
					{sprintf(
						/* translators: 1=covered count, 2=partial count, 3=missing count, 4=total */
						__('%1$d covered · %2$d partial · %3$d missing across %4$d keyphrase(s).', 'ebq-seo'),
						statusCounts.covered, statusCounts.partial, statusCounts.missing, phrases.length
					)}
				</p>
			</div>

			<div className="ebq-coverage__topics ebq-mt-2">
				{rows.map((r, i) => (
					<span key={r.keyword + i} className={`ebq-coverage__chip ebq-coverage__chip--${r.status}`}>
						{r.status === 'covered' ? <IconCheck /> : r.status === 'missing' ? <IconCross /> : null}
						{r.keyword}
					</span>
				))}
			</div>

			<div className="ebq-coverage__matrix" role="table" aria-label={__('Coverage matrix', 'ebq-seo')}>
				<div className="ebq-coverage__matrix-h ebq-coverage__matrix-h--label" role="columnheader">
					{__('Keyphrase', 'ebq-seo')}
				</div>
				<div className="ebq-coverage__matrix-h" role="columnheader" title={__('Title', 'ebq-seo')}>{__('T', 'ebq-seo')}</div>
				<div className="ebq-coverage__matrix-h" role="columnheader" title={__('Intro paragraph', 'ebq-seo')}>{__('I', 'ebq-seo')}</div>
				<div className="ebq-coverage__matrix-h" role="columnheader" title={__('Heading', 'ebq-seo')}>{__('H', 'ebq-seo')}</div>
				<div className="ebq-coverage__matrix-h" role="columnheader" title={__('Image alt', 'ebq-seo')}>{__('A', 'ebq-seo')}</div>

				{rows.map((r, i) => (
					<Fragment key={r.keyword + i}>
						<div className={`ebq-coverage__matrix-label${i === 0 ? ' ebq-coverage__matrix-label--primary' : ''}`} title={r.keyword}>
							{r.keyword}
						</div>
						{['inTitle', 'inIntro', 'inHeading', 'inAlt'].map((field) => (
							<div
								key={`${i}-${field}`}
								className={`ebq-coverage__matrix-cell ebq-coverage__matrix-cell--${r[field] ? 'y' : 'n'}`}
								role="cell"
								aria-label={r[field] ? __('Yes', 'ebq-seo') : __('No', 'ebq-seo')}
								title={`${r.keyword} → ${field}`}
							>
								{r[field] ? <IconCheck /> : '–'}
							</div>
						))}
					</Fragment>
				))}
			</div>

			<p className="ebq-help" style={{ marginTop: 4 }}>
				{__('T = SEO title · I = first paragraph · H = a heading · A = an image alt.', 'ebq-seo')}
			</p>
		</Section>
	);
}
