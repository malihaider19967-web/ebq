import { __, sprintf } from '@wordpress/i18n';

import { Section, TextField, Toggle } from '../components/primitives';
import { IconSliders } from '../components/icons';
import { useEditorContext, usePostMeta } from '../hooks/useEditorContext';

/**
 * Advanced tab — canonical, robots directives. Schema controls have moved
 * to the dedicated Schema tab (catalogue + custom builder + per-schema
 * enable/disable). The legacy `_ebq_schema_type` single-override field is
 * superseded by the catalogue and no longer exposed in the UI; the meta
 * key remains registered for backward compat and is still honoured by the
 * output class when no `_ebq_schemas` entries exist.
 */
export default function AdvancedTab() {
	const ctx = useEditorContext();
	const { get, set } = usePostMeta();

	return (
		<div className="ebq-stack">
			<Section title={__('Canonical & robots', 'ebq-seo')} icon={<IconSliders />}>
				<TextField
					label={__('Canonical URL', 'ebq-seo')}
					value={get('_ebq_canonical', '')}
					onChange={(v) => set('_ebq_canonical', v)}
					placeholder={ctx.postLink}
					hint={sprintf(__('Slug: %s', 'ebq-seo'), ctx.slug || '—')}
				/>

				<div className="ebq-toggle-row" style={{ marginTop: 4 }}>
					<Toggle
						label={__('Noindex', 'ebq-seo')}
						checked={!!get('_ebq_robots_noindex', false)}
						onChange={(v) => set('_ebq_robots_noindex', v)}
					/>
					<Toggle
						label={__('Nofollow', 'ebq-seo')}
						checked={!!get('_ebq_robots_nofollow', false)}
						onChange={(v) => set('_ebq_robots_nofollow', v)}
					/>
				</div>

				<TextField
					label={__('Advanced robots', 'ebq-seo')}
					value={get('_ebq_robots_advanced', '')}
					onChange={(v) => set('_ebq_robots_advanced', v)}
					placeholder="noarchive, nosnippet, max-snippet:-1"
					hint={__('Comma-separated. Merged after the index/follow choice above.', 'ebq-seo')}
				/>
			</Section>
		</div>
	);
}
