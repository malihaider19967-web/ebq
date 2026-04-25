import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TextField, TextArea, Button } from './primitives';
import Modal from './Modal';
import { VARIABLES } from '../schema/templates';
import { buildPreview } from '../schema/variables';
import { useEditorContext, publicConfig } from '../hooks/useEditorContext';

/**
 * Edit-form for a single stored schema entry. Renders the template's field
 * defs into the appropriate controls, supports repeaters, and writes the
 * whole row back via onSave on confirm. Cancel discards local state without
 * touching post-meta.
 */
export default function SchemaForm({ open, template, entry, onSave, onClose }) {
	const [type, setType] = useState(entry?.type || template?.type || '');
	const [data, setData] = useState(() => ({ ...(entry?.data || {}) }));
	const [showPreview, setShowPreview] = useState(false);
	const ctx = useEditorContext();
	const cfg = publicConfig();

	useEffect(() => {
		if (open) {
			setType(entry?.type || template?.type || '');
			setData({ ...(entry?.data || {}) });
			setShowPreview(false);
		}
	}, [open, entry, template]);

	const previewCtx = useMemo(() => ({
		postTitle: ctx.postTitle || '',
		excerpt: ctx.excerpt || '',
		postLink: ctx.postLink || '',
		featuredImageUrl: ctx.featuredImageUrl || '',
		authorName: ctx.authorName || '',
		publishDate: ctx.publishDate || '',
		modifiedDate: ctx.modifiedDate || '',
		siteName: cfg.siteName || '',
		meta: ctx.meta || {},
	}), [ctx, cfg.siteName]);

	const previewJson = useMemo(() => {
		if (!showPreview) return '';
		try {
			return JSON.stringify(buildPreview({ type: type || template?.type || 'Thing', data }, previewCtx), null, 2);
		} catch (e) {
			return String(e);
		}
	}, [showPreview, type, data, template, previewCtx]);

	if (!open || !template) return null;

	const setField = (key, value) => setData((prev) => ({ ...prev, [key]: value }));
	const subtypes = template.subtypes || null;

	const footer = (
		<div className="ebq-modal__foot-actions">
			<Button onClick={() => setShowPreview((v) => !v)}>
				{showPreview ? __('Hide preview', 'ebq-seo') : __('Preview JSON-LD', 'ebq-seo')}
			</Button>
			<Button variant="ghost" onClick={onClose}>{__('Cancel', 'ebq-seo')}</Button>
			<Button variant="primary" onClick={() => onSave({ type, data })}>{__('Save schema', 'ebq-seo')}</Button>
		</div>
	);

	return (
		<Modal
			open={open}
			onClose={onClose}
			title={entry?.id ? __('Edit schema', 'ebq-seo') : __('Configure schema', 'ebq-seo')}
			size="md"
			footer={footer}
		>
			<div className="ebq-schema-form">
				{template.description ? (
					<p className="ebq-help" style={{ marginTop: 0 }}>{template.description}</p>
				) : null}

				{template.typeFreeform ? (
					<TextField
						label={__('Schema @type', 'ebq-seo')}
						hint={__('Any valid schema.org type — e.g. Person, Organization, Course, NewsMediaOrganization.', 'ebq-seo')}
						value={type}
						onChange={setType}
					/>
				) : subtypes && subtypes.length > 1 ? (
					<SelectField
						label={__('Schema type', 'ebq-seo')}
						value={type}
						options={subtypes}
						onChange={setType}
					/>
				) : null}

				{template.fields.map((field) => (
					<FieldControl
						key={field.key}
						field={field}
						value={data[field.key]}
						onChange={(v) => setField(field.key, v)}
					/>
				))}

				<VariableHint />

				{showPreview ? (
					<div className="ebq-schema-preview">
						<div className="ebq-schema-preview__head">
							<span>{__('JSON-LD preview', 'ebq-seo')}</span>
							<span className="ebq-schema-preview__note">{__('Approximate — final markup may include additional schema.org structure.', 'ebq-seo')}</span>
						</div>
						<pre className="ebq-schema-preview__body"><code>{previewJson}</code></pre>
					</div>
				) : null}
			</div>
		</Modal>
	);
}

