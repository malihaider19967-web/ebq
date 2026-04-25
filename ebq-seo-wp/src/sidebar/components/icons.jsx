/** Tiny SVG icons. 16×16 viewBox. Inherits currentColor. */

export function IconCheck() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" d="M3.5 8.5l3 3 6-6.5" />
		</svg>
	);
}
export function IconCross() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" d="M4 4l8 8M12 4l-8 8" />
		</svg>
	);
}
export function IconWarn() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="currentColor" d="M8 2.6l6.4 11H1.6L8 2.6zM7 7v3.5h2V7H7zm0 4.5v1.6h2v-1.6H7z" />
		</svg>
	);
}
export function IconDot() {
	return <svg viewBox="0 0 16 16" aria-hidden focusable="false"><circle fill="currentColor" cx="8" cy="8" r="3" /></svg>;
}
export function IconSearch() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor" strokeWidth="1.6" />
			<path stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" d="M10.5 10.5L14 14" />
		</svg>
	);
}
export function IconShare() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="currentColor" d="M11.5 1.5a2 2 0 100 4 2 2 0 000-4zM4 6a2 2 0 100 4 2 2 0 000-4zm7.5 4.5a2 2 0 100 4 2 2 0 000-4z" />
			<path stroke="currentColor" strokeWidth="1.4" d="M5.6 7.2l4.4-2.4M5.6 8.8l4.4 2.4" />
		</svg>
	);
}
export function IconChart() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" d="M2 13h12M3.5 11V8M6.5 11V5M9.5 11V7M12.5 11V3" />
		</svg>
	);
}
export function IconBook() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round" d="M2.5 3.5C4 2.5 6.5 2.5 8 4c1.5-1.5 4-1.5 5.5-.5v9C12 11.5 9.5 11.5 8 13c-1.5-1.5-4-1.5-5.5-.5v-9zM8 4v9" />
		</svg>
	);
}
export function IconSliders() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" d="M2 5h6m4 0h2M2 11h2m4 0h6" />
			<circle cx="10" cy="5" r="1.6" fill="currentColor" />
			<circle cx="6" cy="11" r="1.6" fill="currentColor" />
		</svg>
	);
}
export function IconSparkle() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="currentColor" d="M8 1l1.6 4.4L14 7l-4.4 1.6L8 13l-1.6-4.4L2 7l4.4-1.6L8 1z" />
		</svg>
	);
}
export function IconExternal() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" d="M9 3h4v4M13 3l-6 6M11 9.5V13H3V5h3.5" />
		</svg>
	);
}
export function IconArrowDown() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" d="M8 3v9M4 9l4 4 4-4" />
		</svg>
	);
}
export function IconArrowUp() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" d="M8 13V4M4 7l4-4 4 4" />
		</svg>
	);
}
export function IconRefresh() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" d="M2.6 8a5.4 5.4 0 019.4-3.6L13.4 3M13.4 3v3h-3M13.4 8a5.4 5.4 0 01-9.4 3.6L2.6 13M2.6 13v-3h3" />
		</svg>
	);
}

/* Tab-row distinct icons. Each tab needs a recognisable shape — duplicates
   make scanning harder. Below are dedicated symbols for Links, Insights,
   Schema, and Advanced, distinct from IconChart / IconSliders. */
export function IconLink() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" d="M9.5 6.5a2.5 2.5 0 003.5 0l1.5-1.5a2.5 2.5 0 00-3.5-3.5L10 2.5M6.5 9.5a2.5 2.5 0 00-3.5 0l-1.5 1.5a2.5 2.5 0 003.5 3.5L6 13.5M5.5 10.5l5-5" />
		</svg>
	);
}
export function IconRadar() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<circle cx="8" cy="8" r="6" fill="none" stroke="currentColor" strokeWidth="1.4" />
			<path fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" d="M8 8L13 4.5" />
			<circle cx="8" cy="8" r="1.4" fill="currentColor" />
		</svg>
	);
}
export function IconCode() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" d="M5 4L1.5 8 5 12M11 4l3.5 4-3.5 4M9.5 3.5l-3 9" />
		</svg>
	);
}
export function IconGear() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<circle cx="8" cy="8" r="2" fill="none" stroke="currentColor" strokeWidth="1.4" />
			<path fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" d="M8 1.6V3M8 13v1.4M14.4 8H13M3 8H1.6M12.5 3.5l-1 1M4.5 11.5l-1 1M12.5 12.5l-1-1M4.5 4.5l-1-1" />
		</svg>
	);
}
export function IconShield() {
	return (
		<svg viewBox="0 0 16 16" aria-hidden focusable="false">
			<path fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinejoin="round" d="M8 1.5L2.5 3.5v4c0 3.5 2.4 6.4 5.5 7 3.1-.6 5.5-3.5 5.5-7v-4L8 1.5z" />
		</svg>
	);
}
