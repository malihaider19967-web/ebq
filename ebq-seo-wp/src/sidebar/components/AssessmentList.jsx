import { IconCheck, IconCross, IconWarn, IconDot } from './icons';
import { CheckCard } from './primitives';

const ICONS = {
	good: <IconCheck />,
	ok: <IconWarn />,
	bad: <IconCross />,
	mute: <IconDot />,
};

/**
 * Offline self-check rows. Renders each assessment as a `CheckCard` with
 * `kind="offline"` so it shares layout with the live-score factor cards
 * but gets a distinct neutral tint to signal the source.
 */
export default function AssessmentList({ items }) {
	if (!items || !items.length) {
		return null;
	}
	return (
		<div className="ebq-check-list">
			{items.map((a, i) => (
				<CheckCard
					key={i}
					kind="offline"
					level={a.level}
					label={a.label}
					detail={null}
					recommendation={a.hint || null}
					icon={ICONS[a.level] || <IconDot />}
				/>
			))}
		</div>
	);
}
