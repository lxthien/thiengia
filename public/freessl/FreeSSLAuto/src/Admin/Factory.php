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
 * @since      Class available since Release 1.0.0
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

namespace FreeSslDotTech\FreeSSLAuto\Admin;

use InvalidArgumentException;

class Factory
{
    public function __construct()
    {
    }

    /**
     * Sanitize string.
     *
     * @param string $data
     *
     * @return mixed
     */
    public function sanitize_string($data)
    {
        //remove space before and after
        $data = trim($data);

        //remove slashes
        $data = stripslashes($data);

        return filter_var($data, FILTER_SANITIZE_STRING);
    }

    /**
     * redirect to another page.
     *
     * @param string $url
     */
    public function redirect($url)
    {
        if (!headers_sent()) {
            header('Location: '.$url);
            exit;
        }
        echo '<script type="text/javascript">';
        echo 'window.location.href="'.$url.'";';
        echo '</script>';
        echo '<noscript>';
        echo '<meta http-equiv="refresh" content="0;url='.$url.'" />';
        echo '</noscript>';
        exit;
    }

    /**
     * Random encryption token generator: 64 characters.
     */
    public function encryptionTokenGenerator()
    {
        $chars_lower_case = 'abcdefghijklmnopqrstuvwxyz';
        $chars_upper_case = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $chars_numbers = '0123456789';
        $chars_special = '!@#$%^&*(){}[]_-=+;:,?';

        $password = substr(str_shuffle($chars_lower_case), 0, 25);
        $password .= substr(str_shuffle($chars_upper_case), 0, 14);
        $password .= substr(str_shuffle($chars_special), 0, 15);
        $password .= substr(str_shuffle($chars_numbers), 0, 10);

        return str_shuffle($password);
    }

    /**
     * Random Password RESET TOKEN Generator: 32 characters.
     */
    public function passwordResetTokenGenerator()
    {
        $chars_lower_case = 'abcdefghijklmnopqrstuvwxyz';
        $chars_numbers = '0123456789';

        $password = substr(str_shuffle($chars_lower_case), 0, 24);
        $password .= substr(str_shuffle($chars_numbers), 0, 8);

        return str_shuffle($password);
    }

    /**
     * CSRF token generator: 64 bits.
     *
     * @param string $form_name
     * @param bool   $unset_previous
     *
     * @return string
     */
    public function getCsrfToken($form_name, $unset_previous = false)
    {
        //start session
        if (!session_id()) {
            session_start();
        }

        if ($unset_previous) {
            //This option will enable us to generate new token for every load (GET only) of a form
            unset($_SESSION['token'], $_SESSION['token_timestamp']);
        }

        if (!isset($_SESSION['token']) || empty($_SESSION['token'])) {
            if (\function_exists('random_bytes')) {
                $token = bin2hex(random_bytes(32));
            } elseif (\function_exists('mcrypt_create_iv')) {
                $token = bin2hex(mcrypt_create_iv(32, MCRYPT_DEV_URANDOM));
            } else {
                $token = bin2hex(openssl_random_pseudo_bytes(32));
            }

            $_SESSION['token'] = $token;

            $_SESSION['token_timestamp'] = time();
        }

        return hash_hmac('sha256', $form_name, $_SESSION['token']);
    }

    /**
     * Verify CSRF token: 64 bits.
     *
     * @param string $form_name
     * @param string $token_returned
     *
     * @return bool
     */
    public function verifyCsrfToken($form_name, $token_returned)
    {
        $generated_token = $this->getCsrfToken($form_name);

        //verify against time
        $currentTime = time();

        if ($currentTime - $_SESSION['token_timestamp'] > 15 * 60) {
            //15 minutes exceeded, i.e., token expired
            
            unset($_SESSION['token'], $_SESSION['token_timestamp']);

            return false;
        }
        if (hash_equals($generated_token, $token_returned)) {
            // verified
            
            unset($_SESSION['token'], $_SESSION['token_timestamp']);

            return true;
        }
        
        return false;
        //Sorry! This form's security token expired. Please submit the form again.
    }

    /**
     * Encryption with open SSL.
     *
     * @param string $plaintext
     *
     * @return string
     */
    public function encryptText($plaintext)
    {
        //$key previously generated safely, ie: openssl_random_pseudo_bytes

        $ivlen = openssl_cipher_iv_length($cipher = 'AES-128-CBC');
        $iv = openssl_random_pseudo_bytes($ivlen);
        $ciphertext_raw = openssl_encrypt($plaintext, $cipher, KEY, $options = OPENSSL_RAW_DATA, $iv);
        $hmac = hash_hmac('sha256', $ciphertext_raw, KEY, $as_binary = true);

        return base64_encode($iv.$hmac.$ciphertext_raw);
    }

    /**
     * decrypt with open SSL.
     *
     * @param string $ciphertext
     *
     * @return bool|string
     */
    public function decryptText($ciphertext)
    {
        $c = base64_decode($ciphertext, true);
        $ivlen = openssl_cipher_iv_length($cipher = 'AES-128-CBC');
        $iv = substr($c, 0, $ivlen);
        $hmac = substr($c, $ivlen, $sha2len = 32);
        $ciphertext_raw = substr($c, $ivlen + $sha2len);
        $original_plaintext = openssl_decrypt($ciphertext_raw, $cipher, KEY, $options = OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertext_raw, KEY, $as_binary = true);

        if (hash_equals($hmac, $calcmac)) {//PHP 5.6+ timing attack safe comparison; now backported with hash_equals.php
            return $original_plaintext;
        }

        return false;
    }

    /**
     * get sub-directories in the given directory.
     *
     * @param string $dirPath
     *
     * @throws InvalidArgumentException
     *
     * @return array
     */
    public function getSubDirectories($dirPath)
    {
        if (!is_dir($dirPath)) {
            throw new InvalidArgumentException("${dirPath} must be a directory");
        }
        if ('/' !== substr($dirPath, \strlen($dirPath) - 1, 1)) {
            $dirPath .= '/';
        }

        $dirs = [];

        $files = glob($dirPath.'*', GLOB_MARK);

        foreach ($files as $file) {
            if (is_dir($file)) {
                $dirs[] = $file;
            }
        }

        return $dirs;
    }

    /**
     * get existing SSLs in the given directory.
     *
     * @param string $dirPath
     *
     * @return array
     */
    public function getExistingSslList($dirPath)
    {
        $dirs = $this->getSubDirectories($dirPath);

        $ssl_domains = [];

        foreach ($dirs as $dir) {
            $domain = basename($dir);

            if ('_account' !== $domain) {
                $ssl_domains[] = $domain;
            }
        }

        return $ssl_domains;
    }
}
