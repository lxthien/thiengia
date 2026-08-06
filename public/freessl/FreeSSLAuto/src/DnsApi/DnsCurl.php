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

namespace FreeSslDotTech\FreeSSLAuto\DnsApi;

use FreeSslDotTech\FreeSSLAuto\Logger;

class DnsCurl
{
    private $provider_name;
    private $api_key;
    private $api_secret;
    private $logger;

    /**
     * Initiates the DnsCurl class.
     *
     * @param string $provider_name
     * @param string $api_identifier
     * @param string $api_credential
     */
    public function __construct($provider_name, $api_identifier, $api_credential)
    {
        $this->provider_name = $provider_name;
        $this->api_identifier = $api_identifier;
        $this->api_credential = $api_credential;
        $this->logger = new Logger();
    }

    /**
     * Connect to the DNS API with Curl.
     *
     * @param string     $method
     * @param string     $url
     * @param null|array $data
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public function connect($method, $url, $data = null)
    {
        $headers = null;

        switch ($this->provider_name) {
            case 'GoDaddy':
                $headers = ['Authorization: sso-key '.$this->api_identifier.':'.$this->api_credential, 'Accept: application/json', 'Content-Type: application/json'];

                break;
            case 'Cloudflare':
                $headers = ['X-Auth-Email: '.$this->api_identifier, 'X-Auth-Key: '.$this->api_credential, 'Accept: application/json', 'Content-Type: application/json'];

                break;
            case 'Namecheap':
                $headers = ['Accept: text/xml', 'Content-Type: text/xml'];

                break;
        }

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HEADER, true);

        switch ($method) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($handle, CURLOPT_POST, true);

                break;
            case 'PATCH':
                curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'PATCH');

                break;
            case 'PUT':
                curl_setopt($handle, CURLOPT_CUSTOMREQUEST, 'PUT');

                break;
        }

        if (null !== $data) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($handle);

        if (curl_errno($handle)) {
            throw new \RuntimeException('Curl: '.curl_error($handle));
        }

        $header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);

        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        $lastHeader = $header;
        $lastCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        $response_body = json_decode($body, true);

        $result = [];

        $result['header'] = $response;
        $result['http_code'] = $lastCode;
        $result['body'] = (null === $response_body) ? $body : $response_body;

        //Check status code
        if (200 !== $result['http_code']) {
            //Failed
            $this->logger->log('Sorry, unexpected server response detected. Complete server response given below.');
            echo '<pre>';
            print_r($result);
            echo '</pre>';
        }

        return $result;
    }
}
