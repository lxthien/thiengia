const crypto = require('crypto');
const crypto_orig_createHash = crypto.createHash;
crypto.createHash = algorithm => crypto_orig_createHash(algorithm === 'md4' ? 'sha256' : algorithm);

var Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')
    .autoProvidejQuery()
    .autoProvideVariables({
        "window.Bloodhound": require.resolve('bloodhound-js'),
        "jQuery.tagsinput": "bootstrap-tagsinput"
    })
    .enableSassLoader(function(options) {
        options.sassOptions = Object.assign({}, options.sassOptions, {
            quietDeps: true,
            silenceDeprecations: [
                'legacy-js-api',
                'import',
                'global-builtin',
                'color-functions',
                'slash-div',
                'if-function'
            ]
        });
    })
    .enableVersioning(Encore.isProduction())
    .cleanupOutputBeforeBuild()
    .disableSingleRuntimeChunk()
    .addEntry('manifest', './public/assets/js/manifest.js')
    .addEntry('js/common', './public/assets/js/common.js')
    .addEntry('js/app', './public/assets/js/front/app.js')
    .addEntry('js/admin', './public/assets/js/admin/admin.js')
    .addEntry('js/search', './public/assets/js/admin/search.js')
    .addEntry('js/login', './public/assets/js/admin/login.js')
    .addEntry('js/menu-editor', './public/assets/js/admin/menu-editor.js')
    .addStyleEntry('css/app', ['./public/assets/scss/front/app.scss'])
    .addStyleEntry('css/first', ['./public/assets/scss/front/first.scss'])
    .addStyleEntry('css/homepage', ['./public/assets/scss/front/homepage.scss'])
    .addStyleEntry('css/news', ['./public/assets/scss/front/news.scss'])
    .addStyleEntry('css/list', ['./public/assets/scss/front/list.scss'])
    .addStyleEntry('css/contact', ['./public/assets/scss/front/contact.scss'])
    .addStyleEntry('css/admin', ['./public/assets/scss/admin/admin.scss'])
    .addStyleEntry('css/ckeditor-content', ['./public/assets/scss/admin/ckeditor-content.scss'])
;

module.exports = Encore.getWebpackConfig();
