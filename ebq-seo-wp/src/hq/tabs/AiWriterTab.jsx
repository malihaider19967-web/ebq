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
	// `editorRef` is the underlying <textarea>; once `wp.editor.initialize`
	// runs, TinyMCE mounts on top and the textarea is read/written via
	// `wp.editor.getContent` / `tinymce.get(id).setContent`.
	const editorRef = useRef(null);
	const editorIdRef = useRef('ebq-aiw-editor-' + Math.random().toString(36).slice(2, 8));
	const additionalKws = useMemo(
		() => additionalRaw.split(',').map((s) => s.trim()).filter(Boolean),
		[additionalRaw],
	);

	const [saveState, setSaveState] = useState({ status: 'idle', message: '', postId: 0, editLink: '' });

	const canFetchPlan = title.trim().length >= 2 && focusKw.trim().length >= 2;

	// Mount WordPress's native TinyMCE editor on the textarea. The editor
	// is the source of truth for content while the page is open — we
	// only sync `editorHtml` state back from TinyMCE on visible events
	// (insert / save) instead of on every keystroke.
	useEffect(() => {
		const id = editorIdRef.current;
		const wpEditor = (typeof window !== 'undefined' && window.wp && window.wp.editor) || null;
		if (!wpEditor || !editorRef.current) return undefined;

		// Bail if already mounted (React StrictMode double-invokes effects in dev).
		if (window.tinymce && window.tinymce.get && window.tinymce.get(id)) return undefined;

		wpEditor.initialize(id, {
			tinymce: {
				wpautop: true,
				plugins: 'charmap colorpicker compat3x directionality fullscreen hr image lists media paste tabfocus textcolor wordpress wpautoresize wpdialogs wpeditimage wpemoji wpgallery wplink wptextpattern wpview',
				toolbar1: 'formatselect bold italic bullist numlist blockquote alignleft aligncenter alignright link unlink wp_more strikethrough hr forecolor pastetext removeformat charmap outdent indent undo redo wp_help',
				toolbar2: '',
				height: 520,
			},
			quicktags: { buttons: 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,close' },
			mediaButtons: true,
		});

		return () => {
			try { wpEditor.remove(id); } catch (_) { /* may already be torn down */ }
		};
	}, []);

	// When `editorHtml` changes from OUTSIDE (a section was just
	// generated server-side), push the new content into TinyMCE. Skip
	// when TinyMCE's current content already matches — avoids overwriting
	// the user's direct typing and prevents cursor jumps.
	useEffect(() => {
		const id = editorIdRef.current;
		const tm = (typeof window !== 'undefined' && window.tinymce) || null;
		const ed = tm && tm.get ? tm.get(id) : null;
		if (ed) {
			const cur = ed.getContent();
			if (cur !== editorHtml) {
				ed.setContent(editorHtml);
				// Mark dirty so the toolbar's autosave logic doesn't think nothing happened.
				ed.fire('change');
			}
		} else if (editorRef.current && editorRef.current.value !== editorHtml) {
			// Fallback when TinyMCE is in HTML / "Text" tab — just update the textarea.
			editorRef.current.value = editorHtml;
		}
	}, [editorHtml]);

	// Read the live editor content (TinyMCE → textarea fallback).
	const readEditor = useCallback(() => {
		const id = editorIdRef.current;
		const wpEditor = (typeof window !== 'undefined' && window.wp && window.wp.editor) || null;
		if (wpEditor && typeof wpEditor.getContent === 'function') {
			try { return wpEditor.getContent(id); } catch (_) { /* fallthrough */ }
		}
		return editorRef.current ? editorRef.current.value : editorHtml;
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
					current_html: readEditor(),
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
			const live = readEditor();
			const next = (live.replace(/\s+$/, '') + '\n\n' + String(best.proposed_html).trim()).replace(/^\s+/, '');
			setEditorHtml(next);
			setGenState((g) => ({ ...g, [topic]: 'done' }));
		} catch (err) {
			setGenState((g) => ({ ...g, [topic]: 'error:' + (err?.message || __('Network error', 'ebq-seo')) }));
		}
	}, [focusKw, title, additionalKws, readEditor]);

	const insertH1 = () => {
		if (!title.trim()) return;
		const live = readEditor();
		if (/<h1\b/i.test(live)) return; // already there
		const next = `<h1>${escapeHtml(title.trim())}</h1>\n\n` + live;
		setEditorHtml(next);
	};

	const saveDraft = useCallback(async () => {
		const liveHtml = readEditor();
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
	}, [title, focusKw, additionalKws, readEditor]);

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

					{/*
					  Native WordPress editor mounts on this textarea via
					  wp.editor.initialize() in the effect above. The
					  `wp-editor` class lets WP's CSS pick it up before
					  TinyMCE swaps in the toolbar/iframe.
					*/}
					<div className="ebq-aiw-main__editor">
						<textarea
							ref={editorRef}
							id={editorIdRef.current}
							className="wp-editor-area"
							defaultValue={editorHtml}
							onChange={(e) => setEditorHtml(e.target.value)}
							placeholder={__('Generate sections from the left panel — they\'ll land here. You can also write, edit, or delete anything in this editor directly using the WordPress editor toolbar.', 'ebq-seo')}
							rows={20}
							style={{ width: '100%' }}
						/>
					</div>

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
