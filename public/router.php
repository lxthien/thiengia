<?php

/*
 * Router cho PHP built-in server khi chạy dev cục bộ:
 *   php -S 127.0.0.1:8000 -t public public/router.php
 *
 * Vì sao cần file này: nếu chạy "php -S ... -t public/" KHÔNG có router,
 * PHP tự 404 thẳng (không hề gọi tới index.php/Symfony) cho bất kỳ URL nào
 * "trông giống file" (có phần mở rộng, vd .jpg) mà không tồn tại sẵn trên
 * đĩa — đây là hành vi mặc định của PHP built-in server, không phải lỗi
 * code. Ảnh hưởng trực tiếp tới LiipImagine: thumbnail (/media/cache/
 * resolve/{filter}/{path}) chỉ được TẠO ra khi request đó thực sự chạy tới
 * Symfony để gọi filterAction — nếu PHP tự 404 trước khi vào Symfony,
 * thumbnail không bao giờ được sinh ra, ảnh hiện trống trên toàn site.
 * File này bắt đúng path đã tồn tại thật trên đĩa thì trả thẳng (return
 * false để PHP tự phục vụ file tĩnh), còn lại luôn chuyển vào Symfony —
 * kể cả URL có đuôi file — để các route sinh tài nguyên động (ảnh
 * thumbnail, sitemap.xml, v.v.) luôn hoạt động đúng khi dev cục bộ.
 */

$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($path !== '/' && file_exists(__DIR__ . $path) && !is_dir(__DIR__ . $path)) {
    return false;
}

require __DIR__ . '/index.php';
