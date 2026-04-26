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

	// step = 'idle' | 'planning' | 'plan-error' | 'selecting' | 'generating' | 'ready' | 'gen-error'
	const [step, setStep] = useState('idle');
	const [planError, setPlanError] = useState('');
	const [genError, setGenError] = useState('');
	const [plan, setPlan] = useState(null); // { brief: {...}, gaps: {...} }
	// `topics` merges suggested_h2_outline + subtopics — same conceptual
	// signal at different granularity. We track origin per item so the
	// writer prompt still receives both buckets populated downstream.
	const [pick, setPick] = useState({ h1: '', h1Mode: 'suggested', topics: {}, paa: {}, gap_topics: {}, competitor_subtopics: {} });
	// Editable item lists — lifted out of `plan` so the user can rewrite
	// individual suggestions inline before generation.
	const [lists, setLists] = useState({ topics: [], paa: [], gap_topics: [], competitor_subtopics: [] });
	const [data, setData] = useState(null); // writer response
	// Per-section approval state, keyed by section.id. true = approved.
	const [approved, setApproved] = useState({});
	const [applyState, setApplyState] = useState({ status: 'idle', message: '' });

	const fetchPlan = useCallback(() => {
		if (!focusKw || focusKw.length < 2) return;
		setStep('planning');
		setPlanError('');
		apiFetch({
			path: `/ebq/v1/ai-writer/${ctx.postId}/plan`,
			method: 'POST',
			data: {
				focus_keyword: focusKw,
				current_html: String(ctx.content || '').slice(0, 200000),
			},
		})
			.then((res) => {
				const inner = res?.plan;
				if (res?.ok === false || res?.error || !inner) {
					setPlanError(res?.message || res?.error || 'Failed to load suggestions.');
					setStep('plan-error');
					return;
				}
				setPlan(inner);

				// Pre-tick everything by default — the user removes what they
				// don't want rather than ticking each item from scratch.
				const tickAll = (arr) => (Array.isArray(arr) ? Object.fromEntries(arr.map((v) => [v, true])) : {});
				// Merge H2 outline + subtopics into a single ordered list,
				// deduping case-insensitively. H2 outline items go first
				// (the narrative spine); subtopics fill in granular ideas.
				const mergedTopics = mergeTopics(
					inner.brief?.suggested_h2_outline,
					inner.brief?.subtopics,
				);
				const seenAfterTopics = new Set(mergedTopics.map(normTopic));
				const dropDup = (arr) => (Array.isArray(arr) ? arr.filter((v) => {
					const k = normTopic(v);
					if (!k || seenAfterTopics.has(k)) return false;
					seenAfterTopics.add(k);
					return true;
				}) : []);
				const paaList = dropDup(inner.brief?.people_also_ask);
				const gapTopicsList = dropDup(inner.gaps?.missing_subtopics);
				const competitorList = dropDup(inner.gaps?.competitor_subtopics);
				setLists({
					topics: mergedTopics,
					paa: paaList,
					gap_topics: gapTopicsList,
					competitor_subtopics: competitorList,
				});
				setPick({
					h1: (inner.brief?.suggested_h1 || ''),
					h1Mode: 'suggested',
					topics: tickAll(mergedTopics),
					paa: tickAll(paaList),
					gap_topics: tickAll(gapTopicsList),
					competitor_subtopics: tickAll(competitorList),
				});
				setStep('selecting');
			})
			.catch((err) => {
				setPlanError(err?.message || 'Network error');
				setStep('plan-error');
			});
	}, [ctx.postId, ctx.content, focusKw]);

	const generate = useCallback(() => {
		if (!focusKw || focusKw.length < 2) return;
		setStep('generating');
		setGenError('');
		setApplyState({ status: 'idle', message: '' });

		const ticked = (obj) => Object.entries(obj || {}).filter(([, v]) => v).map(([k]) => k);
		// Merged "Topics to cover" in the UI maps to BOTH backend buckets
		// (suggested_outline + subtopics). The writer's applySelection
		// filters both lists to the same picked set — works either way.
		const pickedTopics = ticked(pick.topics);
		const selected = {
			h1: pick.h1Mode === 'none' ? '' : (pick.h1 || ''),
			h2_outline: pickedTopics,
			subtopics: pickedTopics,
			paa: ticked(pick.paa),
			gap_topics: ticked(pick.gap_topics),
			competitor_subtopics: ticked(pick.competitor_subtopics),
		};

		apiFetch({
			path: `/ebq/v1/ai-writer/${ctx.postId}`,
			method: 'POST',
			data: {
				focus_keyword: focusKw,
				current_html: String(ctx.content || '').slice(0, 200000),
				url: ctx.postLink || '',
				selected,
			},
		})
			.then((res) => {
				const inner = res?.writer || {};
				if (inner?.ok === false || res?.ok === false) {
					setGenError(inner?.message || inner?.error || res?.message || res?.error || 'Failed');
					setStep('gen-error');
					return;
				}
				const sections = Array.isArray(inner?.sections) ? inner.sections : [];
				if (!sections.length) {
					setGenError('No proposals returned');
					setStep('gen-error');
					return;
				}
				const next = {};
				sections.forEach((s) => { next[s.id] = true; });
				setApproved(next);
				setData(inner);
				setStep('ready');
			})
			.catch((err) => {
				setGenError(err?.message || 'Network error');
				setStep('gen-error');
			});
	}, [ctx.postId, ctx.content, ctx.postLink, focusKw, pick]);

	const sections = data?.sections || [];
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
			(data?.sections || []).forEach((s) => { next[s.id] = val; });
			return next;
		});
	}, [data]);

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

	const goBackToSelection = () => { setStep('selecting'); };

	return (
		<div className="ebq-stack ebq-aiw">
			<Section
				title={__('AI Writer', 'ebq-seo')}
				icon={<IconSparkle />}
				aside={step === 'ready' ? (
					<Button size="sm" variant="ghost" onClick={goBackToSelection}>{__('Edit selection', 'ebq-seo')}</Button>
				) : null}
			>
				{step === 'idle' ? (
					<div className="ebq-aiw-hero">
						<p className="ebq-aiw-hero__title">
							<IconSparkle /> {__('Draft from your brief + SERP gaps', 'ebq-seo')}
						</p>
						<p className="ebq-aiw-hero__sub">
							{__('Step 1: load your content brief and topical-gap analysis. Pick what you want the writer to use. Step 2: review section proposals and approve the ones you like.', 'ebq-seo')}
						</p>
						<div className="ebq-aiw-hero__cta">
							<Button variant="primary" onClick={fetchPlan}>
								<IconSparkle /> {__('Get suggestions', 'ebq-seo')}
							</Button>
						</div>
					</div>
				) : null}

				{step === 'planning' ? (
					<div className="ebq-aiw-loading">
						<span className="ebq-spinner" />
						{__('Loading brief + topical gaps…', 'ebq-seo')}
					</div>
				) : null}

				{step === 'plan-error' ? (
					<div className="ebq-aiw-error">
						<div>{planError}</div>
						<div className="ebq-aiw-error__actions">
							<Button size="sm" onClick={fetchPlan}>{__('Retry', 'ebq-seo')}</Button>
						</div>
					</div>
				) : null}

				{step === 'selecting' && plan ? (
					<SelectionPanel
						plan={plan}
						lists={lists}
						setLists={setLists}
						pick={pick}
						setPick={setPick}
						onGenerate={generate}
					/>
				) : null}

				{step === 'generating' ? (
					<div className="ebq-aiw-loading">
						<span className="ebq-spinner" />
						{__('Drafting proposals from your selection…', 'ebq-seo')}
					</div>
				) : null}

				{step === 'gen-error' ? (
					<div className="ebq-aiw-error">
						<div>{genError}</div>
						<div className="ebq-aiw-error__actions">
							<Button size="sm" variant="primary" onClick={generate}>{__('Retry', 'ebq-seo')}</Button>
							<Button size="sm" variant="ghost" onClick={goBackToSelection}>{__('Back to selection', 'ebq-seo')}</Button>
						</div>
					</div>
				) : null}

				{step === 'ready' && data ? (
					<>
						{data.summary ? (
							<p className="ebq-aiw-summary">{data.summary}</p>
						) : null}

						<DiagnosticsRow diag={data.diagnostics} />

						<div className="ebq-aiw-toolbar">
							<SourcesUsedRow used={data.sources_used} />
							<div className="ebq-aiw-toolbar__spacer" />
							<Button size="sm" variant="ghost" onClick={() => handleAll(true)} disabled={allApproved}>
								{__('Approve all', 'ebq-seo')}
							</Button>
							<Button size="sm" variant="ghost" onClick={() => handleAll(false)} disabled={noneApproved}>
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

						<div className="ebq-aiw-apply">
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
								<span className={`ebq-aiw-apply__msg ${applyState.status === 'error' ? 'ebq-aiw-apply__msg--bad' : 'ebq-aiw-apply__msg--good'}`}>
									{applyState.message}
								</span>
							) : null}
						</div>
						{data.cached ? (
							<p className="ebq-aiw-apply__cached">
								{__('Cached for 24h — re-clicks within today are free.', 'ebq-seo')}
							</p>
						) : null}
					</>
				) : null}
			</Section>
		</div>
	);
}

