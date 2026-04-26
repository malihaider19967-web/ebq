import { useState, useCallback, useEffect, useMemo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { dispatch, select } from '@wordpress/data';
import { parse as parseBlocks, rawHandler } from '@wordpress/blocks';
import { Section, Button, EmptyState, Pill, NeedsSetup } from '../components/primitives';
import { IconSparkle } from '../components/icons';
import { useEditorContext, usePostMeta, publicConfig } from '../hooks/useEditorContext';
import { useTier } from '../hooks/useTier';

/**
 * AI Writer tab (Pro).
 *
 * Inputs the writer pulls together (any subset is fine):
 *   • Existing post content (current_html) — read from the editor.
 *   • AI content brief — fetched server-side from the existing service,
 *     7d cached so this is free if the user already opened the Brief tab.
 *   • Topical-coverage gaps — same; cached 7d.
 *
 * The model returns up to 8 section proposals, each tagged add / edit /
 * replace, with a verbatim slice of the post to diff against (for
 * edits) and an HTML proposal. The user approves per section, then
 * clicks Apply — we stage the merged HTML in the active editor (Gutenberg
 * core/editor store, or TinyMCE/textarea in classic mode) so the user
 * still hits Save/Update normally to persist.
 */
export default function AiWriterTab() {
	const ctx = useEditorContext();
	const { get } = usePostMeta();
	const cfg = publicConfig();
	const tier = useTier();

	const focusKw = (get('_ebq_focus_keyword', '') || '').trim();

	const [state, setState] = useState({ status: 'idle', data: null, error: null });
	// Per-section approval state, keyed by section.id. true = approved.
	const [approved, setApproved] = useState({});
	const [applyState, setApplyState] = useState({ status: 'idle', message: '' });

	const generate = useCallback(() => {
		if (!focusKw || focusKw.length < 2) return;
		setState({ status: 'loading', data: null, error: null });
		setApplyState({ status: 'idle', message: '' });
		apiFetch({
			path: `/ebq/v1/ai-writer/${ctx.postId}`,
			method: 'POST',
			data: {
				focus_keyword: focusKw,
				current_html: String(ctx.content || '').slice(0, 200000),
			},
		})
			.then((res) => {
				const inner = res?.writer || {};
				if (inner?.ok === false || res?.ok === false) {
					setState({
						status: 'error',
						data: null,
						error: inner?.message || inner?.error || res?.message || res?.error || 'Failed',
					});
					return;
				}
				const sections = Array.isArray(inner?.sections) ? inner.sections : [];
				if (!sections.length) {
					setState({ status: 'error', data: null, error: 'No proposals returned' });
					return;
				}
				// Default: every section approved. User unchecks what they don't want.
				const next = {};
				sections.forEach((s) => { next[s.id] = true; });
				setApproved(next);
				setState({ status: 'ready', data: inner, error: null });
			})
			.catch((err) => setState({ status: 'error', data: null, error: err?.message || 'Network error' }));
	}, [ctx.postId, ctx.content, focusKw]);

	const sections = state.data?.sections || [];
	const approvedSections = useMemo(
		() => sections.filter((s) => approved[s.id]),
		[sections, approved],
	);
	const allApproved = sections.length > 0 && approvedSections.length === sections.length;
	const noneApproved = approvedSections.length === 0;

	const handleToggle = useCallback((id) => {
		setApproved((a) => ({ ...a, [id]: !a[id] }));
	}, []);
	const handleAll = useCallback((val) => {
		setApproved(() => {
			const next = {};
			(state.data?.sections || []).forEach((s) => { next[s.id] = val; });
			return next;
		});
	}, [state.data]);

	const handleApply = useCallback(() => {
		if (approvedSections.length === 0) return;
		setApplyState({ status: 'pending', message: '' });
		try {
			const plan = buildMergePlan(ctx.content || '', approvedSections);
			writeContentToEditor(plan);
			setApplyState({
				status: 'ok',
				message: sprintf(
					__('Applied %d section(s). Click Save/Update to publish the changes.', 'ebq-seo'),
					approvedSections.length,
				),
			});
		} catch (e) {
			setApplyState({ status: 'error', message: e?.message || 'Apply failed' });
		}
	}, [approvedSections, ctx.content]);

	if (tier !== 'pro') {
		return (
			<div className="ebq-stack">
				<Section title={__('AI Writer', 'ebq-seo')} icon={<IconSparkle />}>
					<EmptyState
						icon={<IconSparkle />}
						title={__('Pro feature', 'ebq-seo')}
						sub={__('Combine your content brief, the topical-gap analysis, and your existing post into AI-generated section proposals you can review and approve one at a time.', 'ebq-seo')}
					>
						<a
							className="ebq-btn ebq-btn--primary ebq-btn--sm"
							href={cfg.appBase ? `${cfg.appBase}/settings` : '#'}
							target="_blank"
							rel="noopener noreferrer"
						>
							{__('Upgrade to Pro', 'ebq-seo')} →
						</a>
					</EmptyState>
				</Section>
			</div>
		);
	}

	if (!focusKw || focusKw.length < 2) {
		return (
			<div className="ebq-stack">
				<Section title={__('AI Writer', 'ebq-seo')} icon={<IconSparkle />}>
					<NeedsSetup
						feature={__('Focus keyphrase required', 'ebq-seo')}
						why={__('The writer needs a target keyword to know what to optimize the post for.', 'ebq-seo')}
						fix={__('Set a focus keyphrase on the SEO tab, then come back here.', 'ebq-seo')}
						tone="warn"
					/>
				</Section>
			</div>
		);
	}

	return (
		<div className="ebq-stack">
			<Section
				title={__('AI Writer', 'ebq-seo')}
				icon={<IconSparkle />}
				aside={state.status === 'ready' ? (
					<Button size="sm" variant="ghost" onClick={generate}>{__('Regenerate', 'ebq-seo')}</Button>
				) : null}
			>
				{state.status === 'idle' ? (
					<>
						<p className="ebq-help" style={{ marginTop: 0 }}>
							{__('We pull your content brief, the topical-coverage gaps vs. top SERP, and your current post — then propose specific section-level changes you can approve one at a time.', 'ebq-seo')}
						</p>
						<Button variant="primary" onClick={generate}>
							<IconSparkle /> {__('Generate proposals', 'ebq-seo')}
						</Button>
					</>
				) : null}

				{state.status === 'loading' ? (
					<p className="ebq-help">
						<span className="ebq-spinner" />{' '}
						{__('Pulling brief + gaps and drafting proposals…', 'ebq-seo')}
					</p>
				) : null}

				{state.status === 'error' ? (
					<>
						<p className="ebq-help" style={{ color: 'var(--ebq-bad-text)' }}>{state.error}</p>
						<Button size="sm" onClick={generate}>{__('Retry', 'ebq-seo')}</Button>
					</>
				) : null}

				{state.status === 'ready' && state.data ? (
					<>
						{state.data.summary ? (
							<p className="ebq-help" style={{ marginTop: 0 }}>{state.data.summary}</p>
						) : null}

						<div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap', marginBottom: 8 }}>
							<SourcesUsedRow used={state.data.sources_used} />
							<div style={{ flex: 1 }} />
							<Button
								size="sm"
								variant="ghost"
								onClick={() => handleAll(true)}
								disabled={allApproved}
							>
								{__('Approve all', 'ebq-seo')}
							</Button>
							<Button
								size="sm"
								variant="ghost"
								onClick={() => handleAll(false)}
								disabled={noneApproved}
							>
								{__('Reject all', 'ebq-seo')}
							</Button>
						</div>

						{sections.map((s) => (
							<SectionProposal
								key={s.id}
								section={s}
								approved={!!approved[s.id]}
								onToggle={() => handleToggle(s.id)}
							/>
						))}

						<div className="ebq-row" style={{ marginTop: 12, display: 'flex', gap: 8, alignItems: 'center' }}>
							<Button
								variant="primary"
								onClick={handleApply}
								disabled={approvedSections.length === 0 || applyState.status === 'pending'}
							>
								{applyState.status === 'pending'
									? __('Applying…', 'ebq-seo')
									: sprintf(__('Apply %d approved section(s)', 'ebq-seo'), approvedSections.length)}
							</Button>
							{applyState.message ? (
								<span
									className="ebq-text-xs"
									style={{ color: applyState.status === 'error' ? 'var(--ebq-bad-text)' : 'var(--ebq-good-text)' }}
								>
									{applyState.message}
								</span>
							) : null}
						</div>
						{state.data.cached ? (
							<p className="ebq-help" style={{ marginTop: 8, marginBottom: 0 }}>
								{__('Cached for 24h — re-clicks within today are free.', 'ebq-seo')}
							</p>
						) : null}
					</>
				) : null}
			</Section>
		</div>
	);
}

function SourcesUsedRow({ used }) {
	if (!used) return null;
	const tags = [];
	if (used.brief)   tags.push({ key: 'brief',   label: __('Brief', 'ebq-seo'),   tone: 'good' });
	if (used.gaps)    tags.push({ key: 'gaps',    label: __('Gaps', 'ebq-seo'),    tone: 'good' });
	if (used.content) tags.push({ key: 'content', label: __('Existing post', 'ebq-seo'), tone: 'good' });
	if (tags.length === 0) return null;
	return (
		<span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, fontSize: 11 }}>
			<span className="ebq-text-soft">{__('Using:', 'ebq-seo')}</span>
			{tags.map((t) => <Pill key={t.key} tone={t.tone}>{t.label}</Pill>)}
		</span>
	);
}

