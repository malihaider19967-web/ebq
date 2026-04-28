import { useState, useMemo, useCallback, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Section, Button, Toggle, EmptyState } from '../components/primitives';
import SchemaCatalogModal from '../components/SchemaCatalogModal';
import SchemaForm from '../components/SchemaForm';
import BreadcrumbBuilder from '../components/BreadcrumbBuilder';
import { useEditorContext, usePostMeta, publicConfig } from '../hooks/useEditorContext';
import { getTemplate, initialDataForTemplate } from '../schema/templates';

/**
 * Schema generator tab — list of schemas the user has added to this post,
 * with add / edit / remove / disable. Persists into `_ebq_schemas` as a JSON
 * string so the same field round-trips through both editors.
 */
export default function SchemaTab() {
	const { get, set } = usePostMeta();
	const ctx = useEditorContext();
	const cfg = publicConfig();

	const raw = get('_ebq_schemas', '');
	const schemas = useMemo(() => parseSchemas(raw), [raw]);
	const schemaDisabled = !!get('_ebq_schema_disabled', false);
	const breadcrumbsRaw = get('_ebq_breadcrumbs', '');

	// Schemas emitted by sources OTHER than the user-configured
	// `_ebq_schemas` list. Three sources:
	//   1. Server-fetched: EBQ's own auto-emitted graph (Article +
	//      WebPage + Organization + …) plus other plugins' wp_head
	//      JSON-LD captured via our /schemas-on-page endpoint.
	//   2. Post-content scan: FAQ/HowTo blocks + inline JSON-LD scripts
	//      pasted into the editor.
	//   3. Cross-plugin meta overrides (Yoast / Rank Math / AIOSEO type).
	const [serverDetected, setServerDetected] = useState([]);
	useEffect(() => {
		if (!ctx.postId) return;
		let cancelled = false;
		apiFetch({ path: `/ebq/v1/schemas-on-page/${ctx.postId}` })
			.then((res) => {
				if (cancelled) return;
				const items = Array.isArray(res?.items) ? res.items : [];
				setServerDetected(items);
			})
			.catch(() => { /* best-effort; client-side detection still runs */ });
		return () => { cancelled = true; };
	}, [ctx.postId]);

	const clientDetected = useMemo(
		() => detectExternalSchemas(ctx.content, ctx.meta || {}, ctx.postLink || ''),
		[ctx.content, ctx.meta, ctx.postLink]
	);

	// Merge server + client detections, dedup by (type|source|kind).
	const externalSchemas = useMemo(() => {
		const seen = new Set();
		const out = [];
		const add = (e) => {
			const k = `${e.type}|${e.source}|${e.kind}`;
			if (seen.has(k)) return;
			seen.add(k);
			out.push(e);
		};
		serverDetected.forEach(add);
		clientDetected.forEach(add);
		return out;
	}, [serverDetected, clientDetected]);

	// Auto-trail seed — what the server would emit by default for this post.
	// Last item carries no URL by spec.
	const breadcrumbDefaults = useMemo(() => {
		const home = { name: __('Home', 'ebq-seo'), url: cfg.homeUrl || '/' };
		const current = { name: ctx.postTitle || __('This post', 'ebq-seo'), url: '' };
		return { items: [home, current] };
	}, [cfg.homeUrl, ctx.postTitle]);

	const [catalogOpen, setCatalogOpen] = useState(false);
	const [editing, setEditing] = useState(null); // { mode: 'add'|'edit', template, entry }

	const writeSchemas = useCallback(
		(next) => {
			const safe = Array.isArray(next) ? next : [];
			set('_ebq_schemas', safe.length ? JSON.stringify(safe) : '');
		},
		[set]
	);

	// Open the schema editor pre-filled with a template that will
	// suppress and replace the auto-emitted node of the same @type.
	// Used by the "Override" button on each auto-emitted row.
	const overrideAutoEmitted = (templateId) => {
		const template = getTemplate(templateId);
		if (!template) return;
		setEditing({
			mode: 'add',
			template,
			entry: {
				id: makeId(),
				template: template.id,
				type: template.type,
				enabled: true,
				data: initialDataForTemplate(template),
			},
		});
	};

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
			{schemaDisabled ? (
				<div className="ebq-schema-disabled-banner" role="status">
					<strong>{__('Schema is disabled for this post.', 'ebq-seo')}</strong>
					<span>{__('No JSON-LD will be emitted on the front-end. Toggle below to re-enable.', 'ebq-seo')}</span>
				</div>
			) : null}

			<Section
				title={__('Schema output', 'ebq-seo')}
				flush
			>
				<Toggle
					label={__('Disable schema (JSON-LD) for this post entirely', 'ebq-seo')}
					checked={schemaDisabled}
					onChange={(v) => set('_ebq_schema_disabled', v)}
				/>
			</Section>

			<Section
				title={__('Schemas on this post', 'ebq-seo')}
				aside={<Button size="sm" variant="primary" onClick={() => setCatalogOpen(true)} disabled={schemaDisabled}>{__('Add schema', 'ebq-seo')}</Button>}
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

			{externalSchemas.length > 0 ? (
				<Section
					title={__('Detected from other sources', 'ebq-seo')}
				>
					<p className="ebq-help" style={{ marginTop: 0 }}>
						{__('These schemas are emitted by other plugins, blocks, your theme, or EBQ\'s built-in graph. EBQ-emitted ones can be replaced with your own custom version using the Override button — your version takes precedence and the auto emission is suppressed.', 'ebq-seo')}
					</p>
					<div className="ebq-schema-list">
						{externalSchemas.map((entry, i) => {
							const overrideTemplateId = ebqOverrideTemplateId(entry);
							const canOverride = !!overrideTemplateId && entry.kind === 'ebq_auto';
							return (
								<div key={`ext-${i}-${entry.kind}`} className="ebq-schema-row ebq-schema-row--external">
									<div className="ebq-schema-row__main">
										<div className="ebq-schema-row__head">
											<span className="ebq-schema-row__type">{entry.type}</span>
											<span className="ebq-schema-row__label">{entry.source}</span>
										</div>
										<p className="ebq-schema-row__sub">{entry.note}</p>
									</div>
									<div className="ebq-schema-row__actions">
										{canOverride ? (
											<Button
												size="sm"
												variant="primary"
												onClick={() => overrideAutoEmitted(overrideTemplateId)}
											>
												{__('Override', 'ebq-seo')}
											</Button>
										) : (
											<span className="ebq-schema-row__readonly" title={__('Read-only — managed elsewhere', 'ebq-seo')}>
												{__('read-only', 'ebq-seo')}
											</span>
										)}
									</div>
								</div>
							);
						})}
					</div>
				</Section>
			) : null}

			<BreadcrumbBuilder
				value={breadcrumbsRaw}
				onChange={(v) => set('_ebq_breadcrumbs', v)}
				defaults={breadcrumbDefaults}
			/>

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

/**
 * Map an auto-emitted entry's @type to the EBQ schema-builder template
 * that overrides it. Returns null when no template exists for that type
 * (in which case the row stays read-only — the user can still use
 * "Custom" from the catalogue if they need a fully manual override).
 */
function ebqOverrideTemplateId(entry) {
	if (!entry || entry.kind !== 'ebq_auto') return null;
	const t = String(entry.type || '').toLowerCase();
	const map = {
		'website': 'website',
		'organization': 'organization',
		'webpage': 'webpage',
		'aboutpage': 'webpage',
		'contactpage': 'webpage',
		'faqpage': 'faq',
		'collectionpage': 'webpage',
		'profilepage': 'webpage',
		'person': 'person',
		'article': 'article',
		'blogposting': 'article',
		'newsarticle': 'article',
		'product': 'product',
		'event': 'event',
		'recipe': 'recipe',
		'localbusiness': 'local_business',
		'restaurant': 'local_business',
		'store': 'local_business',
		'professionalservice': 'local_business',
		'medicalbusiness': 'local_business',
		'jobposting': 'job_posting',
		'softwareapplication': 'software',
		'videoobject': 'video',
		'course': 'course',
		'review': 'review',
		'service': 'service',
		'book': 'book',
	};
	return map[t] || null;
}

/**
 * Tolerant parser for the `_ebq_schemas` meta value. Accepts:
 *   - JSON string (the canonical shape `sanitize_schemas` writes)
 *   - already-decoded array (some REST setups return pre-parsed JSON
 *     when `show_in_rest` schema treats the field as object/array)
 *   - PHP-serialized fallback (legacy posts that pre-date the meta
 *     being registered as `type: string` — WP returns the unserialized
 *     array directly)
 *   - JSON-string that's been escaped one extra time (`"\"[…]\""`),
 *     which can happen when something double-encodes during save
 *
 * Returning a defensive `[]` only when we genuinely can't recognize
 * the shape — previously a single bad encoding made existing schemas
 * vanish from the UI even though the DB still had the data.
 */
function parseSchemas(raw) {
	if (raw == null || raw === '') return [];
	if (Array.isArray(raw)) {
		return raw.filter((s) => s && typeof s === 'object');
	}
	if (typeof raw === 'object') {
		// Some REST setups return `{"0": {...}, "1": {...}}` instead of
		// a true array. Coerce numeric-keyed objects into a list.
		return Object.values(raw).filter((s) => s && typeof s === 'object');
	}
	if (typeof raw !== 'string') return [];

	const tryParse = (s) => {
		try { return JSON.parse(s); } catch { return undefined; }
	};

	let parsed = tryParse(raw);
	// Double-encoded ("\"[...]\"") — parse twice.
	if (typeof parsed === 'string') {
		parsed = tryParse(parsed);
	}
	if (Array.isArray(parsed)) {
		return parsed.filter((s) => s && typeof s === 'object');
	}
	if (parsed && typeof parsed === 'object') {
		return Object.values(parsed).filter((s) => s && typeof s === 'object');
	}
	return [];
}

function makeId() {
	if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
		return crypto.randomUUID();
	}
	return `s_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`;
}

