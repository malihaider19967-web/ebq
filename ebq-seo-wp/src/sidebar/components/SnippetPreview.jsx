import { __ } from '@wordpress/i18n';
import { safeUrl } from '../utils/sanitizeUrl';

function trim(str, max) {
	const s = String(str || '');
	if (!s) return '';
	return s.length <= max ? s : s.slice(0, max - 1) + '…';
}

function deriveBreadcrumbs(url) {
	if (!url) return { host: '', path: [] };
	try {
		const u = new URL(url);
		const path = u.pathname.split('/').filter(Boolean);
		return { host: u.host.replace(/^www\./, ''), path: path.slice(0, 3) };
	} catch {
		return { host: url, path: [] };
	}
}

/**
 * Google-style desktop SERP card. Mobile variant tightens title size.
 *
 * Defensive: every URL passes through safeUrl so an attacker-supplied
 * `_ebq_canonical` of `javascript:alert(1)` can't fire in a click.
 */
export default function SnippetPreview({ url, siteName, title, description, mobile = false }) {
	const safe = safeUrl(url);
	const { host, path } = deriveBreadcrumbs(safe);
	const safeTitle = trim(title || __('SEO title preview', 'ebq-seo'), mobile ? 60 : 70);
	const safeDesc = trim(
		description || __('Add a meta description to control how your page appears in search results.', 'ebq-seo'),
		mobile ? 130 : 156
	);
	const initial = (siteName || host || 'E').trim().charAt(0).toUpperCase() || 'E';

	return (
		<div className={`ebq-snippet${mobile ? ' ebq-snippet--mobile' : ''}`} role="img"
			aria-label={__('Search result preview', 'ebq-seo')}>
			<div className="ebq-snippet__breadcrumbs">
				<span className="ebq-snippet__favicon" aria-hidden>{initial}</span>
				<span className="ebq-snippet__site" title={siteName || host}>{trim(siteName || host, 28)}</span>
				{path.length ? (
					<>
						{path.map((seg, i) => (
							<span key={i}>
								<span className="ebq-snippet__sep" aria-hidden>›</span> {trim(decodeURIComponent(seg).replace(/-/g, ' '), 24)}
							</span>
						))}
					</>
				) : (
					<span className="ebq-snippet__sep" aria-hidden>› {trim(host, 28)}</span>
				)}
			</div>
			<a
				className="ebq-snippet__title"
				href={safe || '#'}
				title={safe || ''}
				onClick={(e) => e.preventDefault()}
				rel="noopener noreferrer"
			>
				{safeTitle}
			</a>
			<p className="ebq-snippet__desc">{safeDesc}</p>
		</div>
	);
}
