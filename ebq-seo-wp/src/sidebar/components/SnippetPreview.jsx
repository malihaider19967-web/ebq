import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { safeUrl, safeCssUrl } from '../utils/sanitizeUrl';

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
 * Off-DOM Image() probe so we don't render a torn-image icon if the user-supplied
 * URL 404s, blocks via CORS, or is just garbage. Mirrors the SocialCards probe.
 */
function useImageLoadable(url) {
	const safe = safeCssUrl(url);
	const [ok, setOk] = useState(false);
	useEffect(() => {
		setOk(false);
		if (!safe) return;
		let cancelled = false;
		const img = new window.Image();
		img.onload = () => { if (!cancelled) setOk(true); };
		img.onerror = () => { if (!cancelled) setOk(false); };
		img.src = safe;
		return () => { cancelled = true; img.onload = img.onerror = null; };
	}, [safe]);
	return ok ? safe : '';
}

/**
 * Google-style desktop SERP card. Mobile variant tightens title size.
 *
 * Defensive: every URL passes through safeUrl so an attacker-supplied
 * `_ebq_canonical` of `javascript:alert(1)` can't fire in a click.
 *
 * If `image` is supplied (and loads), shows Google's right-aligned
 * thumbnail tile alongside the result text — what real SERP cards look
 * like for posts with featured images.
 */
export default function SnippetPreview({ url, siteName, title, description, image, mobile = false }) {
	const safe = safeUrl(url);
	const { host, path } = deriveBreadcrumbs(safe);
	const safeTitle = trim(title || __('SEO title preview', 'ebq-seo'), mobile ? 60 : 70);
	const safeDesc = trim(
		description || __('Add a meta description to control how your page appears in search results.', 'ebq-seo'),
		mobile ? 130 : 156
	);
	const initial = (siteName || host || 'E').trim().charAt(0).toUpperCase() || 'E';
	const thumbUrl = useImageLoadable(image);

	return (
		<div className={`ebq-snippet${mobile ? ' ebq-snippet--mobile' : ''}${thumbUrl ? ' ebq-snippet--has-image' : ''}`}
			role="img" aria-label={__('Search result preview', 'ebq-seo')}>
			<div className="ebq-snippet__main">
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
			{thumbUrl ? (
				<div className="ebq-snippet__thumb" style={{ backgroundImage: `url(${thumbUrl})` }} aria-hidden />
			) : null}
		</div>
	);
}
