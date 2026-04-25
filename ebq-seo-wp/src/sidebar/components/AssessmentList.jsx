import { IconCheck, IconCross, IconWarn, IconDot } from './icons';

const ICONS = {
	good: <IconCheck />,
	ok: <IconWarn />,
	bad: <IconCross />,
	mute: <IconDot />,
};

export default function AssessmentList({ items }) {
	if (!items || !items.length) {
		return null;
	}
	return (
		<ul className="ebq-checklist">
			{items.map((a, i) => (
				<li className="ebq-check" key={i}>
					<span className={`ebq-check__icon ebq-check__icon--${a.level === 'ok' ? 'warn' : a.level}`}>
						{ICONS[a.level] || <IconDot />}
					</span>
					<div className="ebq-check__text">
						<span>{a.label}</span>
						{a.hint ? <div className="ebq-check__hint">{a.hint}</div> : null}
					</div>
				</li>
			))}
		</ul>
	);
}
