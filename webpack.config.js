// webpack.config.js
const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .addEntry('app', './assets/main.js')
    .enablePostCssLoader()
    .autoProvidejQuery()   // very important
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())
    .copyFiles({
    from: './assets/image',
    to: 'images/[path][name].[ext]',
})
;

module.exports = Encore.getWebpackConfig();
