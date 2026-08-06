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

namespace FreeSslDotTech\FreeSSLAuto\cPanel;

use FreeSslDotTech\FreeSSLAuto\Admin\Factory as AdminFactory;
use FreeSslDotTech\FreeSSLAuto\Logger;

class cPanel
{
    private $cpanel_host;
    private $username;
    private $password;
    private $logger;

    /**
     * Initiates the cPanel class.
     *
     * @param string $cpanel_host
     * @param string $username
     * @param string $password
     */
    public function __construct($cpanel_host, $username, $password)
    {
        $this->cpanel_host = $cpanel_host;
        $this->username = $username;

        $adminFactory = new AdminFactory();

        $this->password = $adminFactory->decryptText($password);
        $this->logger = new Logger();
    }

    /**
     * Fetch all domains hosted in the cPanel - using cPanel UAPI.
     *
     * @return array|bool
     */
    public function allDomains()
    {
        //List primary domain, add-on domains and sub domain's info including document root, home directory etc
        $request_uri = 'https://'.$this->cpanel_host.':2083/execute/DomainInfo/domains_data?format=hash';

        $domains_data = $this->connectUapi($request_uri);

        //Validate output
        if (empty($domains_data)) {
            $this->logger->log("Oops! We can't connect to your cPanel. Please recheck the cPanel settings and provide correct credentials.");
            echo '<pre>';
            print_r($domains_data);
            echo '</pre>';
            die($domains_data);
        }
        if (!$domains_data->status) {
            $this->logger->log('The domains_data cURL call returned valid JSON, but reported errors:');
            echo $domains_data->errors[0].'<br />';

            return false;
        }
        if ($domains_data->status) {
            //Success
            $all_domains = [];

            $all_domains[] = [
                'domain' => $domains_data->data->main_domain->domain,
                'serveralias' => $domains_data->data->main_domain->serveralias,
                'documentroot' => $domains_data->data->main_domain->documentroot,
            ];

            foreach ($domains_data->data->addon_domains as $addon_domain) {
                $all_domains[] = [
                    'domain' => $addon_domain->domain,
                    'serveralias' => $addon_domain->serveralias,
                    'documentroot' => $addon_domain->documentroot,
                ];
            }

            foreach ($domains_data->data->sub_domains as $sub_domain) {
                $all_domains[] = [
                    'domain' => $sub_domain->domain,
                    'serveralias' => $sub_domain->serveralias,
                    'documentroot' => $sub_domain->documentroot,
                ];
            }

            return $all_domains;
        }
    }

    /**
     * Fetch installed SSL data from the cPanel - using cPanel UAPI.
     *
     * @return array|bool
     */
    public function installedHosts()
    {
        // Get already installed SSL's data (expiry date etc)

        $request_uri = 'https://'.$this->cpanel_host.':2083/execute/SSL/installed_hosts';

        $installed_hosts = $this->connectUapi($request_uri);

        //Validate output
        if (empty($installed_hosts) || !$installed_hosts->status) {
            $this->logger->log('The installed_hosts cURL call did not returned valid JSON, but reported errors:');
            echo $installed_hosts->errors[0].'<br />';

            return false;
        }

        return $installed_hosts;
    }

    /**
     * Install an SSL certificate on the domain provided - using cPanel UAPI.
     *
     * @param string $domain
     * @param string $domainPath
     *
     * @return bool
     */
    public function installSSL($domain, $domainPath)
    {
        $this->logger->log('Let\'s install the SSL now!!');

        // Define the SSL certificate and key files.
        $cert_file = realpath($domainPath.DS.'certificate.pem');
        $key_file = realpath($domainPath.DS.'private.pem');
        $cabundle_file = realpath($domainPath.DS.'cabundle.pem');

        // Declare your username and password of the cPanel for authentication.

        // Define the API call.
        $request_uri = 'https://'.$this->cpanel_host.':2083/execute/SSL/install_ssl';

        // Set up the payload to send to the server.
        $payload = [
            'domain' => $domain,
            'cert' => file_get_contents($cert_file),
            'key' => file_get_contents($key_file),
            'cabundle' => file_get_contents($cabundle_file),
        ];

        $response = $this->connectUapi($request_uri, $payload);

        //Validate $response
        if (empty($response)) {
            $this->logger->log('The install_ssl cURL call did not return valid JSON:');
            $this->logger->log('Sorry, there was a problem installing the SSL on '.$domain);
            die($response);
        }
        if (!$response->status) {
            $this->logger->log('The install_ssl cURL call returned valid JSON, but reported errors:');
            echo $response->errors[0].'<br />';
            $this->logger->log('Sorry, there was a problem installing the SSL on '.$domain);

            return false;
        }
        if ($response->status) {
            $this->logger->log('Congrats! SSL installed on '.$domain.' successfully.');

            return true;
        }
    }

    /**
     * Connect to the cPanel using UAPI.
     *
     * @param string     $request_uri
     * @param null|array $payload
     *
     * @return mixed
     */
    public function connectUapi($request_uri, $payload = null)
    {
        // Set up the cURL request object.
        $ch = curl_init($request_uri);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $this->username.':'.$this->password);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if (null !== $payload) {
            // Set up a POST request with the payload.
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Make the call, and then terminate the cURL caller object.
        $curl_response = curl_exec($ch);
        curl_close($ch);

        // Decode and return output.
        return json_decode($curl_response);
    }

    /**
     * Set DNS TXT record using Json API through cPanel XMLAPI.
     *
     * @param string $domain
     * @param string $txt_name
     * @param string $txt_value
     *
     * @return array
     */
    public function setDnsTxt($domain, $txt_name, $txt_value)
    {
        $xmlapi = new xmlapi($this->cpanel_host, $this->username, $this->password);

        $xmlapi->set_output('json');

        $xmlapi->set_port('2083');

        $xmlapi->set_debug(1);

        $responce = $xmlapi->api2_query(
            $this->username,
            'ZoneEdit',
            'add_zone_record',
            [
                'domain' => $domain,
                'name' => $txt_name,
                'type' => 'TXT',
                'txtdata' => $txt_value,
                'ttl' => '600',
                'class' => 'IN',
            ]
            );

        $responce_array = json_decode($responce, true);

        $result = [];

        //Check status

        $event_result = (bool) $responce_array['cpanelresult']['event']['result'];

        $preevent_result = isset($responce_array['cpanelresult']['preevent']) ? (bool) $responce_array['cpanelresult']['preevent']['result'] : true; //Some cPanel doesn't provide this key. In that case, ignore it by setting 'true'.

        $postevent_result = isset($responce_array['cpanelresult']['postevent']) ? (bool) $responce_array['cpanelresult']['postevent']['result'] : true; //Some cPanel doesn't provide this key. In that case, ignore it by setting 'true'.

        if ($event_result && $preevent_result && $postevent_result) {
            //$result['header'] = $response;
            $result['http_code'] = 200;
            $result['body'] = $responce_array;

            $this->logger->log('Congrats! TXT record added successfully.');
        } else {
            //$result['header'] = $response;
            $result['http_code'] = 404;
            $result['body'] = $responce_array;

            $this->logger->log('Sorry, the record was not added due to an error');
        }

        return $result;
    }
}
