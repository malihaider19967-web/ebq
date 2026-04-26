import { useState, useCallback, useEffect, useMemo, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Card, ErrorState, Pill, Button } from '../components/primitives';

/**
 * EBQ HQ → AI Writer.
 *
 * Standalone admin-page version of the writer (the sidebar/editor version
 * lives nowhere — this is the only place). Flow:
 *   1. Pick a post by search.
 *   2. Confirm focus keyword (defaults to the post's _ebq_focus_keyword
 *      meta or post title).
 *   3. Plan — fetch brief + topical-gaps; user curates which suggestions
 *      to feed to the writer.
 *   4. Generate — server runs Mistral with the curated inputs.
 *   5. Review — section-by-section approve/reject, inline edit.
 *   6. Apply — merged HTML written back via WP REST POST /wp/v2/posts/{id}.
 *
 * Schema suggestions (Article + FAQPage when Q&A detected, etc.) appear
 * after the proposals; selecting + applying writes them into the post's
 * `_ebq_schemas` meta in the same REST call.
 */
export default function AiWriterTab() {
	// ── post selection ───────────────────────────────────────────
	const [post, setPost] = useState(null); // { id, title, link, content, focusKw }
	const [postLoadingId, setPostLoadingId] = useState(null);
	const [searchTerm, setSearchTerm] = useState('');
	const [searchResults, setSearchResults] = useState(null); // null | array
	const [searching, setSearching] = useState(false);
	const debounceRef = useRef(null);

	// Search WP posts via core REST. Debounced.
	useEffect(() => {
		const term = searchTerm.trim();
		if (term.length < 2) {
			setSearchResults(null);
			return;
		}
		if (debounceRef.current) clearTimeout(debounceRef.current);
		debounceRef.current = setTimeout(async () => {
			setSearching(true);
			try {
				const res = await apiFetch({
					path: `/wp/v2/search?type=post&per_page=10&search=${encodeURIComponent(term)}`,
				});
				setSearchResults(Array.isArray(res) ? res : []);
			} catch (err) {
				setSearchResults([]);
			}
			setSearching(false);
		}, 300);
		return () => { if (debounceRef.current) clearTimeout(debounceRef.current); };
	}, [searchTerm]);

	const choosePost = useCallback(async (id) => {
		setPostLoadingId(id);
		try {
			const res = await apiFetch({ path: `/wp/v2/posts/${id}?context=edit&_embed=false` });
			const focus = (res?.meta?._ebq_focus_keyword || '').toString().trim();
			setPost({
				id: res.id,
				title: res?.title?.raw || res?.title?.rendered || '',
				link: res?.link || '',
				content: res?.content?.raw || '',
				focusKw: focus,
			});
			setFocusInput(focus);
			setSearchTerm('');
			setSearchResults(null);
			resetWriter();
		} catch (err) {
			alert(err?.message || __('Failed to load post.', 'ebq-seo'));
		} finally {
			setPostLoadingId(null);
		}
	}, []);

	const clearPost = () => {
		setPost(null);
		setFocusInput('');
		resetWriter();
	};

	// ── focus keyword + writer state ─────────────────────────────
	const [focusInput, setFocusInput] = useState('');

	// step = 'idle' | 'planning' | 'plan-error' | 'selecting' | 'generating' | 'ready' | 'gen-error'
	const [step, setStep] = useState('idle');
	const [planError, setPlanError] = useState('');
	const [genError, setGenError] = useState('');
	const [plan, setPlan] = useState(null);
	const [pick, setPick] = useState({ h1: '', h1Mode: 'suggested', topics: {}, paa: {}, gap_topics: {}, competitor_subtopics: {} });
	const [lists, setLists] = useState({ topics: [], paa: [], gap_topics: [], competitor_subtopics: [] });
	const [data, setData] = useState(null);
	const [approved, setApproved] = useState({});
	const [applyState, setApplyState] = useState({ status: 'idle', message: '' });

	const resetWriter = () => {
		setStep('idle');
		setPlanError('');
		setGenError('');
		setPlan(null);
		setData(null);
		setApproved({});
		setApplyState({ status: 'idle', message: '' });
	};

	const focusKw = (focusInput || '').trim();

	// ── plan ─────────────────────────────────────────────────────
	const fetchPlan = useCallback(() => {
		if (!post || !focusKw || focusKw.length < 2) return;
		setStep('planning');
		setPlanError('');
		apiFetch({
			path: `/ebq/v1/ai-writer/${post.id}/plan`,
			method: 'POST',
			data: {
				focus_keyword: focusKw,
				current_html: String(post.content || '').slice(0, 200000),
			},
		})
			.then((res) => {
				const inner = res?.plan;
				if (res?.ok === false || res?.error || !inner) {
					setPlanError(res?.message || res?.error || __('Failed to load suggestions.', 'ebq-seo'));
					setStep('plan-error');
					return;
				}
				setPlan(inner);
				const tickAll = (arr) => (Array.isArray(arr) ? Object.fromEntries(arr.map((v) => [v, true])) : {});
				const merged = mergeTopics(inner.brief?.suggested_h2_outline, inner.brief?.subtopics);
				const seen = new Set(merged.map(normTopic));
				const drop = (arr) => (Array.isArray(arr) ? arr.filter((v) => {
					const k = normTopic(v);
					if (!k || seen.has(k)) return false;
					seen.add(k);
					return true;
				}) : []);
				const paaList = drop(inner.brief?.people_also_ask);
				const gapTopicsList = drop(inner.gaps?.missing_subtopics);
				const competitorList = drop(inner.gaps?.competitor_subtopics);
				setLists({ topics: merged, paa: paaList, gap_topics: gapTopicsList, competitor_subtopics: competitorList });
				setPick({
					h1: (inner.brief?.suggested_h1 || ''),
					h1Mode: 'suggested',
					topics: tickAll(merged),
					paa: tickAll(paaList),
					gap_topics: tickAll(gapTopicsList),
					competitor_subtopics: tickAll(competitorList),
				});
				setStep('selecting');
			})
			.catch((err) => {
				setPlanError(err?.message || __('Network error.', 'ebq-seo'));
				setStep('plan-error');
			});
	}, [post, focusKw]);

	// ── generate ─────────────────────────────────────────────────
	const generate = useCallback(() => {
		if (!post || !focusKw || focusKw.length < 2) return;
		setStep('generating');
		setGenError('');
		setApplyState({ status: 'idle', message: '' });
		const ticked = (obj) => Object.entries(obj || {}).filter(([, v]) => v).map(([k]) => k);
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
			path: `/ebq/v1/ai-writer/${post.id}`,
			method: 'POST',
			data: {
				focus_keyword: focusKw,
				current_html: String(post.content || '').slice(0, 200000),
				url: post.link || '',
				selected,
			},
		})
			.then((res) => {
				const inner = res?.writer || {};
				if (inner?.ok === false || res?.ok === false) {
					setGenError(inner?.message || inner?.error || res?.message || res?.error || __('Failed', 'ebq-seo'));
					setStep('gen-error');
					return;
				}
				const sections = Array.isArray(inner?.sections) ? inner.sections : [];
				if (!sections.length) {
					setGenError(__('No proposals returned', 'ebq-seo'));
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
				setGenError(err?.message || __('Network error.', 'ebq-seo'));
				setStep('gen-error');
			});
	}, [post, focusKw, pick]);

	// ── approval state ───────────────────────────────────────────
	const sections = data?.sections || [];
	const approvedSections = useMemo(
		() => sections.filter((s) => approved[s.id]),
		[sections, approved],
	);
	const allApproved = sections.length > 0 && approvedSections.length === sections.length;
	const noneApproved = approvedSections.length === 0;
	const handleToggle = useCallback((id) => setApproved((a) => ({ ...a, [id]: !a[id] })), []);
	const handleAll = useCallback((val) => {
		setApproved(() => {
			const next = {};
			(data?.sections || []).forEach((s) => { next[s.id] = val; });
			return next;
		});
	}, [data]);

	// ── apply: write merged HTML + selected schemas via WP REST ──
	const handleApply = useCallback(async (chosenSchemas = []) => {
		if (!post) return;
		if (approvedSections.length === 0 && chosenSchemas.length === 0) return;
		setApplyState({ status: 'pending', message: '' });
		try {
			const updates = {};

			// Content merge — same algorithm as the sidebar version.
			if (approvedSections.length > 0) {
				const replace = approvedSections.find((s) => s.kind === 'replace');
				if (replace) {
					updates.content = String(replace.proposed_html || '');
				} else {
					let next = String(post.content || '');
					const appended = [];
					for (const s of approvedSections) {
						if (s.kind === 'edit' && s.current_html) {
							const idx = next.indexOf(s.current_html);
							if (idx !== -1) {
								next = next.slice(0, idx) + (s.proposed_html || '') + next.slice(idx + s.current_html.length);
								continue;
							}
							appended.push(s.proposed_html || '');
							continue;
						}
						if (s.kind === 'add') appended.push(s.proposed_html || '');
					}
					updates.content = appended.length
						? next.replace(/\s+$/, '') + '\n\n' + appended.join('\n\n')
						: next;
				}
			}

			// Schema merge — append non-duplicate-by-template entries.
			if (chosenSchemas.length > 0) {
				const cur = await apiFetch({ path: `/wp/v2/posts/${post.id}?context=edit&_embed=false` });
				let existing = [];
				const raw = cur?.meta?._ebq_schemas || '';
				try { existing = raw ? JSON.parse(raw) : []; } catch { existing = []; }
				if (!Array.isArray(existing)) existing = [];
				const seen = new Set(existing.map((e) => e?.template).filter(Boolean));
				const next = [...existing];
				for (const s of chosenSchemas) {
					if (seen.has(s.template)) continue;
					next.push({
						id: 'aiw_' + s.template + '_' + Math.random().toString(36).slice(2, 8),
						template: s.template,
						type: s.type,
						data: s.data || {},
						enabled: true,
					});
					seen.add(s.template);
				}
				updates.meta = { _ebq_schemas: JSON.stringify(next) };
			}

			await apiFetch({
				path: `/wp/v2/posts/${post.id}`,
				method: 'POST',
				data: updates,
			});

			// Reflect the new content locally so subsequent generates use it.
			setPost((p) => p ? { ...p, content: updates.content ?? p.content } : p);

			setApplyState({
				status: 'ok',
				message: sprintf(
					__('Saved to "%s". Open the post in the editor to verify.', 'ebq-seo'),
					post.title || ('#' + post.id),
				),
			});
		} catch (err) {
			setApplyState({ status: 'error', message: err?.message || __('Apply failed', 'ebq-seo') });
		}
	}, [post, approvedSections]);

	const goBackToSelection = () => setStep('selecting');

	// ── render ───────────────────────────────────────────────────
	return (
		<div className="ebq-hq-page ebq-aiw">
			<div className="ebq-hq-page__head">
				<h2 className="ebq-hq-page__title">{__('AI Writer', 'ebq-seo')}</h2>
			</div>

			{!post ? (
				<Card title={__('Pick a post or page', 'ebq-seo')}>
					<p className="ebq-hq-help" style={{ marginTop: 0 }}>
						{__('Choose the post you want the AI Writer to draft against. We\'ll pull its brief, topical-gap analysis, and current content.', 'ebq-seo')}
					</p>
					<input
						type="search"
						className="ebq-hq-search"
						placeholder={__('Search posts by title…', 'ebq-seo')}
						value={searchTerm}
						onChange={(e) => setSearchTerm(e.target.value)}
						style={{ width: '100%', maxWidth: 480 }}
						autoFocus
					/>
					{searching ? (
						<p className="ebq-hq-muted" style={{ marginTop: 8 }}>{__('Searching…', 'ebq-seo')}</p>
					) : null}
					{searchResults && searchResults.length === 0 && !searching ? (
						<p className="ebq-hq-muted" style={{ marginTop: 8 }}>{__('No matches.', 'ebq-seo')}</p>
					) : null}
					{searchResults && searchResults.length > 0 ? (
						<ul className="ebq-aiw-search-results">
							{searchResults.map((r) => (
								<li key={r.id}>
									<button
										type="button"
										className="ebq-aiw-search-results__item"
										disabled={postLoadingId === r.id}
										onClick={() => choosePost(r.id)}
									>
										<span className="ebq-aiw-search-results__title">{r.title || ('#' + r.id)}</span>
										{r.url ? <span className="ebq-aiw-search-results__url">{r.url}</span> : null}
										{postLoadingId === r.id ? <span className="ebq-aiw-search-results__loading">{__('Loading…', 'ebq-seo')}</span> : null}
									</button>
								</li>
							))}
						</ul>
					) : null}
				</Card>
			) : (
				<>
					<div className="ebq-aiw-pickedpost">
						<div className="ebq-aiw-pickedpost__main">
							<span className="ebq-aiw-pickedpost__label">{__('Drafting against:', 'ebq-seo')}</span>
							<a href={post.link || '#'} target="_blank" rel="noopener noreferrer" className="ebq-aiw-pickedpost__title">
								{post.title || ('#' + post.id)}
							</a>
						</div>
						<button type="button" className="ebq-hq-btn ebq-hq-btn--ghost ebq-hq-btn--sm" onClick={clearPost}>
							{__('Change post', 'ebq-seo')}
						</button>
					</div>

					{step === 'idle' || step === 'plan-error' ? (
						<Card title={__('Focus keyword', 'ebq-seo')}>
							<input
								type="text"
								className="ebq-hq-search"
								placeholder={__('e.g. "vegan protein powder"', 'ebq-seo')}
								value={focusInput}
								onChange={(e) => setFocusInput(e.target.value)}
								style={{ width: '100%', maxWidth: 480 }}
							/>
							{step === 'plan-error' ? (
								<p className="ebq-aiw-error" style={{ marginTop: 10 }}>{planError}</p>
							) : null}
							<div style={{ marginTop: 10 }}>
								<Button variant="primary" onClick={fetchPlan} disabled={!focusKw || focusKw.length < 2}>
									{step === 'plan-error' ? __('Retry', 'ebq-seo') : __('Get suggestions', 'ebq-seo')}
								</Button>
							</div>
						</Card>
					) : null}

					{step === 'planning' ? (
						<Card>
							<p className="ebq-aiw-loading"><span className="ebq-spinner" /> {__('Loading brief + topical gaps…', 'ebq-seo')}</p>
						</Card>
					) : null}

					{step === 'selecting' && plan ? (
						<Card title={sprintf(__('Curate inputs for "%s"', 'ebq-seo'), focusKw)}
							action={<Button size="sm" variant="ghost" onClick={() => { resetWriter(); }}>{__('Start over', 'ebq-seo')}</Button>}>
							<SelectionPanel plan={plan} lists={lists} setLists={setLists} pick={pick} setPick={setPick} onGenerate={generate} />
						</Card>
					) : null}

					{step === 'generating' ? (
						<Card>
							<p className="ebq-aiw-loading"><span className="ebq-spinner" /> {__('Drafting proposals from your selection…', 'ebq-seo')}</p>
						</Card>
					) : null}

					{step === 'gen-error' ? (
						<Card>
							<p className="ebq-aiw-error">{genError}</p>
							<div style={{ display: 'flex', gap: 6 }}>
								<Button size="sm" variant="primary" onClick={generate}>{__('Retry', 'ebq-seo')}</Button>
								<Button size="sm" variant="ghost" onClick={goBackToSelection}>{__('Back to selection', 'ebq-seo')}</Button>
							</div>
						</Card>
					) : null}

					{step === 'ready' && data ? (
						<ReviewPanel
							data={data}
							sections={sections}
							approved={approved}
							handleToggle={handleToggle}
							handleAll={handleAll}
							allApproved={allApproved}
							noneApproved={noneApproved}
							approvedSections={approvedSections}
							applyState={applyState}
							onApply={handleApply}
							onBack={goBackToSelection}
						/>
					) : null}
				</>
			)}
		</div>
	);
}

