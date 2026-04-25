import { __ } from '@wordpress/i18n';
import { IconArrowUp, IconArrowDown } from './icons';

function rankClass(p) {
	if (p == null) return '';
	const n = Number(p);
	if (n <= 10) return 'ebq-rank--top10';
	if (n <= 20) return 'ebq-rank--page1';
	if (n <= 30) return 'ebq-rank--striking';
	return 'ebq-rank--deep';
}

export default function RankBadge({ tracked }) {
	if (!tracked) return null;
	const cur = tracked.current_position;
	const change = Number(tracked.position_change || 0);
	const best = tracked.best_position;
	return (
		<div className="ebq-stack">
			<div className="ebq-row" style={{ alignItems: 'center', gap: 12 }}>
				<span className={`ebq-rank ${rankClass(cur)}`}>
					<span className="ebq-rank__num">{cur ?? '—'}</span>
					{change > 0 ? (
						<span className="ebq-rank__delta ebq-rank__delta--up">
							<IconArrowUp /> +{change}
						</span>
					) : change < 0 ? (
						<span className="ebq-rank__delta ebq-rank__delta--down">
							<IconArrowDown /> {change}
						</span>
					) : (
						<span className="ebq-rank__delta ebq-rank__delta--flat">{__('no change', 'ebq-seo')}</span>
					)}
				</span>
				<span className="ebq-text-xs ebq-text-soft">
					{__('Best:', 'ebq-seo')} #{best ?? '—'}
				</span>
			</div>
			{tracked.target_keyword ? (
				<p className="ebq-text-xs ebq-text-soft" style={{ margin: 0 }}>
					{__('Tracking:', 'ebq-seo')} <strong>{tracked.target_keyword}</strong>
					{tracked.country ? ` · ${tracked.country.toUpperCase()}` : ''}
					{tracked.device ? ` · ${tracked.device}` : ''}
				</p>
			) : null}
		</div>
	);
}
