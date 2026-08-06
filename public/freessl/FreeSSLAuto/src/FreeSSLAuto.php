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

use FreeSslDotTech\FreeSSLAuto\Acme\AcmeV1;
use FreeSslDotTech\FreeSSLAuto\Acme\AcmeV2;
use FreeSslDotTech\FreeSSLAuto\Acme\Factory;
use FreeSslDotTech\FreeSSLAuto\cPanel\cPanel;

class FreeSSLAuto
{
    /**
     * Initiates the FreeSSLAuto class.
     *
     * @param array $appConfig
     */
    public function __construct($appConfig = [])
    {
        $this->appConfig = $appConfig;
    }

    /**
     * Run the App.
     *
     * @throws \RuntimeException
     */
    public function run()
    {
        //Set to no time restriction
        set_time_limit(0);

        date_default_timezone_set('UTC');

        //Store the micro time so that we know when our script started to run.
        $executionStartTime = microtime(true);

        $logger = new Logger();

        //if domains_to_exclude is NOT set, use default settings
        if (!isset($this->appConfig['domains_to_exclude'])) {
            $this->appConfig['domains_to_exclude'] = [];
        }

        //Check if ACME version is set properly
        if (1 !== $this->appConfig['acme_version'] && 2 !== $this->appConfig['acme_version']) {
            throw new \RuntimeException("Invalid 'acme_version' provided. It must be 1 or 2.");
        }

        //Is the web hosting control panel cPanel?
        if ($this->appConfig['is_cpanel']) {
            $cpanel = new cPanel($this->appConfig['cpanel_host'], $this->appConfig['username'], $this->appConfig['password']);

            $all_domains = $cpanel->allDomains();
                        
            $pos_public_html = strpos($all_domains[0]['documentroot'], DS.'public_html');

            $homedir = str_replace(DS.'public_html', '', $all_domains[0]['documentroot']);
                        
            if(strlen($homedir) > $pos_public_html){
                //Default cPanel document root is modified. So, use the homedir value provided during config
                $homedir = $this->appConfig['homedir'];
            }
            
            
            if (false !== $all_domains) {
                $installed_hosts = $cpanel->installedHosts();

                if (\is_object($installed_hosts)) {
                    $ssl_installation_feature = $installed_hosts->status;
                } else {
                    $ssl_installation_feature = false;
                }
            } else {
                $installed_hosts = null;
                $ssl_installation_feature = false;
            }
        } else {
            $installed_hosts = null;
            $ssl_installation_feature = false;
            $homedir = $this->appConfig['homedir'];
            $all_domains = $this->appConfig['all_domains'];
        }

        //Make an array with cPanel data
        if ($this->appConfig['is_cpanel']) {
            $cPanel = [
                'is_cpanel' => $this->appConfig['is_cpanel'],
                'cpanel_host' => $this->appConfig['cpanel_host'],
                'username' => $this->appConfig['username'],
                'password' => $this->appConfig['password'],
            ];
        } else {
            $cPanel = [
                'is_cpanel' => $this->appConfig['is_cpanel'],
            ];
        }

        //Issue SSL
        if (ISSUE_SSL) {
            //Make wildcard domain if required
            if ($this->appConfig['use_wildcard']) {
                if (2 !== $this->appConfig['acme_version']) {
                    throw new \RuntimeException("'use_wildcard' is set TRUE. But 'acme_version' is ".$this->appConfig['acme_version'].". Please set 'acme_version' => 2 in order to issue wildcard SSL.");
                }

                $controller = new Controller();

                $all_domains = $controller->makeWildcardComplete($all_domains, $installed_hosts, $homedir.DS.$this->appConfig['certificate_directory'], $this->appConfig['acme_version'], $this->appConfig['is_staging'], $this->appConfig['using_cdn'], $this->appConfig['days_before_expiry_to_renew_ssl'], $this->appConfig['domains_to_exclude']);

                $logger->log('Wildcard version of all domains are given below. Wildcard version will appear only if at least one subdomain found that need new SSL.');
                echo '<pre>';
                print_r($all_domains);
                echo '</pre><br />';
            }

            foreach ($all_domains as $key => $single_domain) {
                $controller = new Controller();
                //domains array
                $domains_array = $controller->domainsArray($single_domain, $this->appConfig['domains_to_exclude']);

                //call the appropriate class name according to the ACME version
                if (1 === $this->appConfig['acme_version']) {
                    $freessl = new AcmeV1($homedir.DS.$this->appConfig['certificate_directory'], $this->appConfig['admin_email'], $this->appConfig['is_staging'], 'http-01', $this->appConfig['key_size']);
                } elseif (2 === $this->appConfig['acme_version']) {
                    //if DNS provider is NOT set, use default settings to avoid error
                    //This will enable making entry OPTIONAL if domain registrar/DNS service provider is other than the supported providers by this app

                    if (!isset($this->appConfig['dns_provider'])) {
                        $this->appConfig['dns_provider'][] = [
                            'name' => false,
                            'dns_provider_takes_longer_to_propagate' => true,
                            'domains' => $domains_array,
                            'server_ip' => $this->appConfig['server_ip']
                        ];
                    }
                                      
                    $freessl = new AcmeV2($homedir.DS.$this->appConfig['certificate_directory'], $this->appConfig['admin_email'], $this->appConfig['is_staging'], $this->appConfig['dns_provider'], $this->appConfig['key_size'], $cPanel, $this->appConfig['server_ip']);
                }

                //The parameter below is NOT correct this depends $certificatesDir on ACME version and staging option
                $factory = new Factory($homedir.DS.$this->appConfig['certificate_directory'], $this->appConfig['acme_version'], $this->appConfig['is_staging']);

                //get the path of SSL files
                $domainPath = $factory->getDomainPath($single_domain['domain']);

                if (false === strpos($single_domain['domain'], '*.')) {
                    $ssl_required = $controller->sslRequired($single_domain['domain'], $installed_hosts, $this->appConfig['days_before_expiry_to_renew_ssl'], $domainPath, $this->appConfig['using_cdn'], $this->appConfig['domains_to_exclude']);
                } else {
                    /*
                     * Wildcard SSL will always return TRUE with sslRequired()
                     * So, for Wildcard SSL check check for 'installation_required' key. If at least one element found return TRUE. If no element found return FALSE
                     * Check with count($single_domain['installation_required'])
                     */
                    if (0 === \count($single_domain['installation_required'])) {
                        $ssl_required = false;
                    } else {
                        $ssl_required = true;
                    }
                }

                if ($ssl_required && \count($domains_array) > 0) {
                    //Start the process to generate SSL

                    $logger->log('Generating SSL for '.$domains_array[0]);

                    $logger->log('Domains array');

                    echo '<pre>';
                    print_r($domains_array);
                    echo '</pre>';

                    try {
                        if ($freessl->obtainSsl($domains_array, $single_domain['documentroot'], false, $this->appConfig['country_code'], $this->appConfig['state'], $this->appConfig['organization'])) {
                            if ($ssl_installation_feature) {
                                if (false === strpos($single_domain['domain'], '*.')) {
                                    //Install SSL. This returns false if there is any problem to install SSL
                                    $ssl_installation_status = $cpanel->installSSL($domains_array[0], $domainPath);
                                    //Send email
                                    $email = new Email();
                                    $email->sendEmail($this->appConfig['admin_email'], $domains_array, $ssl_installation_feature, $ssl_installation_status, $domainPath, $homedir);
                                } else {
                                    foreach ($single_domain['installation_required'] as $install_on_this) {
                                        //Install SSL. This returns true for success, false otherwise.
                                        $ssl_installation_status = $cpanel->installSSL($install_on_this, $domainPath);
                                        //Send email to the admin
                                        $email = new Email();
                                        $email->sendEmail($this->appConfig['admin_email'], $install_on_this, $ssl_installation_feature, $ssl_installation_status, $domainPath, $homedir);
                                    }
                                }
                            } else {
                                $ssl_installation_status = false;
                                //Send email
                                $email = new Email();
                                $email->sendEmail($this->appConfig['admin_email'], $domains_array, $ssl_installation_feature, $ssl_installation_status, $domainPath, $homedir);
                            }
                        }
                    } catch (\Exception $e) {
                        $logger->error($e->getMessage());
                        $logger->error($e->getTraceAsString());
                    }
                } else {
                    $logger->log('This app will not generate SSL for '.$single_domain['domain'].' today.');
                }
            }

            //At the end of the code, compare the current microtime to the microtime that we stored at the beginning of the script.
            $executionEndTime = microtime(true);

            //The result will be in seconds
            $seconds = round(($executionEndTime - $executionStartTime), 2);

            //Print it with the log
            $logger->log('This script took '.$seconds.' seconds to execute.');
            $logger->log("Powered by Let's Encrypt, https://SpeedUpWebsite.info and https://GetWWW.me");
            $logger->log('FreeSSL.tech Auto (https://freessl.tech)');
        }

        //Change Let's Encrypt account key / Account key roll-over
        if (KEY_CHANGE) {
            
            //if DNS provider is NOT set, use the following settings to avoid error            
            if (!isset($this->appConfig['dns_provider'])) {
                $this->appConfig['dns_provider'][] = [
                    'name' => false,
                    'dns_provider_takes_longer_to_propagate' => true,
                    'domains' => [],
                    'server_ip' => $this->appConfig['server_ip']
                ];
            }
            
            //call the appropriate class name according to the ACME version
            if (1 === $this->appConfig['acme_version']) {
                $freessl = new AcmeV1($homedir.DS.$this->appConfig['certificate_directory'], $this->appConfig['admin_email'], $this->appConfig['is_staging'], 'http-01', $this->appConfig['key_size']);
            } elseif (2 === $this->appConfig['acme_version']) {
                $freessl = new AcmeV2($homedir.DS.$this->appConfig['certificate_directory'], $this->appConfig['admin_email'], $this->appConfig['is_staging'], $this->appConfig['dns_provider'], $this->appConfig['key_size'], $cPanel, $this->appConfig['server_ip']);
            }

            try {
                $freessl->keyChange();
            } catch (\Exception $e) {
                $logger->error($e->getMessage());
                $logger->error($e->getTraceAsString());
            }
        }

        //Revoke SSL
        if (REVOKE_CERT) {
            
            //if DNS provider is NOT set, use the following settings to avoid error            
            if (!isset($this->appConfig['dns_provider'])) {
                $this->appConfig['dns_provider'][] = [
                    'name' => false,
                    'dns_provider_takes_longer_to_propagate' => true,
                    'domains' => [],
                    'server_ip' => $this->appConfig['server_ip']
                ];
            }
            
            //call the appropriate class name according to the ACME version
            if (1 === $this->appConfig['acme_version']) {
                $freessl = new AcmeV1($homedir.DS.$this->appConfig['certificate_directory'], $this->appConfig['admin_email'], $this->appConfig['is_staging'], 'http-01', $this->appConfig['key_size']);
            } elseif (2 === $this->appConfig['acme_version']) {
                $freessl = new AcmeV2($homedir.DS.$this->appConfig['certificate_directory'], $this->appConfig['admin_email'], $this->appConfig['is_staging'], $this->appConfig['dns_provider'], $this->appConfig['key_size'], $cPanel, $this->appConfig['server_ip']);
            }

            if (\count($this->appConfig['domains_to_revoke_cert']) > 0) {
                foreach ($this->appConfig['domains_to_revoke_cert'] as $single_domain) {
                    //initialize Factory class
                    $factory = new Factory($homedir.DS.$this->appConfig['certificate_directory'], $this->appConfig['acme_version'], $this->appConfig['is_staging']);

                    //get the path of SSL files
                    $domainPath = $factory->getDomainPath($single_domain);

                    try {
                        $freessl->revokeCert($single_domain, $domainPath);
                    } catch (\Exception $e) {
                        $logger->error($e->getMessage());
                        $logger->error($e->getTraceAsString());
                    }
                }
            } else {
                $logger->log("'domains_to_revoke_cert' array is empty in the config.php file. Please make entry in it if you want to revoke any SSL certificate.");
            }
        }
    }
}
