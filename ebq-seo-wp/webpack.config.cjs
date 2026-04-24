const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
	...defaultConfig,
	entry: {
		sidebar: path.resolve(process.cwd(), 'src', 'sidebar', 'index.js'),
		'seo-panel': path.resolve(process.cwd(), 'src', 'seo-panel', 'index.js'),
	},
};
