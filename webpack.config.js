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
    .enablePostCssLoader()
    .configureCssLoader((options) => {
        // root-relative url() paths (e.g. /assets/images/...) are served
        // as static files, not part of the webpack build — leave them as-is
        options.url = (url) => !url.startsWith('/');
    })
    .enableVersioning(Encore.isProduction())
    .cleanupOutputBeforeBuild()
    .disableSingleRuntimeChunk()
    // ---------- Dùng chung + Admin (giữ nguyên) ----------
    .addEntry('manifest', './public/assets/js/manifest.js')
    .addEntry('js/common', './public/assets/js/common.js')
    .addEntry('js/admin', './public/assets/js/admin/admin.js')
    .addEntry('js/search', './public/assets/js/admin/search.js')
    .addEntry('js/login', './public/assets/js/admin/login.js')
    .addEntry('js/menu-editor', './public/assets/js/admin/menu-editor.js')
    .addStyleEntry('css/admin', ['./public/assets/scss/admin/admin.scss'])
    .addStyleEntry('css/ckeditor-content', ['./public/assets/scss/admin/ckeditor-content.scss'])

    // ---------- Front: theme v3 (Thiện Gia) ----------
    .addEntry('js/v3', './public/assets/js/v3/app.js')
    // Tailwind nạp TRƯỚC app.scss để SCSS component có thể ghi đè utility
    .addStyleEntry('css/v3-app', [
        './public/assets/css/v3/tailwind.css',
        './public/assets/scss/v3/app.scss',
    ])
    .addStyleEntry('css/v3-homepage', ['./public/assets/scss/v3/homepage.scss'])
    .addStyleEntry('css/v3-list', ['./public/assets/scss/v3/list.scss'])
    .addStyleEntry('css/v3-news', ['./public/assets/scss/v3/news.scss'])
    .addStyleEntry('css/v3-contact', ['./public/assets/scss/v3/contact.scss'])
;

module.exports = Encore.getWebpackConfig();