function FieldControl({ field, value, onChange }) {
	switch (field.type) {
		case 'textarea':
			return (
				<TextArea
					label={field.label}
					hint={field.helper}
					value={value || ''}
					onChange={onChange}
					placeholder={field.placeholder || ''}
				/>
			);
		case 'select':
			return (
				<SelectField
					label={field.label}
					hint={field.helper}
					value={value || ''}
					options={field.options || []}
					onChange={onChange}
					allowEmpty
				/>
			);
		case 'repeater':
			return (
				<Repeater
					label={field.label}
					addLabel={field.addLabel}
					subfields={field.subfields || []}
					value={Array.isArray(value) ? value : []}
					onChange={onChange}
				/>
			);
		case 'date':
		case 'datetime':
			return (
				<DateField
					label={field.label}
					hint={field.helper}
					value={value || ''}
					onChange={onChange}
					mode={field.type}
				/>
			);
		case 'number':
			return (
				<TextField
					label={field.label}
					hint={field.helper}
					value={value ?? ''}
					onChange={onChange}
					type="number"
				/>
			);
		case 'url':
			return (
				<TextField
					label={field.label}
					hint={field.helper}
					value={value || ''}
					onChange={onChange}
					type="url"
				/>
			);
		default:
			return (
				<TextField
					label={field.label}
					hint={field.helper}
					value={value || ''}
					onChange={onChange}
				/>
			);
	}
}

function SelectField({ label, hint, value, options, onChange, allowEmpty = false }) {
	return (
		<div className="ebq-field">
			<label className="ebq-label"><span>{label}</span></label>
			<select
				className="ebq-input"
				value={value || ''}
				onChange={(e) => onChange(e.target.value)}
			>
				{allowEmpty ? <option value="">{__('— Select —', 'ebq-seo')}</option> : null}
				{options.map((opt) => (
					<option key={opt} value={opt}>{opt}</option>
				))}
			</select>
			{hint ? <p className="ebq-help">{hint}</p> : null}
		</div>
	);
}

function DateField({ label, hint, value, onChange, mode }) {
	return (
		<div className="ebq-field">
			<label className="ebq-label"><span>{label}</span></label>
			<input
				className="ebq-input"
				type={mode === 'datetime' ? 'datetime-local' : 'date'}
				value={value || ''}
				onChange={(e) => onChange(e.target.value)}
			/>
			{hint ? <p className="ebq-help">{hint}</p> : null}
		</div>
	);
}

function Repeater({ label, addLabel, subfields, value, onChange }) {
	const rows = value;

	const addRow = () => {
		const next = subfields.length === 1 && subfields[0].key === 'value'
			? { value: '' }
			: subfields.reduce((acc, f) => ({ ...acc, [f.key]: '' }), {});
		onChange([...rows, next]);
	};
	const removeRow = (idx) => {
		const next = rows.slice();
		next.splice(idx, 1);
		onChange(next);
	};
	const updateRow = (idx, key, v) => {
		const next = rows.slice();
		next[idx] = { ...next[idx], [key]: v };
		onChange(next);
	};

	return (
		<div className="ebq-field">
			<label className="ebq-label"><span>{label}</span></label>
			<div className="ebq-repeater">
				{rows.length === 0 ? (
					<p className="ebq-help" style={{ margin: 0 }}>{__('No entries yet.', 'ebq-seo')}</p>
				) : null}
				{rows.map((row, idx) => (
					<div key={idx} className="ebq-repeater__row">
						<div className="ebq-repeater__head">
							<span className="ebq-repeater__index">#{idx + 1}</span>
							<button
								type="button"
								className="ebq-repeater__remove"
								onClick={() => removeRow(idx)}
								aria-label={__('Remove entry', 'ebq-seo')}
							>×</button>
						</div>
						{subfields.map((sf) => (
							<FieldControl
								key={sf.key}
								field={sf}
								value={row?.[sf.key]}
								onChange={(v) => updateRow(idx, sf.key, v)}
							/>
						))}
					</div>
				))}
			</div>
			<Button size="sm" onClick={addRow}>{addLabel || __('Add entry', 'ebq-seo')}</Button>
		</div>
	);
}

function VariableHint() {
	const tokens = useMemo(() => VARIABLES.map((v) => v.token).join(' · '), []);
	return (
		<details className="ebq-schema-vars">
			<summary>{__('Available variables', 'ebq-seo')}</summary>
			<p className="ebq-help" style={{ marginBottom: 6 }}>
				{__('Drop these into any text field. They resolve at render time using the latest post data.', 'ebq-seo')}
			</p>
			<ul className="ebq-schema-vars__list">
				{VARIABLES.map((v) => (
					<li key={v.token}>
						<code>{v.token}</code>
						<span> — {v.label}</span>
					</li>
				))}
				<li><code>%post_meta(key)%</code> — {__('Any post-meta value', 'ebq-seo')}</li>
			</ul>
			<p className="ebq-help" style={{ marginBottom: 0, opacity: 0.7 }}>{tokens}</p>
		</details>
	);
}
