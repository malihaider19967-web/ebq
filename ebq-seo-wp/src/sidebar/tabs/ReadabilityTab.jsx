import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';

import { Section, ScoreBadge, EmptyState, Stat, StatGrid } from '../components/primitives';
import { IconBook } from '../components/icons';
import AssessmentList from '../components/AssessmentList';
import { useEditorContext } from '../hooks/useEditorContext';
import useDebounced from '../hooks/useDebounced';
import { analyzeReadability } from '../analysis/readability';

export default function ReadabilityTab() {
	const ctx = useEditorContext();
	const debounced = useDebounced(ctx.content, 400);

	const result = useMemo(
		() => analyzeReadability({ serializedContent: debounced, locale: ctx.lang }),
		[debounced, ctx.lang]
	);

	if (result.assessments.length === 1 && result.assessments[0].level === 'mute') {
		return (
			<div className="ebq-stack">
				<EmptyState
					icon={<IconBook />}
					title={__('Add more content for a readability score', 'ebq-seo')}
					sub={__('At least ~50 words and 3 sentences.', 'ebq-seo')}
				/>
			</div>
		);
	}

	return (
		<div className="ebq-stack">
			<ScoreBadge
				score={result.score}
				label={__('Readability score', 'ebq-seo')}
				caption={result.scoreLabel}
			/>

			<Section title={__('Snapshot', 'ebq-seo')} icon={<IconBook />}>
				<StatGrid>
					<Stat label={__('Words', 'ebq-seo')} value={result.meta.words} />
					<Stat label={__('Sentences', 'ebq-seo')} value={result.meta.sentences} />
					<Stat
						label={__('Avg / sentence', 'ebq-seo')}
						value={result.meta.sentences ? Math.round((result.meta.words / result.meta.sentences) * 10) / 10 : '—'}
					/>
					<Stat
						label={__('Flesch', 'ebq-seo')}
						value={result.meta.flesch ?? '—'}
						sub={
							result.meta.flesch == null
								? null
								: result.meta.flesch >= 60
									? __('Easy', 'ebq-seo')
									: result.meta.flesch >= 50
										? __('Fair', 'ebq-seo')
										: __('Hard', 'ebq-seo')
						}
					/>
				</StatGrid>
			</Section>

			<Section title={__('Recommendations', 'ebq-seo')}>
				<AssessmentList items={result.assessments} />
			</Section>
		</div>
	);
}
