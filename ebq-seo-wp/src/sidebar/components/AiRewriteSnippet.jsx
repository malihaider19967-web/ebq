import { useState, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button } from './primitives';
import { IconSparkle } from './icons';
import Modal from './Modal';

/**
 * "Improve with AI" — Pro-tier-only action that asks EBQ for 3 ranked
 * title + meta-description rewrites with rationales. Each rewrite shows
 * the angle (commercial / informational / curiosity / etc.) so the user
 * can pick by intent, not just by which one looks shiniest.
 *
 * Tier gating
 * ───────────
 * The button itself only renders for `tier === 'pro'`. The `<Section>`
 * caller decides whether to show the upsell CTA in its place. This way
 * the editor never has a non-functional button visible.
 *
 * Cost guard
 * ──────────
 * Server caches per (post × content-hash × keyword × top-3) for 7 days,
 * so re-clicks within a week are free. We surface that to the user
 * explicitly ("cached") so power users learn the behavior.
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
	const [state, setState] = useState({ status: 'idle', data: null, error: null });

	const canRequest =
		!!focusKeyword &&
		focusKeyword.trim().length >= 2 &&
		!!contentExcerpt &&
		String(contentExcerpt).length >= 50;

	const fetchRewrites = useCallback(() => {
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
				} else {
					setState({ status: 'error', data: null, error: 'No rewrites returned' });
				}
			})
			.catch((err) => {
				setState({ status: 'error', data: null, error: err?.message || 'Network error' });
			});
	}, [postId, focusKeyword, currentTitle, currentMeta, contentExcerpt, competitorTitles]);

	const handleOpen = () => {
		setOpen(true);
		if (state.status === 'idle' || state.status === 'error') {
			fetchRewrites();
		}
	};

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
						? __('Generate 3 AI-written title + meta options', 'ebq-seo')
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
				{state.status === 'loading' ? (
					<p className="ebq-help">
						<span className="ebq-spinner" />{' '}
						{__('Asking the model for 3 ranked rewrites…', 'ebq-seo')}
					</p>
				) : null}

				{state.status === 'error' ? (
					<>
						<p className="ebq-help" style={{ color: 'var(--ebq-bad-text)' }}>
							{state.error}
						</p>
						<Button size="sm" onClick={fetchRewrites}>
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