function SectionProposal({ section, approved, onToggle }) {
	const kindLabel = section.kind === 'add'
		? __('NEW', 'ebq-seo')
		: section.kind === 'edit'
			? __('EDIT', 'ebq-seo')
			: __('REPLACE ALL', 'ebq-seo');
	const kindTone = section.kind === 'add' ? 'good' : section.kind === 'edit' ? 'warn' : 'bad';

	return (
		<div
			style={{
				border: '1px solid var(--ebq-border, #e5e7eb)',
				borderRadius: 8,
				padding: 10,
				marginBottom: 8,
				opacity: approved ? 1 : 0.55,
				background: approved ? 'transparent' : 'rgba(0,0,0,0.02)',
			}}
		>
			<div style={{ display: 'flex', alignItems: 'flex-start', gap: 8 }}>
				<input
					type="checkbox"
					checked={approved}
					onChange={onToggle}
					style={{ marginTop: 3, cursor: 'pointer' }}
					aria-label={approved ? __('Reject this section', 'ebq-seo') : __('Approve this section', 'ebq-seo')}
				/>
				<div style={{ flex: 1, minWidth: 0 }}>
					<div
						style={{ display: 'flex', alignItems: 'center', gap: 6, flexWrap: 'wrap', cursor: 'pointer' }}
						onClick={onToggle}
						role="button"
						tabIndex={0}
						onKeyDown={(e) => { if (e.key === ' ' || e.key === 'Enter') { e.preventDefault(); onToggle(); } }}
					>
						<Pill tone={kindTone}>{kindLabel}</Pill>
						<strong style={{ fontSize: 12 }}>{section.title}</strong>
						{(section.source_tags || []).map((t) => (
							<span key={t} className="ebq-text-xs ebq-text-soft" style={{ fontSize: 10 }}>
								#{t}
							</span>
						))}
					</div>
					{section.rationale ? (
						<p className="ebq-text-xs ebq-text-soft" style={{ margin: '4px 0 6px' }}>
							{section.rationale}
						</p>
					) : null}
					{section.kind === 'edit' && section.current_html ? (
						<DiffPair
							leftLabel={__('Current', 'ebq-seo')}
							leftHtml={section.current_html}
							rightLabel={__('Proposed', 'ebq-seo')}
							rightHtml={section.proposed_html}
						/>
					) : (
						<HtmlBlock label={__('Proposed', 'ebq-seo')} html={section.proposed_html} tone="good" />
					)}
				</div>
			</div>
		</div>
	);
}

