import { __, sprintf } from '@wordpress/i18n';

import { Section, TextField, Toggle } from '../components/primitives';
import { IconSliders } from '../components/icons';
import { useEditorContext, usePostMeta } from '../hooks/useEditorContext';

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

			<Section title={__('Schema', 'ebq-seo')} icon={<IconSliders />}>
				<TextField
					label={__('Schema type', 'ebq-seo')}
					value={get('_ebq_schema_type', '')}
					onChange={(v) => set('_ebq_schema_type', v)}
					placeholder="Article"
					hint={__('Override the default piece (Article, BlogPosting, NewsArticle, FAQPage…).', 'ebq-seo')}
				/>
				<Toggle
					label={__('Disable schema for this post', 'ebq-seo')}
					checked={!!get('_ebq_schema_disabled', false)}
					onChange={(v) => set('_ebq_schema_disabled', v)}
				/>
			</Section>
		</div>
	);
}
