import { useState, useMemo, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Section, Button, Toggle, EmptyState } from '../components/primitives';
import SchemaCatalogModal from '../components/SchemaCatalogModal';
import SchemaForm from '../components/SchemaForm';
import { usePostMeta } from '../hooks/useEditorContext';
import { getTemplate, initialDataForTemplate } from '../schema/templates';

/**
 * Schema generator tab — list of schemas the user has added to this post,
 * with add / edit / remove / disable. Persists into `_ebq_schemas` as a JSON
 * string so the same field round-trips through both editors.
 */
export default function SchemaTab() {
	const { get, set } = usePostMeta();

	const raw = get('_ebq_schemas', '');
	const schemas = useMemo(() => parseSchemas(raw), [raw]);

	const [catalogOpen, setCatalogOpen] = useState(false);
	const [editing, setEditing] = useState(null); // { mode: 'add'|'edit', template, entry }

	const writeSchemas = useCallback(
		(next) => {
			const safe = Array.isArray(next) ? next : [];
			set('_ebq_schemas', safe.length ? JSON.stringify(safe) : '');
		},
		[set]
	);

	const addSchema = (template) => {
		setCatalogOpen(false);
		setEditing({
			mode: 'add',
			template,
			entry: {
				id: makeId(),
				template: template.id,
				type: (template.subtypes && template.subtypes[0]) || template.type,
				enabled: true,
				data: initialDataForTemplate(template),
			},
		});
	};

	const editSchema = (entry) => {
		const template = getTemplate(entry.template);
		if (!template) return;
		setEditing({ mode: 'edit', template, entry });
	};

	const onFormSave = ({ type, data }) => {
		if (!editing) return;
		const next = schemas.slice();
		const updated = { ...editing.entry, type: type || editing.template.type, data };
		const idx = next.findIndex((s) => s.id === updated.id);
		if (idx === -1) {
			next.push(updated);
		} else {
			next[idx] = updated;
		}
		writeSchemas(next);
		setEditing(null);
	};

	const removeSchema = (id) => {
		writeSchemas(schemas.filter((s) => s.id !== id));
	};

	const toggleSchema = (id, enabled) => {
		writeSchemas(schemas.map((s) => (s.id === id ? { ...s, enabled } : s)));
	};

	return (
		<>
			<Section
				title={__('Schemas on this post', 'ebq-seo')}
				aside={<Button size="sm" variant="primary" onClick={() => setCatalogOpen(true)}>{__('Add schema', 'ebq-seo')}</Button>}
			>
				{schemas.length === 0 ? (
					<EmptyState
						title={__('No schemas added yet', 'ebq-seo')}
						sub={__('Pick a template from the catalogue — Article, Product, Event, FAQ, Recipe, Local Business — and fill in the fields. Each one becomes its own JSON-LD node in the page head.', 'ebq-seo')}
					>
						<Button variant="primary" onClick={() => setCatalogOpen(true)}>
							{__('Open catalogue', 'ebq-seo')}
						</Button>
					</EmptyState>
				) : (
					<div className="ebq-schema-list">
						{schemas.map((entry) => {
							const template = getTemplate(entry.template);
							const fieldCount = Object.keys(entry.data || {}).filter((k) => entry.data[k] && (!Array.isArray(entry.data[k]) || entry.data[k].length)).length;
							return (
								<div key={entry.id} className={`ebq-schema-row${entry.enabled === false ? ' is-disabled' : ''}`}>
									<div className="ebq-schema-row__main">
										<div className="ebq-schema-row__head">
											<span className="ebq-schema-row__type">{entry.type || template?.type || '—'}</span>
											<span className="ebq-schema-row__label">{template?.label || entry.template}</span>
										</div>
										<p className="ebq-schema-row__sub">
											{fieldCount > 0
												? __(`${fieldCount} field(s) configured`, 'ebq-seo')
												: __('No fields filled', 'ebq-seo')}
										</p>
									</div>
									<div className="ebq-schema-row__actions">
										<Toggle
											label=""
											checked={entry.enabled !== false}
											onChange={(v) => toggleSchema(entry.id, v)}
										/>
										<Button size="sm" onClick={() => editSchema(entry)}>{__('Edit', 'ebq-seo')}</Button>
										<button
											type="button"
											className="ebq-schema-row__remove"
											onClick={() => removeSchema(entry.id)}
											aria-label={__('Remove schema', 'ebq-seo')}
										>×</button>
									</div>
								</div>
							);
						})}
					</div>
				)}
			</Section>

			<Section title={__('How schemas work', 'ebq-seo')} collapsible defaultOpen={false}>
				<div className="ebq-schema-help">
					<p>
						{__('Each schema you add becomes its own JSON-LD node in the page', 'ebq-seo')} <code>&lt;head&gt;</code>.
						{' '}{__('Search engines read these to power rich results — recipe cards, review stars, event listings, FAQ accordions, and more.', 'ebq-seo')}
					</p>
					<p>
						<strong>{__('Variables', 'ebq-seo')}:</strong>{' '}
						{__('any text field accepts', 'ebq-seo')} <code>%title%</code>, <code>%excerpt%</code>, <code>%url%</code>, <code>%featured_image%</code>, <code>%author%</code>, <code>%date%</code>, <code>%modified%</code>, <code>%sitename%</code>, {__('and', 'ebq-seo')} <code>%post_meta(key)%</code>.
						{' '}{__('They resolve at render time so the values stay current as the post evolves.', 'ebq-seo')}
					</p>
					<p>
						<strong>{__('Visible cards', 'ebq-seo')}:</strong>{' '}
						{__('drop the shortcode into post content to render a styled card from any schema.', 'ebq-seo')}
					</p>
					<ul className="ebq-schema-help__shortcodes">
						<li><code>[ebq_schema]</code> — {__('auto-picks Recipe → Review → first enabled', 'ebq-seo')}</li>
						<li><code>[ebq_schema type="Recipe"]</code> — {__('first matching type or template', 'ebq-seo')}</li>
						<li><code>[ebq_schema id="..."]</code> — {__('specific entry by id', 'ebq-seo')}</li>
					</ul>
					<p className="ebq-help" style={{ marginBottom: 0 }}>
						{__('Recipe and Review render as polished visual cards. Other types render as a plain definition list — useful for debugging.', 'ebq-seo')}
					</p>
				</div>
			</Section>

			<SchemaCatalogModal
				open={catalogOpen}
				onClose={() => setCatalogOpen(false)}
				onPick={addSchema}
			/>

			<SchemaForm
				open={!!editing}
				template={editing?.template || null}
				entry={editing?.entry || null}
				onSave={onFormSave}
				onClose={() => setEditing(null)}
			/>
		</>
	);
}

function parseSchemas(raw) {
	if (!raw) return [];
	try {
		const parsed = JSON.parse(raw);
		return Array.isArray(parsed) ? parsed.filter((s) => s && typeof s === 'object') : [];
	} catch {
		return [];
	}
}

function makeId() {
	if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
		return crypto.randomUUID();
	}
	return `s_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`;
}
