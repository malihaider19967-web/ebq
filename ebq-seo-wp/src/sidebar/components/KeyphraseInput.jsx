import { __ } from '@wordpress/i18n';

import { TextField } from './primitives';

/**
 * Bare focus-keyphrase input. The "From Google Search Console" suggestion
 * list moved out into <GscSuggestions /> so SeoTab can position it under
 * the topical-coverage section instead of inline.
 */
export default function KeyphraseInput({ value, onChange }) {
	return (
		<TextField
			label={__('Focus keyphrase', 'ebq-seo')}
			value={value}
			onChange={onChange}
			placeholder={__('e.g. "best running shoes"', 'ebq-seo')}
			hint={__('The main phrase you want this page to rank for. Analysis below updates in real time.', 'ebq-seo')}
		/>
	);
}
