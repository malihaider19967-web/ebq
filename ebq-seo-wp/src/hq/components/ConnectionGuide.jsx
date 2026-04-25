import { __ } from '@wordpress/i18n';
import { useCallback, useState } from '@wordpress/element';
import { HQ_CONFIG } from '../api';

/**
 * Friendly first-run / disconnected state. Shown in two places:
 *   1. App.jsx — when the plugin has no token at all (HQ_CONFIG.isConnected
 *      is false on first install).
 *   2. ErrorState — when an API call returns {ok:false, error:'not_connected'}
 *      because the saved token was revoked / expired upstream.
 *
 * Layout: hero blurb + 3-step "what happens when you click Connect" + a big
 * primary CTA, plus a discreet "I just connected — recheck" button so users
 * who connect in another tab don't have to refresh the whole admin.
 */
export default function ConnectionGuide({ compact = false, reason = '' }) {
	const [reloading, setReloading] = useState(false);
	const recheck = useCallback(() => {
		setReloading(true);
		window.location.reload();
	}, []);

	const Wrap = compact ? CompactWrap : FullWrap;

	return (
		<Wrap>
			{reason ? (
				<div className="ebq-hq-connect__reason">
					<span aria-hidden>!</span>
					<span>{reasonLabel(reason)}</span>
				</div>
			) : null}

			<h2 className="ebq-hq-connect__title">
				{__('Connect this site to EBQ.io', 'ebq-seo')}
			</h2>
			<p className="ebq-hq-connect__lead">
				{__('EBQ HQ pulls Search Console rankings, page audits, and the full opportunity feed from your EBQ.io workspace. The connection takes about 30 seconds — click below to start.', 'ebq-seo')}
			</p>

			<ol className="ebq-hq-connect__steps">
				<li>
					<span className="ebq-hq-connect__step-num">1</span>
					<div>
						<strong>{__('Click "Connect this site"', 'ebq-seo')}</strong>
						<p>{__('We open EBQ.io in a new tab and pre-fill the connection details for this WordPress site.', 'ebq-seo')}</p>
					</div>
				</li>
				<li>
					<span className="ebq-hq-connect__step-num">2</span>
					<div>
						<strong>{__('Pick the workspace', 'ebq-seo')}</strong>
						<p>{__('Sign in to EBQ.io (or create a free workspace) and select which website this WordPress install represents.', 'ebq-seo')}</p>
					</div>
				</li>
				<li>
					<span className="ebq-hq-connect__step-num">3</span>
					<div>
						<strong>{__('Approve the token', 'ebq-seo')}</strong>
						<p>{__('A scoped, read-only token is sent back to this WordPress site automatically. You\'ll land here with live data flowing.', 'ebq-seo')}</p>
					</div>
				</li>
			</ol>

			<div className="ebq-hq-connect__cta">
				<a className="ebq-hq-btn ebq-hq-btn--primary ebq-hq-btn--md" href={HQ_CONFIG.connectUrl || HQ_CONFIG.settingsUrl}>
					{__('Connect this site', 'ebq-seo')} →
				</a>
				<button type="button" className="ebq-hq-connect__recheck" onClick={recheck} disabled={reloading}>
					{reloading ? __('Re-checking…', 'ebq-seo') : __('I just connected — recheck', 'ebq-seo')}
				</button>
			</div>

			<p className="ebq-hq-connect__foot">
				{__('No credit card required. The token is per-website and only grants read access to insights.', 'ebq-seo')}
			</p>
		</Wrap>
	);
}

function FullWrap({ children }) {
	return (
		<div className="ebq-hq-connect ebq-hq-connect--full">
			<div className="ebq-hq-connect__panel">{children}</div>
		</div>
	);
}

function CompactWrap({ children }) {
	return (
		<div className="ebq-hq-connect ebq-hq-connect--compact">
			<div className="ebq-hq-connect__panel">{children}</div>
		</div>
	);
}

function reasonLabel(reason) {
	if (reason === 'not_connected') return __('We don\'t have a valid EBQ token for this site yet.', 'ebq-seo');
	if (reason === 'http_401') return __('The saved token was rejected by EBQ.io — it may have been revoked. Reconnect below.', 'ebq-seo');
	if (reason === 'http_403') return __('The saved token doesn\'t have access to this workspace. Reconnect to mint a fresh one.', 'ebq-seo');
	if (reason === 'network_error') return __('Could not reach EBQ.io. Check your network and retry.', 'ebq-seo');
	return reason;
}
