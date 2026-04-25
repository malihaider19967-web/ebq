import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button } from './primitives';
import { IconRefresh } from './icons';

/**
 * Catches React render errors anywhere in the editor surface and renders a
 * friendly, recoverable card instead of a blank metabox / sidebar.
 *
 * On reset the boundary remounts its child tree so transient errors (a stale
 * cached state, a one-shot null from a missing GSC field, etc.) clear up.
 */
export default class ErrorBoundary extends Component {
	constructor(props) {
		super(props);
		this.state = { error: null, attempt: 0 };
	}

	static getDerivedStateFromError(error) {
		return { error };
	}

	componentDidCatch(error, info) {
		// eslint-disable-next-line no-console
		console.error('[EBQ SEO] Caught render error:', error, info?.componentStack);
	}

	reset = () => {
		this.setState((s) => ({ error: null, attempt: s.attempt + 1 }));
	};

	render() {
		if (this.state.error) {
			const message = (this.state.error && (this.state.error.message || String(this.state.error))) || '';
			return (
				<div className="ebq-root ebq-sidebar-frame">
					<div className="ebq-body">
						<div className="ebq-section">
							<div className="ebq-section__head">
								<h3 className="ebq-section__title">{__('Something went wrong', 'ebq-seo')}</h3>
							</div>
							<div className="ebq-section__body">
								<p className="ebq-help" style={{ margin: 0 }}>
									{__('The EBQ SEO panel hit an unexpected error and stopped rendering. Your post is unaffected.', 'ebq-seo')}
								</p>
								{message ? (
									<details>
										<summary className="ebq-text-xs ebq-text-soft" style={{ cursor: 'pointer' }}>
											{__('Technical details', 'ebq-seo')}
										</summary>
										<pre style={{
											marginTop: 6, padding: 10,
											background: 'var(--ebq-bg-emboss)',
											borderRadius: 6, fontSize: 11,
											whiteSpace: 'pre-wrap', wordBreak: 'break-word',
											fontFamily: 'var(--ebq-font-mono)',
										}}>{message}</pre>
									</details>
								) : null}
								<Button variant="primary" size="sm" onClick={this.reset}>
									<IconRefresh /> {__('Try again', 'ebq-seo')}
								</Button>
							</div>
						</div>
					</div>
				</div>
			);
		}

		// `key` forces a fresh mount on reset so any stuck state inside child
		// trees clears alongside the boundary.
		return <div key={this.state.attempt}>{this.props.children}</div>;
	}
}
