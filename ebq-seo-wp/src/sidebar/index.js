import { registerPlugin } from '@wordpress/plugins';
import { PluginSidebar, PluginSidebarMoreMenuItem } from '@wordpress/edit-post';
import { __ } from '@wordpress/i18n';
import Panel from './panel';

const icon = (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" strokeWidth="1.5">
        <path strokeLinecap="round" strokeLinejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
    </svg>
);

registerPlugin('ebq-seo-sidebar', {
    render: () => (
        <>
            <PluginSidebarMoreMenuItem target="ebq-seo-sidebar" icon={icon}>
                {__('EBQ SEO', 'ebq-seo')}
            </PluginSidebarMoreMenuItem>
            <PluginSidebar name="ebq-seo-sidebar" icon={icon} title={__('EBQ SEO', 'ebq-seo')}>
                <Panel />
            </PluginSidebar>
        </>
    ),
});