/* ────────────────── selection panel ──────────────────────── */

function SelectionPanel({ plan, lists, setLists, pick, setPick, onGenerate }) {
	const briefAvail = !!plan?.brief?.available;
	const gapsAvail = !!plan?.gaps?.available;

	// Items now come from `lists` (lifted state, editable). Dedupe is
	// already done in fetchPlan; the lists arrive non-overlapping.
	const mergedTopics = lists.topics || [];
	const dedupedPaa = lists.paa || [];
	const dedupedGapMissing = lists.gap_topics || [];
	const dedupedGapCompetitor = lists.competitor_subtopics || [];

	const toggle = (group, key) => {
		setPick((p) => ({ ...p, [group]: { ...p[group], [key]: !p[group]?.[key] } }));
	};
	const setAll = (group, items, val) => {
		setPick((p) => ({ ...p, [group]: Object.fromEntries((items || []).map((k) => [k, val])) }));
	};

	// Inline rename: swap `oldVal` for `newVal` in the items list AND
	// remap the picks map so the new key carries forward the prior
	// approval. Skip if the new value is empty / unchanged / would
	// duplicate an existing item in the same group.
	const renameItem = (group, oldVal, newVal) => {
		const next = String(newVal || '').trim();
		if (!next || next === oldVal) return;
		const list = Array.isArray(lists[group]) ? lists[group] : [];
		const lowerNext = normTopic(next);
		const conflict = list.some((v) => v !== oldVal && normTopic(v) === lowerNext);
		if (conflict) return;
		setLists((s) => ({
			...s,
			[group]: (s[group] || []).map((v) => (v === oldVal ? next : v)),
		}));
		setPick((p) => {
			const cur = p[group] || {};
			const wasOn = cur[oldVal];
			const nextPicks = { ...cur };
			delete nextPicks[oldVal];
			nextPicks[next] = wasOn === undefined ? true : wasOn;
			return { ...p, [group]: nextPicks };
		});
	};

	const totalPicked =
		(pick.h1 && pick.h1Mode !== 'none' ? 1 : 0) +
		Object.values(pick.topics || {}).filter(Boolean).length +
		Object.values(pick.paa || {}).filter(Boolean).length +
		Object.values(pick.gap_topics || {}).filter(Boolean).length +
		Object.values(pick.competitor_subtopics || {}).filter(Boolean).length;

	return (
		<div className="ebq-aiw-groups">
			{!briefAvail && !gapsAvail ? (
				<p className="ebq-aiw-empty">
					{__('No brief or gaps data available — try generating those tabs first, or just click Generate to let the writer work from your existing post.', 'ebq-seo')}
				</p>
			) : null}

			{briefAvail ? (
				<>
					{plan.brief.suggested_h1 ? (
						<SelectionGroup title={__('H1 (page title in body)', 'ebq-seo')}>
							<div className="ebq-aiw-h1">
								<label className="ebq-aiw-h1__option ebq-aiw-h1__option--suggested">
									<input
										type="radio"
										checked={pick.h1Mode === 'suggested'}
										onChange={() => setPick((p) => ({ ...p, h1Mode: 'suggested', h1: plan.brief.suggested_h1 }))}
									/>
									<strong>{plan.brief.suggested_h1}</strong>
									<span className="ebq-aiw-h1__option-tag">{__('suggested', 'ebq-seo')}</span>
								</label>
								<label className="ebq-aiw-h1__option">
									<input
										type="radio"
										checked={pick.h1Mode === 'custom'}
										onChange={() => setPick((p) => ({ ...p, h1Mode: 'custom' }))}
									/>
									<input
										type="text"
										className="ebq-aiw-h1__custom"
										placeholder={__('Write a custom H1…', 'ebq-seo')}
										value={pick.h1Mode === 'custom' ? pick.h1 : ''}
										onFocus={() => setPick((p) => ({ ...p, h1Mode: 'custom' }))}
										onChange={(e) => setPick((p) => ({ ...p, h1Mode: 'custom', h1: e.target.value }))}
									/>
								</label>
								<label className="ebq-aiw-h1__option">
									<input
										type="radio"
										checked={pick.h1Mode === 'none'}
										onChange={() => setPick((p) => ({ ...p, h1Mode: 'none' }))}
									/>
									<span>{__("Don't add an H1", 'ebq-seo')}</span>
								</label>
							</div>
						</SelectionGroup>
					) : null}

					<SelectionGroup
						title={__('Topics to cover', 'ebq-seo')}
						hint={__('Merged from your brief\'s suggested H2 outline and subtopics. Click any item to edit it before generation.', 'ebq-seo')}
						items={mergedTopics}
						picks={pick.topics}
						onToggle={(k) => toggle('topics', k)}
						onAll={(val) => setAll('topics', mergedTopics, val)}
						onEdit={(oldVal, newVal) => renameItem('topics', oldVal, newVal)}
					/>

					<SelectionGroup
						title={__('People also ask', 'ebq-seo')}
						hint={__('Click any question to edit it.', 'ebq-seo')}
						items={dedupedPaa}
						picks={pick.paa}
						onToggle={(k) => toggle('paa', k)}
						onAll={(val) => setAll('paa', dedupedPaa, val)}
						onEdit={(oldVal, newVal) => renameItem('paa', oldVal, newVal)}
					/>
				</>
			) : (
				<p className="ebq-aiw-empty">{__('Brief unavailable for this keyword.', 'ebq-seo')}</p>
			)}

			{gapsAvail ? (
				<>
					<SelectionGroup
						title={__('Subtopics to add (missing vs. top SERP)', 'ebq-seo')}
						items={dedupedGapMissing}
						picks={pick.gap_topics}
						onToggle={(k) => toggle('gap_topics', k)}
						onAll={(val) => setAll('gap_topics', dedupedGapMissing, val)}
					/>

					<SelectionGroup
						title={__('Subtopics covered by top 5', 'ebq-seo')}
						items={dedupedGapCompetitor}
						picks={pick.competitor_subtopics}
						onToggle={(k) => toggle('competitor_subtopics', k)}
						onAll={(val) => setAll('competitor_subtopics', dedupedGapCompetitor, val)}
					/>
				</>
			) : (
				<p className="ebq-aiw-empty">
					{__('Topical-gaps unavailable — needs ≥200 chars of existing content to compare against the SERP.', 'ebq-seo')}
				</p>
			)}

			<div className="ebq-aiw-generate">
				<Button variant="primary" onClick={onGenerate}>
					<IconSparkle /> {sprintf(__('Generate from %d selection(s)', 'ebq-seo'), totalPicked)}
				</Button>
				<span className="ebq-aiw-generate__hint">
					{__('The writer will also propose improvements to existing content.', 'ebq-seo')}
				</span>
			</div>
		</div>
	);
}