/* ────────────────── selection panel ──────────────────────── */

function SelectionPanel({ plan, lists, setLists, pick, setPick, onGenerate }) {
	const briefAvail = !!plan?.brief?.available;
	const gapsAvail = !!plan?.gaps?.available;

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
	const renameItem = (group, oldVal, newVal) => {
		const next = String(newVal || '').trim();
		if (!next || next === oldVal) return;
		const list = Array.isArray(lists[group]) ? lists[group] : [];
		const lowerNext = normTopic(next);
		const conflict = list.some((v) => v !== oldVal && normTopic(v) === lowerNext);
		if (conflict) return;
		setLists((s) => ({ ...s, [group]: (s[group] || []).map((v) => v === oldVal ? next : v) }));
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
					{__('No brief or gaps data for this keyword. Click Generate anyway — the writer will scaffold a fresh article from the focus keyword.', 'ebq-seo')}
				</p>
			) : null}

			{briefAvail ? (
				<>
					{plan.brief.suggested_h1 ? (
						<SelectionGroup title={__('H1 (page title in body)', 'ebq-seo')}>
							<div className="ebq-aiw-h1">
								<label className="ebq-aiw-h1__option">
									<input type="radio" checked={pick.h1Mode === 'suggested'}
										onChange={() => setPick((p) => ({ ...p, h1Mode: 'suggested', h1: plan.brief.suggested_h1 }))} />
									<strong>{plan.brief.suggested_h1}</strong>
									<span className="ebq-aiw-h1__option-tag">{__('suggested', 'ebq-seo')}</span>
								</label>
								<label className="ebq-aiw-h1__option">
									<input type="radio" checked={pick.h1Mode === 'custom'}
										onChange={() => setPick((p) => ({ ...p, h1Mode: 'custom' }))} />
									<input type="text" className="ebq-aiw-h1__custom"
										placeholder={__('Write a custom H1…', 'ebq-seo')}
										value={pick.h1Mode === 'custom' ? pick.h1 : ''}
										onFocus={() => setPick((p) => ({ ...p, h1Mode: 'custom' }))}
										onChange={(e) => setPick((p) => ({ ...p, h1Mode: 'custom', h1: e.target.value }))} />
								</label>
								<label className="ebq-aiw-h1__option">
									<input type="radio" checked={pick.h1Mode === 'none'}
										onChange={() => setPick((p) => ({ ...p, h1Mode: 'none' }))} />
									<span>{__("Don't add an H1", 'ebq-seo')}</span>
								</label>
							</div>
						</SelectionGroup>
					) : null}

					<SelectionGroup
						title={__('Topics to cover', 'ebq-seo')}
						hint={__('Merged from your brief\'s suggested H2 outline and subtopics. Click any item to edit it.', 'ebq-seo')}
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
				<p className="ebq-aiw-empty">{__('Topical-gaps unavailable — needs ≥200 chars of existing content to compare against the SERP.', 'ebq-seo')}</p>
			)}

			<div className="ebq-aiw-generate">
				<Button variant="primary" onClick={onGenerate}>
					{sprintf(__('Generate from %d selection(s)', 'ebq-seo'), totalPicked)}
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
				<div className="ebq-aiw-group__head"><span className="ebq-aiw-group__title">{title}</span></div>
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
					<EditableRow key={item} item={item}
						checked={!!picks?.[item]}
						onToggle={() => onToggle(item)}
						onSave={onEdit ? (next) => onEdit(item, next) : null} />
				))}
			</div>
		</fieldset>
	);
}

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
				<input type="text" className="ebq-aiw-row__input" value={draft}
					onChange={(e) => setDraft(e.target.value)}
					onKeyDown={(e) => {
						if (e.key === 'Enter') { e.preventDefault(); commit(); }
						else if (e.key === 'Escape') { e.preventDefault(); cancel(); }
					}}
					onBlur={commit} autoFocus />
			</div>
		);
	}
	return (
		<div className="ebq-aiw-row">
			<input type="checkbox" className="ebq-aiw-row__check" checked={checked} onChange={onToggle} />
			<button type="button" className="ebq-aiw-row__text"
				onClick={() => { setDraft(item); setEditing(true); }}
				title={__('Click to edit', 'ebq-seo')}>{item}</button>
			<svg className="ebq-aiw-row__edit-icon" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
				<path d="M11.5 2.5l2 2L5 13H3v-2L11.5 2.5z" />
			</svg>
		</div>
	);
}

