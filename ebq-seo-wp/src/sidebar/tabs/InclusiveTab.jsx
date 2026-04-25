import { __, sprintf } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';

import { Section, ScoreBadge, EmptyState, Pill, Stat, StatGrid } from '../components/primitives';
import { useEditorContext } from '../hooks/useEditorContext';
import useDebounced from '../hooks/useDebounced';
import { analyzeInclusive } from '../analysis/inclusive';
import { IconSparkle } from '../components/icons';

const SEVERITY_TONE = { high: 'bad', medium: 'warn', low: 'neutral' };
const SEVERITY_LABEL = { high: __('Reconsider', 'ebq-seo'), medium: __('Watch', 'ebq-seo'), low: __('Note', 'ebq-seo') };

function ItemCard({ item }) {
	const [expanded, setExpanded] = useState(false);
	const tone = SEVERITY_TONE[item.severity] || 'neutral';
	return (
		<div className="ebq-inclusive-card">
			<div className="ebq-row ebq-row--between" style={{ alignItems: 'flex-start' }}>
				<div style={{ minWidth: 0, flex: 1 }}>
					<div className="ebq-row" style={{ gap: 8, flexWrap: 'wrap' }}>
						<strong style={{ fontSize: 14, color: 'var(--ebq-text)' }}>"{item.match}"</strong>
						<Pill tone={tone}>{SEVERITY_LABEL[item.severity] || ''}</Pill>
						{item.count > 1 ? (
							<span className="ebq-text-xs ebq-text-soft">×{item.count}</span>
						) : null}
					</div>
					<p className="ebq-text-xs ebq-text-soft" style={{ margin: '4px 0 0' }}>
						{item.categoryLabel}
					</p>
				</div>
			</div>
			<div className="ebq-mt-2">
				<p className="ebq-text-sm" style={{ margin: 0, color: 'var(--ebq-text-muted)' }}>
					{__('Try:', 'ebq-seo')}{' '}
					{item.replacements.slice(0, expanded ? item.replacements.length : 3).map((r, i, arr) => (
						<span key={r}>
							<span className="ebq-inclusive-suggestion">{r}</span>
							{i < arr.length - 1 ? ', ' : ''}
						</span>
					))}
					{item.replacements.length > 3 && !expanded ? (
						<>
							{' '}
							<button
								type="button"
								className="ebq-btn ebq-btn--quiet ebq-btn--sm"
								onClick={() => setExpanded(true)}
							>
								+{item.replacements.length - 3} more
							</button>
						</>
					) : null}
				</p>
			</div>
		</div>
	);
}

export default function InclusiveTab() {
	const ctx = useEditorContext();
	const debounced = useDebounced(ctx.content, 500);

	const result = useMemo(() => analyzeInclusive(debounced), [debounced]);

	if (!debounced || debounced.replace(/\s+/g, ' ').trim().length < 50) {
		return (
			<div className="ebq-stack">
				<EmptyState
					icon={<IconSparkle />}
					title={__('Add some content to start the inclusive language pass', 'ebq-seo')}
					sub={__('We scan for ableist, gendered, racially-coded, or dated language and suggest alternatives.', 'ebq-seo')}
				/>
			</div>
		);
	}

	if (result.totalMatches === 0) {
		return (
			<div className="ebq-stack">
				<ScoreBadge
					score={100}
					label={__('Inclusive language', 'ebq-seo')}
					caption={__('No flagged terms', 'ebq-seo')}
				/>
				<EmptyState
					icon={<IconSparkle />}
					title={__('Reads cleanly', 'ebq-seo')}
					sub={__('No ableist, gendered, racially-coded, or dated terms detected. Nice work.', 'ebq-seo')}
				/>
			</div>
		);
	}

	const counts = Object.values(result.byCategory).map((c) => ({
		label: c.label,
		count: c.items.reduce((acc, x) => acc + x.count, 0),
	}));

	return (
		<div className="ebq-stack">
			<ScoreBadge
				score={result.score}
				label={__('Inclusive language', 'ebq-seo')}
				caption={result.scoreLabel}
			/>

			<Section title={__('Snapshot', 'ebq-seo')} icon={<IconSparkle />}>
				<StatGrid>
					<Stat label={__('Flagged terms', 'ebq-seo')} value={result.items.length} />
					<Stat label={__('Total occurrences', 'ebq-seo')} value={result.totalMatches} />
				</StatGrid>
				<div className="ebq-row ebq-row--wrap" style={{ gap: 6, marginTop: 8 }}>
					{counts.map((c) => (
						<Pill key={c.label}>{c.label} · {c.count}</Pill>
					))}
				</div>
				<p className="ebq-help ebq-mt-2">
					{__('Suggestions are written to surface options, not to enforce. Keep what works for your audience.', 'ebq-seo')}
				</p>
			</Section>

			<Section title={__('Suggestions', 'ebq-seo')}>
				<div className="ebq-stack" style={{ gap: 10 }}>
					{result.items.map((item) => (
						<ItemCard key={item.term} item={item} />
					))}
				</div>
			</Section>
		</div>
	);
}