function SelectionGroup({ title, hint, items, picks, onToggle, onAll, onEdit, children }) {
	const list = Array.isArray(items) ? items : [];
	if (children) {
		return (
			<fieldset className="ebq-aiw-group">
				<div className="ebq-aiw-group__head">
					<span className="ebq-aiw-group__title">{title}</span>
				</div>
				{children}
			</fieldset>
		);
	}
	if (list.length === 0) return null;
	const tickedCount = Object.values(picks || {}).filter(Boolean).length;
	return (
		<fieldset className="ebq-aiw-group">
			<div className="ebq-aiw-group__head">
				<span className="ebq-aiw-group__title">{title}</span>
				<span className="ebq-aiw-group__count">{tickedCount}/{list.length}</span>
				{onAll ? (
					<span className="ebq-aiw-group__bulk">
						<button type="button" onClick={() => onAll(true)}>{__('All', 'ebq-seo')}</button>
						<button type="button" onClick={() => onAll(false)}>{__('None', 'ebq-seo')}</button>
					</span>
				) : null}
			</div>
			{hint ? <p className="ebq-aiw-group__hint">{hint}</p> : null}
			<div>
				{list.map((item) => (
					<EditableRow
						key={item}
						item={item}
						checked={!!picks?.[item]}
						onToggle={() => onToggle(item)}
						onSave={onEdit ? (next) => onEdit(item, next) : null}
					/>
				))}
			</div>
		</fieldset>
	);
}

