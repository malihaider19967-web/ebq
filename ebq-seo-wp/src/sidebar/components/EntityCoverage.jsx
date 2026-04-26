import { useState, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Section, Button, EmptyState, Pill, NeedsSetup } from './primitives';
import { IconSparkle } from './icons';
import { entityCoverageUnavailable } from './dependencyMessages';
import { publicConfig } from '../hooks/useEditorContext';

/**
 * Phase 3 #11 — Entity coverage analysis (E-E-A-T signal).
 *
 * Compares the entities in the user's page against the entities top-3
 * SERP competitors mention. Surfaces the gap as actionable "you should
 * mention X because…" rows. Reuses the audit's already-cached body text
 * + benchmark — no new audit required, but does require a completed
 * audit to exist.
 *
 * Manual trigger (one Mistral call per click). Cached server-side 7d
 * per content-hash so re-clicks within a week are free.
 */
export default function EntityCoverage({ postId, isConnected }) {
	const [state, setState] = useState({ status: 'idle', data: null, error: null });

	const analyze = useCallback(() => {
		setState({ status: 'loading', data: null, error: null });
		apiFetch({ path: `/ebq/v1/entity-coverage/${postId}` })
			.then((res) => {
				const inner = res?.entities || {};
				if (inner?.ok === false || res?.ok === false) {
					setState({
						status: 'error',
						data: null,
						error: inner?.reason || res?.message || res?.error || 'Failed',
					});
				} else if (inner?.ok) {
					setState({ status: 'ready', data: inner, error: null });
				} else {
					setState({ status: 'error', data: null, error: 'Empty response' });
				}
			})
			.catch((err) => {
				setState({ status: 'error', data: null, error: err?.message || 'Network error' });
			});
	}, [postId]);

	if (!isConnected) {
		return (
			<Section title={__('Entity coverage (E-E-A-T)', 'ebq-seo')} icon={<IconSparkle />}>
				<EmptyState
					title={__('Connect to EBQ', 'ebq-seo')}
					sub={__('Compares entities your page mentions against entities top-ranking competitors mention. Surfaces the gap so you can close it.', 'ebq-seo')}
				/>
			</Section>
		);
	}

	return (
		<Section
			title={__('Entity coverage (E-E-A-T)', 'ebq-seo')}
			icon={<IconSparkle />}
			aside={state.status === 'ready' ? (
				<Button size="sm" variant="ghost" onClick={analyze}>{__('Re-analyze', 'ebq-seo')}</Button>
			) : null}
		>
			{state.status === 'idle' ? (
				<>
					<p className="ebq-help" style={{ marginTop: 0 }}>
						{__('We extract people, brands, products, and concepts from your page and from the top-3 ranking competitors, then surface what they cover and you don\'t. Strongest signal for E-E-A-T improvements.', 'ebq-seo')}
					</p>
					<Button variant="primary" onClick={analyze}>
						<IconSparkle /> {__('Analyze entity coverage', 'ebq-seo')}
					</Button>
				</>
			) : null}

			{state.status === 'loading' ? (
				<p className="ebq-help"><span className="ebq-spinner" /> {__('Extracting entities… (10–20s)', 'ebq-seo')}</p>
			) : null}

			{state.status === 'error' ? (() => {
				const cfg = publicConfig();
				const setup = entityCoverageUnavailable(state.error || null, cfg);
				return (
					<>
						<NeedsSetup
							feature={setup.feature}
							why={setup.why}
							fix={setup.fix}
							action={setup.action}
							tone={setup.tone}
						/>
						<div style={{ marginTop: 8 }}>
							<Button size="sm" onClick={analyze}>{__('Retry', 'ebq-seo')}</Button>
						</div>
					</>
				);
			})() : null}

			{state.status === 'ready' && state.data ? (
				<div className="ebq-entity-coverage">
					<div className="ebq-entity-summary">
						<div className="ebq-entity-stat">
							<span className="ebq-entity-stat__num">{state.data.yours.length}</span>
							<span className="ebq-entity-stat__label">{__('you cover', 'ebq-seo')}</span>
						</div>
						<div className="ebq-entity-stat ebq-entity-stat--missing">
							<span className="ebq-entity-stat__num">{state.data.missing.length}</span>
							<span className="ebq-entity-stat__label">{__('missing vs top 3', 'ebq-seo')}</span>
						</div>
						<div className="ebq-entity-stat">
							<span className="ebq-entity-stat__num">{state.data.competitors.length}</span>
							<span className="ebq-entity-stat__label">{__('competitor entities', 'ebq-seo')}</span>
						</div>
					</div>

					{state.data.missing.length ? (
						<div className="ebq-entity-missing">
							<h4>{__('Entities to add', 'ebq-seo')}</h4>
							<ul>
								{state.data.missing.map((m, i) => (
									<li key={i}>
										<div className="ebq-entity-missing__head">
											<strong>{m.entity}</strong>
											<Pill tone="warn">{m.type}</Pill>
										</div>
										{m.why ? <p className="ebq-entity-missing__why">{m.why}</p> : null}
									</li>
								))}
							</ul>
						</div>
					) : (
						<EmptyState
							title={__('No entity gaps', 'ebq-seo')}
							sub={__('You mention every entity the top-3 competitors mention. Strong topical authority signal.', 'ebq-seo')}
						/>
					)}

					{state.data.yours.length ? (
						<details className="ebq-entity-details">
							<summary>{__('Entities in your page', 'ebq-seo')} ({state.data.yours.length})</summary>
							<div className="ebq-entity-chips">
								{state.data.yours.map((e, i) => <span key={i} className="ebq-entity-chip">{e}</span>)}
							</div>
						</details>
					) : null}
					{state.data.competitors.length ? (
						<details className="ebq-entity-details">
							<summary>{__('Entities competitors mention', 'ebq-seo')} ({state.data.competitors.length})</summary>
							<div className="ebq-entity-chips">
								{state.data.competitors.map((e, i) => <span key={i} className="ebq-entity-chip">{e}</span>)}
							</div>
						</details>
					) : null}

					<p className="ebq-help" style={{ marginTop: 8, marginBottom: 0 }}>
						{__('Cached for 7 days on EBQ — re-analyze after content edits is free.', 'ebq-seo')}
					</p>
				</div>
			) : null}
		</Section>
	);
}
