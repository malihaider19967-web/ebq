import { useState, useMemo, useCallback, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Section, Toggle, TextField, Button } from './primitives';
import { IconCross } from './icons';

/**
 * Powerful breadcrumb editor — per-post override of the BreadcrumbList
 * JSON-LD that the schema output emits. Two modes:
 *
 *   auto   — emit the default Home → ancestors → current trail (server side)
 *   custom — emit the user-defined item list, in order, with optional URLs
 *            and hidden flags. The last visible item gets no URL (per
 *            schema.org spec — it represents the current page).
 *
 * Initial data: when the user flips into custom mode the first time, we
 * seed the list with the post's auto trail so they don't start from blank.
 */
export default function BreadcrumbBuilder({ value, onChange, defaults = {} }) {
	const parsed = useMemo(() => parseValue(value, defaults), [value, defaults]);
	const [mode, setMode] = useState(parsed.mode);
	const [items, setItems] = useState(parsed.items);

	// Re-sync local state when post changes (different post → different value).
	useEffect(() => {
		setMode(parsed.mode);
		setItems(parsed.items);
	}, [value]); // intentionally not including `parsed` to avoid loop

	const persist = useCallback((nextMode, nextItems) => {
		// Empty out when reverting to auto with no edits → store '' so the
		// post-meta entry is deleted by the sanitizer.
		if (nextMode === 'auto' && (!nextItems || nextItems.length === 0)) {
			onChange('');
			return;
		}
		onChange(JSON.stringify({ mode: nextMode, items: nextItems }));
	}, [onChange]);

	const switchMode = (next) => {
		// First time switching to custom → seed from defaults so the user
		// has something to edit instead of an empty list.
		let nextItems = items;
		if (next === 'custom' && (!items || items.length === 0)) {
			nextItems = (defaults.items || []).map((it) => ({ ...it }));
			setItems(nextItems);
		}
		setMode(next);
		persist(next, nextItems);
	};

	const updateItem = (idx, patch) => {
		const next = items.slice();
		next[idx] = { ...next[idx], ...patch };
		setItems(next);
		persist(mode, next);
	};
	const removeItem = (idx) => {
		const next = items.slice();
		next.splice(idx, 1);
		setItems(next);
		persist(mode, next);
	};
	const moveItem = (idx, dir) => {
		const target = idx + dir;
		if (target < 0 || target >= items.length) return;
		const next = items.slice();
		[next[idx], next[target]] = [next[target], next[idx]];
		setItems(next);
		persist(mode, next);
	};
	const addItem = () => {
		const next = [...items, { name: '', url: '', hidden: false }];
		setItems(next);
		persist(mode, next);
	};
	const reset = () => {
		setItems([]);
		setMode('auto');
		persist('auto', []);
	};

	// Live preview — auto mode shows the defaults, custom shows the edits.
	const previewItems = mode === 'custom'
		? items.filter((i) => !i.hidden && (i.name || '').trim() !== '')
		: (defaults.items || []);

	return (
		<Section title={__('Breadcrumb (BreadcrumbList)', 'ebq-seo')}>
			<p className="ebq-help" style={{ marginTop: 0 }}>
				{__('Controls the BreadcrumbList JSON-LD Google reads to render the breadcrumb trail in search results. Auto uses your post hierarchy; switch to custom for full control.', 'ebq-seo')}
			</p>

			<div className="ebq-bc-mode" role="radiogroup" aria-label={__('Breadcrumb mode', 'ebq-seo')}>
				<button
					type="button"
					role="radio"
					aria-checked={mode === 'auto'}
					className={`ebq-bc-mode__btn${mode === 'auto' ? ' is-active' : ''}`}
					onClick={() => switchMode('auto')}
				>
					{__('Automatic', 'ebq-seo')}
				</button>
				<button
					type="button"
					role="radio"
					aria-checked={mode === 'custom'}
					className={`ebq-bc-mode__btn${mode === 'custom' ? ' is-active' : ''}`}
					onClick={() => switchMode('custom')}
				>
					{__('Custom', 'ebq-seo')}
				</button>
			</div>

			{mode === 'custom' ? (
				<>
					<div className="ebq-bc-list">
						{items.length === 0 ? (
							<p className="ebq-help" style={{ margin: 0 }}>{__('No items yet — add one below.', 'ebq-seo')}</p>
						) : items.map((it, idx) => (
							<div key={idx} className={`ebq-bc-row${it.hidden ? ' is-hidden' : ''}`}>
								<div className="ebq-bc-row__head">
									<span className="ebq-bc-row__num">#{idx + 1}</span>
									<div className="ebq-bc-row__moves">
										<button type="button" disabled={idx === 0} onClick={() => moveItem(idx, -1)} aria-label={__('Move up', 'ebq-seo')}>↑</button>
										<button type="button" disabled={idx === items.length - 1} onClick={() => moveItem(idx, 1)} aria-label={__('Move down', 'ebq-seo')}>↓</button>
									</div>
									<button type="button" className="ebq-bc-row__remove" onClick={() => removeItem(idx)} aria-label={__('Remove item', 'ebq-seo')}>
										<IconCross />
									</button>
								</div>
								<TextField
									label={__('Label', 'ebq-seo')}
									value={it.name || ''}
									onChange={(v) => updateItem(idx, { name: v })}
									placeholder={__('e.g. Home, Blog, Category, Post title', 'ebq-seo')}
								/>
								{idx < items.length - 1 ? (
									<TextField
										label={__('URL (optional)', 'ebq-seo')}
										value={it.url || ''}
										onChange={(v) => updateItem(idx, { url: v })}
										placeholder="https://"
										hint={__('Leave empty to render an unlinked label.', 'ebq-seo')}
									/>
								) : (
									<p className="ebq-help" style={{ margin: 0 }}>
										{__('Last item is the current page — schema.org spec omits its URL.', 'ebq-seo')}
									</p>
								)}
								<Toggle
									label={__('Hide this item', 'ebq-seo')}
									checked={!!it.hidden}
									onChange={(v) => updateItem(idx, { hidden: v })}
								/>
							</div>
						))}
					</div>

					<div className="ebq-row" style={{ gap: 6, marginTop: 6, flexWrap: 'wrap' }}>
						<Button size="sm" variant="primary" onClick={addItem}>+ {__('Add item', 'ebq-seo')}</Button>
						<Button size="sm" variant="ghost" onClick={reset}>{__('Reset to automatic', 'ebq-seo')}</Button>
					</div>
				</>
			) : null}

			{previewItems.length > 0 ? (
				<div className="ebq-bc-preview" aria-label={__('Breadcrumb preview', 'ebq-seo')}>
					<span className="ebq-bc-preview__label">{__('Preview', 'ebq-seo')}</span>
					<ol>
						{previewItems.map((it, i) => (
							<li key={i}>
								{it.url && i < previewItems.length - 1 ? (
									<a href={it.url} target="_blank" rel="noopener noreferrer">{it.name || '—'}</a>
								) : (
									<span>{it.name || '—'}</span>
								)}
								{i < previewItems.length - 1 ? <em aria-hidden> › </em> : null}
							</li>
						))}
					</ol>
				</div>
			) : null}
		</Section>
	);
}

function parseValue(raw, defaults) {
	const fallback = { mode: 'auto', items: [] };
	if (!raw) return fallback;
	try {
		const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
		if (!parsed || typeof parsed !== 'object') return fallback;
		const mode = parsed.mode === 'custom' ? 'custom' : 'auto';
		const items = Array.isArray(parsed.items) ? parsed.items.map((it) => ({
			name: String(it?.name || ''),
			url: String(it?.url || ''),
			hidden: !!it?.hidden,
		})) : [];
		return { mode, items: items.length ? items : (defaults.items || []) };
	} catch {
		return fallback;
	}
}