/**
 * One row in a SelectionGroup. Click the text to edit it inline; press
 * Enter (or click Save) to commit, Escape to cancel. Falls back to a
 * plain checkbox+label when `onSave` is not provided.
 */
function EditableRow({ item, checked, onToggle, onSave }) {
	const [editing, setEditing] = useState(false);
	const [draft, setDraft] = useState(item);

	if (!onSave) {
		return (
			<label className="ebq-aiw-row">
				<input type="checkbox" className="ebq-aiw-row__check" checked={checked} onChange={onToggle} />
				<span className="ebq-aiw-row__text">{item}</span>
			</label>
		);
	}

	if (editing) {
		const commit = () => {
			const trimmed = String(draft || '').trim();
			if (trimmed && trimmed !== item) onSave(trimmed);
			setEditing(false);
		};
		const cancel = () => { setDraft(item); setEditing(false); };
		return (
			<div className="ebq-aiw-row">
				<input type="checkbox" className="ebq-aiw-row__check" checked={checked} onChange={onToggle} />
				<input
					type="text"
					className="ebq-aiw-row__input"
					value={draft}
					onChange={(e) => setDraft(e.target.value)}
					onKeyDown={(e) => {
						if (e.key === 'Enter') { e.preventDefault(); commit(); }
						else if (e.key === 'Escape') { e.preventDefault(); cancel(); }
					}}
					onBlur={commit}
					autoFocus
				/>
			</div>
		);
	}

	return (
		<div className="ebq-aiw-row">
			<input type="checkbox" className="ebq-aiw-row__check" checked={checked} onChange={onToggle} />
			<button
				type="button"
				className="ebq-aiw-row__text"
				onClick={() => { setDraft(item); setEditing(true); }}
				title={__('Click to edit', 'ebq-seo')}
			>
				{item}
			</button>
			<svg className="ebq-aiw-row__edit-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
				<path d="M11.5 2.5l2 2L5 13H3v-2L11.5 2.5z" />
			</svg>
		</div>
	);
}

