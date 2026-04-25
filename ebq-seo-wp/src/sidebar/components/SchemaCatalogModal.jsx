import { __ } from '@wordpress/i18n';
import Modal from './Modal';
import { templatesByGroup } from '../schema/templates';

/**
 * Catalogue picker — grouped grid of every template the user can add.
 * Selecting one calls onPick(template) so the parent can hand off to the
 * form. Mirrors the RankMath "schema catalog" UX without their licensing
 * headaches.
 */
export default function SchemaCatalogModal({ open, onClose, onPick }) {
	const groups = templatesByGroup();

	return (
		<Modal open={open} onClose={onClose} title={__('Add schema', 'ebq-seo')} size="lg">
			<p className="ebq-help" style={{ marginTop: 0 }}>
				{__('Pick a schema template to add to this post. You can add as many as you like — each one becomes its own JSON-LD node.', 'ebq-seo')}
			</p>

			{Object.entries(groups).map(([group, list]) => (
				<div key={group} className="ebq-schema-cat-group">
					<h4 className="ebq-schema-cat-group__title">{group}</h4>
					<div className="ebq-schema-cat-grid">
						{list.map((tpl) => (
							<button
								key={tpl.id}
								type="button"
								className="ebq-schema-cat-card"
								onClick={() => onPick(tpl)}
							>
								<span className="ebq-schema-cat-card__type">{tpl.type}</span>
								<span className="ebq-schema-cat-card__label">{tpl.label}</span>
								{tpl.description ? (
									<span className="ebq-schema-cat-card__desc">{tpl.description}</span>
								) : null}
							</button>
						))}
					</div>
				</div>
			))}
		</Modal>
	);
}
