import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { ScoreChip } from './components/primitives';
import { IconSearch, IconBook, IconShare, IconChart, IconSparkle, IconLink, IconRadar, IconCode, IconGear } from './components/icons';
import SeoTab from './tabs/SeoTab';
import ReadabilityTab from './tabs/ReadabilityTab';
import SocialTab from './tabs/SocialTab';
import InsightsTab from './tabs/InsightsTab';
import AdvancedTab from './tabs/AdvancedTab';
import InclusiveTab from './tabs/InclusiveTab';
import LinksTab from './tabs/LinksTab';
import SchemaTab from './tabs/SchemaTab';

import { useEditorContext, usePostMeta, resolveTitleTemplate, publicConfig } from './hooks/useEditorContext';
import useDebounced from './hooks/useDebounced';
import { analyzeSeo, labelForScore } from './analysis/seo';
import { analyzeReadability } from './analysis/readability';
import { analyzeInclusive } from './analysis/inclusive';

const TABS = [
	{ id: 'seo',         label: __('SEO', 'ebq-seo'),         icon: IconSearch,  Component: SeoTab },
	{ id: 'readability', label: __('Readability', 'ebq-seo'), icon: IconBook,    Component: ReadabilityTab },
	{ id: 'inclusive',   label: __('Inclusive', 'ebq-seo'),   icon: IconSparkle, Component: InclusiveTab },
	{ id: 'links',       label: __('Links', 'ebq-seo'),       icon: IconLink,    Component: LinksTab },
	{ id: 'social',      label: __('Social', 'ebq-seo'),      icon: IconShare,   Component: SocialTab },
	{ id: 'schema',      label: __('Schema', 'ebq-seo'),      icon: IconCode,    Component: SchemaTab },
	{ id: 'insights',    label: __('Insights', 'ebq-seo'),    icon: IconChart,   Component: InsightsTab },
	{ id: 'advanced',    label: __('Advanced', 'ebq-seo'),    icon: IconGear,    Component: AdvancedTab },
];

function tabDot(level, label) {
	if (!level) return null;
	const tone = level === 'good' ? __('good', 'ebq-seo') : level === 'warn' ? __('needs work', 'ebq-seo') : __('attention', 'ebq-seo');
	return (
		<span
			className={`ebq-tab__dot ebq-tab__dot--${level}`}
			role="img"
			aria-label={`${label} ${tone}`}
		/>
	);
}

function levelFromScore(score) {
	if (score >= 65) return 'good';
	if (score >= 45) return 'warn';
	return 'bad';
}

