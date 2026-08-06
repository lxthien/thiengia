/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './templates/**/*.twig',
        './public/assets/js/**/*.js'
    ],

    // Preflight BẬT: admin có base riêng (admin/base.html.twig) và chỉ nạp
    // css/admin.css, không bao giờ nạp css/v3-app.css → không ảnh hưởng nhau.
    // Không tự viết reset trong SCSS: reset thủ công có specificity cao hơn
    // utility (vd `body.v3 p` > `.mt-6`) sẽ vô hiệu hoá toàn bộ margin utility.

    theme: {
        container: {
            center: true,
            padding: {
                DEFAULT: '1.25rem',
                lg: '2rem'
            }
        },
        extend: {
            colors: {
                // Bảng màu phong thủy — đồng bộ scss/v3/_tokens.scss
                primary: '#b5462e',
                accent: '#e07b39',
                sand: '#e8dcc8',
                base: '#faf7f2',
                ink: '#2b2622',
                gold: '#c9a227',
                brown: '#3b322a',
                dark: '#221c17'
            },
            fontFamily: {
                display: ['"Playfair Display"', 'Georgia', 'serif'],
                sans: ['"Be Vietnam Pro"', 'system-ui', 'sans-serif']
            }
        }
    },
    plugins: []
};
