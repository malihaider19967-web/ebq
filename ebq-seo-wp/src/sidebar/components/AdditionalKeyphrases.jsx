import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useMemo } from '@wordpress/element';

import { IconCross, IconSparkle } from './icons';
import TrackKeywordButton from './TrackKeywordButton';

// No hard cap on additional keyphrases — users can add as many as they like.
// A soft guard is still applied at sanitize time on the PHP side to keep
// payloads sane (≤200 entries), but the UI doesn't enforce a limit.
const MAX_ADDITIONAL = 200;

/**
 * Editor for up to 5 additional keyphrases (Yoast Premium parity).
 *
 * Source of truth is a JSON-encoded string on `_ebq_additional_keywords`
 * post meta — the parent owns the value/setter so this component doesn't
 * need to care about Gutenberg vs Classic-mode storage.
 *
 * `onShowRelated(index, currentRowValue)` — optional. When supplied, each
 * row renders a "Related keyphrases" trigger; the modal that opens always
 * searches from the FOCUS keyphrase (not the row's own text) so writers
 * can fill empty rows directly from focus-related suggestions. The button
 * is gated on whether related search is possible at all, controlled via
 * `relatedAvailable` from the parent.
 */
export default function AdditionalKeyphrases({ value, onChange, onShowRelated, relatedAvailable = false }) {
	const list = useMemo(() => parse(value), [value]);

	const updateAt = useCallback((idx, next) => {
		const copy = [...list];
		copy[idx] = next;
		onChange(stringify(copy));
	}, [list, onChange]);

	const removeAt = useCallback((idx) => {
		const copy = list.filter((_, i) => i !== idx);
		onChange(stringify(copy));
	}, [list, onChange]);

	const add = useCallback(() => {
		if (list.length >= MAX_ADDITIONAL) return;
		onChange(stringify([...list, '']));
	}, [list, onChange]);

	return (
		<div className="ebq-additional">
			{/* Inline header dropped — the parent Section title now reads
			    "Add additional keyphrases to expand topical coverage", so a
			    second "Additional keyphrases (N)" label here was redundant.
			    The row count is implicit from the visible inputs. */}
			{list.map((kw, idx) => {
				const trimmed = (kw || '').trim();
				const canShowRelated = !!onShowRelated && relatedAvailable;
				return (
					<div key={idx} className="ebq-additional__row">
						<input
							type="text"
							className="ebq-input"
							value={kw}
							onChange={(e) => updateAt(idx, e.target.value)}
							placeholder={sprintf(__('Keyphrase %d', 'ebq-seo'), idx + 1)}
							maxLength={120}
						/>
						{onShowRelated ? (
							<button
								type="button"
								className="ebq-additional__related"
								onClick={() => onShowRelated(idx, trimmed)}
								disabled={!canShowRelated}
								aria-label={canShowRelated
									? __('Pick from related keyphrases for the focus keyphrase', 'ebq-seo')
									: __('Set a focus keyphrase first to see related keyphrases', 'ebq-seo')}
								title={canShowRelated
									? __('Related keyphrases', 'ebq-seo')
									: __('Set a focus keyphrase first', 'ebq-seo')}
							>
								<IconSparkle />
							</button>
						) : null}
						<TrackKeywordButton keyword={trimmed} />
						<button
							type="button"
							className="ebq-additional__remove"
							onClick={() => removeAt(idx)}
							aria-label={sprintf(__('Remove keyphrase %d', 'ebq-seo'), idx + 1)}
							title={__('Remove', 'ebq-seo')}
						>
							<IconCross />
						</button>
					</div>
				);
			})}

			<button
				type="button"
				className="ebq-additional__add"
				onClick={add}
				aria-label={__('Add another keyphrase', 'ebq-seo')}
			>
				+ {list.length === 0
					? __('Add a related keyphrase', 'ebq-seo')
					: __('Add another', 'ebq-seo')}
			</button>
		</div>
	);
}

function parse(value) {
	if (Array.isArray(value)) return value.map((s) => String(s ?? ''));
	if (typeof value !== 'string' || value.trim() === '') return [];
	try {
		const decoded = JSON.parse(value);
		if (Array.isArray(decoded)) return decoded.map((s) => String(s ?? '')).slice(0, MAX_ADDITIONAL);
	} catch { /* fall through */ }
	return [];
}

function stringify(list) {
	return JSON.stringify(list.slice(0, MAX_ADDITIONAL));
}

export { parse as parseAdditionalKeywords, stringify as stringifyAdditionalKeywords, MAX_ADDITIONAL };
