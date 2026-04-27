import { __ } from '@wordpress/i18n';

/**
 * Single source of truth for "this feature can't run, here's why and
 * how to fix it" messages across the sidebar.
 *
 * Every entry returns:
 *   { feature, why, fix, action?: { label, url?, onClick? }, tone? }
 *
 * Consumed by `<NeedsSetup />` in primitives.jsx. Keeping the copy
 * centralized means a single place to update when the actual fix path
 * changes (e.g. EBQ moves a settings page).
 *
 * Conventions:
 *   - `cfg` is the object returned by `publicConfig()` in useEditorContext
 *     (gives us `appBase`, `settingsUrl`, `tier`, etc.)
 *   - URLs always open in a new tab (target=_blank handled by NeedsSetup)
 *   - Reasons are the EXACT string values the backend returns in
 *     `data.reason` so the resolver maps directly without fuzzy matching
 */

const t = (k, en) => __(en, 'ebq-seo');   // shorter alias — every string still extractable

// ─── Live SEO score ─────────────────────────────────────────────
export function liveScoreUnavailable(reason, cfg) {
	switch (reason) {
		case 'url_not_for_website':
			return {
				feature: t('Live SEO score', 'Live SEO score'),
				why: t('This URL doesn\'t belong to the EBQ workspace this site is connected to.', 'This URL doesn\'t belong to the EBQ workspace this site is connected to.'),
				fix: t('Either re-connect the plugin to the correct workspace, or check the post\'s permalink — a redirect or staging URL might be in the way.', 'Either re-connect the plugin to the correct workspace, or check the post\'s permalink — a redirect or staging URL might be in the way.'),
				action: { label: t('Open EBQ settings', 'Open EBQ settings'), url: cfg.settingsUrl },
				tone: 'warn',
			};
		// `no_gsc_data_for_url` was the previous fallback. It's no longer
		// returned by the backend — fresh URLs now produce a partial
		// score from audit + indexing + backlinks, with a "Provisional"
		// banner shown inside the score popover instead of a setup card.
		// Kept the default branch as a generic safety net for any new
		// reason the backend might surface.
		default:
			return {
				feature: t('Live SEO score', 'Live SEO score'),
				why: t('The live score is unavailable for this URL right now.', 'The live score is unavailable for this URL right now.'),
				fix: t('Reload the editor in a moment. If this persists, check that the EBQ connection is active in plugin settings.', 'Reload the editor in a moment. If this persists, check that the EBQ connection is active in plugin settings.'),
				action: cfg.settingsUrl ? { label: t('Open EBQ settings', 'Open EBQ settings'), url: cfg.settingsUrl } : null,
				tone: 'warn',
			};
	}
}

// ─── Topical gaps ───────────────────────────────────────────────
export function topicalGapsUnavailable(reason, cfg) {
	switch (reason) {
		case 'missing_focus_keyword':
			return {
				feature: t('Topical gaps vs. top SERP', 'Topical gaps vs. top SERP'),
				why: t('A focus keyphrase is required — the analysis compares your page against the top-5 ranking pages for that exact query.', 'A focus keyphrase is required — the analysis compares your page against the top-5 ranking pages for that exact query.'),
				fix: t('Set the focus keyphrase in the SEO tab above, then re-analyze.', 'Set the focus keyphrase in the SEO tab above, then re-analyze.'),
				tone: 'info',
			};
		case 'content_too_short':
			return {
				feature: t('Topical gaps vs. top SERP', 'Topical gaps vs. top SERP'),
				why: t('There isn\'t enough content yet to compare against competitors (we need at least 200 characters).', 'There isn\'t enough content yet to compare against competitors (we need at least 200 characters).'),
				fix: t('Draft a couple of paragraphs first — even rough copy works. The analyzer will look for missing subtopics in whatever you have.', 'Draft a couple of paragraphs first — even rough copy works. The analyzer will look for missing subtopics in whatever you have.'),
				tone: 'info',
			};
		case 'llm_not_configured':
			return {
				feature: t('Topical gaps vs. top SERP', 'Topical gaps vs. top SERP'),
				why: t('AI gap analysis isn\'t configured on this EBQ workspace.', 'AI gap analysis isn\'t configured on this EBQ workspace.'),
				fix: t('Ask your EBQ admin to add an LLM API key in EBQ → Settings → AI integrations.', 'Ask your EBQ admin to add an LLM API key in EBQ → Settings → AI integrations.'),
				action: cfg.appBase ? { label: t('Open EBQ settings', 'Open EBQ settings'), url: cfg.appBase + '/settings' } : null,
				tone: 'warn',
			};
		case 'no_serp_data':
			return {
				feature: t('Topical gaps vs. top SERP', 'Topical gaps vs. top SERP'),
				why: t('We couldn\'t pull live SERP results for that keyphrase right now.', 'We couldn\'t pull live SERP results for that keyphrase right now.'),
				fix: t('This is usually temporary — try again in a minute. If it persists, the keyphrase may be too niche for the SERP scraper to return organic results.', 'This is usually temporary — try again in a minute. If it persists, the keyphrase may be too niche for the SERP scraper to return organic results.'),
				tone: 'warn',
			};
		case 'llm_parse_failed':
			return {
				feature: t('Topical gaps vs. top SERP', 'Topical gaps vs. top SERP'),
				why: t('The AI returned malformed output we couldn\'t parse.', 'The AI returned malformed output we couldn\'t parse.'),
				fix: t('Click Re-analyze — this almost always succeeds on the second try. The result is also cached for 7 days once it works.', 'Click Re-analyze — this almost always succeeds on the second try. The result is also cached for 7 days once it works.'),
				tone: 'warn',
			};
		default:
			return {
				feature: t('Topical gaps vs. top SERP', 'Topical gaps vs. top SERP'),
				why: t('Gap analysis is unavailable right now.', 'Gap analysis is unavailable right now.'),
				fix: t('Try Re-analyze. If it keeps failing, contact EBQ support with the post URL.', 'Try Re-analyze. If it keeps failing, contact EBQ support with the post URL.'),
				tone: 'warn',
			};
	}
}