/* ────────────────── review panel ──────────────────────────── */

function ReviewPanel({ data, sections, approved, handleToggle, handleAll, allApproved, noneApproved, approvedSections, applyState, onApply, onBack }) {
	// Schema-suggestion picks live here so the user can apply them
	// alongside content in one Save call.
	const configurable = (data.schema_suggestions || []).filter((s) => !s.auto_emitted);
	const auto = (data.schema_suggestions || []).filter((s) => s.auto_emitted);
	const [schemaPicks, setSchemaPicks] = useState(
		Object.fromEntries(configurable.map((s) => [s.template, true])),
	);
	const [expanded, setExpanded] = useState({});
	const chosenSchemas = useMemo(
		() => configurable.filter((s) => schemaPicks[s.template]),
		[configurable, schemaPicks],
	);

	return (
		<>
			<Card
				title={__('Proposals', 'ebq-seo')}
				action={<Button size="sm" variant="ghost" onClick={onBack}>{__('Edit selection', 'ebq-seo')}</Button>}
			>
				{data.summary ? <p className="ebq-aiw-summary">{data.summary}</p> : null}
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
					<SectionProposal key={s.id}
						section={s}
						approved={!!approved[s.id]}
						onToggle={() => handleToggle(s.id)} />
				))}
			</Card>

			{configurable.length > 0 ? (
				<Card title={__('Suggested schema', 'ebq-seo')}>
					<p className="ebq-hq-help" style={{ marginTop: 0 }}>
						{__('JSON-LD that matches what was generated. Pick which to apply alongside the content.', 'ebq-seo')}
					</p>
					{configurable.map((s) => (
						<div key={s.template} className="ebq-aiw-schema__row">
							<label className="ebq-aiw-schema__head-row">
								<input type="checkbox" className="ebq-aiw-schema__check"
									checked={!!schemaPicks[s.template]}
									onChange={() => setSchemaPicks((p) => ({ ...p, [s.template]: !p[s.template] }))} />
								<span className="ebq-aiw-schema__type">{s.label}</span>
								<span className="ebq-aiw-schema__type-pill">{s.type}</span>
								<button type="button" className="ebq-aiw-schema__expand"
									onClick={(e) => { e.preventDefault(); setExpanded((x) => ({ ...x, [s.template]: !x[s.template] })); }}>
									{expanded[s.template] ? __('Hide JSON-LD', 'ebq-seo') : __('Show JSON-LD', 'ebq-seo')}
								</button>
							</label>
							<p className="ebq-aiw-schema__why">{s.rationale}</p>
							{expanded[s.template] && s.jsonld ? (
								<pre className="ebq-aiw-schema__code">{JSON.stringify(s.jsonld, null, 2)}</pre>
							) : null}
						</div>
					))}
					{auto.length ? (
						<div className="ebq-aiw-schema__auto">
							<div className="ebq-aiw-schema__auto-title">{__('Already in the schema graph (auto-emitted)', 'ebq-seo')}</div>
							<div className="ebq-aiw-schema__auto-list">
								{auto.map((s) => (
									<span key={s.template} className="ebq-aiw-schema__auto-pill" title={s.rationale}>{s.label}</span>
								))}
							</div>
						</div>
					) : null}
				</Card>
			) : null}

			<div className="ebq-aiw-apply">
				<Button variant="primary" onClick={() => onApply(chosenSchemas)}
					disabled={(approvedSections.length === 0 && chosenSchemas.length === 0) || applyState.status === 'pending'}>
					{applyState.status === 'pending'
						? __('Saving…', 'ebq-seo')
						: sprintf(__('Save %1$d section(s) + %2$d schema(s) to post', 'ebq-seo'), approvedSections.length, chosenSchemas.length)}
				</Button>
				{applyState.message ? (
					<span className={`ebq-aiw-apply__msg ebq-aiw-apply__msg--${applyState.status === 'error' ? 'bad' : 'good'}`}>
						{applyState.message}
					</span>
				) : null}
			</div>
		</>
	);
}

