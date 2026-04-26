import { useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Section, Button, EmptyState, NeedsSetup } from './primitives';
import { IconSparkle } from './icons';
import { topicalGapsUnavailable } from './dependencyMessages';
import { publicConfig } from '../hooks/useEditorContext';

/**
 * Topical-coverage gap analysis. Manual trigger (it costs Serper credits
 * + Mistral tokens, so we don't auto-fire on every keystroke). Click
 * "Analyze top 5" → POSTs the post body to EBQ → Mistral compares the
 * subtopics → renders missing ones with one-line rationales + competitor
 * source links.
 *
 * Server-side cache is 7d on (post × content-hash × keyword × country),
 * so re-clicks within a week are free. We surface that in the UI ("cached
 * for 7d") so the user knows further clicks are cheap.
 */
export default function TopicalGaps({ postId, focusKeyword, content, isConnected }) {
	const [state, setState] = useState({ status: 'idle', data: null, error: null });

	const analyze = useCallback(() => {
		if (! postId || ! focusKeyword || focusKeyword.trim().length < 2) return;
		setState({ status: 'loading', data: null, error: null });
		apiFetch({
			path: `/ebq/v1/topical-gaps/${postId}`,
			method: 'POST',
			data: {
				focus_keyword: focusKeyword,
				content: String(content || '').slice(0, 80000),
			},
		}).then((res) => {
			if (res?.ok === false || res?.error) {
				setState({ status: 'error', data: null, error: res?.message || res?.error || 'Failed' });
			} else {
				setState({ status: 'ready', data: res?.gaps || null, error: null });
			}
		}).catch((err) => {
			setState({ status: 'error', data: null, error: err?.message || 'Network error' });
		});
	}, [postId, focusKeyword, content]);

	if (! isConnected) {
		return (
			<Section title={__('Topical gaps vs. top SERP', 'ebq-seo')} icon={<IconSparkle />}>
				<EmptyState
					title={__('Connect to EBQ for live gap analysis', 'ebq-seo')}
					sub={__('We compare your draft against the top 5 ranking pages and surface subtopics they cover that you don\'t.', 'ebq-seo')}
				/>
			</Section>
		);
	}

	const canRun = !!focusKeyword && focusKeyword.trim().length >= 2 && String(content || '').length >= 200;

	return (
		<Section
			title={__('Topical gaps vs. top SERP', 'ebq-seo')}
			icon={<IconSparkle />}
			aside={
				state.status === 'ready' ? (
					<Button size="sm" variant="ghost" onClick={analyze}>{__('Re-analyze', 'ebq-seo')}</Button>
				) : null
			}
		>
			{state.status === 'idle' ? (
				<>
					<p className="ebq-help" style={{ marginTop: 0 }}>
						{__('We scrape the top 5 results for your focus keyphrase and ask AI to extract the subtopics they cover. Anything they cover and you don\'t becomes a writing prompt.', 'ebq-seo')}
					</p>
					<Button variant="primary" onClick={analyze} disabled={!canRun}>
						<IconSparkle /> {__('Analyze top 5 SERP results', 'ebq-seo')}
					</Button>
					{!canRun ? (
						<p className="ebq-help" style={{ marginTop: 6 }}>
							{!focusKeyword || focusKeyword.trim().length < 2
								? __('Set a focus keyphrase first.', 'ebq-seo')
								: __('Write at least 200 characters of content first.', 'ebq-seo')}
						</p>
					) : null}
				</>
			) : null}

			{state.status === 'loading' ? (
				<p className="ebq-help"><span className="ebq-spinner" /> {__('Scraping top 5 + asking AI for subtopics… (8–15s)', 'ebq-seo')}</p>
			) : null}

			{state.status === 'error' ? (
				<>
					<p className="ebq-help" style={{ color: 'var(--ebq-bad-text)' }}>{state.error}</p>
					<Button size="sm" onClick={analyze}>{__('Retry', 'ebq-seo')}</Button>
				</>
			) : null}

			{state.status === 'ready' && state.data ? (
				<GapResults data={state.data} />
			) : null}
		</Section>
	);
}

function GapResults({ data }) {
	if (data.available === false) {
		const cfg = publicConfig();
		const setup = topicalGapsUnavailable(data.reason || null, cfg);
		return (
			<NeedsSetup
				feature={setup.feature}
				why={setup.why}
				fix={setup.fix}
				action={setup.action}
				tone={setup.tone}
			/>
		);
	}

	const missing = data.missing || [];
	const covered = data.covered || [];
	const yours = data.your_subtopics || [];
	const theirs = data.competitor_subtopics || [];

	return (
		<div className="ebq-topical-gaps">
			<div className="ebq-topical-gaps__summary">
				<div className="ebq-topical-gaps__stat">
					<span className="ebq-topical-gaps__stat-num">{covered.length}</span>
					<span className="ebq-topical-gaps__stat-label">{__('subtopics you cover', 'ebq-seo')}</span>
				</div>
				<div className="ebq-topical-gaps__stat ebq-topical-gaps__stat--missing">
					<span className="ebq-topical-gaps__stat-num">{missing.length}</span>
					<span className="ebq-topical-gaps__stat-label">{__('missing vs. top 5', 'ebq-seo')}</span>
				</div>
				<div className="ebq-topical-gaps__stat">
					<span className="ebq-topical-gaps__stat-num">{theirs.length}</span>
					<span className="ebq-topical-gaps__stat-label">{__('competitor subtopics', 'ebq-seo')}</span>
				</div>
			</div>

			{missing.length > 0 ? (
				<div className="ebq-topical-gaps__missing">
					<h4 className="ebq-topical-gaps__heading">{__('Subtopics to add', 'ebq-seo')}</h4>
					<ul>
						{missing.map((m, i) => (
							<li key={i} className="ebq-topical-gaps__row">
								<div className="ebq-topical-gaps__row-head">
									<strong>{m.topic}</strong>
								</div>
								{m.rationale ? <p className="ebq-topical-gaps__rationale">{m.rationale}</p> : null}
								{m.sources?.length ? (
									<div className="ebq-topical-gaps__sources">
										{m.sources.map((s, j) => (
											<a key={j} href={s.url} target="_blank" rel="noopener noreferrer" className="ebq-topical-gaps__source">
												{s.title || s.url}
											</a>
										))}
									</div>
								) : null}
							</li>
						))}
					</ul>
				</div>
			) : (
				<EmptyState
					title={__('No gaps found', 'ebq-seo')}
					sub={__('Your content covers every subtopic the top 5 results do. Strong topical authority — keep going.', 'ebq-seo')}
				/>
			)}

			{yours.length > 0 ? (
				<details className="ebq-topical-gaps__details">
					<summary>{__('Subtopics in your draft', 'ebq-seo')} ({yours.length})</summary>
					<div className="ebq-topical-gaps__chips">
						{yours.map((s, i) => <span key={i} className="ebq-topical-gaps__chip">{s}</span>)}
					</div>
				</details>
			) : null}

			{theirs.length > 0 ? (
				<details className="ebq-topical-gaps__details">
					<summary>{__('Subtopics in the top 5', 'ebq-seo')} ({theirs.length})</summary>
					<div className="ebq-topical-gaps__chips">
						{theirs.map((s, i) => <span key={i} className="ebq-topical-gaps__chip">{s}</span>)}
					</div>
				</details>
			) : null}

			<p className="ebq-help" style={{ marginTop: 8, marginBottom: 0 }}>
				{__('Cached for 7 days on EBQ — re-analysis after content changes is free.', 'ebq-seo')}
			</p>
		</div>
	);
}
