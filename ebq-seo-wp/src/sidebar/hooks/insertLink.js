/**
 * Insert a hyperlink at the user's cursor in the active editor.
 *
 * - Block editor: inserts a paragraph block containing <a href> at the
 *   currently selected block (or appends one).
 * - Classic editor (visual TinyMCE): wraps current selection or inserts <a>.
 * - Classic editor (text/HTML mode): writes to #content textarea at cursor.
 *
 * Returns true if anything was inserted, false otherwise (e.g. unsafe URL,
 * no editor surface available).
 */
import { isClassicMode } from './useEditorContext';
import { safeUrl } from '../utils/sanitizeUrl';

function escapeHtml(s) {
	return String(s).replace(/[&<>"']/g, (c) => ({
		'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
	}[c]));
}

function insertInTextarea(textarea, html) {
	const start = textarea.selectionStart ?? textarea.value.length;
	const end = textarea.selectionEnd ?? textarea.value.length;
	textarea.value = textarea.value.slice(0, start) + html + textarea.value.slice(end);
	textarea.dispatchEvent(new Event('input', { bubbles: true }));
	textarea.focus();
	const cursor = start + html.length;
	textarea.setSelectionRange(cursor, cursor);
}

export async function insertLink({ url, anchor }) {
	const cleanedUrl = safeUrl(url);
	const safeAnchor = String(anchor || url || '').trim();
	if (!cleanedUrl) return false;
	const html = `<a href="${escapeHtml(cleanedUrl)}">${escapeHtml(safeAnchor)}</a>`;

	if (isClassicMode) {
		// Visual TinyMCE.
		const tm = window.tinymce;
		if (tm && tm.activeEditor && !tm.activeEditor.isHidden()) {
			try {
				const ed = tm.activeEditor;
				const sel = ed.selection.getContent({ format: 'text' });
				if (sel) {
					ed.selection.setContent(`<a href="${escapeHtml(cleanedUrl)}">${escapeHtml(sel)}</a>`);
				} else {
					ed.insertContent(html + ' ');
				}
				ed.focus();
				return true;
			} catch { /* fallthrough */ }
		}
		// HTML / text mode.
		const textarea = document.getElementById('content');
		if (textarea) {
			insertInTextarea(textarea, html);
			return true;
		}
		return false;
	}

	// Block editor — use the data store.
	try {
		const { dispatch } = window.wp.data;
		const { createBlock } = window.wp.blocks;
		const block = createBlock('core/paragraph', { content: html });
		dispatch('core/block-editor').insertBlocks([block]);
		return true;
	} catch {
		return false;
	}
}
