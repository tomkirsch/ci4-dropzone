const path = require('path');
const webpack = require('webpack');
const CopyPlugin = require("copy-webpack-plugin");
const {
	CleanWebpackPlugin
} = require('clean-webpack-plugin');
module.exports = {
	entry: './src/index.js',
	plugins: [
		new CleanWebpackPlugin(),
		new webpack.ProvidePlugin({
			$: "jquery",
			jQuery: "jquery",
			"window.jQuery": "jquery'",
			"window.$": "jquery"
		}),
		new CopyPlugin({
			patterns: [{
					from: "**/*.css",
					context: "node_modules/dropzone/dist/min/",
					toType: "file",
					to({
						context,
						absoluteFilename
					}) {
						return `dropzone/${path.relative(context, absoluteFilename)}`;
					},
				},

			],
		}),
	],
	module: {
		rules: [{
			test: /\.css$/i,
			use: ['style-loader', 'css-loader'],
		}, ],
	},
	output: {
		filename: 'main.js',
		path: path.resolve(__dirname, 'public/js'),
	},
};
