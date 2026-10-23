<?php
/*
 * CKFinder
 * ========
 * http://cksource.com/ckfinder
 * Copyright (c) 2007-2017, CKSource - Frederico Knabben. All rights reserved.
 *
 * The software, this file and its contents are subject to the CKFinder
 * License. Please read the license.txt file before using, installing, copying,
 * modifying or distribute this file or part of its contents. The contents of
 * this file is part of the Source Code of CKFinder.
 */

// Thư viện kèm CKFinder viết cho PHP 5–7 phát hàng loạt deprecation ngay lúc nạp
// (PHP 8.4+: implicit nullable, E_STRICT…). Phải tắt TRƯỚC autoload — config.php
// chạy quá muộn — nếu không, khi server bật display_errors chúng in ra trước JSON
// và chặn session_start() (headers already sent) nên CKFinder báo "disabled".
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');

require_once __DIR__ . '/vendor/autoload.php';

use CKSource\CKFinder\CKFinder;

$ckfinder = new CKFinder(__DIR__ . '/../../../config.php');

$ckfinder->run();
