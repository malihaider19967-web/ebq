const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
	...defaultConfig,
	entry: {
		sidebar:               path.resolve(process.cwd(), 'src', 'sidebar', 'index.js'),
		'classic-editor':      path.resolve(process.cwd(), 'src', 'classic', 'classic-editor.js'),
		admin:                 path.resolve(process.cwd(), 'src', 'admin',   'admin.js'),
		'post-column-hydrate': path.resolve(process.cwd(), 'src', 'admin',   'post-column-hydrate.js'),
		'dashboard-hydrate':   path.resolve(process.cwd(), 'src', 'admin',   'dashboard-hydrate.js'),
		'meta-box-hydrate':    path.resolve(process.cwd(), 'src', 'admin',   'meta-box-hydrate.js'),
	},
};
