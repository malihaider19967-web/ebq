import { __ } from '@wordpress/i18n';

import { Section, TextField, TextArea } from '../components/primitives';
import { IconShare } from '../components/icons';
import { FacebookCard, TwitterCard } from '../components/SocialCards';
import { useEditorContext, usePostMeta } from '../hooks/useEditorContext';

export default function SocialTab() {
	const ctx = useEditorContext();
	const { get, set } = usePostMeta();

	const ogTitle = get('_ebq_og_title', '') || get('_ebq_title', '') || ctx.postTitle;
	const ogDesc  = get('_ebq_og_description', '') || get('_ebq_description', '');
	const ogImg   = get('_ebq_og_image', '') || ctx.featuredImageUrl || '';

	const twTitle = get('_ebq_twitter_title', '') || ogTitle;
	const twDesc  = get('_ebq_twitter_description', '') || ogDesc;
	const twImg   = get('_ebq_twitter_image', '') || ogImg;

	const url = get('_ebq_canonical', '') || ctx.postLink;

	return (
		<div className="ebq-stack">
			<Section title={__('Facebook · LinkedIn · WhatsApp', 'ebq-seo')} icon={<IconShare />}>
				<FacebookCard url={url} title={ogTitle} description={ogDesc} image={ogImg} />
				<TextField
					label={__('Open Graph title', 'ebq-seo')}
					value={get('_ebq_og_title', '')}
					onChange={(v) => set('_ebq_og_title', v)}
					placeholder={__('Leave empty to use the SEO title', 'ebq-seo')}
				/>
				<TextArea
					label={__('Open Graph description', 'ebq-seo')}
					value={get('_ebq_og_description', '')}
					onChange={(v) => set('_ebq_og_description', v)}
					placeholder={__('Leave empty to use the meta description', 'ebq-seo')}
					rows={3}
				/>
				<TextField
					label={__('Image URL', 'ebq-seo')}
					value={get('_ebq_og_image', '')}
					onChange={(v) => set('_ebq_og_image', v)}
					placeholder="https://…/preview.jpg"
					hint={__('Recommended 1200×630.', 'ebq-seo')}
				/>
			</Section>

			<Section title={__('X · Twitter', 'ebq-seo')} icon={<IconShare />}>
				<TwitterCard url={url} title={twTitle} description={twDesc} image={twImg} />
				<TextField
					label={__('X / Twitter title', 'ebq-seo')}
					value={get('_ebq_twitter_title', '')}
					onChange={(v) => set('_ebq_twitter_title', v)}
				/>
				<TextArea
					label={__('X / Twitter description', 'ebq-seo')}
					value={get('_ebq_twitter_description', '')}
					onChange={(v) => set('_ebq_twitter_description', v)}
					rows={3}
				/>
				<TextField
					label={__('Image URL', 'ebq-seo')}
					value={get('_ebq_twitter_image', '')}
					onChange={(v) => set('_ebq_twitter_image', v)}
					placeholder="https://…/twitter.jpg"
				/>
			</Section>
		</div>
	);
}
