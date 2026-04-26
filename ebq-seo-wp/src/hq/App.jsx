import { useState, useCallback, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { HQ_CONFIG } from './api';
import { Button } from './components/primitives';
import AddKeywordModal from './components/AddKeywordModal';
import ConnectionGuide from './components/ConnectionGuide';

import OverviewTab from './tabs/OverviewTab';
import PerformanceTab from './tabs/PerformanceTab';
import KeywordsTab from './tabs/KeywordsTab';
import GscKeywordsTab from './tabs/GscKeywordsTab';
import PagesTab from './tabs/PagesTab';
import IndexStatusTab from './tabs/IndexStatusTab';
import InsightsTab from './tabs/InsightsTab';
import RedirectSuggestionsTab from './tabs/RedirectSuggestionsTab';

const TABS = [
	{ id: 'overview',     label: __('Overview', 'ebq-seo'),       Component: OverviewTab,     icon: HomeIcon },
	{ id: 'performance',  label: __('SEO Performance', 'ebq-seo'), Component: PerformanceTab,  icon: ChartIcon },
	{ id: 'keywords',     label: __('Keywords', 'ebq-seo'),       Component: GscKeywordsTab,  icon: SearchIcon },
	{ id: 'rank_tracker', label: __('Rank Tracker', 'ebq-seo'),   Component: KeywordsTab,     icon: TargetIcon },
	{ id: 'pages',        label: __('Pages', 'ebq-seo'),          Component: PagesTab,        icon: PageIcon },
	{ id: 'index_status', label: __('Index Status', 'ebq-seo'),   Component: IndexStatusTab,  icon: ShieldIcon },
	{ id: 'insights',     label: __('Insights', 'ebq-seo'),       Component: InsightsTab,     icon: SparkIcon },
	{ id: 'redirects_ai', label: __('Redirects (AI)', 'ebq-seo'), Component: RedirectSuggestionsTab, icon: SparkIcon },
];

export default function App() {
	const [tab, setTab] = useState('overview');
	const [trackSeed, setTrackSeed] = useState(null); // null = closed, string = open with seed
	const [trackToast, setTrackToast] = useState(null);

	// Deep-link handling: ?ebq_track=... lands users from the admin bar,
	// post row action, or any external link straight onto Rank Tracker with
	// the AddKeywordModal pre-seeded. ebq_track=1 means "open empty form".
	useEffect(() => {
		const params = new URLSearchParams(window.location.search);
		const trackParam = params.get('ebq_track');
		if (trackParam !== null) {
			setTab('rank_tracker');
			setTrackSeed(trackParam === '1' || trackParam === '' ? '' : decodeURIComponent(trackParam));
			// Clean the URL so a refresh doesn't re-open the modal.
			params.delete('ebq_track');
			params.delete('ebq_track_url');
			const next = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
			window.history.replaceState({}, '', next);
		}
	}, []);

	if (!HQ_CONFIG.isConnected) {
		return <NotConnected />;
	}

	const Active = (TABS.find((t) => t.id === tab) || TABS[0]).Component;

	return (
		<div className="ebq-hq">
			<header className="ebq-hq-topbar">
				<div className="ebq-hq-topbar__brand">
					<span className="ebq-hq-topbar__mark" aria-hidden>E</span>
					<div>
						<h1 className="ebq-hq-topbar__title">EBQ Head Quarter</h1>
						<p className="ebq-hq-topbar__sub">
							{HQ_CONFIG.workspaceDomain ? <strong>{HQ_CONFIG.workspaceDomain}</strong> : __('Connected workspace', 'ebq-seo')} · {__('Live data from EBQ.io', 'ebq-seo')}
						</p>
					</div>
				</div>
				<div className="ebq-hq-topbar__actions">
					<Button variant="ghost" href="https://ebq.io" target="_blank">{__('Open EBQ.io', 'ebq-seo')} ↗</Button>
				</div>
			</header>

			<nav className="ebq-hq-nav" role="tablist" aria-label={__('Sections', 'ebq-seo')}>
				{TABS.map((t) => {
					const Icon = t.icon;
					return (
						<button
							key={t.id}
							type="button"
							role="tab"
							aria-selected={tab === t.id}
							className={`ebq-hq-nav__btn${tab === t.id ? ' is-active' : ''}`}
							onClick={() => setTab(t.id)}
						>
							<Icon />
							<span>{t.label}</span>
						</button>
					);
				})}
			</nav>

			<main className="ebq-hq-main">
				{trackToast ? <div className={`ebq-hq-toast ebq-hq-toast--${trackToast.tone}`} role="status" style={{ marginBottom: 12 }}>{trackToast.msg}</div> : null}
				<Active />
			</main>

			<AddKeywordModal
				open={trackSeed !== null}
				onClose={() => setTrackSeed(null)}
				onCreated={() => {
					setTrackToast({ msg: __('Now tracking — first SERP check queued.', 'ebq-seo'), tone: 'good' });
					setTimeout(() => setTrackToast(null), 3500);
				}}
				defaultDomain={HQ_CONFIG.workspaceDomain}
				seedKeyword={trackSeed || ''}
			/>
		</div>
	);
}

function NotConnected() {
	return (
		<div className="ebq-hq">
			<header className="ebq-hq-topbar">
				<div className="ebq-hq-topbar__brand">
					<span className="ebq-hq-topbar__mark" aria-hidden>E</span>
					<div>
						<h1 className="ebq-hq-topbar__title">EBQ Head Quarter</h1>
						<p className="ebq-hq-topbar__sub">{__('Connect this site to start seeing live analytics.', 'ebq-seo')}</p>
					</div>
				</div>
			</header>
			<ConnectionGuide reason="not_connected" />
		</div>
	);
}

/* ─── Inline icons ───────────────────────────────────────── */

function iconProps() { return { width: 16, height: 16, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round', strokeLinejoin: 'round', 'aria-hidden': true }; }
function HomeIcon() { return <svg {...iconProps()}><path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/></svg>; }
function ChartIcon() { return <svg {...iconProps()}><path d="M3 3v18h18"/><path d="M7 14l3-3 4 4 5-7"/></svg>; }
function SearchIcon() { return <svg {...iconProps()}><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>; }
function PageIcon() { return <svg {...iconProps()}><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6"/></svg>; }
function ShieldIcon() { return <svg {...iconProps()}><path d="M12 2 4 5v7c0 5 3.5 9 8 10 4.5-1 8-5 8-10V5z"/></svg>; }
function SparkIcon() { return <svg {...iconProps()}><path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l3 3M16 16l3 3M19 5l-3 3M8 16l-3 3"/></svg>; }
function TargetIcon() { return <svg {...iconProps()}><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/></svg>; }