/**
 * Detect schemas emitted by sources OTHER than EBQ — FAQ / HowTo blocks
 * in the post content (regardless of which plugin authored the block),
 * plus Yoast / Rank Math / AIOSEO schema-type overrides stored in their
 * own post-meta keys when those plugins expose meta via REST.
 *
 * Returns a flat list of { type, source, kind, note } entries the
 * SchemaTab renders read-only. We deliberately don't try to parse the
 * inner JSON-LD — we just surface "this schema type is on the page"
 * so the user knows it's there. Editing happens in the originating
 * block / plugin.
 */
function detectExternalSchemas(content, meta, _url) {
	const out = [];
	const html = String(content || '');
	const seen = new Set();
	const push = (entry) => {
		const key = `${entry.kind}|${entry.type}|${entry.source}`;
		if (seen.has(key)) return;
		seen.add(key);
		out.push(entry);
	};

	// FAQ blocks — match every block whose name ends in `faq`. Captures
	// Yoast (`yoast/faq-block`), Rank Math (`rank-math/faq-block`),
	// Stackable (`ugb/faq`), Generate Blocks (`generateblocks/faq`),
	// and EBQ's own (`ebq/faq`). Plus inline FAQ schema from a JSON-LD
	// script tag the user / theme dropped into post content.
	const faqBlockRx = /<!--\s+wp:([a-z0-9-]+)\/(faq[a-z0-9-]*)/gi;
	let m;
	while ((m = faqBlockRx.exec(html)) !== null) {
		const ns = m[1].toLowerCase();
		push({
			type: 'FAQPage',
			source: prettyPluginName(ns),
			kind: 'faq_block',
			note: `FAQ block in post content (${ns}). Edit the block to change questions/answers.`,
		});
	}

	// HowTo blocks — same pattern.
	const howtoBlockRx = /<!--\s+wp:([a-z0-9-]+)\/(how-?to[a-z0-9-]*)/gi;
	while ((m = howtoBlockRx.exec(html)) !== null) {
		const ns = m[1].toLowerCase();
		push({
			type: 'HowTo',
			source: prettyPluginName(ns),
			kind: 'howto_block',
			note: `HowTo block in post content (${ns}). Edit the block to change steps.`,
		});
	}

	// Inline JSON-LD scripts that the user pasted into post content. We
	// peek inside enough to grab the @type so the row is informative.
	const jsonLdRx = /<script[^>]+type=["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi;
	while ((m = jsonLdRx.exec(html)) !== null) {
		const body = m[1].trim();
		const typeMatch = body.match(/"@type"\s*:\s*"([^"]+)"/);
		const t = typeMatch ? typeMatch[1] : 'Unknown';
		push({
			type: t,
			source: 'Inline JSON-LD',
			kind: 'inline_jsonld',
			note: 'Pasted directly into post content as a <script type="application/ld+json"> tag.',
		});
	}

	// SEO-plugin meta overrides (visible only when those plugins expose
	// the meta via REST — Yoast / Rank Math typically do).
	const yoastSchema = meta._yoast_wpseo_schema_page_type || meta._yoast_wpseo_schema_article_type;
	if (yoastSchema && typeof yoastSchema === 'string' && yoastSchema.trim() !== '') {
		push({
			type: yoastSchema,
			source: 'Yoast SEO',
			kind: 'yoast_schema_override',
			note: 'Yoast schema page-type override. Edit under Yoast → Schema settings on this post.',
		});
	}
	const rankMathSnippet = meta.rank_math_rich_snippet;
	if (rankMathSnippet && typeof rankMathSnippet === 'string' && rankMathSnippet.trim() !== '' && rankMathSnippet !== 'off') {
		push({
			type: rankMathSnippet,
			source: 'Rank Math',
			kind: 'rankmath_snippet',
			note: 'Rank Math rich-snippet selection. Edit under Rank Math → Schema on this post.',
		});
	}
	const aioseoSchema = meta._aioseo_schema_type;
	if (aioseoSchema && typeof aioseoSchema === 'string' && aioseoSchema.trim() !== '') {
		push({
			type: aioseoSchema,
			source: 'All in One SEO',
			kind: 'aioseo_schema',
			note: 'AIOSEO schema type. Edit under AIOSEO → Schema on this post.',
		});
	}

	return out;
}

function prettyPluginName(ns) {
	switch (ns) {
		case 'yoast':         return 'Yoast SEO';
		case 'rank-math':     return 'Rank Math';
		case 'ugb':           return 'Stackable';
		case 'generateblocks':return 'GenerateBlocks';
		case 'ebq':           return 'EBQ';
		case 'core':          return 'WordPress core';
		default:              return ns;
	}
}
