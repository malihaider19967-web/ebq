import { __, sprintf } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';

import { Section, TextField, TextArea, CharGauge, ScoreBadge, EmptyState, Pill, Button } from '../components/primitives';
import { IconSearch, IconChart, IconSparkle } from '../components/icons';
import SnippetPreview from '../components/SnippetPreview';
import AssessmentList from '../components/AssessmentList';
import KeyphraseInput from '../components/KeyphraseInput';
import GscSuggestions from '../components/GscSuggestions';
import RelatedKeyphrases from '../components/RelatedKeyphrases';
import AdditionalKeyphrases, { parseAdditionalKeywords, stringifyAdditionalKeywords } from '../components/AdditionalKeyphrases';
import TopicalCoverage from '../components/TopicalCoverage';
import KeywordDensity from '../components/KeywordDensity';
import Modal from '../components/Modal';

import { useEditorContext, usePostMeta, resolveTitleTemplate, publicConfig } from '../hooks/useEditorContext';
import useDebounced from '../hooks/useDebounced';
import { analyzeSeo, labelForScore } from '../analysis/seo';

export default function SeoTab() {
	const ctx = useEditorContext();
	const { get, set } = usePostMeta();
	const cfg = publicConfig();
	const [previewMode, setPreviewMode] = useState('desktop');
	// `relatedTarget` doubles as the open/closed flag: null when closed,
	// `{ kind: 'focus' | 'additional', index?: number, keyword: string }`
	// when the modal is open. The kind decides which post-meta slot a
	// chosen suggestion replaces.
	const [relatedTarget, setRelatedTarget] = useState(null);
	const relatedOpen = relatedTarget !== null;

	const seoTitleRaw = get('_ebq_title', '');
	const titleResolved = useMemo(
		() => resolveTitleTemplate(seoTitleRaw, { postTitle: ctx.postTitle, sep: cfg.sep, siteName: cfg.siteName }),
		[seoTitleRaw, ctx.postTitle, cfg.sep, cfg.siteName]
	);
	const effectiveTitle = titleResolved || ctx.postTitle;
	const description = get('_ebq_description', '');
	const focusKeyword = get('_ebq_focus_keyword', '');
	const additionalRaw = get('_ebq_additional_keywords', '');
	const additionalList = useMemo(() => parseAdditionalKeywords(additionalRaw).filter((s) => s && s.trim() !== ''), [additionalRaw]);

	const debouncedContent = useDebounced(ctx.content, 400);

	const analysis = useMemo(
		() =>
			analyzeSeo({
				serializedContent: debouncedContent,
				postTitle: ctx.postTitle,
				seoTitleResolved: effectiveTitle,
				metaDescription: description,
				slug: ctx.slug,
				focusKeyword,
				additionalKeywords: additionalList,
				homeUrl: cfg.homeUrl,
			}),
		[debouncedContent, ctx.postTitle, ctx.slug, effectiveTitle, description, focusKeyword, additionalList, cfg.homeUrl]
	);

	const titleLen = String(seoTitleRaw).length || effectiveTitle.length;
	const descLen = description.length;

	const onChooseRelated = (kw) => {
		if (!relatedTarget) return;
		if (relatedTarget.kind === 'additional' && typeof relatedTarget.index === 'number') {
			const next = parseAdditionalKeywords(additionalRaw);
			next[relatedTarget.index] = kw;
			set('_ebq_additional_keywords', stringifyAdditionalKeywords(next));
		} else {
			set('_ebq_focus_keyword', kw);
		}
		setRelatedTarget(null);
	};

	return (
		<div className="ebq-stack">
			<ScoreBadge
				score={analysis.score}
				label={__('SEO score', 'ebq-seo')}
				caption={analysis.scoreLabel}
			/>

			<Section
				title={__('Snippet preview', 'ebq-seo')}
				icon={<IconSearch />}
				aside={
					<div className="ebq-row">
						<button
							type="button"
							className={`ebq-btn ebq-btn--quiet ebq-btn--sm${previewMode === 'desktop' ? ' is-active' : ''}`}
							onClick={() => setPreviewMode('desktop')}
							style={previewMode === 'desktop' ? { color: 'var(--ebq-accent)' } : undefined}
						>
							{__('Desktop', 'ebq-seo')}
						</button>
						<button
							type="button"
							className={`ebq-btn ebq-btn--quiet ebq-btn--sm${previewMode === 'mobile' ? ' is-active' : ''}`}
							onClick={() => setPreviewMode('mobile')}
							style={previewMode === 'mobile' ? { color: 'var(--ebq-accent)' } : undefined}
						>
							{__('Mobile', 'ebq-seo')}
						</button>
					</div>
				}
			>
				<SnippetPreview
					url={get('_ebq_canonical', '') || ctx.postLink}
					siteName={cfg.siteName}
					title={effectiveTitle}
					description={description}
					image={get('_ebq_og_image', '') || ctx.featuredImageUrl || ''}
					mobile={previewMode === 'mobile'}
				/>

				<TextField
					label={__('SEO title', 'ebq-seo')}
					value={seoTitleRaw}
					onChange={(v) => set('_ebq_title', v)}
					placeholder={__('Leave empty to use the post title', 'ebq-seo')}
					hint={
						<>
							{__('Variables:', 'ebq-seo')} <code>%%title%%</code> <code>%%sep%%</code> <code>%%sitename%%</code>
						</>
					}
					maxHint={`${titleLen} / 60`}
				/>
				<CharGauge length={titleLen} goodMin={30} goodMax={60} hardMax={70} />

				<TextArea
					label={__('Meta description', 'ebq-seo')}
					value={description}
					onChange={(v) => set('_ebq_description', v)}
					placeholder={__('Write a 130–155 character summary that includes your focus keyphrase.', 'ebq-seo')}
					rows={3}
					maxHint={`${descLen} / 155`}
				/>
				<CharGauge length={descLen} goodMin={130} goodMax={155} hardMax={170} />
			</Section>

			<Section title={__('Focus keyphrase', 'ebq-seo')} icon={<IconChart />}>
				<KeyphraseInput
					value={focusKeyword}
					onChange={(v) => set('_ebq_focus_keyword', v)}
				/>

				<div className="ebq-row" style={{ marginTop: 6 }}>
					<Button
						variant="ghost"
						size="sm"
						onClick={() => setRelatedTarget({ kind: 'focus', keyword: focusKeyword })}
						disabled={!focusKeyword || focusKeyword.trim().length < 3}
						aria-haspopup="dialog"
						aria-expanded={relatedOpen}
					>
						<IconSparkle /> {__('Related keyphrases', 'ebq-seo')}
					</Button>
					{!focusKeyword || focusKeyword.trim().length < 3 ? (
						<span className="ebq-text-xs ebq-text-soft" style={{ marginLeft: 4 }}>
							{__('Type a focus keyphrase first', 'ebq-seo')}
						</span>
					) : null}
				</div>

				<div className="ebq-divider" />

				<AdditionalKeyphrases
					value={additionalRaw}
					onChange={(v) => set('_ebq_additional_keywords', v)}
					relatedAvailable={!!focusKeyword && focusKeyword.trim().length >= 3}
					onShowRelated={(index) => setRelatedTarget({ kind: 'additional', index, keyword: focusKeyword })}
				/>
			</Section>

			<TopicalCoverage
				focusKeyword={focusKeyword}
				additional={additionalList}
				postTitle={ctx.postTitle}
				seoTitle={effectiveTitle}
				content={debouncedContent}
			/>

			<Section
				title={__('From Google Search Console', 'ebq-seo')}
				icon={<IconChart />}
				collapsible
				defaultOpen={false}
			>
				<GscSuggestions
					postId={ctx.postId}
					onChoose={(v) => set('_ebq_focus_keyword', v)}
				/>
			</Section>

			<KeywordDensity
				content={debouncedContent}
				focusKeyword={focusKeyword}
				additional={additionalList}
			/>

			<Section
				title={__('SEO analysis', 'ebq-seo')}
				icon={<IconSparkle />}
				aside={
					focusKeyword ? (
						<Pill tone={analysis.score >= 65 ? 'good' : analysis.score >= 45 ? 'warn' : 'bad'}>
							{analysis.score} · {labelForScore(analysis.score)}
						</Pill>
					) : null
				}
			>
				{!focusKeyword ? (
					<EmptyState
						icon={<IconSparkle />}
						title={__('Set a focus keyphrase to see analysis', 'ebq-seo')}
						sub={__('We score keyphrase placement, link health, alt text, length, and more.', 'ebq-seo')}
					/>
				) : (
					<>
						<AssessmentList items={analysis.assessments} />
						<div className="ebq-divider" />
						<div className="ebq-row ebq-row--between ebq-text-xs ebq-text-soft">
							<span>
								{sprintf(
									/* translators: words / images / links */
									__('%1$d words · %2$d images · %3$d internal / %4$d outbound links', 'ebq-seo'),
									analysis.meta.wordCount,
									analysis.meta.images,
									analysis.meta.links.internal,
									analysis.meta.links.external
								)}
							</span>
							<span>
								{sprintf(__('Density %s%%', 'ebq-seo'), String(analysis.meta.density))}
							</span>
						</div>
					</>
				)}
			</Section>

			<Modal
				open={relatedOpen}
				onClose={() => setRelatedTarget(null)}
				title={
					relatedTarget?.kind === 'additional'
						? sprintf(__('Pick additional keyphrase #%d', 'ebq-seo'), (relatedTarget.index ?? 0) + 1)
						: __('Related keyphrases', 'ebq-seo')
				}
				size="md"
			>
				{relatedTarget ? (
					<>
						<RelatedKeyphrases
							postId={ctx.postId}
							focusKeyword={focusKeyword}
							onChoose={onChooseRelated}
						/>
						<p className="ebq-help" style={{ marginTop: 10 }}>
							{relatedTarget.kind === 'additional'
								? sprintf(
									__('Suggestions are related to your focus keyphrase ("%s"). Pick one to fill this additional keyphrase slot.', 'ebq-seo'),
									focusKeyword
								)
								: __('Pulled from your Search Console queries and any related/PAA blocks captured by the rank tracker. Click a row to set it as your focus keyphrase.', 'ebq-seo')}
						</p>
					</>
				) : null}
			</Modal>
		</div>
	);
}
