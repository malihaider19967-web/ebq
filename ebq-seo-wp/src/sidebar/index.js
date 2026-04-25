/**
 * EBQ SEO Gutenberg sidebar.
 *
 * Two mount points:
 *  1. PluginSidebar — the pinned right-rail panel. The flagship UX, full height,
 *     toggled by an icon button registered via PluginSidebarMoreMenuItem.
 *  2. The classic-style metabox div (#ebq-seo-editor-root) injected by the PHP
 *     class `EBQ_Block_Editor_Seo_Metabox` — kept as a fallback / familiar
 *     in-editor surface.
 *
 * Both render the same React tree.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot, StrictMode } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { __ } from '@wordpress/i18n';

import App from './App';
import ErrorBoundary from './components/ErrorBoundary';
import './sidebar.css';

const SIDEBAR_NAME = 'ebq-seo-sidebar';
const ICON = (
	<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden>
		<rect x="2" y="2" width="16" height="16" rx="4" fill="#5b3df5" />
		<text
			x="10" y="14" textAnchor="middle"
			fontFamily="-apple-system, Segoe UI, sans-serif"
			fontWeight="700" fontSize="12" fill="#fff"
		>
			E
		</text>
	</svg>
);

function PluginSidebarRoot() {
	return (
		<>
			<PluginSidebarMoreMenuItem target={SIDEBAR_NAME} icon={ICON}>
				{__('EBQ SEO', 'ebq-seo')}
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				name={SIDEBAR_NAME}
				title={__('EBQ SEO', 'ebq-seo')}
				icon={ICON}
				className="ebq-plugin-sidebar"
			>
				<StrictMode>
					<ErrorBoundary>
						<App />
					</ErrorBoundary>
				</StrictMode>
			</PluginSidebar>
		</>
	);
}

registerPlugin('ebq-seo', { render: PluginSidebarRoot });

/**
 * Mount inside the metabox region too, so users get the same UI even if the
 * pinned sidebar is closed. Idempotent — safe to call multiple times.
 */
function mountMetabox() {
	const el = document.getElementById('ebq-seo-editor-root');
	if (!el || el.dataset.ebqMounted === '1') {
		return;
	}
	el.dataset.ebqMounted = '1';
	el.classList.add('ebq-metabox-mount');
	createRoot(el).render(
		<StrictMode>
			<ErrorBoundary>
				<App />
			</ErrorBoundary>
		</StrictMode>
	);
}

domReady(mountMetabox);
