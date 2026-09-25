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
    .addEntry('js/page-index', './public/assets/js/admin/page-index.js')
    .addStyleEntry('css/page-index', './public/assets/scss/admin/page-index.scss')
    .addStyleEntry('css/page-editor', './public/assets/scss/admin/page-editor.scss')
    .addEntry('js/newscategory-index', './public/assets/js/admin/newscategory-index.js')
    .addStyleEntry('css/newscategory-index', './public/assets/scss/admin/newscategory-index.scss')
    .addEntry('js/news-index', './public/assets/js/admin/news-index.js')
    .addStyleEntry('css/news-index', './public/assets/scss/admin/news-index.scss')
    .addEntry('js/news-editor', './public/assets/js/admin/news-editor.js')
    .addEntry('js/activity-log', './public/assets/js/admin/activity-log.js')
    .addStyleEntry('css/activity-log', './public/assets/scss/admin/activity-log.scss')
    .addEntry('js/user-manager', './public/assets/js/admin/user-manager.js')
    .addStyleEntry('css/user-manager', './public/assets/scss/admin/user-manager.scss')
    .addEntry('js/redirect-manager', './public/assets/js/admin/redirect-manager.js')
    .addStyleEntry('css/redirect-manager', './public/assets/scss/admin/redirect-manager.scss')
    .addEntry('js/settings-global', './public/assets/js/admin/settings-global.js')
    .addStyleEntry('css/settings-global', './public/assets/scss/admin/settings-global.scss')
    .addEntry('js/newsletter-index', './public/assets/js/admin/newsletter-index.js')
    .addStyleEntry('css/newsletter-index', './public/assets/scss/admin/newsletter-index.scss')
    .addEntry('js/contact-index', './public/assets/js/admin/contact-index.js')
    .addStyleEntry('css/contact-index', './public/assets/scss/admin/contact-index.scss')
    .addEntry('js/comment-index', './public/assets/js/admin/comment-index.js')
    .addStyleEntry('css/comment-index', './public/assets/scss/admin/comment-index.scss')
    .addEntry('js/search', './public/assets/js/admin/search.js')
    .addEntry('js/login', './public/assets/js/admin/login.js')
    .addEntry('js/menu-editor', './public/assets/js/admin/menu-editor.js')
    .addEntry('js/banner-index', './public/assets/js/admin/banner-index.js')
    .addStyleEntry('css/banner-index', ['./public/assets/scss/admin/banner-index.scss'])
    .addEntry('js/testimonial-index', './public/assets/js/admin/testimonial-index.js')
    .addEntry('js/media-index', './public/assets/js/admin/media-index.js')
    .addStyleEntry('css/media-index', ['./public/assets/scss/admin/media-index.scss'])
    .addStyleEntry('css/health-index', ['./public/assets/scss/admin/health-index.scss'])
    .addStyleEntry('css/dashboard-index', ['./public/assets/scss/admin/dashboard-index.scss'])
    .addStyleEntry('css/content-decay', ['./public/assets/scss/admin/content-decay.scss'])
    .addStyleEntry('css/testimonial-index', ['./public/assets/scss/admin/testimonial-index.scss'])
    .addEntry('js/homepage-section-edit', './public/assets/js/admin/homepage-section-edit.js')
    .addStyleEntry('css/homepage-section-edit', ['./public/assets/scss/admin/homepage-section-edit.scss'])
    .addStyleEntry('css/catalog-index', ['./public/assets/scss/admin/catalog-index.scss'])
    .addEntry('js/reorder-manager', './public/assets/js/admin/reorder-manager.js')
    .addStyleEntry('css/homepage-section-index', ['./public/assets/scss/admin/homepage-section-index.scss'])
    .addEntry('js/gallery-album-index', './public/assets/js/admin/gallery-album-index.js')
    .addStyleEntry('css/gallery-album-index', ['./public/assets/scss/admin/gallery-album-index.scss'])
    .addStyleEntry('css/gallery-album-editor', ['./public/assets/scss/admin/gallery-album-editor.scss'])
    .addEntry('js/gallery-album-editor', './public/assets/js/admin/gallery-album-editor.js')
    .addStyleEntry('css/admin', ['./public/assets/scss/admin/admin.scss'])
    .addStyleEntry('css/ckeditor-content', ['./public/assets/scss/admin/ckeditor-content.scss'])
    // CKEditor 5 (đang migrate dần từ CKEditor 4/ivoryckeditor — xem admin/ckeditor5.js)
    .addStyleEntry('css/ckeditor5', ['./node_modules/ckeditor5/dist/ckeditor5.css'])
    // Chỉ giao diện editor (không có style nội dung mặc định) — cho form có ckeditor-content.css giống website
    .addStyleEntry('css/ckeditor5-editor', ['./node_modules/ckeditor5/dist/ckeditor5-editor.css'])

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
    .addStyleEntry('css/v3-loban', ['./public/assets/scss/v3/lo-ban.scss'])
    .addEntry('js/v3-density', './public/assets/js/v3/building-density.js')
    .addStyleEntry('css/v3-density', ['./public/assets/scss/v3/building-density.scss'])
    .addEntry('js/v3-age', './public/assets/js/v3/building-age.js')
    .addEntry('js/v3-direction', './public/assets/js/v3/house-direction.js')
    .addStyleEntry('css/v3-direction', ['./public/assets/scss/v3/house-direction.scss'])
    .addStyleEntry('css/v3-age', ['./public/assets/scss/v3/building-age.scss'])
;

module.exports = Encore.getWebpackConfig();
