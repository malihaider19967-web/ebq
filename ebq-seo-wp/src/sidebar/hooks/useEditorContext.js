/**
 * Environment-aware re-export of the editor context.
 *
 * Block editor → core/editor data store (gutenberg-context)
 * Classic editor → DOM-based store (classic-context)
 *
 * The choice is fixed at module-load time by the global flag set by the
 * classic-editor entry, so React hook ordering is stable across renders.
 */
import * as gutenberg from './gutenberg-context';
import * as classic from './classic-context';

const IS_CLASSIC = typeof window !== 'undefined' && window.__EBQ_CLASSIC__ === true;

const impl = IS_CLASSIC ? classic : gutenberg;

export const useEditorContext = impl.useEditorContext;
export const usePostMeta = impl.usePostMeta;
export const publicConfig = impl.publicConfig;
export const resolveTitleTemplate = impl.resolveTitleTemplate;

export const isClassicMode = IS_CLASSIC;
