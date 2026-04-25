import { useEffect, useRef } from '@wordpress/element';
import { createPortal } from 'react-dom';
import { __ } from '@wordpress/i18n';
import { IconCross } from './icons';

/**
 * Lightweight modal — portaled to <body> so it sits above the editor's
 * own panels, traps focus, closes on ESC + backdrop click. No external
 * dep, ~80 lines, fits the rest of the sidebar tone.
 */
export default function Modal({ open, onClose, title, children, footer, size = 'md' }) {
	const dialogRef = useRef(null);
	const lastFocusedRef = useRef(null);

	useEffect(() => {
		if (!open) return;
		// Remember whatever had focus so we can restore it on close.
		lastFocusedRef.current = document.activeElement;

		const onKey = (e) => {
			if (e.key === 'Escape') {
				e.stopPropagation();
				onClose?.();
			}
			if (e.key === 'Tab' && dialogRef.current) {
				const focusables = dialogRef.current.querySelectorAll(
					'button:not([disabled]), [href], input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])'
				);
				if (!focusables.length) return;
				const first = focusables[0];
				const last = focusables[focusables.length - 1];
				if (e.shiftKey && document.activeElement === first) {
					last.focus();
					e.preventDefault();
				} else if (!e.shiftKey && document.activeElement === last) {
					first.focus();
					e.preventDefault();
				}
			}
		};
		document.addEventListener('keydown', onKey, true);

		// Lock body scroll while the modal is open.
		const prevOverflow = document.body.style.overflow;
		document.body.style.overflow = 'hidden';

		// Initial focus on the dialog so screen readers announce the title.
		const t = setTimeout(() => dialogRef.current?.focus(), 0);

		return () => {
			document.removeEventListener('keydown', onKey, true);
			document.body.style.overflow = prevOverflow;
			clearTimeout(t);
			if (lastFocusedRef.current && typeof lastFocusedRef.current.focus === 'function') {
				try { lastFocusedRef.current.focus(); } catch { /* ignore */ }
			}
		};
	}, [open, onClose]);

	if (!open) return null;

	// IMPORTANT: portal renders into <body>, which sits OUTSIDE the
	// `.ebq-root` token scope. The CSS custom-properties (--ebq-bg, etc.)
	// would otherwise resolve to empty and the panel renders transparent.
	// Wrapping the portal contents in `.ebq-root` republishes the tokens.
	const node = (
		<div className="ebq-root">
			<div className="ebq-modal" role="presentation" onMouseDown={(e) => {
				if (e.target === e.currentTarget) {
					onClose?.();
				}
			}}>
				<div
					ref={dialogRef}
					className={`ebq-modal__panel ebq-modal__panel--${size}`}
					role="dialog"
					aria-modal="true"
					aria-label={title || __('Dialog', 'ebq-seo')}
					tabIndex={-1}
				>
					<header className="ebq-modal__head">
						<h3 className="ebq-modal__title">{title}</h3>
						<button
							type="button"
							className="ebq-modal__close"
							onClick={onClose}
							aria-label={__('Close', 'ebq-seo')}
						>
							<IconCross />
						</button>
					</header>
					<div className="ebq-modal__body">{children}</div>
					{footer ? <footer className="ebq-modal__foot">{footer}</footer> : null}
				</div>
			</div>
		</div>
	);

	return createPortal(node, document.body);
}
