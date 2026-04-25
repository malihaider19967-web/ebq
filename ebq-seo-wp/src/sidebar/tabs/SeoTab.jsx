import { __, sprintf } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';

import { Section, TextField, TextArea, CharGauge, ScoreBadge, EmptyState, Pill, Button } from '../components/primitives';
import { IconSearch, IconChart, IconSparkle } from '../components/icons';
import SnippetPreview from '../components/SnippetPreview';
import AssessmentList from '../components/AssessmentList';
import KeyphraseInput from '../components/KeyphraseInput';

import { useEditorContext, usePostMeta, resolveTitleTemplate, publicConfig } from '../hooks/useEditorContext';
import useDebounced from '../hooks/useDebounced';
import { analyzeSeo, labelForScore } from '../analysis/seo';

export default function SeoTab() {
	const ctx = useEditorContext();
	const { get, set } = usePostMeta();
	const cfg = publicConfig();
	const [previewMode, setPreviewMode] = useState('desktop');

	const seoTitleRaw = get('_ebq_title', '');
	const titleResolved = useMemo(
		() => resolveTitleTemplate(seoTitleRaw, { postTitle: ctx.postTitle, sep: cfg.sep, siteName: cfg.siteName }),
		[seoTitleRaw, ctx.postTitle, cfg.sep, cfg.siteName]
	);
	const effectiveTitle = titleResolved || ctx.postTitle;
	const description = get('_ebq_description', '');
	const focusKeyword = get('_ebq_focus_keyword', '');

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
				homeUrl: cfg.homeUrl,
			}),
		[debouncedContent, ctx.postTitle, ctx.slug, effectiveTitle, description, focusKeyword, cfg.homeUrl]
	);

	const titleLen = String(seoTitleRaw).length || effectiveTitle.length;
	const descLen = description.length;

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
					postId={ctx.postId}
					value={focusKeyword}
					onChange={(v) => set('_ebq_focus_keyword', v)}
				/>
			</Section>

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
		</div>
	);
}
