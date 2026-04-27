import { __ } from '@wordpress/i18n';
import AiWriterTab from './tabs/AiWriterTab';
import { HQ_CONFIG } from './api';
import ConnectionGuide from './components/ConnectionGuide';

/**
 * Standalone wrapper for the AI Writer when mounted as its own top-level
 * admin page (registered by EBQ_AiWriter_Page). Mirrors the App.jsx
 * outer-div convention so we don't double-wrap the PHP-rendered
 * .ebq-hq-wrap (which carries the negative left margin needed to break
 * out of WP admin's gutter).
 */
export default function AiWriterStandalone() {
	if (!HQ_CONFIG.isConnected) {
		return (
			<div className="ebq-hq">
				<header className="ebq-hq-topbar">
					<div className="ebq-hq-topbar__brand">
						<span className="ebq-hq-topbar__mark" aria-hidden>E</span>
						<div>
							<h1 className="ebq-hq-topbar__title">{__('AI Writer', 'ebq-seo')}</h1>
							<p className="ebq-hq-topbar__sub">{__('Connect EBQ to use the AI Writer.', 'ebq-seo')}</p>
						</div>
					</div>
				</header>
				<main className="ebq-hq-main">
					<ConnectionGuide reason="not_connected" />
				</main>
			</div>
		);
	}

	return (
		<div className="ebq-hq">
			<header className="ebq-hq-topbar">
				<div className="ebq-hq-topbar__brand">
					<span className="ebq-hq-topbar__mark" aria-hidden>E</span>
					<div>
						<h1 className="ebq-hq-topbar__title">{__('AI Writer', 'ebq-seo')}</h1>
						<p className="ebq-hq-topbar__sub">
							{__('Draft a post from a focus keyword and the SERP brief, section by section. Save as a fresh WordPress draft when you\'re ready.', 'ebq-seo')}
						</p>
					</div>
				</div>
			</header>
			<main className="ebq-hq-main">
				<AiWriterTab />
			</main>
		</div>
	);
}
