import { useState, useCallback, useEffect, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button } from './primitives';
import { IconSparkle } from './icons';
import Modal from './Modal';

/**
 * "Improve with AI" — Pro-tier-only action.
 *
 * Two modes:
 *   - **Auto** (default) — model picks 3 different angles for the user.
 *     Same behaviour as the original release.
 *   - **Single intent** — user picks ONE intent from the registry
 *     (list-based, problem-solution, beginner-friendly, question-based,
 *     benefit-driven, authority/expert, freshness/updated, use-case
 *     focused, myth-busting, comparison-with-verdict, plus the original
 *     5). Backend returns 3 distinct VARIATIONS of that single intent.
 *
 * Per-intent results are cached server-side 7 days, so flipping intents
 * back and forth is free after the first generation.
 */
export default function AiRewriteSnippet({
	postId,
	focusKeyword,
	currentTitle,
	currentMeta,
	contentExcerpt,
	competitorTitles = [],
	onApplyTitle,
	onApplyMeta,
}) {
	const [open, setOpen] = useState(false);
	// `intentsState` holds the registry pulled once on first open. We
	// don't fetch this until the modal opens to avoid an extra API call
	// on pages where the user never clicks "Improve with AI".
	const [intentsState, setIntentsState] = useState({ status: 'idle', list: [] });
	const [intent, setIntent] = useState('auto');
	const [state, setState] = useState({ status: 'idle', data: null, error: null });
	// Track which (intent → result) we've already fetched so re-selecting
	// an intent shows a fresh-data spinner only the first time per modal
	// session. Re-clicks within a week hit the server-side 7d cache.
	const seenIntents = useRef(new Set());

	const canRequest =
		!!focusKeyword &&
		focusKeyword.trim().length >= 2 &&
		!!contentExcerpt &&
		String(contentExcerpt).length >= 50;

	const fetchRewrites = useCallback((forIntent) => {
		setState({ status: 'loading', data: null, error: null });
		apiFetch({
			path: `/ebq/v1/rewrite-snippet/${postId}`,
			method: 'POST',
			data: {
				focus_keyword: focusKeyword,
				current_title: currentTitle || '',
				current_meta: currentMeta || '',
				content_excerpt: String(contentExcerpt || '').slice(0, 6000),
				competitor_titles: (competitorTitles || []).slice(0, 5),
				intent: forIntent || 'auto',
			},
		})
			.then((res) => {
				const inner = res?.rewrite || {};
				if (inner?.ok === false || res?.ok === false) {
					setState({
						status: 'error',
						data: null,
						error: inner?.message || inner?.error || res?.message || res?.error || 'Failed',
					});
				} else if (Array.isArray(inner.rewrites) && inner.rewrites.length) {
					setState({ status: 'ready', data: inner, error: null });
					seenIntents.current.add(forIntent || 'auto');
				} else {
					setState({ status: 'error', data: null, error: 'No rewrites returned' });
				}
			})
			.catch((err) => {
				setState({ status: 'error', data: null, error: err?.message || 'Network error' });
			});
	}, [postId, focusKeyword, currentTitle, currentMeta, contentExcerpt, competitorTitles]);

	// Pull the intent registry once, on first modal open. Cheap call;
	// its only purpose is keeping the picker labels in sync with the
	// server-side INTENTS map without hardcoding a copy here.
	useEffect(() => {
		if (!open || intentsState.status !== 'idle') return;
		setIntentsState({ status: 'loading', list: [] });
		apiFetch({ path: '/ebq/v1/rewrite-intents' })
			.then((res) => {
				const list = Array.isArray(res?.intents) ? res.intents : [];
				setIntentsState({ status: 'ready', list });
			})
			.catch(() => {
				// Fallback: bare 'auto' option so the modal still works
				// even if the registry call fails (network blip / old
				// backend). User can still get auto-mode rewrites.
				setIntentsState({
					status: 'ready',
					list: [{ key: 'auto', label: __('Auto · 3 angles', 'ebq-seo'), desc: '' }],
				});
			});
	}, [open, intentsState.status]);

	const handleOpen = () => {
		setOpen(true);
		if (state.status === 'idle' || state.status === 'error') {
			fetchRewrites(intent);
		}
	};

	const handleIntentChange = (e) => {
		const next = e.target.value;
		setIntent(next);
		fetchRewrites(next);
	};

	// Build a stable selected-intent description for the helper line.
	const selectedIntent = intentsState.list.find((i) => i.key === intent);

	return (
		<>
			<Button
				variant="primary"
				size="sm"
				onClick={handleOpen}
				disabled={!canRequest}
				aria-haspopup="dialog"
				aria-expanded={open}
				title={
					canRequest
						? __('Generate AI title + meta options across 15 intents', 'ebq-seo')
						: __('Set a focus keyphrase + add some content first', 'ebq-seo')
				}
			>
				<IconSparkle /> {__('Improve with AI', 'ebq-seo')}
			</Button>

			<Modal
				open={open}
				onClose={() => setOpen(false)}
				title={__('AI snippet rewrites', 'ebq-seo')}
				size="md"
			>
				{/* Intent picker — sits above the rewrites so the user can
				    flip angles without leaving the modal. Auto stays at top. */}
				<div className="ebq-ai-intent-bar">
					<label className="ebq-ai-intent-bar__label" htmlFor="ebq-ai-intent">
						{__('Intent', 'ebq-seo')}
					</label>
					<select
						id="ebq-ai-intent"
						className="ebq-ai-intent-bar__select"
						value={intent}
						onChange={handleIntentChange}
						disabled={intentsState.status !== 'ready' || state.status === 'loading'}
					>
						{intentsState.list.map((i) => (
							<option key={i.key} value={i.key} title={i.desc || ''}>
								{i.label}
							</option>
						))}
					</select>
					{selectedIntent?.desc && intent !== 'auto' ? (
						<p className="ebq-ai-intent-bar__desc">{selectedIntent.desc}</p>
					) : intent === 'auto' && selectedIntent?.desc ? (
						<p className="ebq-ai-intent-bar__desc">{selectedIntent.desc}</p>
					) : null}
				</div>

				{state.status === 'loading' ? (
					<p className="ebq-help">
						<span className="ebq-spinner" />{' '}
						{intent === 'auto'
							? __('Asking the model for 3 ranked rewrites…', 'ebq-seo')
							: sprintf(
								__('Generating 3 "%s" variations…', 'ebq-seo'),
								selectedIntent?.label || intent,
							)}
					</p>
				) : null}

				{state.status === 'error' ? (
					<>
						<p className="ebq-help" style={{ color: 'var(--ebq-bad-text)' }}>
							{state.error}
						</p>
						<Button size="sm" onClick={() => fetchRewrites(intent)}>
							{__('Retry', 'ebq-seo')}
						</Button>
					</>
				) : null}

				{state.status === 'ready' && state.data ? (
					<div className="ebq-ai-rewrites">
						{state.data.cached ? (
							<p className="ebq-help" style={{ marginTop: 0 }}>
								{__('Cached for 7 days — re-clicks are free.', 'ebq-seo')}
							</p>
						) : null}
						{state.data.rewrites.map((r, i) => (
							<div key={i} className="ebq-ai-rewrite">
								<div className="ebq-ai-rewrite__head">
									<span className="ebq-ai-rewrite__angle">{r.angle}</span>
									<span className="ebq-ai-rewrite__counts">
										{sprintf(__('Title %d · Meta %d', 'ebq-seo'), r.title.length, r.meta.length)}
									</span>
								</div>
								<div className="ebq-ai-rewrite__title">{r.title}</div>
								<div className="ebq-ai-rewrite__meta">{r.meta}</div>
								{r.rationale ? <p className="ebq-ai-rewrite__rationale">{r.rationale}</p> : null}
								<div className="ebq-ai-rewrite__actions">
									<Button
										size="sm"
										variant="ghost"
										onClick={() => {
											onApplyTitle?.(r.title);
										}}
									>
										{__('Use title', 'ebq-seo')}
									</Button>
									<Button
										size="sm"
										variant="ghost"
										onClick={() => {
											onApplyMeta?.(r.meta);
										}}
									>
										{__('Use meta', 'ebq-seo')}
									</Button>
									<Button
										size="sm"
										variant="primary"
										onClick={() => {
											onApplyTitle?.(r.title);
											onApplyMeta?.(r.meta);
										}}
									>
										{__('Use both', 'ebq-seo')}
									</Button>
								</div>
							</div>
						))}
					</div>
				) : null}
			</Modal>
		</>
	);
}