function SectionProposal({ section, approved, onToggle }) {
	const kindLabel = section.kind === 'add' ? __('NEW', 'ebq-seo')
		: section.kind === 'edit' ? __('EDIT', 'ebq-seo')
		: __('REPLACE ALL', 'ebq-seo');
	return (
		<div className={`ebq-aiw-section${approved ? '' : ' ebq-aiw-section--rejected'}`}>
			<div className="ebq-aiw-section__head">
				<input type="checkbox" className="ebq-aiw-section__check"
					checked={approved} onChange={onToggle}
					aria-label={approved ? __('Reject', 'ebq-seo') : __('Approve', 'ebq-seo')} />
				<div className="ebq-aiw-section__body">
					<div className="ebq-aiw-section__meta">
						<span className={`ebq-aiw-kind ebq-aiw-kind--${section.kind}`}>{kindLabel}</span>
						<span className="ebq-aiw-section__title">{section.title}</span>
						{(section.source_tags || []).map((t) => (
							<span key={t} className="ebq-aiw-section__source-tag">#{t}</span>
						))}
					</div>
					{section.rationale ? <p className="ebq-aiw-section__rationale">{section.rationale}</p> : null}
					{section.kind === 'edit' && section.current_html ? (
						<div className="ebq-aiw-section__diff">
							<HtmlBlock label={__('Current', 'ebq-seo')} html={section.current_html} tone="bad" />
							<HtmlBlock label={__('Proposed', 'ebq-seo')} html={section.proposed_html} tone="good" />
						</div>
					) : (
						<HtmlBlock label={__('Proposed', 'ebq-seo')} html={section.proposed_html} tone="good" />
					)}
				</div>
			</div>
		</div>
	);
}

