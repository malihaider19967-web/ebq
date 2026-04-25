import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { safeUrl, safeCssUrl } from '../utils/sanitizeUrl';

function host(url) {
	if (!url) return '';
	try {
		return new URL(url).host.replace(/^www\./, '');
	} catch {
		return '';
	}
}

function trim(s, n) {
	const t = String(s || '');
	return t.length <= n ? t : t.slice(0, n - 1) + '…';
}

/**
 * Probes whether an image URL actually loads — gives the social cards a real
 * placeholder when the URL 404s, blocks via CORS, or is just garbage. Avoids
 * showing a torn-page icon to users.
 */
function useImageLoadable(url) {
	const safe = safeCssUrl(url);
	const [state, setState] = useState({ ok: false, loading: !!safe });

	useEffect(() => {
		if (!safe) {
			setState({ ok: false, loading: false });
			return;
		}
		let cancelled = false;
		setState({ ok: false, loading: true });
		const img = new window.Image();
		img.onload = () => { if (!cancelled) setState({ ok: true, loading: false }); };
		img.onerror = () => { if (!cancelled) setState({ ok: false, loading: false }); };
		img.src = safe;
		return () => { cancelled = true; img.onload = img.onerror = null; };
	}, [safe]);

	return { ...state, safeUrl: safe };
}

function MediaWell({ image, height }) {
	const { ok, loading, safeUrl: src } = useImageLoadable(image);
	if (loading) {
		return <div className="ebq-social__media" style={{ height }} aria-busy="true">{__('Loading…', 'ebq-seo')}</div>;
	}
	if (ok) {
		return <div className="ebq-social__media" style={{ height, backgroundImage: `url(${src})` }} aria-hidden />;
	}
	return (
		<div className="ebq-social__media" style={{ height }} aria-hidden>
			{image ? __('Image failed to load', 'ebq-seo') : __('No image', 'ebq-seo')}
		</div>
	);
}

export function FacebookCard({ url, title, description, image }) {
	const safe = safeUrl(url);
	return (
		<div className="ebq-social" role="img"
			aria-label={__('Facebook share preview', 'ebq-seo')}>
			<MediaWell image={image} height={130} />
			<div className="ebq-social__body">
				<div className="ebq-social__domain">{host(safe) || __('your-site.com', 'ebq-seo')}</div>
				<h4 className="ebq-social__title">
					{trim(title || __('Open Graph title preview', 'ebq-seo'), 88)}
				</h4>
				<p className="ebq-social__desc">
					{trim(description || __('Open Graph description preview.', 'ebq-seo'), 200)}
				</p>
			</div>
		</div>
	);
}

export function TwitterCard({ url, title, description, image }) {
	const safe = safeUrl(url);
	return (
		<div className="ebq-social ebq-social--twitter" role="img"
			aria-label={__('X / Twitter share preview', 'ebq-seo')}>
			<MediaWell image={image} height={200} />
			<div className="ebq-social__body">
				<h4 className="ebq-social__title">{trim(title || __('Card title', 'ebq-seo'), 88)}</h4>
				<p className="ebq-social__desc">
					{trim(description || __('Card description.', 'ebq-seo'), 200)}
				</p>
				<div className="ebq-social__domain" style={{ marginTop: 6 }}>
					{host(safe) || __('your-site.com', 'ebq-seo')}
				</div>
			</div>
		</div>
	);
}