function DiffPair({ leftLabel, leftHtml, rightLabel, rightHtml }) {
	return (
		<div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 6, marginTop: 4 }}>
			<HtmlBlock label={leftLabel} html={leftHtml} tone="bad" />
			<HtmlBlock label={rightLabel} html={rightHtml} tone="good" />
		</div>
	);
}

function HtmlBlock({ label, html, tone }) {
	const color = tone === 'good' ? 'var(--ebq-good, #16a34a)' : tone === 'bad' ? 'var(--ebq-bad, #dc2626)' : 'var(--ebq-text-soft)';
	return (
		<div
			style={{
				border: `1px solid ${color}`,
				borderRadius: 6,
				padding: 6,
				fontSize: 11,
				maxHeight: 220,
				overflow: 'auto',
				background: 'var(--ebq-card, #fff)',
			}}
		>
			<div style={{ fontSize: 10, fontWeight: 600, color, marginBottom: 4, textTransform: 'uppercase', letterSpacing: 0.4 }}>{label}</div>
			<div className="ebq-ai-rendered" dangerouslySetInnerHTML={{ __html: html }} />
		</div>
	);
}

/* ────────────────── apply helpers ──────────────────────────── */

/**
 * Build the apply plan from approved sections.
 *
 *  - kind=replace → overrides everything (first one wins).
 *  - kind=edit    → if section.current_html appears verbatim in the
 *                   current post HTML, swap it. Otherwise append (Gutenberg
 *                   block markers in the source rarely match the model's
 *                   plain-text excerpt; fall back rather than drop).
 *  - kind=add     → appended in order.
 *
 * Returns:
 *   { mode: 'replace', replaceHtml }
 *   { mode: 'merge', editedBaseHtml, additiveHtmls: list<string> }
 *
 * `additiveHtmls` is a LIST not a joined string — the Gutenberg apply
 * path converts each section to blocks INDIVIDUALLY so the editor ends
 * up with one or more blocks per section instead of every section
 * collapsing into a single Classic block.
 */
