import { useState, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Section, Button, EmptyState, Pill, TextField } from '../components/primitives';
import { IconSparkle, IconChart } from '../components/icons';
import { useEditorContext, usePostMeta, publicConfig } from '../hooks/useEditorContext';
import { useTier } from '../hooks/useTier';

/**
 * "Brief" tab — Pro tier only.
 *
 * The user enters a target keyword, EBQ scrapes top SERP via Serper, an
 * LLM extracts subtopics + recommended depth + schema type + outline +
 * PAA, and we bolt on internal-link targets from the user's own GSC data.
 * Output reads like a content brief a senior strategist would deliver.
 *
 * Tier-gated at render time — free tier sees an upgrade CTA in place of
 * the form. Server ALSO enforces; this is just the UX gate.
 */
export default function BriefTab() {
	const ctx = useEditorContext();
	const { get } = usePostMeta();
	const cfg = publicConfig();
	// Reactive tier — flips Free → Pro UI without an editor reload when
	// the backend reports an upgrade on any in-flight API response.
	const tier = useTier();

	const [keyword, setKeyword] = useState(get('_ebq_focus_keyword', '') || '');
	const [state, setState] = useState({ status: 'idle', data: null, error: null });

	const fetchBrief = useCallback(() => {
		const kw = (keyword || '').trim();
		if (kw.length < 2) return;
		setState({ status: 'loading', data: null, error: null });
		apiFetch({
			path: `/ebq/v1/content-brief/${ctx.postId}`,
			method: 'POST',
			data: { focus_keyword: kw },
		})
			.then((res) => {
				const inner = res?.brief || {};
				if (inner?.ok === false || res?.ok === false) {
					setState({
						status: 'error',
						data: null,
						error: inner?.message || inner?.error || res?.message || res?.error || 'Failed',
					});
				} else if (inner?.brief) {
					setState({ status: 'ready', data: inner, error: null });
				} else {
					setState({ status: 'error', data: null, error: 'Empty brief' });
				}
			})
			.catch((err) => {
				setState({ status: 'error', data: null, error: err?.message || 'Network error' });
			});
	}, [ctx.postId, keyword]);

	if (tier !== 'pro') {
		return (
			<div className="ebq-stack">
				<Section title={__('AI content brief', 'ebq-seo')} icon={<IconSparkle />}>
					<EmptyState
						icon={<IconSparkle />}
						title={__('Pro feature', 'ebq-seo')}
						sub={__('Generate writer-ready content briefs from any keyword: subtopics to cover, recommended word count, schema type, suggested H2 outline, "people also ask", and internal-link targets pulled from your own GSC data.', 'ebq-seo')}
					>
						<a
							className="ebq-btn ebq-btn--primary ebq-btn--sm"
							href={cfg.appBase ? `${cfg.appBase}/billing` : '#'}
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

	return (
		<div className="ebq-stack">
			<Section title={__('AI content brief', 'ebq-seo')} icon={<IconSparkle />}>
				<TextField
					label={__('Target keyword', 'ebq-seo')}
					value={keyword}
					onChange={setKeyword}
					placeholder={__('e.g. "vegan protein powder for muscle gain"', 'ebq-seo')}
					hint={__('EBQ pulls the top 10 SERP for this query and asks the model to extract subtopics, depth, schema type, outline, and entities to cover.', 'ebq-seo')}
				/>
				<div className="ebq-row" style={{ marginTop: 6 }}>
					<Button
						variant="primary"
						onClick={fetchBrief}
						disabled={!keyword || keyword.trim().length < 2 || state.status === 'loading'}
					>
						<IconSparkle /> {__('Generate brief', 'ebq-seo')}
					</Button>
				</div>

				{state.status === 'loading' ? (
					<p className="ebq-help" style={{ marginTop: 10 }}>
						<span className="ebq-spinner" /> {__('Scraping SERP + asking the model… (15–25s)', 'ebq-seo')}
					</p>
				) : null}

				{state.status === 'error' ? (
					<p className="ebq-help" style={{ marginTop: 10, color: 'var(--ebq-bad-text)' }}>
						{state.error}
					</p>
				) : null}
			</Section>

			{state.status === 'ready' && state.data?.brief ? (
				<BriefView brief={state.data.brief} cached={!!state.data.cached} />
			) : null}
		</div>
	);
}

function BriefView({ brief, cached }) {
	return (
		<>
			<Section
				title={__('Brief overview', 'ebq-seo')}
				icon={<IconChart />}
				aside={
					<div className="ebq-row">
						<Pill tone="good">{brief.angle}</Pill>
						{cached ? <Pill tone="neutral">{__('cached', 'ebq-seo')}</Pill> : null}
					</div>
				}
			>
				<div className="ebq-brief-stats">
					<div className="ebq-brief-stat">
						<span className="ebq-brief-stat__num">{brief.recommended_word_count || '—'}</span>
						<span className="ebq-brief-stat__label">{__('words target', 'ebq-seo')}</span>
					</div>
					<div className="ebq-brief-stat">
						<span className="ebq-brief-stat__num">{brief.suggested_schema_type}</span>
						<span className="ebq-brief-stat__label">{__('schema', 'ebq-seo')}</span>
					</div>
					<div className="ebq-brief-stat">
						<span className="ebq-brief-stat__num">{(brief.subtopics || []).length}</span>
						<span className="ebq-brief-stat__label">{__('subtopics', 'ebq-seo')}</span>
					</div>
				</div>
			</Section>

			{(brief.suggested_outline || []).length ? (
				<Section title={__('Suggested H2 outline', 'ebq-seo')} icon={<IconSparkle />}>
					<ol className="ebq-brief-outline">
						{brief.suggested_outline.map((h, i) => (
							<li key={i}>{h}</li>
						))}
					</ol>
				</Section>
			) : null}

			{(brief.subtopics || []).length ? (
				<Section title={__('Subtopics to cover', 'ebq-seo')} icon={<IconSparkle />}>
					<div className="ebq-brief-chips">
						{brief.subtopics.map((s, i) => (
							<span key={i} className="ebq-brief-chip">{s}</span>
						))}
					</div>
				</Section>
			) : null}

			{(brief.must_have_entities || []).length ? (
				<Section title={__('Entities to mention', 'ebq-seo')} icon={<IconSparkle />} collapsible defaultOpen={false}>
					<div className="ebq-brief-chips">
						{brief.must_have_entities.map((s, i) => (
							<span key={i} className="ebq-brief-chip ebq-brief-chip--entity">{s}</span>
						))}
					</div>
				</Section>
			) : null}

			{(brief.people_also_ask || []).length ? (
				<Section title={__('People also ask', 'ebq-seo')} icon={<IconChart />} collapsible defaultOpen={false}>
					<ul className="ebq-brief-paa">
						{brief.people_also_ask.map((q, i) => (
							<li key={i}>{q}</li>
						))}
					</ul>
				</Section>
			) : null}

			{(brief.internal_link_targets || []).length ? (
				<Section title={__('Internal link targets (your site)', 'ebq-seo')} icon={<IconChart />}>
					<ul className="ebq-brief-internal">
						{brief.internal_link_targets.map((t, i) => (
							<li key={i}>
								<a href={t.url} target="_blank" rel="noopener noreferrer">{t.url}</a>
								<span className="ebq-brief-internal__hint">
									{sprintf(__('anchor: "%s" · %d clicks/30d', 'ebq-seo'), t.anchor_hint, t.clicks_30d)}
								</span>
							</li>
						))}
					</ul>
				</Section>
			) : null}
		</>
	);
}
