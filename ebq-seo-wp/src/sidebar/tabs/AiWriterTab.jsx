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
	const [pick, setPick] = useState({ h1: '', h1Mode: 'suggested', h2_outline: {}, subtopics: {}, paa: {}, gap_topics: {}, competitor_subtopics: {} });
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
				setPick({
					h1: (inner.brief?.suggested_h1 || ''),
					h1Mode: 'suggested',
					h2_outline: tickAll(inner.brief?.suggested_h2_outline),
					subtopics: tickAll(inner.brief?.subtopics),
					paa: tickAll(inner.brief?.people_also_ask),
					gap_topics: tickAll(inner.gaps?.missing_subtopics),
					competitor_subtopics: tickAll(inner.gaps?.competitor_subtopics),
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
		const selected = {
			h1: pick.h1Mode === 'none' ? '' : (pick.h1 || ''),
			h2_outline: ticked(pick.h2_outline),
			subtopics: ticked(pick.subtopics),
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
		<div className="ebq-stack">
			<Section
				title={__('AI Writer', 'ebq-seo')}
				icon={<IconSparkle />}
				aside={step === 'ready' ? (
					<Button size="sm" variant="ghost" onClick={goBackToSelection}>{__('Edit selection', 'ebq-seo')}</Button>
				) : null}
			>
				{step === 'idle' ? (
					<>
						<p className="ebq-help" style={{ marginTop: 0 }}>
							{__('Step 1: pull your content brief and the topical-gap analysis. Then pick which suggestions you want the writer to use. Step 2: review section-level proposals and approve.', 'ebq-seo')}
						</p>
						<Button variant="primary" onClick={fetchPlan}>
							<IconSparkle /> {__('Get suggestions', 'ebq-seo')}
						</Button>
					</>
				) : null}

				{step === 'planning' ? (
					<p className="ebq-help">
						<span className="ebq-spinner" />{' '}
						{__('Loading brief + topical gaps…', 'ebq-seo')}
					</p>
				) : null}

				{step === 'plan-error' ? (
					<>
						<p className="ebq-help" style={{ color: 'var(--ebq-bad-text)' }}>{planError}</p>
						<Button size="sm" onClick={fetchPlan}>{__('Retry', 'ebq-seo')}</Button>
					</>
				) : null}

				{step === 'selecting' && plan ? (
					<SelectionPanel plan={plan} pick={pick} setPick={setPick} onGenerate={generate} />
				) : null}

				{step === 'generating' ? (
					<p className="ebq-help">
						<span className="ebq-spinner" />{' '}
						{__('Drafting proposals from your selection…', 'ebq-seo')}
					</p>
				) : null}

				{step === 'gen-error' ? (
					<>
						<p className="ebq-help" style={{ color: 'var(--ebq-bad-text)' }}>{genError}</p>
						<div className="ebq-row" style={{ display: 'flex', gap: 6 }}>
							<Button size="sm" variant="primary" onClick={generate}>{__('Retry', 'ebq-seo')}</Button>
							<Button size="sm" variant="ghost" onClick={goBackToSelection}>{__('Back to selection', 'ebq-seo')}</Button>
						</div>
					</>
				) : null}

				{step === 'ready' && data ? (
					<>
						{data.summary ? (
							<p className="ebq-help" style={{ marginTop: 0 }}>{data.summary}</p>
						) : null}

						<DiagnosticsRow diag={data.diagnostics} />

						<div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap', marginBottom: 8 }}>
							<SourcesUsedRow used={data.sources_used} />
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

						<div className="ebq-row" style={{ marginTop: 12, display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
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
						{data.cached ? (
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

/* ────────────────── selection panel ──────────────────────── */

function SelectionPanel({ plan, pick, setPick, onGenerate }) {
	const briefAvail = !!plan?.brief?.available;
	const gapsAvail = !!plan?.gaps?.available;

	const toggle = (group, key) => {
		setPick((p) => ({ ...p, [group]: { ...p[group], [key]: !p[group]?.[key] } }));
	};
	const setAll = (group, items, val) => {
		setPick((p) => ({ ...p, [group]: Object.fromEntries((items || []).map((k) => [k, val])) }));
	};

	const totalPicked =
		(pick.h1 && pick.h1Mode !== 'none' ? 1 : 0) +
		Object.values(pick.h2_outline).filter(Boolean).length +
		Object.values(pick.subtopics).filter(Boolean).length +
		Object.values(pick.paa).filter(Boolean).length +
		Object.values(pick.gap_topics).filter(Boolean).length +
		Object.values(pick.competitor_subtopics).filter(Boolean).length;

	return (
		<div className="ebq-stack" style={{ gap: 14 }}>
			{!briefAvail && !gapsAvail ? (
				<p className="ebq-help">
					{__('No brief or gaps data available — try generating those tabs first, or just click Generate to let the writer work from your existing post.', 'ebq-seo')}
				</p>
			) : null}

			{briefAvail ? (
				<>
					{plan.brief.suggested_h1 ? (
						<SelectionGroup title={__('H1 (page title in body)', 'ebq-seo')}>
							<label className="ebq-text-xs" style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
								<input
									type="radio"
									checked={pick.h1Mode === 'suggested'}
									onChange={() => setPick((p) => ({ ...p, h1Mode: 'suggested', h1: plan.brief.suggested_h1 }))}
								/>
								<span><strong>{plan.brief.suggested_h1}</strong> <span className="ebq-text-soft">— {__('suggested', 'ebq-seo')}</span></span>
							</label>
							<label className="ebq-text-xs" style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 4 }}>
								<input
									type="radio"
									checked={pick.h1Mode === 'custom'}
									onChange={() => setPick((p) => ({ ...p, h1Mode: 'custom' }))}
								/>
								<input
									type="text"
									className="ebq-input"
									placeholder={__('Custom H1…', 'ebq-seo')}
									value={pick.h1Mode === 'custom' ? pick.h1 : ''}
									onFocus={() => setPick((p) => ({ ...p, h1Mode: 'custom' }))}
									onChange={(e) => setPick((p) => ({ ...p, h1Mode: 'custom', h1: e.target.value }))}
									style={{ flex: 1 }}
								/>
							</label>
							<label className="ebq-text-xs" style={{ display: 'flex', alignItems: 'center', gap: 6, marginTop: 4 }}>
								<input
									type="radio"
									checked={pick.h1Mode === 'none'}
									onChange={() => setPick((p) => ({ ...p, h1Mode: 'none' }))}
								/>
								<span>{__('Don\'t add an H1', 'ebq-seo')}</span>
							</label>
						</SelectionGroup>
					) : null}

					<SelectionGroup
						title={__('Suggested H2 outline', 'ebq-seo')}
						items={plan.brief.suggested_h2_outline}
						picks={pick.h2_outline}
						onToggle={(k) => toggle('h2_outline', k)}
						onAll={(val) => setAll('h2_outline', plan.brief.suggested_h2_outline, val)}
					/>

					<SelectionGroup
						title={__('Subtopics to cover', 'ebq-seo')}
						items={plan.brief.subtopics}
						picks={pick.subtopics}
						onToggle={(k) => toggle('subtopics', k)}
						onAll={(val) => setAll('subtopics', plan.brief.subtopics, val)}
					/>

					<SelectionGroup
						title={__('People also ask', 'ebq-seo')}
						items={plan.brief.people_also_ask}
						picks={pick.paa}
						onToggle={(k) => toggle('paa', k)}
						onAll={(val) => setAll('paa', plan.brief.people_also_ask, val)}
					/>
				</>
			) : (
				<p className="ebq-help" style={{ margin: 0 }}>{__('Brief unavailable for this keyword.', 'ebq-seo')}</p>
			)}

			{gapsAvail ? (
				<>
					<SelectionGroup
						title={__('Subtopics to add (missing vs. top SERP)', 'ebq-seo')}
						items={plan.gaps.missing_subtopics}
						picks={pick.gap_topics}
						onToggle={(k) => toggle('gap_topics', k)}
						onAll={(val) => setAll('gap_topics', plan.gaps.missing_subtopics, val)}
					/>

					<SelectionGroup
						title={__('Subtopics covered by top 5', 'ebq-seo')}
						items={plan.gaps.competitor_subtopics}
						picks={pick.competitor_subtopics}
						onToggle={(k) => toggle('competitor_subtopics', k)}
						onAll={(val) => setAll('competitor_subtopics', plan.gaps.competitor_subtopics, val)}
					/>
				</>
			) : (
				<p className="ebq-help" style={{ margin: 0 }}>
					{__('Topical-gaps unavailable — needs ≥200 chars of existing content to compare against the SERP.', 'ebq-seo')}
				</p>
			)}

			<div className="ebq-row" style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap', marginTop: 4 }}>
				<Button variant="primary" onClick={onGenerate}>
					<IconSparkle /> {sprintf(__('Generate from %d selection(s)', 'ebq-seo'), totalPicked)}
				</Button>
				<span className="ebq-text-xs ebq-text-soft">
					{__('The writer will also propose improvements to existing content.', 'ebq-seo')}
				</span>
			</div>
		</div>
	);
}

function SelectionGroup({ title, items, picks, onToggle, onAll, children }) {
	const list = Array.isArray(items) ? items : [];
	if (children) {
		return (
			<fieldset style={{ border: 'none', padding: 0, margin: 0 }}>
				<legend className="ebq-text-xs" style={{ fontWeight: 600, marginBottom: 4 }}>{title}</legend>
				{children}
			</fieldset>
		);
	}
	if (list.length === 0) return null;
	const tickedCount = Object.values(picks || {}).filter(Boolean).length;
	return (
		<fieldset style={{ border: 'none', padding: 0, margin: 0 }}>
			<legend className="ebq-text-xs" style={{ fontWeight: 600, marginBottom: 4 }}>
				{title} <span className="ebq-text-soft">({tickedCount}/{list.length})</span>
			</legend>
			<div style={{ display: 'flex', gap: 6, marginBottom: 4 }}>
				<button type="button" className="ebq-link" onClick={() => onAll(true)}>{__('All', 'ebq-seo')}</button>
				<span className="ebq-text-soft">·</span>
				<button type="button" className="ebq-link" onClick={() => onAll(false)}>{__('None', 'ebq-seo')}</button>
			</div>
			<div style={{ display: 'flex', flexDirection: 'column', gap: 3 }}>
				{list.map((item) => (
					<label key={item} className="ebq-text-xs" style={{ display: 'flex', alignItems: 'flex-start', gap: 6, cursor: 'pointer' }}>
						<input
							type="checkbox"
							checked={!!picks?.[item]}
							onChange={() => onToggle(item)}
							style={{ marginTop: 2 }}
						/>
						<span>{item}</span>
					</label>
				))}
			</div>
		</fieldset>
	);
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
		<div style={{ display: 'flex', flexDirection: 'column', gap: 4, marginBottom: 8 }}>
			<span className="ebq-text-xs" style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
				<Pill tone={linkRow.tone}>{linkRow.text}</Pill>
			</span>
			<span className="ebq-text-xs ebq-text-soft" style={{ fontSize: 11 }}>
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