export default function App() {
	const [tab, setTab] = useState('seo');
	const tabRefs = useRef({});
	const ctx = useEditorContext();
	const { get } = usePostMeta();
	const cfg = publicConfig();

	const debounced = useDebounced(ctx.content, 500);

	const seoTitleRaw = get('_ebq_title', '');
	const description = get('_ebq_description', '');
	const focusKw = get('_ebq_focus_keyword', '');
	const additionalRaw = get('_ebq_additional_keywords', '');
	const additionalList = useMemo(() => {
		if (!additionalRaw) return [];
		try {
			const parsed = JSON.parse(additionalRaw);
			return Array.isArray(parsed)
				? parsed.map((s) => String(s || '').trim()).filter(Boolean)
				: [];
		} catch { return []; }
	}, [additionalRaw]);
	const titleResolved = useMemo(
		() => resolveTitleTemplate(seoTitleRaw, { postTitle: ctx.postTitle, sep: cfg.sep, siteName: cfg.siteName }),
		[seoTitleRaw, ctx.postTitle, cfg.sep, cfg.siteName]
	);
	const seoResult = useMemo(
		() =>
			analyzeSeo({
				serializedContent: debounced,
				postTitle: ctx.postTitle,
				seoTitleResolved: titleResolved || ctx.postTitle,
				metaDescription: description,
				slug: ctx.slug,
				focusKeyword: focusKw,
				additionalKeywords: additionalList,
				homeUrl: cfg.homeUrl,
			}),
		[debounced, ctx.postTitle, ctx.slug, titleResolved, description, focusKw, additionalList, cfg.homeUrl]
	);

	const readResult = useMemo(
		() => analyzeReadability({ serializedContent: debounced, locale: ctx.lang }),
		[debounced, ctx.lang]
	);

	const inclusiveResult = useMemo(() => analyzeInclusive(debounced), [debounced]);

	// SEO tab dot — show "bad" (red) when no focus keyphrase is set so the
	// tab visually flags the missing setup. Same chip in the header.
	const seoLevel = focusKw ? levelFromScore(seoResult.score) : 'bad';
	const readLevel = readResult.score > 0 ? levelFromScore(readResult.score) : null;
	const inclusiveLevel = inclusiveResult.totalMatches > 0
		? (inclusiveResult.score >= 90 ? 'good' : inclusiveResult.score >= 70 ? 'warn' : 'bad')
		: null;

	const overallScore = focusKw ? Math.round((seoResult.score + readResult.score) / 2) : 0;
	// Always show the overall score chip — when no focus keyphrase is set,
	// it renders as a red "0 · No focus keyphrase" so the missing setup is
	// always visible from the topbar, not just on the SEO tab.
	const showOverall = true;

	// ─── Keyboard navigation: roving tabindex (Left/Right/Home/End) ─────
	const onTabKeyDown = useCallback((e) => {
		const idx = TABS.findIndex((t) => t.id === tab);
		if (idx === -1) return;
		let nextIdx = -1;
		if (e.key === 'ArrowRight') nextIdx = (idx + 1) % TABS.length;
		else if (e.key === 'ArrowLeft') nextIdx = (idx - 1 + TABS.length) % TABS.length;
		else if (e.key === 'Home') nextIdx = 0;
		else if (e.key === 'End') nextIdx = TABS.length - 1;
		if (nextIdx === -1) return;
		e.preventDefault();
		const next = TABS[nextIdx];
		setTab(next.id);
		// Defer focus so React commits the new active tab first.
		requestAnimationFrame(() => {
			tabRefs.current[next.id]?.focus();
		});
	}, [tab]);

	// Scroll the active tab into view when it changes (matters for overflow).
	useEffect(() => {
		const node = tabRefs.current[tab];
		if (node && node.scrollIntoView) {
			try { node.scrollIntoView({ block: 'nearest', inline: 'nearest' }); } catch { /* ignore */ }
		}
	}, [tab]);

	const goToSeoTab = useCallback(() => setTab('seo'), []);

	const ActiveComponent = (TABS.find((t) => t.id === tab) || TABS[0]).Component;
	const activePanelId = `ebq-tabpanel-${tab}`;

	const isConnected = cfg.isConnected;

	return (
		<div className="ebq-root ebq-sidebar-frame">
			{!isConnected ? (
				<div className="ebq-connect-banner" role="status">
					<span className="ebq-connect-banner__icon" aria-hidden>!</span>
					<div className="ebq-connect-banner__text">
						<strong>{__('EBQ SEO is not connected', 'ebq-seo')}</strong>
						<span className="ebq-text-xs ebq-text-soft">
							{__('Live insights, GSC suggestions, rank tracking, and related-keyphrase data stay empty until you connect this site to your EBQ workspace.', 'ebq-seo')}
						</span>
					</div>
					{cfg.settingsUrl ? (
						<a
							className="ebq-btn ebq-btn--primary ebq-btn--sm"
							href={cfg.settingsUrl}
							target="_top"
							rel="noopener"
						>
							{__('Connect now', 'ebq-seo')} →
						</a>
					) : null}
				</div>
			) : null}

			<div className="ebq-topbar">
			<header className="ebq-header">
				<div className="ebq-header__mark" aria-hidden>E</div>
				<div className="ebq-header__text">
					<h2 className="ebq-header__title">{__('EBQ SEO', 'ebq-seo')}</h2>
					<p className="ebq-header__sub">
						{focusKw
							? labelForScore(overallScore)
							: __('Set a focus keyphrase to start scoring.', 'ebq-seo')}
					</p>
				</div>
				{showOverall ? (
					<button
						type="button"
						className="ebq-score-chip-btn"
						onClick={goToSeoTab}
						aria-label={__('Open SEO tab', 'ebq-seo')}
						title={__('Jump to SEO analysis', 'ebq-seo')}
					>
						<ScoreChip score={overallScore} label={labelForScore(overallScore)} />
					</button>
				) : null}
			</header>

			<div
				className="ebq-tabs"
				role="tablist"
				aria-label={__('EBQ SEO sections', 'ebq-seo')}
				onKeyDown={onTabKeyDown}
			>
				{TABS.map((t) => {
					const isActive = tab === t.id;
					const Icon = t.icon;
					let dot = null;
					if (t.id === 'seo')              dot = tabDot(seoLevel, t.label);
					else if (t.id === 'readability') dot = tabDot(readLevel, t.label);
					else if (t.id === 'inclusive')   dot = tabDot(inclusiveLevel, t.label);
					return (
						<button
							key={t.id}
							type="button"
							role="tab"
							id={`ebq-tab-${t.id}`}
							aria-selected={isActive}
							aria-controls={`ebq-tabpanel-${t.id}`}
							tabIndex={isActive ? 0 : -1}
							className={`ebq-tab${isActive ? ' is-active' : ''}`}
							onClick={() => setTab(t.id)}
							ref={(el) => { tabRefs.current[t.id] = el; }}
						>
							{Icon ? <Icon /> : null}
							<span className="ebq-tab__label">{t.label}</span>
							{dot}
						</button>
					);
				})}
			</div>
			</div>

			<div
				className="ebq-body"
				role="tabpanel"
				id={activePanelId}
				aria-labelledby={`ebq-tab-${tab}`}
				tabIndex={0}
			>
				<ActiveComponent />
			</div>
		</div>
	);
}
