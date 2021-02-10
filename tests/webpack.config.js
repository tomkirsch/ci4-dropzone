const path = require('path');
const webpack = require('webpack');
module.exports = {
	entry: './src/index.js',
	plugins: [
		new webpack.ProvidePlugin({
			$: "jquery",
			jQuery: "jquery",
			"window.jQuery": "jquery'",
			"window.$": "jquery"
		})
	],
	module: {
		rules: [
			{
				test: /\.css$/i,
				use: ['style-loader', 'css-loader'],
			},
		],
	},
	output: {
		filename: 'main.js',
		path: path.resolve(__dirname, 'public/js'),
	},
};
