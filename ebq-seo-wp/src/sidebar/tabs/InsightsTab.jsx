import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useMemo, useState } from '@wordpress/element';

import {
	Section,
	StatGrid,
	Stat,
	EmptyState,
	Spinner,
	Button,
	Pill,
	SkeletonRow,
} from '../components/primitives';
import { IconChart, IconExternal, IconRefresh, IconSparkle } from '../components/icons';
import Sparkline from '../components/Sparkline';
import RankBadge from '../components/RankBadge';
import { useEditorContext, publicConfig } from '../hooks/useEditorContext';
import { fetchPostInsights } from '../api';

export default function InsightsTab() {
	const ctx = useEditorContext();
	const cfg = publicConfig();
	const [state, setState] = useState({ loading: true, data: null, error: null });
	const [reloadKey, setReloadKey] = useState(0);

	useEffect(() => {
		if (!ctx.postId) return;
		let cancelled = false;
		setState({ loading: true, data: null, error: null });
		fetchPostInsights(ctx.postId)
			.then((data) => {
				if (cancelled) return;
				if (data && data.ok === false) {
					setState({ loading: false, data: null, error: data.error || 'unknown' });
				} else {
					setState({ loading: false, data, error: null });
				}
			})
			.catch((err) => {
				if (cancelled) return;
				setState({ loading: false, data: null, error: err?.message || 'fetch_failed' });
			});
		return () => {
			cancelled = true;
		};
	}, [ctx.postId, reloadKey]);

	const auditUrl = useMemo(() => {
		const reportId = state.data?.audit?.report_id;
		if (!reportId || !cfg.appBase) return '';
		return `${cfg.appBase}/page-audits/${reportId}`;
	}, [state.data, cfg.appBase]);

	const newAuditUrl = useMemo(() => {
		if (!cfg.appBase || !ctx.postLink) return '';
		const params = new URLSearchParams({ pageUrl: ctx.postLink });
		const focus = ctx.meta?._ebq_focus_keyword;
		if (focus) params.set('targetKeyword', focus);
		return `${cfg.appBase}/custom-audit?${params.toString()}`;
	}, [cfg.appBase, ctx.postLink, ctx.meta]);

	if (state.loading) {
		return (
			<div className="ebq-stack" aria-busy="true" aria-live="polite">
				<Section title={__('Live insights', 'ebq-seo')} icon={<IconChart />}>
					<div className="ebq-stack">
						<SkeletonRow label={__('Loading insights', 'ebq-seo')} />
						<SkeletonRow width="80%" />
						<SkeletonRow width="60%" />
					</div>
				</Section>
			</div>
		);
	}

	if (state.error) {
		const errorMessages = {
			not_connected: {
				title: __('Connect to EBQ to see insights', 'ebq-seo'),
				sub: __('Open Settings → EBQ SEO and connect this site to your EBQ workspace.', 'ebq-seo'),
			},
			url_not_for_website: {
				title: __('Domain mismatch', 'ebq-seo'),
				sub: __("This post's URL isn't on the domain connected to EBQ. Reconnect from Settings → EBQ SEO if you've switched workspaces.", 'ebq-seo'),
			},
			network_error: {
				title: __('Could not reach EBQ', 'ebq-seo'),
				sub: __('Network error. Hit Retry.', 'ebq-seo'),
			},
		};
		const m = errorMessages[state.error] || {
			title: __('Could not load insights', 'ebq-seo'),
			sub: sprintf(__('Reason: %s', 'ebq-seo'), String(state.error)),
		};
		return (
			<div className="ebq-stack">
				<EmptyState icon={<IconChart />} title={m.title} sub={m.sub}>
					<Button variant="ghost" size="sm" onClick={() => setReloadKey((k) => k + 1)}>
						<IconRefresh /> {__('Retry', 'ebq-seo')}
					</Button>
				</EmptyState>
			</div>
		);
	}

	const data = state.data || {};
	const gsc = data.gsc || {};
	const t30 = gsc.totals_30d || {};
	const t90 = gsc.totals_90d || {};
	const tracked = data.tracked_keyword;
	const audit = data.audit;
	const indexing = data.indexing;
	const flags = data.flags || {};
	const cannibalized = Array.isArray(data.cannibalization) ? data.cannibalization : [];
	const striking = Array.isArray(data.striking_distance) ? data.striking_distance : [];

	const focus = (ctx.meta?._ebq_focus_keyword || '').trim().toLowerCase();
	const primaryQuery = (gsc.primary_query || '').trim().toLowerCase();
	const intentMismatch = primaryQuery && focus && primaryQuery !== focus;

	return (
		<div className="ebq-stack">
			{indexing?.verdict ? (
				<Section title={__('Google indexing', 'ebq-seo')} icon={<IconChart />}
					aside={<Pill tone={indexing.indexed ? 'good' : 'bad'}>{indexing.indexed ? __('Indexed', 'ebq-seo') : __('Not indexed', 'ebq-seo')}</Pill>}>
					<p style={{ margin: 0, fontSize: 13, fontWeight: 600 }}>{indexing.verdict}</p>
					{indexing.coverage_state ? (
						<p className="ebq-text-xs ebq-text-soft" style={{ margin: 0 }}>{indexing.coverage_state}</p>
					) : null}
				</Section>
			) : null}

			{tracked ? (
				<Section
					title={__('Rank tracking', 'ebq-seo')}
					icon={<IconChart />}
					aside={<Pill tone="accent">{__('EBQ Rank Tracker', 'ebq-seo')}</Pill>}
				>
					<RankBadge tracked={tracked} />
				</Section>
			) : focus ? (
				<Section title={__('Rank tracking', 'ebq-seo')} icon={<IconChart />}>
					<EmptyState
						icon={<IconChart />}
						title={__('Not tracked yet', 'ebq-seo')}
						sub={__('Add this keyphrase to EBQ Rank Tracking to see daily positions and SERP movement.', 'ebq-seo')}
					>
						{cfg.appBase ? (
							<Button variant="primary" size="sm" href={`${cfg.appBase}/rank-tracking?keyword=${encodeURIComponent(ctx.meta._ebq_focus_keyword)}`} target="_blank" rel="noopener noreferrer">
								{__('Add to Rank Tracking', 'ebq-seo')} <IconExternal />
							</Button>
						) : null}
					</EmptyState>
				</Section>
			) : null}

			<Section title={__('Search performance', 'ebq-seo')} icon={<IconChart />}
				aside={<span className="ebq-text-xs ebq-text-soft">{__('30 days', 'ebq-seo')}</span>}>
				<StatGrid>
					<Stat label={__('Clicks', 'ebq-seo')} value={t30.clicks ?? '—'} sub={t90.clicks ? `${t90.clicks} / 90d` : null} />
					<Stat label={__('Impressions', 'ebq-seo')} value={t30.impressions ?? '—'} />
					<Stat label={__('Avg position', 'ebq-seo')} value={t30.position ?? '—'} />
					<Stat label={__('CTR', 'ebq-seo')} value={t30.ctr != null ? `${t30.ctr}%` : '—'} />
				</StatGrid>
				{Array.isArray(gsc.click_series_90d) && gsc.click_series_90d.length > 1 ? (
					<>
						<p className="ebq-text-xs ebq-text-soft" style={{ margin: '8px 0 4px' }}>
							{__('Clicks · last 90 days', 'ebq-seo')}
						</p>
						<Sparkline series={gsc.click_series_90d} />
					</>
				) : null}
			</Section>

			{intentMismatch ? (
				<Section title={__('Keyword alignment', 'ebq-seo')} icon={<IconSparkle />} aside={<Pill tone="warn">{__('Mismatch', 'ebq-seo')}</Pill>}>
					<p className="ebq-text-sm" style={{ margin: 0 }}>
						{sprintf(
							/* translators: 1: top GSC query, 2: focus keyphrase */
							__('Top Search Console query is "%1$s" but your focus keyphrase is "%2$s". Align your title and H1 to capture the existing intent or pivot.', 'ebq-seo'),
							gsc.primary_query,
							ctx.meta._ebq_focus_keyword
						)}
					</p>
				</Section>
			) : null}

			{(flags.cannibalized || flags.striking_distance) ? (
				<Section title={__('Opportunities', 'ebq-seo')} icon={<IconSparkle />}>
					{flags.cannibalized ? (
						<div className="ebq-stack" style={{ gap: 6 }}>
							<div className="ebq-row ebq-row--between">
								<strong style={{ fontSize: 13 }}>{__('Keyword cannibalization', 'ebq-seo')}</strong>
								<Pill tone="bad">{cannibalized.length}</Pill>
							</div>
							<p className="ebq-text-xs ebq-text-soft" style={{ margin: 0 }}>
								{__('Multiple pages compete for the same query. Consolidate or de-optimize duplicates.', 'ebq-seo')}
							</p>
							{cannibalized.slice(0, 3).map((c, i) => (
								<div key={i} className="ebq-row ebq-row--between ebq-text-xs" style={{ gap: 8 }}>
									<span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
										{c.query}
									</span>
									<span className="ebq-text-soft">{c.competing_urls ?? c.urls?.length ?? '?'} URLs</span>
								</div>
							))}
						</div>
					) : null}

					{flags.cannibalized && flags.striking_distance ? <div className="ebq-divider" /> : null}

					{flags.striking_distance ? (
						<div className="ebq-stack" style={{ gap: 6 }}>
							<div className="ebq-row ebq-row--between">
								<strong style={{ fontSize: 13 }}>{__('Striking distance', 'ebq-seo')}</strong>
								<Pill tone="warn">{striking.length}</Pill>
							</div>
							<p className="ebq-text-xs ebq-text-soft" style={{ margin: 0 }}>
								{__('Queries ranking 5–20 with strong impressions — small wins that move you to page one.', 'ebq-seo')}
							</p>
							{striking.slice(0, 3).map((s, i) => (
								<div key={i} className="ebq-row ebq-row--between ebq-text-xs" style={{ gap: 8 }}>
									<span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
										{s.query}
									</span>
									<span className="ebq-text-soft">#{s.position} · {s.impressions} impr</span>
								</div>
							))}
						</div>
					) : null}
				</Section>
			) : null}

			{audit ? (
				<Section
					title={__('Latest audit', 'ebq-seo')}
					icon={<IconChart />}
					aside={
						audit.score != null ? (
							<Pill tone={audit.score >= 80 ? 'good' : audit.score >= 50 ? 'warn' : 'bad'}>
								{audit.score}/100
							</Pill>
						) : null
					}
				>
					<StatGrid>
						<Stat label={__('Perf · mobile', 'ebq-seo')} value={audit.performance_score_mobile ?? '—'} />
						<Stat label={__('Perf · desktop', 'ebq-seo')} value={audit.performance_score_desktop ?? '—'} />
						<Stat label={__('LCP · mobile', 'ebq-seo')} value={audit.lcp_ms_mobile ? `${audit.lcp_ms_mobile} ms` : '—'} />
						<Stat label={__('CLS · mobile', 'ebq-seo')} value={audit.cls_mobile ?? '—'} />
					</StatGrid>
					{auditUrl ? (
						<Button variant="ghost" href={auditUrl} target="_blank" rel="noopener noreferrer" block>
							{__('Open full audit in EBQ', 'ebq-seo')} <IconExternal />
						</Button>
					) : null}
				</Section>
			) : null}

			<Section title={__('Actions', 'ebq-seo')} icon={<IconRefresh />} plain>
				<div className="ebq-stack" style={{ gap: 8 }}>
					{newAuditUrl ? (
						<Button variant="primary" href={newAuditUrl} target="_blank" rel="noopener noreferrer" block>
							{__('Run new audit in EBQ', 'ebq-seo')} <IconExternal />
						</Button>
					) : null}
					<Button variant="ghost" onClick={() => setReloadKey((k) => k + 1)} block>
						<IconRefresh /> {__('Refresh insights', 'ebq-seo')}
					</Button>
				</div>
			</Section>
		</div>
	);
}