function buildMergePlan(currentHtml, sections) {
	const replace = sections.find((s) => s.kind === 'replace');
	if (replace) {
		return { mode: 'replace', replaceHtml: String(replace.proposed_html || '') };
	}

	let editedBase = String(currentHtml || '');
	const additiveHtmls = [];
	for (const s of sections) {
		if (s.kind === 'edit' && s.current_html) {
			const idx = editedBase.indexOf(s.current_html);
			if (idx !== -1) {
				editedBase = editedBase.slice(0, idx) + (s.proposed_html || '') + editedBase.slice(idx + s.current_html.length);
				continue;
			}
			additiveHtmls.push(s.proposed_html || '');
			continue;
		}
		if (s.kind === 'add') {
			additiveHtmls.push(s.proposed_html || '');
		}
	}
	return { mode: 'merge', editedBaseHtml: editedBase, additiveHtmls };
}

/**
 * Push the change into whichever editor is active.
 *
 * Gutenberg quirk that bit us before: `editPost({ content })` updates
 * the post.content attribute but the block-editor canonical state is
 * the BLOCK TREE, not the content string — so appending raw HTML to
 * the content string had no visible effect on the block tree.
 *
 * The reliable pattern, the one this implementation now uses:
 *   1. Read the LIVE block tree from `core/block-editor`.
 *   2. For each additive section, run `rawHandler({ HTML })` which
 *      converts a contiguous HTML string into proper block(s)
 *      (Heading + Paragraph + List + …). Crucially we call rawHandler
 *      ONCE PER SECTION rather than once on the joined HTML, so
 *      sections never get lumped into a single Classic block.
 *   3. Concatenate the new blocks onto the live block tree and call
 *      `resetBlocks` on the result.
 *
 * Edits don't go through the block tree (their current_html almost
 * never matches the serialized block markers exactly); they fall back
 * to "append" via additiveHtmls in the merge plan above.
 */
function writeContentToEditor(plan) {
	const isClassic = typeof window !== 'undefined' && window.__EBQ_CLASSIC__ === true;

	if (isClassic) {
		// Build the final HTML once for TinyMCE / textarea.
		const html = plan.mode === 'replace'
			? plan.replaceHtml
			: (plan.editedBaseHtml.replace(/\s+$/, '') + (plan.additiveHtmls.length ? '\n\n' + plan.additiveHtmls.join('\n\n') : ''));
		const tm = window.tinymce;
		if (tm && tm.activeEditor && !tm.activeEditor.isHidden()) {
			tm.activeEditor.setContent(html);
			tm.activeEditor.fire('Change');
		}
		const ta = document.getElementById('content');
		if (ta) {
			ta.value = html;
			ta.dispatchEvent(new Event('input', { bubbles: true }));
		}
		return;
	}

	// Gutenberg.
	const blockEditor = dispatch('core/block-editor');
	if (!blockEditor || typeof blockEditor.resetBlocks !== 'function') {
		throw new Error(__('Block editor unavailable.', 'ebq-seo'));
	}

	if (plan.mode === 'replace') {
		// Whole-post replacement. rawHandler is more forgiving than parse
		// for raw HTML without block markers (the model never emits them).
		const parsed = rawHandler({ HTML: plan.replaceHtml || '' });
		blockEditor.resetBlocks(parsed);
		return;
	}

	// Merge mode. Read the LIVE block tree (preserves clientIds + any
	// edits the user already made elsewhere), then append one rawHandler
	// run per additive section so each section becomes its own block(s).
	const liveBlocks = (select('core/block-editor')?.getBlocks?.() || []).slice();
	const newBlocks = [];
	for (const html of plan.additiveHtmls) {
		const trimmed = String(html || '').trim();
		if (!trimmed) continue;
		const parsed = rawHandler({ HTML: trimmed });
		if (Array.isArray(parsed) && parsed.length) {
			newBlocks.push(...parsed);
		}
	}
	if (newBlocks.length === 0 && plan.editedBaseHtml === '') {
		throw new Error(__('Nothing to apply.', 'ebq-seo'));
	}
	blockEditor.resetBlocks([...liveBlocks, ...newBlocks]);
}
