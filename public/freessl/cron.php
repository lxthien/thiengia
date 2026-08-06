<?php

/**
 *
 * @package FreeSSL.tech Auto
 * This PHP app issues and installs free SSL certificates in cPanel shared hosting with complete automation.
 * 
 * @author Anindya Sundar Mandal <anindya@SpeedUpWebsite.info>
 * @copyright  Copyright (C) 2018-2019, Anindya Sundar Mandal
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 * @link       https://SpeedUpWebsite.info
 * @since      Available since Release 1.0.0
 * 
 *
 *   This program is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program.  If not, see <https://www.gnu.org/licenses/>.
 * 
 */

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        die("Unfortunately, this app is not compatible with Windows. It works on Linux hosting.");
    }

    //Display all error
    error_reporting(E_ALL);

    //Set display_errors ON if it is OFF by default
    if (!ini_get('display_errors')) {
        ini_set('display_errors', '1');
    }

    if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50400) {
        die("You need at least PHP 5.4.0\n");
    }

    if (!extension_loaded('openssl')) {
        die("You need OpenSSL extension enabled with PHP\n");
    }

    if (!extension_loaded('curl')) {
        die("You need Curl extension enabled with PHP\n");
    }

    if (!extension_loaded('mysqli')) {
        die("You need Mysqli extension enabled with PHP\n");
    }
    
    if (!ini_get('allow_url_fopen')) {
        die("You need to set PHP directive allow_url_fopen = On. Please contact your web hosting company for help.");
    }

    // Define Directory Separator to make the default DIRECTORY_SEPARATOR short
    if (!defined('DS')) {
        define('DS', DIRECTORY_SEPARATOR);
    }

    $config_file_path = __DIR__.DS.'config'.DS.'config.php';

    // Check if wp-config.php has been created
    if (!file_exists($config_file_path)) {
        header('location: install.php');
    }

    //Include config file
    require_once $config_file_path;

    // Composer autoloading
    include __DIR__.DS.'vendor'.DS.'autoload.php';

    require_once __DIR__.DS.'vendor'.DS.'usrflo'.DS.'registered-domain-libs'.DS.'PHP'.DS.'effectiveTLDs.inc.php';
    require_once __DIR__.DS.'vendor'.DS.'usrflo'.DS.'registered-domain-libs'.DS.'PHP'.DS.'regDomain.inc.php';

    //define ISSUE_SSL true
    define('ISSUE_SSL', true);
    //other constants should be false
    define('KEY_CHANGE', false);
    define('REVOKE_CERT', false);

    use FreeSslDotTech\FreeSSLAuto\FreeSSLAuto;

    //Retrieve settings
    $appConfig = json_decode(file_get_contents(APP_SETTINGS_PATH), true);

    $freeSsl = new FreeSSLAuto($appConfig);

    //Run the App
    $freeSsl->run();
