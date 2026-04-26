import { useState, useCallback, useEffect, useMemo, useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Button, Pill } from '../components/primitives';

/**
 * EBQ HQ → AI Writer (standalone draft builder).
 *
 * Two-pane layout:
 *   LEFT  — controls: Title, Focus keyphrase, Additional keyphrases,
 *           "Get suggestions" → plan results (Topics, PAA, Gaps).
 *           Each row has a "+ Generate" button that asks the writer
 *           for THAT ONE SECTION and appends the result into the
 *           editor on the right.
 *   RIGHT — contentEditable editor that the user can keep editing
 *           directly. "Save as draft" creates a new WP post via REST.
 *
 * No existing-post linkage — every generation starts from a blank
 * editor. Cache-friendly because each per-section call passes a
 * different `selected` hash.
 */
export default function AiWriterTab() {
	const [title, setTitle] = useState('');
	const [focusKw, setFocusKw] = useState('');
	const [additionalRaw, setAdditionalRaw] = useState('');

	const [step, setStep] = useState('form'); // form | planning | plan-error | composing
	const [planError, setPlanError] = useState('');
	const [plan, setPlan] = useState(null);
	// Lifted lists so user-initiated edits / dedup persist.
	const [lists, setLists] = useState({ topics: [], paa: [], gap_topics: [], competitor_subtopics: [] });
	// State per topic: 'idle' | 'pending' | 'done' | 'error:<message>'
	const [genState, setGenState] = useState({});
	const [editorHtml, setEditorHtml] = useState('');
	const editorRef = useRef(null);
	const additionalKws = useMemo(
		() => additionalRaw.split(',').map((s) => s.trim()).filter(Boolean),
		[additionalRaw],
	);

	const [saveState, setSaveState] = useState({ status: 'idle', message: '', postId: 0, editLink: '' });

	const canFetchPlan = title.trim().length >= 2 && focusKw.trim().length >= 2;

	// Sync the contentEditable's innerHTML when editorHtml changes from
	// outside (e.g. a section was generated). Don't fight the user's
	// direct typing — the editor's onInput updates editorHtml first, so
	// this effect's update mirrors the same value back and is a no-op.
	useEffect(() => {
		if (editorRef.current && editorRef.current.innerHTML !== editorHtml) {
			editorRef.current.innerHTML = editorHtml;
		}
	}, [editorHtml]);

	const fetchPlan = useCallback(() => {
		if (!canFetchPlan) return;
		setStep('planning');
		setPlanError('');
		apiFetch({
			path: `/ebq/v1/ai-writer/0/plan`,
			method: 'POST',
			data: {
				focus_keyword: focusKw.trim(),
				current_html: editorHtml,
				title: title.trim(),
				additional_keywords: additionalKws,
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
				const merged = mergeTopics(inner.brief?.suggested_h2_outline, inner.brief?.subtopics);
				const seen = new Set(merged.map(normTopic));
				const drop = (arr) => (Array.isArray(arr) ? arr.filter((v) => {
					const k = normTopic(v);
					if (!k || seen.has(k)) return false;
					seen.add(k);
					return true;
				}) : []);
				setLists({
					topics: merged,
					paa: drop(inner.brief?.people_also_ask),
					gap_topics: drop(inner.gaps?.missing_subtopics),
					competitor_subtopics: drop(inner.gaps?.competitor_subtopics),
				});
				setGenState({});
				setStep('composing');
			})
			.catch((err) => {
				setPlanError(err?.message || __('Network error.', 'ebq-seo'));
				setStep('plan-error');
			});
	}, [canFetchPlan, focusKw, title, additionalKws, editorHtml]);

	// Generate a SINGLE section for one selected topic. Sends the
	// chosen topic as the user's curated selection (subtopics + h2
	// only) so the writer's strict mode produces exactly one "add"
	// section. Result is appended to the editor.
	const generateOne = useCallback(async (topic) => {
		if (!topic) return;
		setGenState((g) => ({ ...g, [topic]: 'pending' }));
		try {
			const res = await apiFetch({
				path: `/ebq/v1/ai-writer/0`,
				method: 'POST',
				data: {
					focus_keyword: focusKw.trim(),
					title: title.trim(),
					additional_keywords: additionalKws,
					current_html: editorRef.current ? editorRef.current.innerHTML : editorHtml,
					selected: {
						h1: '', // user's title is sent separately; never insert <h1> at this stage
						h2_outline: [topic],
						subtopics: [topic],
						paa: [],
						gap_topics: [],
						competitor_subtopics: [],
					},
				},
			});
			const inner = res?.writer || {};
			if (inner?.ok === false || res?.ok === false) {
				const msg = inner?.message || inner?.error || res?.message || res?.error || __('Failed', 'ebq-seo');
				setGenState((g) => ({ ...g, [topic]: 'error:' + msg }));
				return;
			}
			const sections = Array.isArray(inner?.sections) ? inner.sections : [];
			// Pick the single most-relevant section: prefer one whose title
			// or HTML mentions the topic; fall back to first.
			const lowerTopic = topic.toLowerCase();
			const best = sections.find((s) => {
				const haystack = ((s.title || '') + ' ' + (s.proposed_html || '')).toLowerCase();
				return haystack.includes(lowerTopic);
			}) || sections[0];
			if (!best || !best.proposed_html) {
				setGenState((g) => ({ ...g, [topic]: 'error:' + __('Empty response', 'ebq-seo') }));
				return;
			}
			const live = editorRef.current ? editorRef.current.innerHTML : editorHtml;
			const next = (live.replace(/\s+$/, '') + '\n\n' + String(best.proposed_html).trim()).replace(/^\s+/, '');
			setEditorHtml(next);
			setGenState((g) => ({ ...g, [topic]: 'done' }));
		} catch (err) {
			setGenState((g) => ({ ...g, [topic]: 'error:' + (err?.message || __('Network error', 'ebq-seo')) }));
		}
	}, [focusKw, title, additionalKws, editorHtml]);

	const insertH1 = () => {
		if (!title.trim()) return;
		const live = editorRef.current ? editorRef.current.innerHTML : editorHtml;
		if (/<h1\b/i.test(live)) return; // already there
		const next = `<h1>${escapeHtml(title.trim())}</h1>\n\n` + live;
		setEditorHtml(next);
	};

	const saveDraft = useCallback(async () => {
		const liveHtml = editorRef.current ? editorRef.current.innerHTML : editorHtml;
		if (!title.trim()) {
			setSaveState({ status: 'error', message: __('Title is required.', 'ebq-seo'), postId: 0, editLink: '' });
			return;
		}
		if (!liveHtml.trim()) {
			setSaveState({ status: 'error', message: __('Generate at least one section before saving.', 'ebq-seo'), postId: 0, editLink: '' });
			return;
		}
		setSaveState({ status: 'pending', message: '', postId: 0, editLink: '' });
		try {
			const additionalJson = additionalKws.length
				? JSON.stringify(additionalKws)
				: '';
			const meta = {};
			if (focusKw.trim()) meta._ebq_focus_keyword = focusKw.trim();
			if (additionalJson) meta._ebq_additional_keywords = additionalJson;

			const created = await apiFetch({
				path: '/wp/v2/posts',
				method: 'POST',
				data: {
					title: title.trim(),
					content: liveHtml,
					status: 'draft',
					meta,
				},
			});
			setSaveState({
				status: 'ok',
				message: __('Draft saved.', 'ebq-seo'),
				postId: created?.id || 0,
				editLink: created?.link
					? created.link
					: (created?.id ? `${window.location.origin}/wp-admin/post.php?post=${created.id}&action=edit` : ''),
			});
		} catch (err) {
			setSaveState({ status: 'error', message: err?.message || __('Save failed', 'ebq-seo'), postId: 0, editLink: '' });
		}
	}, [title, focusKw, additionalKws, editorHtml]);

	const restart = () => {
		setStep('form');
		setPlan(null);
		setLists({ topics: [], paa: [], gap_topics: [], competitor_subtopics: [] });
		setGenState({});
		setEditorHtml('');
		setSaveState({ status: 'idle', message: '', postId: 0, editLink: '' });
	};

	return (
		<div className="ebq-aiw-page">
			<div className="ebq-hq-page__head">
				<h2 className="ebq-hq-page__title">{__('AI Writer', 'ebq-seo')}</h2>
				{step === 'composing' ? (
					<button type="button" className="ebq-hq-btn ebq-hq-btn--ghost ebq-hq-btn--sm" onClick={restart}>
						{__('Start over', 'ebq-seo')}
					</button>
				) : null}
			</div>

			<div className="ebq-aiw-layout">
				{/* ── Left control panel ─────────────────────────── */}
				<aside className="ebq-aiw-side">
					<section className="ebq-aiw-side__section">
						<label className="ebq-aiw-side__label">{__('Title', 'ebq-seo')}</label>
						<input
							type="text"
							className="ebq-aiw-side__input"
							placeholder={__('e.g. The Definitive Guide to Vegan Protein', 'ebq-seo')}
							value={title}
							onChange={(e) => setTitle(e.target.value)}
						/>
					</section>

					<section className="ebq-aiw-side__section">
						<label className="ebq-aiw-side__label">{__('Focus keyphrase', 'ebq-seo')}</label>
						<input
							type="text"
							className="ebq-aiw-side__input"
							placeholder={__('e.g. vegan protein powder', 'ebq-seo')}
							value={focusKw}
							onChange={(e) => setFocusKw(e.target.value)}
						/>
					</section>

					<section className="ebq-aiw-side__section">
						<label className="ebq-aiw-side__label">{__('Additional keyphrases', 'ebq-seo')}</label>
						<input
							type="text"
							className="ebq-aiw-side__input"
							placeholder={__('comma-separated, e.g. amino acids, muscle gain', 'ebq-seo')}
							value={additionalRaw}
							onChange={(e) => setAdditionalRaw(e.target.value)}
						/>
						{additionalKws.length > 0 ? (
							<div className="ebq-aiw-side__chips">
								{additionalKws.map((k) => (
									<span key={k} className="ebq-aiw-side__chip">{k}</span>
								))}
							</div>
						) : null}
					</section>

					<section className="ebq-aiw-side__section">
						{step === 'form' || step === 'plan-error' ? (
							<>
								<Button variant="primary" onClick={fetchPlan} disabled={!canFetchPlan || step === 'planning'}>
									{step === 'plan-error' ? __('Retry', 'ebq-seo') : __('Get suggestions', 'ebq-seo')}
								</Button>
								{step === 'plan-error' ? (
									<p className="ebq-aiw-side__error">{planError}</p>
								) : null}
							</>
						) : null}

						{step === 'planning' ? (
							<p className="ebq-aiw-side__loading">
								<span className="ebq-spinner" /> {__('Loading suggestions…', 'ebq-seo')}
							</p>
						) : null}
					</section>

					{step === 'composing' && plan ? (
						<>
							{title.trim() ? (
								<section className="ebq-aiw-side__section">
									<button
										type="button"
										className="ebq-aiw-side__quick"
										onClick={insertH1}
										disabled={/<h1\b/i.test(editorHtml)}
										title={/<h1\b/i.test(editorHtml) ? __('H1 already in editor', 'ebq-seo') : ''}
									>
										+ <strong>H1</strong> <span className="ebq-aiw-side__quick-sub">{title.trim()}</span>
									</button>
								</section>
							) : null}

							<TopicGroup
								title={__('Topics to cover', 'ebq-seo')}
								items={lists.topics}
								genState={genState}
								onGenerate={generateOne}
							/>
							<TopicGroup
								title={__('People also ask', 'ebq-seo')}
								items={lists.paa}
								genState={genState}
								onGenerate={generateOne}
							/>
							<TopicGroup
								title={__('Subtopics to add (vs. top SERP)', 'ebq-seo')}
								items={lists.gap_topics}
								genState={genState}
								onGenerate={generateOne}
							/>
							<TopicGroup
								title={__('Subtopics covered by top 5', 'ebq-seo')}
								items={lists.competitor_subtopics}
								genState={genState}
								onGenerate={generateOne}
							/>

							<section className="ebq-aiw-side__section ebq-aiw-side__custom">
								<label className="ebq-aiw-side__label">{__('Or generate a custom section', 'ebq-seo')}</label>
								<CustomTopicInput onGenerate={generateOne} />
							</section>
						</>
					) : null}
				</aside>

				{/* ── Right editor pane ──────────────────────────── */}
				<main className="ebq-aiw-main">
					<div className="ebq-aiw-main__toolbar">
						<span className="ebq-aiw-main__title-preview">
							{title.trim() || __('Untitled draft', 'ebq-seo')}
						</span>
						<div className="ebq-aiw-main__toolbar-spacer" />
						<Button
							variant="primary"
							size="sm"
							onClick={saveDraft}
							disabled={saveState.status === 'pending' || !title.trim() || !editorHtml.trim()}
						>
							{saveState.status === 'pending' ? __('Saving…', 'ebq-seo') : __('Save as draft', 'ebq-seo')}
						</Button>
					</div>

					<div
						ref={editorRef}
						className="ebq-aiw-main__editor"
						contentEditable
						suppressContentEditableWarning
						onInput={(e) => setEditorHtml(e.currentTarget.innerHTML)}
						data-placeholder={__('Generate sections from the left panel — they\'ll land here. You can also type, edit, or delete anything in this editor directly.', 'ebq-seo')}
					/>

					{saveState.message ? (
						<div className={`ebq-aiw-main__save ebq-aiw-main__save--${saveState.status}`}>
							<span>{saveState.message}</span>
							{saveState.status === 'ok' && saveState.editLink ? (
								<a href={saveState.editLink} target="_blank" rel="noopener noreferrer" className="ebq-aiw-main__save-link">
									{__('Open the draft', 'ebq-seo')} →
								</a>
							) : null}
						</div>
					) : null}
				</main>
			</div>
		</div>
	);
}

/* ────────────────── side-panel pieces ──────────────────────── */

function TopicGroup({ title, items, genState, onGenerate }) {
	const list = Array.isArray(items) ? items : [];
	if (list.length === 0) return null;
	const doneCount = list.filter((t) => genState[t] === 'done').length;
	return (
		<section className="ebq-aiw-side__section">
			<div className="ebq-aiw-side__group-head">
				<span className="ebq-aiw-side__label">{title}</span>
				{doneCount > 0 ? (
					<span className="ebq-aiw-side__group-count">{doneCount}/{list.length}</span>
				) : null}
			</div>
			<ul className="ebq-aiw-side__list">
				{list.map((topic) => (
					<TopicRow
						key={topic}
						topic={topic}
						state={genState[topic]}
						onGenerate={() => onGenerate(topic)}
					/>
				))}
			</ul>
		</section>
	);
}

function TopicRow({ topic, state, onGenerate }) {
	const isDone = state === 'done';
	const isPending = state === 'pending';
	const errorMsg = typeof state === 'string' && state.startsWith('error:') ? state.slice(6) : '';

	return (
		<li className={`ebq-aiw-side__row${isDone ? ' is-done' : ''}${isPending ? ' is-pending' : ''}${errorMsg ? ' is-error' : ''}`}>
			<button
				type="button"
				className="ebq-aiw-side__row-btn"
				onClick={onGenerate}
				disabled={isPending}
				title={isDone ? __('Click to generate again', 'ebq-seo') : __('Generate this section', 'ebq-seo')}
			>
				<span className="ebq-aiw-side__row-icon" aria-hidden>
					{isPending ? <span className="ebq-spinner ebq-spinner--xs" /> : isDone ? '✓' : '+'}
				</span>
				<span className="ebq-aiw-side__row-text">{topic}</span>
				{isDone ? <span className="ebq-aiw-side__row-tag">{__('added', 'ebq-seo')}</span> : null}
			</button>
			{errorMsg ? <p className="ebq-aiw-side__row-error">{errorMsg}</p> : null}
		</li>
	);
}

function CustomTopicInput({ onGenerate }) {
	const [value, setValue] = useState('');
	const submit = () => {
		const v = value.trim();
		if (v.length < 2) return;
		onGenerate(v);
		setValue('');
	};
	return (
		<div className="ebq-aiw-side__custom-row">
			<input
				type="text"
				className="ebq-aiw-side__input"
				placeholder={__('Section topic…', 'ebq-seo')}
				value={value}
				onChange={(e) => setValue(e.target.value)}
				onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); submit(); } }}
			/>
			<button
				type="button"
				className="ebq-hq-btn ebq-hq-btn--primary ebq-hq-btn--sm"
				onClick={submit}
				disabled={value.trim().length < 2}
			>+ {__('Generate', 'ebq-seo')}</button>
		</div>
	);
}

/* ────────────────── helpers ──────────────────────────────── */

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
function escapeHtml(s) {
	return String(s || '')
		.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
