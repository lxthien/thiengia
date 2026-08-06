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

namespace FreeSslDotTech\FreeSSLAuto\Acme;

use InvalidArgumentException;

class Factory
{
    public function __construct($certificatesBase, $acme_version, $is_staging)
    {
        $this->certificatesBase = $certificatesBase;
        $this->acme_version = $acme_version;
        $this->is_staging = $is_staging;
    }

    /**
     * Returns the private key.
     *
     * @param string $path
     *
     * @since 1.0.0
     *
     * @return string the certificate begin flag
     */
    public function readPrivateKey($path)
    {
        if (false === ($key = openssl_pkey_get_private('file://'.$path))) {
            throw new \RuntimeException(openssl_error_string());
        }

        return $key;
    }

    /**
     * Returns the certificate begin flag.
     *
     * @since 1.0.0
     *
     * @return string the certificate begin flag
     */
    public function get_cert_begin()
    {
        return '-----BEGIN CERTIFICATE-----';
    }

    /**
     * Returns the certificate end flag.
     *
     * @since 1.0.0
     *
     * @return string the certificate end flag
     */
    public function get_cert_end()
    {
        return '-----END CERTIFICATE-----';
    }

    /**
     *  Perse PEM from response body.
     *
     * @param string $body
     *
     * @return string
     */
    public function parsePemFromBody($body)
    {
        $pem = chunk_split(base64_encode($body), 64, "\n");

        return "-----BEGIN CERTIFICATE-----\n".$pem."-----END CERTIFICATE-----\n";
    }

    /**
     * Returns the certificate directory.
     *
     * @return string
     */
    public function getCertificatesDir()
    {
        $acme_v = 'acme_v'.$this->acme_version;

        return $this->is_staging ? $this->certificatesBase.DS.$acme_v.DS.'staging' : $this->certificatesBase.DS.$acme_v.DS.'live';
    }

    /**
     * Get the domain path.
     *
     * @param string $domain
     *
     * @return string
     */
    public function getDomainPath($domain)
    {
        return $this->getCertificatesDir().DS.$domain.DS;
    }

    /**
     * Get the CSR content from path.
     *
     * @param string $csrPath
     *
     * @return string
     */
    public function getCsrContent($csrPath)
    {
        $csr = file_get_contents($csrPath);

        preg_match('~REQUEST-----(.*)-----END~s', $csr, $matches);

        return trim(Base64UrlSafeEncoder::encode(base64_decode($matches[1], true)));
    }

    /**
     * Generate key pair.
     *
     * @param string $outputDirectory
     * @param int    $key_size
     *
     * @throws \RuntimeException
     */
    public function generateKey($outputDirectory, $key_size)
    {
        $res = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => $key_size,
        ]);

        if (!openssl_pkey_export($res, $privateKey)) {
            throw new \RuntimeException('Key export failed!');
        }

        $details = openssl_pkey_get_details($res);

        if (!is_dir($outputDirectory)) {
            @mkdir($outputDirectory, 0700, true);
        }

        if (!is_dir($outputDirectory)) {
            throw new \RuntimeException("Can't create directory ${outputDirectory}. Please manually create a directory, that you specified in config.php, in your home directory and grant permission 0700 and try again.");
        }

        file_put_contents($outputDirectory.DS.'private.pem', $privateKey);
        file_put_contents($outputDirectory.DS.'public.pem', $details['key']);
    }

    /**
     * Generate SSL Certificate Signing Request (CSR).
     *
     * @param string $privateKey
     * @param array  $domains
     * @param int    $key_size
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    public function generateCSR($privateKey, array $domains, $key_size)
    {
        $domain = reset($domains);
        $san = implode(',', array_map(function ($dns) {
            return 'DNS:'.$dns;
        }, $domains));
        $tmpConf = tmpfile();
        $tmpConfMeta = stream_get_meta_data($tmpConf);
        $tmpConfPath = $tmpConfMeta['uri'];

        // workaround to get SAN working
        fwrite(
                $tmpConf,
                'HOME = .
RANDFILE = $ENV::HOME/.rnd
[ req ]
default_bits = '.$key_size.'
default_keyfile = privkey.pem
distinguished_name = req_distinguished_name
req_extensions = v3_req
[ req_distinguished_name ]
countryName = Country Name (2 letter code)
[ v3_req ]
basicConstraints = CA:FALSE
subjectAltName = '.$san.'
keyUsage = nonRepudiation, digitalSignature, keyEncipherment'
            );

        /**
         * @var Ambiguous
         */

        //The Distinguished Name or subject fields to be used in the certificate.
        $dn = [
            'CN' => $domain,
        ];

        if (\strlen($this->countryCode) > 0) {
            $dn['C'] = $this->countryCode;
        }

        if (\strlen($this->state) > 0) {
            $dn['ST'] = $this->state;
        }

        if (\strlen($this->organization) > 0) {
            $dn['O'] = $this->organization;
        }

        $csr = openssl_csr_new(
                $dn,
                $privateKey,
                [
                    'config' => $tmpConfPath,
                    'digest_alg' => 'sha256',
                ]
                );

        if (!$csr) {
            throw new \RuntimeException("CSR couldn't be generated! ".openssl_error_string());
        }

        openssl_csr_export($csr, $csr);
        fclose($tmpConf);

        $csrPath = $this->getDomainPath($domain).'csr_last.csr';
        file_put_contents($csrPath, $csr);

        return $this->getCsrContent($csrPath);
    }

    /**
     * Convert PEM to DER.
     *
     * @param string $pem
     *
     * @return string
     */
    public function convertPemToDer($pem)
    {
        $matches = [];
        $derData = base64_decode(str_replace(["\r", "\n"], ['', ''], $matches[2]), true);
        $derData = pack('H*', '020100300d06092a864886f70d010101050004'.$this->derLength(\strlen($derData))).$derData;

        return pack('H*', '30'.$this->derLength(\strlen($derData))).$derData;
    }

    /**
     * @param int $length
     *
     * @return int
     */
    public function derLength($length)
    {
        if ($length < 128) {
            return str_pad(dechex($length), 2, '0', STR_PAD_LEFT);
        }
        $output = dechex($length);
        if (0 !== \strlen($output) % 2) {
            $output = '0'.$output;
        }

        return dechex(128 + \strlen($output) / 2).$output;
    }

    /**
     * Delete a directory with all sub-directories and files.
     *
     * @param string $dirPath
     *
     * @throws InvalidArgumentException
     */
    public static function deleteDir($dirPath)
    {
        if (!is_dir($dirPath)) {
            throw new InvalidArgumentException("${dirPath} must be a directory");
        }
        if (DS !== substr($dirPath, \strlen($dirPath) - 1, 1)) {
            $dirPath .= DS;
        }
        $files = glob($dirPath.'*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                self::deleteDir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);
    }
}
