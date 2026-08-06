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

use FreeSslDotTech\FreeSSLAuto\cPanel\cPanel;
use FreeSslDotTech\FreeSSLAuto\Logger;

class DnsApi
{
    private $provider_name;
    private $api_key;
    private $api_secret;
    private $logger;
    private $cPanel;

    /**
     * Initiates DnsApi class.
     *
     * @param string     $provider
     * @param null|array $cPanel
     */
    public function __construct($provider, $cPanel = null)
    {
        $this->provider = $provider;
        $this->logger = new Logger();
        $this->cPanel = $cPanel;
    }

    /**
     * Set the DNS TXT record.
     *
     * @param string $domain
     * @param string $txt_name
     * @param string $txt_value
     * @param string $admin_email
     *
     * @throws \RuntimeException
     *
     * @return array
     */
    public function setTxt($domain, $txt_name, $txt_value, $admin_email)
    {
        if (!$this->provider) {
            //DNS provider not supported. TXT record need to be added manually.
            $result['http_code'] = 'manual';
        } else {
            if (false !== $this->provider['name']) {
                $valid_provider_names = ['godaddy', 'cloudflare', 'namecheap', 'cpanel', 'awsroute53', 'googleclouddns', 'auroradns', 'cloudns', 'cloudxns', 'digitalocean', 'dnsimple', 'dnsmadeeasy', 'dnspark', 'dnspod', 'easydns', 'gandi', 'gehirninfrastructureservice', 'glesys', 'linode', 'luadns', 'memset', 'namesilo', 'ns1', 'onapp', 'ovh', 'pointhq', 'powerdns', 'rackspace', 'rage4', 'sakuracloudbysakurainternetinc', 'softlayer', 'transip', 'yandex', 'vultr', 'zonomi'];

                $dns_provider_name = strtolower($this->provider['name']);

                if (!\in_array($dns_provider_name, $valid_provider_names, true)) {
                    //DNS provider name is NOT currect
                    throw new \RuntimeException('DNS provider name '.$dns_provider_name.' is not valid. Please provide exact spelling by finding from the list of our supported DNS providers.');
                }

                //check if the DNS server is cPanel, if cPanel, call the FreeSslDotTech\FreeSSLAuto\cPanel\cPanel class

                if ('cpanel' === $dns_provider_name) {
                    //check if cPanel login details provided.
                    if ($this->cPanel['is_cpanel']) {
                        $dnsapi = new cPanel($this->cPanel['cpanel_host'], $this->cPanel['username'], $this->cPanel['password']);

                        $result = $dnsapi->setDnsTxt($domain, $txt_name, $txt_value);
                    } else {
                        //cPanel login details not provided.
                        //send error email to admin and
                        //set manual mode
                        $result['http_code'] = 'manual';
                    }
                } else {
                    //DNS provider name is currect
                    //declare the full namespace path of the class
                    $class = 'FreeSslDotTech\\FreeSSLAuto\\DnsApi\\'.$dns_provider_name;
                    $dnsapi = new $class($this->provider);
                    $result = $dnsapi->setTxt($domain, $txt_name, $txt_value);
                }
            } else {
                //DNS provider not supported. TXT record need to be added manually.
                $result['http_code'] = 'manual';
            }
        }

        $body = '<html><body>';

        //Check status code
        //Send email to admin
        if ('manual' === $result['http_code']) {
            //Manual option selected
            $subject = 'Please manually add this DNS TXT record on '.$domain;

            $body .= "We are sorry, your DNS provider is not supported by <a href='https://freessl.tech'>FreeSSL.tech Auto</a>.<br /><br />";
            $body .= '<strong>Please manually add the following DNS TXT record on '.$domain.'.</strong><br /><br />';
        } elseif (200 === $result['http_code']) {
            //Success
            $subject = 'FreeSSL.tech Auto added DNS TXT record on '.$domain.' successfully';

            $body .= "<h2><a href='https://freessl.tech'>FreeSSL.tech Auto</a> added DNS TXT record on ".$domain.' successfully</h2><br />';
            $body .= '<strong>No further action required by you.</strong><br /><br />
                 For your information, TXT record details given below:<br /><br />';
        } else {
            //Failed
            $subject = 'Please add this DNS TXT record on '.$domain.' manually or check your DNS API credentials and try again';

            $body .= '<h2>Please add this DNS TXT record on '.$domain.' manually or check your DNS API credentials and try again.</h2><br />';
            $body .= 'Sorry, an unexpected error detected. HTTP code: '.$result['http_code'].".<br /><br />
                Please check your DNS API credentials in the <strong>DNS Providers settings</strong> of your <a href='https://freessl.tech'>FreeSSL.tech Auto</a> installation and try again.<br /><br />
                If you provided DNS API credentials properly but still getting this error, then please consider manually adding the TXT record.<br /><br />
                <strong>TXT record details are given below:</strong><br /><br />";
        }

        $body .= 'Domain name: '.$domain.'<br /><br />
                 TXT record name/host: '.$txt_name.'<br />
                 TXT record value: '.$txt_value."<br /><br />
                 If you wish to manually check the propagation status (optional), the address is:<strong> ${txt_name}.${domain}</strong><br /><br />";

        if (200 !== $result['http_code'] && 'manual' !== $result['http_code']) {
            $body .= 'Complete response from the DNS Server API given below:<br />
                <pre>'.print_r($result, true).'</pre><br />';
        }

        $body .= "Do not reply to this automated email.<br /><br />
        --------------<br />
        FreeSSL.tech Auto<br />
        Powered by <a href='https://letsencrypt.org'>Let’s Encrypt</a>, <a href='https://speedify.tech'>SpeedUpWebsite.info</a> and <a href='https://getwww.me'>GetWWW.me</a><br /><br />
        </body></html>";

        //Send email to all admin email id
        $to = implode(',', $admin_email);
        // Set content-type header
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=iso-8859-1';
        $headers[] = 'From:noreply@'.$domain;

        // Send the email
        if (mail($to, $subject, $body, implode("\r\n", $headers))) {
            $this->logger->log('Congratulations, email to admin was sent successfully with DNS TXT record details!');
        } else {
            $this->logger->log('Sorry, there was an issue sending the email with DNS TXT record details.');
        }

        return $result;
    }
}
