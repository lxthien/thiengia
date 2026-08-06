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

namespace FreeSslDotTech\FreeSSLAuto;

use DateTime;
use FreeSslDotTech\FreeSSLAuto\Acme\Factory;

//Common actions, even if the control panel is not cPanel
class Controller
{
    /**
     * Initiates the Controller class.
     */
    public function __construct()
    {
        $this->logger = new Logger();
    }

    /**
     * Check if we need to issue an SSL certificate for the domain.
     *
     * @param string $domain
     * @param array  $installed_hosts
     * @param int    $days_before_expiry_to_renew_ssl
     * @param string $domainPath
     * @param bool   $using_cdn
     * @param array  $domains_to_exclude
     *
     * @return bool
     */
    public function sslRequired($domain, $installed_hosts, $days_before_expiry_to_renew_ssl, $domainPath, $using_cdn, $domains_to_exclude)
    {
        if (\in_array($domain, $domains_to_exclude, true)) {
            $this->logger->log($domain.' is in your exclusion list, skipping it');

            return false;
        }
        if (empty($installed_hosts) || !$installed_hosts->status) {
            //if using cloudflare or any other CDN, depending on the installed SSL may results false positive, because it may be the SSL of the CDN instead of the domain itself
            //Get SSL data from the file certificate.pem, if previously created by this app

            if ($using_cdn) {
                $ssl_cert_file = $domainPath.'certificate.pem';

                if (!file_exists($ssl_cert_file)) {
                    // We don't have a SSL certificate, so we need to request one.
                    return true;
                }
                // We have a SSL certificate.
                $ssl_cert_data = openssl_x509_parse(file_get_contents($ssl_cert_file));

                //Return FALSE if expiry date is less than $days_before_expiry_to_renew_ssl days away, else return TRUE
                $now = new DateTime();
                $expiry = new DateTime('@'.$ssl_cert_data['validTo_time_t']);
                $interval = (int) $now->diff($expiry)->format('%R%a');
                
                $this->logger->log('Existing SSL for '.$domain.' found at this location: '.$ssl_cert_file);
                
                $this->logger->log('Existing SSL expires in '.$interval.' days');
                
                $this->logger->log("You have choosen to renew SSL ${days_before_expiry_to_renew_ssl} days before the expiry date");
                
                if ($interval <= $days_before_expiry_to_renew_ssl) {
                    return true;
                }

                return false;
            }
            $socket = @fsockopen($domain, 80, $errno, $errstr, 30);

            if ($socket) {
                $this->logger->log('Domain '.$domain.' is online!');

                $oldErrorReporting = error_reporting(); // save error reporting level
                    error_reporting($oldErrorReporting ^ E_WARNING); // disable warnings

                    //Get already installed SSL's data in alternative way
                $g = stream_context_create(['ssl' => ['capture_peer_cert' => true]]);

                $r = stream_socket_client("ssl://${domain}:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $g);
                error_reporting($oldErrorReporting); // restore error reporting level

                if (false === $r) {
                    //Self signed SSL or no SSL installed
                    $this->logger->log('Possible self signed certificate found! Or no SSL installed on '.$domain);

                    return true;
                }

                $cert = stream_context_get_params($r);

                $certinfo = openssl_x509_parse($cert['options']['ssl']['peer_certificate']);

                $cert_data = print_r($certinfo, true);

                if (!\strlen($cert_data)) {
                    //Self signed SSL or no SSL installed
                    $this->logger->log('Possible self signed certificate found! Or no SSL installed on '.$domain);

                    return true;
                }

                //Self signed SSL may don't have issuer at all
                if (0 === \strlen($certinfo['issuer']['CN'])) {
                    $this->logger->log('Possible self signed certificate found! Or no SSL installed on '.$domain);

                    return true;
                }
                //Self signed SSL may have same issuer CN and subject CN
                if ($certinfo['issuer']['CN'] === $certinfo['subject']['CN']) {
                    $this->logger->log('Possible self signed certificate found! Or no SSL installed on '.$domain);

                    return true;
                }

                $san_array = explode(',', $certinfo['extensions']['subjectAltName']);

                $san_array_filtered = [];

                $san_array_filtered[] = $certinfo['subject']['CN'];

                foreach ($san_array as $san) {
                    if ($certinfo['subject']['CN'] !== str_replace('DNS:', '', $san)) {
                        $san_array_filtered[] = str_replace('DNS:', '', $san);
                    }
                }

                echo 'san_array_filtered<pre>';
                var_dump($san_array_filtered);
                echo '</pre>';

                //Checking existing SSL, if any
                $sslRequired = $this->sslCheck($san_array_filtered, $domain, $certinfo['validTo_time_t'], $days_before_expiry_to_renew_ssl, $certinfo['issuer']['CN']);

                if ($sslRequired) {
                    return true;
                }
                if (false === $sslRequired) {
                    return false;
                }

                fclose($socket);
            } else {
                //Thought this is not a case of okay, but we don't need to generate
                //SSL for an offline domain. So return true
                $this->logger->log('Domain '.$domain.' is offline');

                return false;
            }
        } elseif ($installed_hosts->status) {
            //Success 2nd cURL call - SSL installation feature exist
            //So Get the expiry date internally - Using the data returned by cPanel UAPI

            foreach ($installed_hosts->data as $installed_ssl) {
                //Make wildcard, if the installed SSL is wildcard and if $domain is a sub domain

                $installed_on_domains = $installed_ssl->domains;

                $proceed_next = false;

                //First check with the domain as is
                if (\in_array($domain, $installed_on_domains, true)) {
                    $proceed_next = true;
                } else {
                    //Then check by extracting both the base (naked) domain of the installed domain and the current domain

                    if ($this->isDomainWildcard($installed_on_domains) === $this->getNakedDomain($domain)) {
                        $proceed_next = true;
                    }
                }

                if ($proceed_next) {
                    //Existing SSL installed for $domain
                    //Check if self signed & expiry date
                    if ($installed_ssl->certificate->is_self_signed) {
                        //SSL is self signed
                        $this->logger->log('Self signed SSL installed on '.$domain);

                        return true;
                    }

                    //check if $domain is in the SAN of the installed SSL

                    $san_array = $installed_ssl->certificate->domains;

                    $certificate = (array) $installed_ssl->certificate;

                    $cn = $certificate['subject.commonName'];

                    if (!\in_array($cn, $san_array, true)) {
                        array_push($san_array, $cn);
                    }

                    //Checking existing SSL, if any
                    $sslRequired = $this->sslCheck($san_array, $domain, $installed_ssl->certificate->not_after, $days_before_expiry_to_renew_ssl, $certificate['issuer.commonName']);

                    if ($sslRequired) {
                        return true;
                    }
                    if (false === $sslRequired) {
                        return false;
                    }
                }
            }

            //No SSL installed on $domain

            return true;
        }
    }

    /**
     * Make array of the domains pointing to the same document root of a domain.
     *
     * @param array $single_domain
     * @param array $domains_to_exclude
     *
     * @return array
     */
    public function domainsArray($single_domain, $domains_to_exclude)
    {
        $domains_array = [];

        if (!\in_array($single_domain['domain'], $domains_to_exclude, true)) {
            $domains_array[] = $single_domain['domain'];
        }

        if (\strlen($single_domain['serveralias']) > 1) {
            $domains = explode(' ', $single_domain['serveralias']);

            foreach ($domains as $domain) {
                //Exclude $domains_to_exclude
                if (!\in_array($domain, $domains_to_exclude, true)) {
                    if ($single_domain['domain'] !== $domain) {
                        $domains_array[] = $domain;
                    }
                }
            }
        }

        //remove offline domains
        //e.g., in Godaddy hosting www version of any sub domain is offline by default
        $domains_online = [];

        foreach ($domains_array as $key => $domain) {
            if (false === strpos($domain, '*.')) {
                //check if domain is online, only for non-wildcard domains

                $socket = @fsockopen($domain, 80, $errno, $errstr, 30);

                if ($socket) {
                    //Domain is online
                    $domains_online[] = $domain;
                } else {
                    //domain offline
                    $this->logger->log($domain.' is offline. Skipping it.');
                }
            } else {
                //Domain is wildcard and will be validated by DNS-01 challange, so, online check not required
                $domains_online[] = $domain;
            }
        }

        return $domains_online;
    }

    /**
     * Make the wildcard version of an array of domains.
     *
     * At least one sub-domain required to make wildcard version
     * If the base domain (registered domain) has NO SSL installed but all the sub-domains have SSL installed, then the base domain (registered domain) will NOT be included with wildcard.
     * The base domain (registered domain) will be included with wildcard only if at least one sub-domain and the base domain require SSL at the same time
     *
     * @param array  $all_domains
     * @param array  $installed_hosts
     * @param string $certificatesBaseDir
     * @param int    $acme_version
     * @param bool   $is_staging
     * @param bool   $using_cdn
     * @param int    $days_before_expiry_to_renew_ssl
     * @param array  $domains_to_exclude
     *
     * @return array
     */
    public function makeWildcardComplete($all_domains, $installed_hosts, $certificatesBaseDir, $acme_version, $is_staging, $using_cdn, $days_before_expiry_to_renew_ssl, $domains_to_exclude)
    {
        $wildcard_domain_array = [];

        //The parameter below is NOT correct this depends $certificatesDir on ACME version and staging option
        $factory = new Factory($certificatesBaseDir, $acme_version, $is_staging);

        foreach ($all_domains as $domain_as_is) {
            $registeredDomain = getRegisteredDomain($domain_as_is['domain']);

            if (\strlen($domain_as_is['domain']) > \strlen($registeredDomain)) {
                //This is a sub domain

                //get the path of SSL files
                $domainPath = $factory->getDomainPath($domain_as_is['domain']);

                if ($this->sslRequired($domain_as_is['domain'], $installed_hosts, $days_before_expiry_to_renew_ssl, $domainPath, $using_cdn, $domains_to_exclude)) {
                    $wildcard_domain = $this->getWildcardBase($domain_as_is['domain']);
                    $wildcard_domain_array[$wildcard_domain]['installation_required'][] = $domain_as_is['domain'];
                    $wildcard_domain_array[$wildcard_domain]['domain'] = $wildcard_domain;

                    //Push all server alias (except $domain_as_is['domain']) and the document root in an array

                    if (!array_key_exists('serveralias', $wildcard_domain_array[$wildcard_domain])) {
                        //get the path of SSL files
                        $domainPath = $factory->getDomainPath($registeredDomain);

                        //Push the $registeredDomain into 'serveralias' if this is the 1st level wildcard and if SSL installation required on the registered domain
                        if (('*.'.$registeredDomain === $wildcard_domain) && $this->sslRequired($registeredDomain, $installed_hosts, $days_before_expiry_to_renew_ssl, $domainPath, $using_cdn, $domains_to_exclude)) {
                            $wildcard_domain_array[$wildcard_domain]['serveralias'] = $registeredDomain;
                        }
                    }

                    if ($registeredDomain !== $domain_as_is['serveralias']) {
                        //Filter out $domain_as_is['domain'] from $domain_as_is['serveralias'] before the push

                        $serveralias_array = explode(' ', $domain_as_is['serveralias']);

                        $key = array_search($domain_as_is['domain'], $serveralias_array, true);

                        if (false !== $key) {
                            unset($serveralias_array[$key]);
                        }

                        foreach ($serveralias_array as $serveralias) {
                            if (!\in_array($serveralias, $domains_to_exclude, true)) {
                                if (array_key_exists('serveralias', $wildcard_domain_array[$wildcard_domain])) {
                                    $wildcard_domain_array[$wildcard_domain]['serveralias'] .= ' '.$serveralias;
                                } else {
                                    $wildcard_domain_array[$wildcard_domain]['serveralias'] = $serveralias;
                                }

                                $wildcard_domain_array[$wildcard_domain]['documentroot'][$serveralias] = $domain_as_is['documentroot'];
                            }
                        }
                    }
                }
            } else {
                $wildcard_domain = $registeredDomain;
            }

            if (!array_key_exists($wildcard_domain, $wildcard_domain_array)) {
                $wildcard_domain_array[$wildcard_domain] = $domain_as_is;
            }

            //If at least one wildcard domain exists, remove the root domain from this array
            //because, wildcard SSL will cover the root domain too
            foreach ($wildcard_domain_array as $key => $value) {
                if (array_key_exists('*.'.$key, $wildcard_domain_array)) {
                    unset($wildcard_domain_array[$key]);
                    //set document root of the base domain (registered domain)
                    $wildcard_domain_array['*.'.$key]['documentroot'][$key] = $value['documentroot'];

                    //get the path of SSL files
                    $domainPath = $factory->getDomainPath($value['domain']);

                    if ($this->sslRequired($value['domain'], $installed_hosts, $days_before_expiry_to_renew_ssl, $domainPath, $using_cdn, $domains_to_exclude)) {
                        $wildcard_domain_array['*.'.$key]['installation_required'][] = $value['domain'];
                    }
                }
            }
        }

        return $wildcard_domain_array;
    }

    /**
     * Get the base domain, i.e., the registered domain.
     *
     * @param string $domain_as_is
     *
     * @return string
     */
    public function getNakedDomain($domain_as_is)
    {
        return getRegisteredDomain($domain_as_is);
    }

    /**
     * Get the wildcard base domain.
     *
     * @param string $domain_as_is
     *
     * @return string
     */
    public function getWildcardBase($domain_as_is)
    {
        $registeredDomain = getRegisteredDomain($domain_as_is);

        if (null === $registeredDomain) {
            return false;
        }
        //compute wildcard domain

        if (\strlen($domain_as_is) > \strlen($registeredDomain)) {
            //may be it's a subdomain
            $part = str_replace($registeredDomain, '', $domain_as_is);

            $domain_elements_array = explode('.', $part);

            if (2 === \count($domain_elements_array) && 'www' === $domain_elements_array[0]) {
                //www.domain.com is not considered as wildcard domain

                return $domain_as_is;
            }
            //get the position of first . and ONLY replace the part left to it with *
            $pos = strpos($domain_as_is, '.');

            return '*'.substr($domain_as_is, $pos);
        } elseif (\strlen($domain_as_is) === \strlen($registeredDomain)) {
            return $domain_as_is;
        }
    }

    /**
     * Is the domain wildcard?
     *
     * @param string $domains
     *
     * @return string
     */
    public function isDomainWildcard($domains)
    {
        //Checks both array and string
        if (\is_array($domains)) {
            foreach ($domains as $domain) {
                if (false === strpos($domain, '*.')) {
                    return false;
                }

                return $this->getNakedDomain($domain);
            }
        } else {
            if (false === strpos($domains, '*.')) {
                return false;
            }

            return true;
        }
    }

    /**
     * Helper method of sslRequired().
     *
     * @param array  $sansArray
     * @param string $domain
     * @param int    $validTo
     * @param int    $days_before_expiry_to_renew_ssl
     * @param string $issuer
     *
     * @return bool
     */
    private function sslCheck($sansArray, $domain, $validTo, $days_before_expiry_to_renew_ssl, $issuer)
    {
        //remove space and cast as string
        $sansArrayFiltered = array_map(function ($piece) {
            return (string) trim($piece);
        }, $sansArray);

        //Domain - SSL issued to
        $this->logger->log('Checking existing SSL of the Domain: '.$domain);
        $this->logger->log('Installed SSL was issued to (i.e., SAN): '.implode(', ', $sansArrayFiltered));

        if (\in_array($domain, $sansArrayFiltered, true)) {
            // found in_array. Now check the date

            //EXACT MATCH

            //Return TRUE if expiry date is less than $days_before_expiry_to_renew_ssl days away
            $now = new DateTime();
            $expiry = new DateTime('@'.$validTo);
            $interval = (int) $now->diff($expiry)->format('%R%a');

            //echo $interval;
            $this->logger->log('SSL for '.$domain.' expires in '.$interval.' days');

            $this->logger->log("You have choosen to renew SSL ${days_before_expiry_to_renew_ssl} days before the expiry date");

            if ($interval <= $days_before_expiry_to_renew_ssl) {
                return true;
            }

            return false;
        }
        //NOT found in_array

        //WILDCARD MATCH

        // Now we need only wildcard domains

        $sansArrayWildcard = [];

        foreach ($sansArrayFiltered as $key => $value) {
            if (false !== strpos($value, '*.')) {
                $sansArrayWildcard[] = $value;
            }
        }

        $lastElement = \count($sansArrayWildcard) - 1;

        foreach ($sansArrayWildcard as $key => $san) {
            $baseDomain = str_replace('*', '', $san); //e.g.: .speedupwebsite.info

            if (false !== strpos($domain, $baseDomain)) {
                //Probably $domain is belongs to the wildcard SSL

                $part = str_replace($baseDomain, '', $domain); //test2

                $this->logger->log('Part: '.$part);

                if (false === strpos($part, '.')) {
                    //NO dot in the $part, $domain and the wildcard SSL belongs to the same level

                    //Now check expiry date of the SSL

                    //Return TRUE if expiry date is less than $days_before_expiry_to_renew_ssl days away
                    $now = new DateTime();
                    $expiry = new DateTime('@'.$validTo);
                    $interval = (int) $now->diff($expiry)->format('%R%a');
                    //echo $interval;

                    //Issuer
                    $this->logger->log('Issuer: '.$issuer);

                    //Valid SSL found. Get the expiry date
                    $this->logger->log('SSL of '.$san.' expires in '.$interval.' days');

                    $this->logger->log("You have choosen to renew SSL ${days_before_expiry_to_renew_ssl} days before the expiry date");

                    if ($interval <= $days_before_expiry_to_renew_ssl) {
                        return true;
                    }

                    return false;
                }
                //$domain and the wildcard SSL is NOT belongs to the same level
                continue;
            }
            //$domain is NOT belongs to the wildcard SSL

            $this->logger->log('current SAN: '.$san);

            if ($key === $lastElement) {
                return true;
            }
        }//end foreach
    }
}
