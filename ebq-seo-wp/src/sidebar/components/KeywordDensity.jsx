import { __, sprintf } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';

import { Section, EmptyState, Pill } from './primitives';
import { IconChart, IconWarn, IconCheck, IconCross } from './icons';
import { analyzeDensity, densityLevel } from '../analysis/density';

/**
 * Two views in one section:
 *   - "Tracked phrases": the focus + each additional keyphrase, with their
 *     own occurrence counts and density. Driven by the user's intent.
 *   - "Top terms in body": top-20 single tokens by count (mirrors the
 *     audit's `keyword_density`). Useful for spotting accidental authority
 *     and stuffing risk.
 *
 * Same algorithm as `HtmlAuditor::keywordDensity()` (stopwords, ≥3-char
 * tokens, lower-cased, density = count/total × 100).
 */
export default function KeywordDensity({ content, focusKeyword, additional }) {
	const phrases = useMemo(() => {
		const list = [focusKeyword, ...(additional || [])]
			.map((s) => String(s || '').trim())
			.filter(Boolean);
		const seen = new Set();
		return list.filter((p) => {
			const k = p.toLowerCase();
			if (seen.has(k)) return false;
			seen.add(k);
			return true;
		});
	}, [focusKeyword, additional]);

	const result = useMemo(
		() => analyzeDensity(content, phrases),
		[content, phrases]
	);

	const stuffing = result.stuffingRisk;

	return (
		<Section
			title={__('Keyword density', 'ebq-seo')}
			icon={<IconChart />}
			aside={
				<span className="ebq-text-xs ebq-text-soft">
					{sprintf(
						/* translators: total body words */
						__('%s words', 'ebq-seo'),
						Number(result.wordCount).toLocaleString()
					)}
				</span>
			}
		>
			{result.wordCount === 0 ? (
				<EmptyState
					icon={<IconChart />}
					title={__('No content yet', 'ebq-seo')}
					sub={__('Write some body copy to see how dense your keyphrases are.', 'ebq-seo')}
				/>
			) : (
				<>
					{stuffing ? (
						<div className="ebq-density-warning" role="alert">
							<IconWarn />
							<div>
								<strong>{__('Stuffing risk', 'ebq-seo')}: </strong>
								<code>{stuffing.term}</code> {sprintf(__('at %s%%', 'ebq-seo'), String(stuffing.density))}
								<div className="ebq-text-xs">
									{__('Single-term density above 3% is a known penalty signal. Vary the wording or trim repetitions.', 'ebq-seo')}
								</div>
							</div>
						</div>
					) : null}

					{phrases.length > 0 ? (
						<div>
							<p className="ebq-density__heading">
								{__('Tracked phrases', 'ebq-seo')}
								<span className="ebq-text-faint" style={{ marginLeft: 4 }}>({phrases.length})</span>
							</p>
							<ul className="ebq-density__list">
								{result.trackedTerms.map((row, idx) => {
									const lvl = !row.found ? 'mute' : densityLevel(row.density);
									return (
										<li key={row.term + idx} className="ebq-density__row">
											<span className={`ebq-density__icon ebq-density__icon--${lvl}`} aria-hidden>
												{row.found ? <IconCheck /> : <IconCross />}
											</span>
											<span className="ebq-density__term" title={row.term}>
												{row.term}
												{idx === 0 ? (
													<span className="ebq-density__role">{__('focus', 'ebq-seo')}</span>
												) : null}
											</span>
											<span className="ebq-density__count tabular-nums">
												{row.count > 0 ? sprintf(__('%d×', 'ebq-seo'), row.count) : __('not used', 'ebq-seo')}
											</span>
											<DensityBar density={row.density} />
											<span className={`ebq-density__pct tabular-nums ebq-density__pct--${lvl}`}>
												{row.density}%
											</span>
										</li>
									);
								})}
							</ul>
							<p className="ebq-help" style={{ marginTop: 4 }}>
								{__('Healthy is roughly 0.5–1.5% per keyphrase. Above 3% reads as stuffing.', 'ebq-seo')}
							</p>
						</div>
					) : null}

					<details>
						<summary className="ebq-density__summary">
							<IconChart />
							{__('Top words in your body', 'ebq-seo')}
							<span className="ebq-text-faint">{__('— click to expand', 'ebq-seo')}</span>
						</summary>
						<ul className="ebq-density__list ebq-mt-2">
							{result.topTerms.map((row) => {
								const lvl = densityLevel(row.density);
								return (
									<li key={row.term} className="ebq-density__row ebq-density__row--top">
										<span className="ebq-density__term" title={row.term}>
											{row.term}
											{row.isTracked ? (
												<Pill tone="accent" className="ebq-density__tracked">
													{__('tracked', 'ebq-seo')}
												</Pill>
											) : null}
										</span>
										<span className="ebq-density__count tabular-nums">
											{sprintf(__('%d×', 'ebq-seo'), row.count)}
										</span>
										<DensityBar density={row.density} />
										<span className={`ebq-density__pct tabular-nums ebq-density__pct--${lvl}`}>
											{row.density}%
										</span>
									</li>
								);
							})}
						</ul>
					</details>
				</>
			)}
		</Section>
	);
}

function DensityBar({ density }) {
	// Cap visual at 5% so a 12% spam-burst doesn't blow out the row.
	const pct = Math.min(100, (density / 5) * 100);
	const lvl = densityLevel(density);
	return (
		<div className="ebq-density__bar" aria-hidden>
			<div className={`ebq-density__bar-fill ebq-density__bar-fill--${lvl}`} style={{ width: `${pct}%` }} />
			{/* Sweet-spot guide (0.5%) */}
			<div className="ebq-density__bar-tick" style={{ left: `${(0.5 / 5) * 100}%` }} />
			{/* Watch threshold (1.5%) */}
			<div className="ebq-density__bar-tick" style={{ left: `${(1.5 / 5) * 100}%` }} />
			{/* Stuffing threshold (3%) */}
			<div className="ebq-density__bar-tick ebq-density__bar-tick--bad" style={{ left: `${(3 / 5) * 100}%` }} />
		</div>
	);
}
