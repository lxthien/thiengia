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

use FreeSslDotTech\FreeSSLAuto\Admin\Factory as AdminFactory;
use FreeSslDotTech\FreeSSLAuto\Logger;

class cloudflare
{
    private $api_base = 'https://api.cloudflare.com';
    private $api_email;
    private $api_key;

    /**
     * Initiates the cloudflare class.
     *
     * @param array $provider
     */
    public function __construct($provider)
    {
        $adminFactory = new AdminFactory();

        $this->api_email = $provider['api_identifier'];
        $this->api_key = $adminFactory->decryptText($provider['api_credential']);
        $this->logger = new Logger();
    }

    /**
     * set TXT record.
     *
     * @param string $domain_name
     * @param string $txt_name
     * @param string $txt_value
     *
     * @return array
     */
    public function setTxt($domain_name, $txt_name, $txt_value)
    {
        $data = [
            'type' => 'TXT',
            'name' => $txt_name,
            'content' => $txt_value,
            'ttl' => 600,
        ];

        $curl = new DnsCurl('Cloudflare', $this->api_email, $this->api_key);

        //Get all domains/zones and id
        $result = $curl->connect('GET', $this->api_base.'/client/v4/zones');

        //Remove domain records other than $domain_name
        $domain = array_reduce($result['body']['result'], function ($v, $w) use (&$domain_name) {
            return $v ? $v : ($w['name'] === $domain_name ? $w : false);
        });

        //Get all TXT records for $domain_name
        $result = $curl->connect('GET', $this->api_base.'/client/v4/zones/'.$domain['id'].'/dns_records?type=TXT');

        $txt_record_name = $txt_name.'.'.$domain_name;

        //Remove domain records other than $txt_record_name
        $txt_details = array_reduce($result['body']['result'], function ($v, $w) use (&$txt_record_name) {
            return $v ? $v : ($w['name'] === $txt_record_name ? $w : false);
        });

        if (!$txt_details) {
            //NO data exist for $txt_record_name. Make new TXT data entry
            $result = $curl->connect('POST', $this->api_base.'/client/v4/zones/'.$domain['id'].'/dns_records', $data);
        } else {
            //TXT data exist for $txt_record_name update/replace the record
            $result = $curl->connect('PUT', $this->api_base.'/client/v4/zones/'.$domain['id'].'/dns_records/'.$txt_details['id'], $data);
        }

        //Check status code
        if (200 === $result['http_code']) {
            //Success
            $this->logger->log('Congrats! TXT record added successfully.');
        }

        return $result;
    }

    /**
     * @param string $domain
     *
     * @return string
     */
    public function fetchAll($domain)
    {
        return $domain;
    }
}