function HtmlBlock({ label, html, tone }) {
	const cls = tone === 'good' ? 'ebq-aiw-html--good' : tone === 'bad' ? 'ebq-aiw-html--bad' : '';
	return (
		<div className={`ebq-aiw-html ${cls}`}>
			<div className="ebq-aiw-html__label">{label}</div>
			<div className="ebq-aiw-html__body" dangerouslySetInnerHTML={{ __html: html }} />
		</div>
	);
}

function DiagnosticsRow({ diag }) {
	if (!diag) return null;
	const linkRow = (() => {
		if (diag.internal_links_available === 0) return { tone: 'neutral', text: __('No internal-link targets available.', 'ebq-seo') };
		if (diag.internal_links_in_output >= diag.internal_links_available)
			return { tone: 'good', text: sprintf(__('Internal links: %1$d of %2$d included.', 'ebq-seo'), diag.internal_links_in_output, diag.internal_links_available) };
		return { tone: 'warn', text: sprintf(__('Internal links: %1$d of %2$d — regenerate to retry.', 'ebq-seo'), diag.internal_links_in_output, diag.internal_links_available) };
	})();
	return (
		<div className="ebq-aiw-diag">
			<Pill tone={linkRow.tone}>{linkRow.text}</Pill>
			<span className="ebq-aiw-diag__meta">
				{sprintf(__('Sections: %1$d · PAA available: %2$d · gap topics: %3$d', 'ebq-seo'),
					diag.sections_returned || 0, diag.paa_questions_available || 0, diag.gaps_available || 0)}
			</span>
		</div>
	);
}

function SourcesUsedRow({ used }) {
	if (!used) return null;
	const tags = [];
	if (used.brief) tags.push({ key: 'brief', label: __('Brief', 'ebq-seo'), tone: 'good' });
	if (used.gaps) tags.push({ key: 'gaps', label: __('Gaps', 'ebq-seo'), tone: 'good' });
	if (used.content) tags.push({ key: 'content', label: __('Existing post', 'ebq-seo'), tone: 'good' });
	if (tags.length === 0) return null;
	return (
		<span className="ebq-aiw-toolbar__sources">
			<span className="ebq-aiw-toolbar__sources-label">{__('Using:', 'ebq-seo')}</span>
			{tags.map((t) => <Pill key={t.key} tone={t.tone}>{t.label}</Pill>)}
		</span>
	);
}

/* helpers shared with sidebar version */
function normTopic(s) { return String(s || '').trim().toLowerCase().replace(/\s+/g, ' '); }
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
