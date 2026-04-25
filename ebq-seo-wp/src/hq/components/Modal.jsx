import { useEffect, useRef } from '@wordpress/element';
import { createPortal } from 'react-dom';
import { __ } from '@wordpress/i18n';

/**
 * Headquarter modal — portals to <body>, locks scroll, traps focus, closes
 * on ESC. Standalone from the sidebar Modal so the HQ design tokens (which
 * live in `.ebq-hq-wrap`) propagate via the wrapper class.
 */
export default function Modal({ open, onClose, title, children, footer, size = 'md' }) {
	const dialogRef = useRef(null);

	useEffect(() => {
		if (!open) return;
		const onKey = (e) => {
			if (e.key === 'Escape') {
				e.stopPropagation();
				onClose?.();
			}
		};
		document.addEventListener('keydown', onKey, true);
		const prev = document.body.style.overflow;
		document.body.style.overflow = 'hidden';
		const t = setTimeout(() => dialogRef.current?.focus(), 0);
		return () => {
			document.removeEventListener('keydown', onKey, true);
			document.body.style.overflow = prev;
			clearTimeout(t);
		};
	}, [open, onClose]);

	if (!open) return null;

	const node = (
		<div className="ebq-hq-wrap">
			<div className="ebq-hq-modal" role="presentation" onMouseDown={(e) => {
				if (e.target === e.currentTarget) onClose?.();
			}}>
				<div
					ref={dialogRef}
					className={`ebq-hq-modal__panel ebq-hq-modal__panel--${size}`}
					role="dialog"
					aria-modal="true"
					aria-label={title || __('Dialog', 'ebq-seo')}
					tabIndex={-1}
				>
					<header className="ebq-hq-modal__head">
						<h3 className="ebq-hq-modal__title">{title}</h3>
						<button type="button" className="ebq-hq-modal__close" onClick={onClose} aria-label={__('Close', 'ebq-seo')}>×</button>
					</header>
					<div className="ebq-hq-modal__body">{children}</div>
					{footer ? <footer className="ebq-hq-modal__foot">{footer}</footer> : null}
				</div>
			</div>
		</div>
	);

	return createPortal(node, document.body);
}
