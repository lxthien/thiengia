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
use SimpleXMLElement;

class namecheap
{
    private $api_base = 'https://api.namecheap.com';
    private $api_user;
    private $api_key;
    private $server_ip;

    /**
     * Initiates the namecheap class.
     *
     * @param array $provider
     */
    public function __construct($provider)
    {
        $adminFactory = new AdminFactory();

        $this->api_user = $provider['api_identifier'];
        $this->api_key = $adminFactory->decryptText($provider['api_credential']);
        $this->server_ip = $provider['server_ip'];
        $this->logger = new Logger();
    }

    /**
     * Set DNS TXT record.
     *
     * @param string $domain
     * @param string $txt_name
     * @param string $txt_value
     *
     * @return array
     */
    public function setTxt($domain, $txt_name, $txt_value)
    {
        $sld_tld = $this->extractSldTld($domain);

        $sld = $sld_tld['sld'];
        $tld = $sld_tld['tld'];

        //Add all existing data also, with the new one. Otherwise existing data will be DELETED
        $query = $this->api_base."/xml.response?apiuser={$this->api_user}&apikey={$this->api_key}&username={$this->api_user}&Command=namecheap.domains.dns.setHosts&ClientIp={$this->server_ip}&SLD=${sld}&TLD=${tld}";

        $query .= "&HostName1=${txt_name}&RecordType1=TXT&Address1=${txt_value}&TTL1=600";

        $n = 2;

        $arr = $this->fetchAll($domain, $sld, $tld);
        
        foreach ($arr->host as $host) {            
                $record_name = $host['Name'];
                $type = $host['Type'];
                $address = $host['Address'];
                $mxpref = $host['MXPref'];
                $ttl = $host['TTL'];
                $AssociatedAppTitle = $host['AssociatedAppTitle'];
                $FriendlyName = $host['FriendlyName'];
                $IsActive = $host['IsActive'];
                $IsDDNSEnabled = $host['IsDDNSEnabled'];
    
                if ($record_name != $txt_name) {
                    $query .= "&HostName${n}=${record_name}&RecordType${n}=${type}&Address${n}=${address}&MXPref${n}=${mxpref}&TTL${n}=${ttl}&AssociatedAppTitle${n}=${AssociatedAppTitle}&FriendlyName${n}=${FriendlyName}&IsActive${n}=${IsActive}&IsDDNSEnabled${n}=${IsDDNSEnabled}";
                    ++$n;
                }           
        }
        
        //Blank spaces results in API call failure. So, replace blank spaces with "%20"
        $query = str_replace(" ", "%20", $query);
        
        $curl = new DnsCurl('Namecheap', $this->api_user, $this->api_key);
        $result = $curl->connect('GET', $query);

        /* Namecheap documentation suggests to Use POST when setting more than 10 hostnames
         * But unfortunately POST method is not working
         */

        $xml_to_object = new SimpleXMLElement($result['body']);

        $is_success = (bool) $xml_to_object->CommandResponse->DomainDNSSetHostsResult['IsSuccess'];

        if ($is_success) {
            $this->logger->log('Congrats! TXT record added successfully.');
            $result['http_code'] = 200;
            $result['body'] = $xml_to_object;
        } else {
            $this->logger->log('Sorry, the record was not added due to an error');
            $result['http_code'] = 404;
            $result['body'] = $xml_to_object;

            echo '<pre>';
            print_r($result);
            echo '</pre>';
        }

        return $result;
    }

    /**
     * Fetch all existing DNS records.
     *
     * @param string $domain
     * @param string $sld
     * @param string $tld
     *
     * @return unknown
     */
    public function fetchAll($domain, $sld, $tld)
    {
        $curl = new DnsCurl('Namecheap', $this->api_user, $this->api_key);

        $result = $curl->connect('GET', $this->api_base."/xml.response?ApiUser={$this->api_user}&ApiKey={$this->api_key}&UserName={$this->api_user}&ClientIp={$this->server_ip}&SLD=${sld}&TLD=${tld}&Command=namecheap.domains.dns.getHosts");

        $xml_to_object = new SimpleXMLElement($result['body']);

        return $xml_to_object->CommandResponse->DomainDNSGetHostsResult;
    }

    /**
     * Get Namecheap supported TLD list.
     *
     * @return array
     */
    public function getTldList()
    {
        //NOTE from Namecheap: "We strongly recommend that you cache this API response to avoid repeated calls".

        $filename = __DIR__.'/namecheap_tld_xml.txt';

        $curl = new DnsCurl('Namecheap', $this->api_user, $this->api_key);

        if (!is_file($filename)) {
            //$filename doesn't exist. Make new API call
            $tld_list_xml = $curl->connect('GET', $this->api_base."/xml.response?ApiUser={$this->api_user}&ApiKey={$this->api_key}&UserName={$this->api_user}&ClientIp={$this->server_ip}&Command=namecheap.domains.getTldList");

            file_put_contents($filename, $tld_list_xml['body']);
        } else {
            //Check the file creation date
            //If the file was created more than 7 days ago, make new API call.
            //Otherwise take value from the file

            $filetime = filectime($filename);

            $days = 7;
            $max_cache_time = $days * 24 * 60 * 60; //7 days

            $time_difference = time() - $filetime;

            if ($time_difference > $max_cache_time) {
                //Make new API call
                $tld_list_xml = $curl->connect('GET', $this->api_base."/xml.response?ApiUser={$this->api_user}&ApiKey={$this->api_key}&UserName={$this->api_user}&ClientIp={$this->server_ip}&Command=namecheap.domains.getTldList");

                file_put_contents($filename, $tld_list_xml['body']);
            } else {
                $tld_list_xml['body'] = file_get_contents($filename);
            }
        }

        $tld_list_object = new SimpleXMLElement($tld_list_xml['body']);

        $tld_list = [];
        foreach ($tld_list_object->CommandResponse->Tlds->Tld as $tld) {
            $tld_list[] = $tld['Name'];
        }

        //$tld_list contains following format for every key. So we need to use implode and then explode to make an usual array
        /*
         * [448] => SimpleXMLElement Object
         (
         [0] => yokohama
         )
         */
        $tld_list = implode(',', $tld_list);

        return explode(',', $tld_list);
    }

    /**
     * Extract SLD and TLD from a domain.
     *
     * @param string $domain_as_is
     *
     * @return array
     */
    public function extractSldTld($domain_as_is)
    {
        $tld_list_array = $this->getTldList();

        $domain = getRegisteredDomain($domain_as_is);

        $domain_elements_array = explode('.', $domain);

        $sld = $domain_elements_array[0];

        $tld = str_replace($sld.'.', '', $domain);

        if (!\in_array($tld, $tld_list_array, true)) {
            $this->logger->log("TLD ${tld} is NOT supported by namecheap. You may encounter error!");
        }

        return [
            'sld' => $sld,
            'tld' => $tld,
        ];
    }
}