// ─── Entity coverage ────────────────────────────────────────────
export function entityCoverageUnavailable(reason, cfg) {
	switch (reason) {
		case 'no_audit':
			return {
				feature: t('Entity coverage (E-E-A-T)', 'Entity coverage (E-E-A-T)'),
				why: t('We need a completed page audit for this URL to compare your entities against competitors\'.', 'We need a completed page audit for this URL to compare your entities against competitors\'.'),
				fix: t('Trigger an audit from EBQ HQ → Page Audits for this page (the editor\'s auto-audit runs in lite mode and skips the competitor SERP fetch this analysis needs).', 'Trigger an audit from EBQ HQ → Page Audits for this page (the editor\'s auto-audit runs in lite mode and skips the competitor SERP fetch this analysis needs).'),
				action: cfg.appBase ? { label: t('Open Page Audits in EBQ', 'Open Page Audits in EBQ'), url: cfg.appBase + '/custom-audit' } : null,
				tone: 'info',
			};
		case 'no_body_text':
			return {
				feature: t('Entity coverage (E-E-A-T)', 'Entity coverage (E-E-A-T)'),
				why: t('The audit on file has no extracted body text — possibly because the page was empty when audited or the audit fetched a redirect/blocked response.', 'The audit on file has no extracted body text — possibly because the page was empty when audited or the audit fetched a redirect/blocked response.'),
				fix: t('Re-run the audit from EBQ HQ → Page Audits after the page has real content live.', 'Re-run the audit from EBQ HQ → Page Audits after the page has real content live.'),
				action: cfg.appBase ? { label: t('Open Page Audits in EBQ', 'Open Page Audits in EBQ'), url: cfg.appBase + '/custom-audit' } : null,
				tone: 'warn',
			};
		case 'llm_not_configured':
			return {
				feature: t('Entity coverage (E-E-A-T)', 'Entity coverage (E-E-A-T)'),
				why: t('AI extraction isn\'t configured on this EBQ workspace.', 'AI extraction isn\'t configured on this EBQ workspace.'),
				fix: t('Ask your EBQ admin to add an LLM API key in EBQ → Settings → AI integrations.', 'Ask your EBQ admin to add an LLM API key in EBQ → Settings → AI integrations.'),
				action: cfg.appBase ? { label: t('Open EBQ settings', 'Open EBQ settings'), url: cfg.appBase + '/settings' } : null,
				tone: 'warn',
			};
		case 'llm_parse_failed':
			return {
				feature: t('Entity coverage (E-E-A-T)', 'Entity coverage (E-E-A-T)'),
				why: t('The AI returned malformed output we couldn\'t parse.', 'The AI returned malformed output we couldn\'t parse.'),
				fix: t('Click Re-analyze — this usually succeeds on retry. Result is cached for 7 days once it works.', 'Click Re-analyze — this usually succeeds on retry. Result is cached for 7 days once it works.'),
				tone: 'warn',
			};
		default:
			return {
				feature: t('Entity coverage (E-E-A-T)', 'Entity coverage (E-E-A-T)'),
				why: t('Entity coverage is unavailable right now.', 'Entity coverage is unavailable right now.'),
				fix: t('Try Re-analyze. If it keeps failing, contact EBQ support with the post URL.', 'Try Re-analyze. If it keeps failing, contact EBQ support with the post URL.'),
				tone: 'warn',
			};
	}
}
