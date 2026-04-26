import { __ } from '@wordpress/i18n';
import AiWriterTab from './tabs/AiWriterTab';
import { HQ_CONFIG } from './api';
import ConnectionGuide from './components/ConnectionGuide';

/**
 * Standalone wrapper for the AI Writer when mounted as its own top-level
 * admin page (registered by EBQ_AiWriter_Page). Renders the same tab
 * component the HQ used to expose, but without the HQ chrome — just a
 * simple title bar above the editor pane.
 */
export default function AiWriterStandalone() {
	if (!HQ_CONFIG.isConnected) {
		return (
			<div className="ebq-hq-wrap ebq-hq-wrap--standalone">
				<header className="ebq-hq-topbar">
					<div className="ebq-hq-topbar__brand">
						<div className="ebq-hq-topbar__mark">E</div>
						<div>
							<h1 className="ebq-hq-topbar__title">{__('AI Writer', 'ebq-seo')}</h1>
							<p className="ebq-hq-topbar__sub">{__('Connect EBQ to use the AI Writer.', 'ebq-seo')}</p>
						</div>
					</div>
				</header>
				<div className="ebq-hq-body">
					<ConnectionGuide />
				</div>
			</div>
		);
	}

	return (
		<div className="ebq-hq-wrap ebq-hq-wrap--standalone">
			<header className="ebq-hq-topbar">
				<div className="ebq-hq-topbar__brand">
					<div className="ebq-hq-topbar__mark">E</div>
					<div>
						<h1 className="ebq-hq-topbar__title">{__('AI Writer', 'ebq-seo')}</h1>
						<p className="ebq-hq-topbar__sub">
							{__('Draft a post from a focus keyword and the SERP brief, section by section. Save as a fresh WordPress draft when you\'re ready.', 'ebq-seo')}
						</p>
					</div>
				</div>
			</header>
			<div className="ebq-hq-body">
				<AiWriterTab />
			</div>
		</div>
	);
}