/* Topic-merging helpers: H2 outline (the structural narrative spine)
 * goes first, then subtopics that aren't already in the outline. */
function normTopic(s) {
	return String(s || '').trim().toLowerCase().replace(/\s+/g, ' ');
}
function mergeTopics(h2Outline, subtopics) {
	const seen = new Set();
	const out = [];
	const push = (item) => {
		if (typeof item !== 'string') return;
		const k = normTopic(item);
		if (!k || seen.has(k)) return;
		seen.add(k);
		out.push(item.trim());
	};
	(Array.isArray(h2Outline) ? h2Outline : []).forEach(push);
	(Array.isArray(subtopics) ? subtopics : []).forEach(push);
	return out;
}

function DiagnosticsRow({ diag }) {
	if (!diag) return null;
	const linkRow = (() => {
		if (diag.internal_links_available === 0) {
			return {
				tone: 'neutral',
				text: __('No internal-link targets available — connect Search Console with click history to surface candidates.', 'ebq-seo'),
			};
		}
		if (diag.internal_links_in_output >= diag.internal_links_available) {
			return {
				tone: 'good',
				text: sprintf(__('Internal links: %1$d of %2$d included.', 'ebq-seo'), diag.internal_links_in_output, diag.internal_links_available),
			};
		}
		return {
			tone: 'warn',
			text: sprintf(__('Internal links: %1$d of %2$d included — regenerate to retry.', 'ebq-seo'), diag.internal_links_in_output, diag.internal_links_available),
		};
	})();
	return (
		<div className="ebq-aiw-diag">
			<Pill tone={linkRow.tone}>{linkRow.text}</Pill>
			<span className="ebq-aiw-diag__meta">
				{sprintf(
					__('Sections: %1$d · PAA available: %2$d · gap topics: %3$d', 'ebq-seo'),
					diag.sections_returned || 0,
					diag.paa_questions_available || 0,
					diag.gaps_available || 0,
				)}
			</span>
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
		<span className="ebq-aiw-toolbar__sources">
			<span className="ebq-aiw-toolbar__sources-label">{__('Using:', 'ebq-seo')}</span>
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

	return (
		<div className={`ebq-aiw-section${approved ? '' : ' ebq-aiw-section--rejected'}`}>
			<div className="ebq-aiw-section__head">
				<input
					type="checkbox"
					className="ebq-aiw-section__check"
					checked={approved}
					onChange={onToggle}
					aria-label={approved ? __('Reject this section', 'ebq-seo') : __('Approve this section', 'ebq-seo')}
				/>
				<div className="ebq-aiw-section__body">
					<div className="ebq-aiw-section__meta">
						<span className={`ebq-aiw-kind ebq-aiw-kind--${section.kind}`}>{kindLabel}</span>
						<span className="ebq-aiw-section__title">{section.title}</span>
						{(section.source_tags || []).map((t) => (
							<span key={t} className="ebq-aiw-section__source-tag">#{t}</span>
						))}
					</div>
					{section.rationale ? (
						<p className="ebq-aiw-section__rationale">{section.rationale}</p>
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
		<div className="ebq-aiw-section__diff">
			<HtmlBlock label={leftLabel} html={leftHtml} tone="bad" />
			<HtmlBlock label={rightLabel} html={rightHtml} tone="good" />
		</div>
	);
}

function HtmlBlock({ label, html, tone }) {
	const toneClass = tone === 'good' ? 'ebq-aiw-html--good' : tone === 'bad' ? 'ebq-aiw-html--bad' : '';
	return (
		<div className={`ebq-aiw-html ${toneClass}`}>
			<div className="ebq-aiw-html__label">{label}</div>
			<div className="ebq-aiw-html__body" dangerouslySetInnerHTML={{ __html: html }} />
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
