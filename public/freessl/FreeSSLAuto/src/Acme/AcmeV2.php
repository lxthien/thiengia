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

use FreeSslDotTech\FreeSSLAuto\DnsApi\DnsApi;
use FreeSslDotTech\FreeSSLAuto\Logger;

class AcmeV2
{
    public $le_live = 'https://acme-v02.api.letsencrypt.org'; //live
    public $le_staging = 'https://acme-staging-v02.api.letsencrypt.org'; //staging
    public $ca;
    public $contact = [];
    public $is_staging;
    public $certificatesBaseDir;

    private $dns_provider = [];
    private $cPanel = [];
    private $server_ip;
    private $challenge;
    private $webRootDir;
    private $logger;
    private $client;
    private $accountKeyPath;
    private $kid;
    private $factory;

    /**
     * Initiates the Let's Encrypt main class.
     *
     * @param string $certificatesBaseDir
     * @param array  $contact
     * @param bool   $is_staging
     * @param array  $dns_provider
     * @param int    $key_size
     * @param array  $cPanel
     */
    public function __construct($certificatesBaseDir, array $contact, $is_staging, array $dns_provider, $key_size, array $cPanel, $server_ip)
    {
        $this->is_staging = $is_staging;
        //choose the appropriate Let's Encrypt API endpoint
        $this->ca = $this->is_staging ? $this->le_staging : $this->le_live;

        $this->contact = $contact;
        $this->logger = new Logger();
        $this->client = new Client($this->ca);

        $this->key_size = $key_size;

        $factory = new Factory($certificatesBaseDir, 2, $this->is_staging);
        $this->factory = $factory;
        $this->certificatesDir = $factory->getCertificatesDir();

        $this->accountKeyPath = $this->certificatesDir.DS.'_account'.DS.'private.pem';
        $this->kid = is_file(\dirname($this->accountKeyPath).DS.'kid.txt') ? file_get_contents(\dirname($this->accountKeyPath).DS.'kid.txt') : '';

        $this->dns_provider = $dns_provider;

        $this->cPanel = $cPanel;
        
        $this->server_ip = $server_ip;

        //inialize the account registered with Let's Encrypt
        if (!is_file($this->accountKeyPath)) {
            // generate and save new private key for the account

            $this->logger->log('Starting new account registration');
            $this->factory->generateKey(\dirname($this->accountKeyPath), $this->key_size);

            $response = $this->postNewReg($this->accountKeyPath);

            if ('valid' === $response['status']) {
                $this->kid = $this->client->getLastLocation();
                $this->logger->log('kid: '.$this->kid);

                //Save the kid in a text file
                file_put_contents(\dirname($this->accountKeyPath).DS.'kid.txt', $this->kid);
                $this->logger->log('Congrats! New account registered successfully.');
            } else {
                $this->logger->log('Sorry, there was a problem to register the account. Let\'s Encrypt server response given below. Please try again.');

                //Delete the key files as the registration failed
                unlink(\dirname($this->accountKeyPath).DS.'private.pem');
                unlink(\dirname($this->accountKeyPath).DS.'public.pem');

                echo '<pre>';
                print_r($response);
                echo '</pre>';
            }
        } else {
            $this->logger->log('Account already registered. Continuing...');
        }
    }

    /**
     * Method to issue SSL certificate from Let's Encrypt.
     *
     * @param array  $domains
     * @param string $webRootDir
     * @param bool   $reuseCsr
     * @param string $countryCode
     * @param string $state
     * @param string $organization
     *
     * @throws \RuntimeException
     */
    public function obtainSsl(array $domains, $webRootDir, $reuseCsr, $countryCode, $state, $organization)
    {
        $this->webRootDir = $webRootDir;
        $this->factory->countryCode = $countryCode;
        $this->factory->state = $state;
        $this->factory->organization = $organization;

        $this->logger->log('Starting SSL certificate generation process with ACME V2');

        $privateAccountKey = $this->factory->readPrivateKey($this->accountKeyPath);
        $accountKeyDetails = openssl_pkey_get_details($privateAccountKey);

        // start domains authentication

        $dns = [];

        foreach ($domains as $domain) {
            if (preg_match_all('~(\*\.)~', $domain) > 1) {
                throw new \RuntimeException('Cannot create orders with multiple wildcards in one domain: '.$domain);
            }
            $dns[] = ['type' => 'dns', 'value' => $domain];
        }

        // 1. getting available authentication options

        $this->logger->log('Requesting challenges for the array of domains');

        $newOrderUrl = $this->client->getUrl('newOrder');

        $urlParts = explode('/', $newOrderUrl);

        $response = $this->signedRequestV2(
            $newOrderUrl,
            ['url' => end($urlParts), 'identifiers' => $dns,]
            );

        $n = 0;
        $number_of_validated_domains = 0;

        foreach ($response['authorizations'] as $authorization) {
            ++$n;

            $this->logger->log("Domain ${n}");

            $response2 = $this->client->get($authorization);

            $domain = $response2['identifier']['value'];

            if ('valid' === $response2['status']) {
                //Domain ownership already verified. Skip the verification process
                $this->logger->log("Domain (${domain}) already verified. Skip the verification process...");
                ++$number_of_validated_domains;
            } else {
                //Start the Domain verification process

                if (empty($response2['challenges'])) {
                    throw new \RuntimeException('Challenge for '.$domain.' is not available. Whole response: '.json_encode($response2));
                }

                //ACME V2 supported challenge types are HTTP-01 and DNS-01.

                $challenge_type_tmp = 'http-01';

                $challenge = array_reduce($response2['challenges'], function ($v, $w) use (&$challenge_type_tmp) {
                    return $v ? $v : ($w['type'] === $challenge_type_tmp ? $w : false);
                });

                if (!$challenge) {
                    //"http-01" is NOT available. Check for "dns-01"

                    $challenge_type_tmp = 'dns-01';

                    $challenge = array_reduce($response2['challenges'], function ($v, $w) use (&$challenge_type_tmp) {
                        return $v ? $v : ($w['type'] === $challenge_type_tmp ? $w : false);
                    });
                    if (!$challenge) {
                        //"dns-01" is NOT available
                        throw new \RuntimeException("Neither 'http-01' nor 'dns-01' challenge for ".$domain.' is available. Whole response: '.json_encode($response2));
                    }
                    //"dns-01" is available
                    $this->logger->log("'http-01' challenge NOT found but 'dns-01' challenge found. So, using 'dns-01' challenge.");
                    $this->challenge = 'dns-01';
                } else {
                    //"http-01" is available
                    $this->logger->log("'http-01' challenge found. So, using 'http-01' challenge.");
                    $this->challenge = 'http-01';
                }

                $this->logger->log('Got challenge token for '.$domain);
                $location = $this->client->getLastLocation();

                $header = [
                    // need to be in precise order!
                    'e' => Base64UrlSafeEncoder::encode($accountKeyDetails['rsa']['e']),
                    'kty' => 'RSA',
                    'n' => Base64UrlSafeEncoder::encode($accountKeyDetails['rsa']['n']),
                ];
                $payload = $challenge['token'].'.'.Base64UrlSafeEncoder::encode(hash('sha256', json_encode($header), true));

                $json_challenge = json_encode($challenge);

                // 2. saving authentication token for web verification (if "http-01")

                if ('http-01' === $this->challenge) {
                    $web_root_dir = \is_array($this->webRootDir) ? $this->webRootDir[$domain] : $this->webRootDir;

                    $this->logger->log('Document root is '.$web_root_dir.' for the domain '.$domain);

                    $directory = $web_root_dir.DS.'.well-known'.DS.'acme-challenge';
                    $tokenPath = $directory.DS.$challenge['token'];

                    if (!file_exists($directory) && !@mkdir($directory, 0755, true)) {
                        throw new \RuntimeException("Couldn't create directory to expose challenge: ${tokenPath}");
                    }

                    $uri = "http://${domain}/.well-known/acme-challenge/".$challenge['token'];

                    if (!file_put_contents($tokenPath, $payload)) {
                        $this->logger->log("Sorry, token for ${domain} was NOT SAVED at ${tokenPath} due to some issue. Please make a directory '.well-known' (with permission 0755) in ".$this->webRootDir.' and try again.');
                    } else {
                        $this->logger->log("Token for ${domain} successfully saved at ${tokenPath} and should be available at ${uri}");
                    }

                    chmod($tokenPath, 0644);

                    // 3. verification process itself

                    //First, verify internally. Then send to LE server to verify

                    $payload_from_uri = @file_get_contents($uri);

                    if ($payload !== $payload_from_uri) {
                        
                        //Now try with HTTPS
                        $uri = "https://${domain}/.well-known/acme-challenge/".$challenge['token'];
                        
                        $payload_from_uri = @file_get_contents($uri);
                        
                        if ($payload !== $payload_from_uri) {
                        
                            $this->logger->log("Payload content (${payload}) does not match the content of ${uri}, which is $payload_from_uri. Either ${domain} is not pointed to this server or unavailable over HTTP due to some server-side issue. Please fix this issue and try again later.");
    
                            continue;
                        
                        }
                    }
                } elseif ('dns-01' === $this->challenge) {
                    $dns_txt_record = Base64UrlSafeEncoder::encode(hash('sha256', $payload, true));

                    $domain = str_replace('*', '', $domain);

                    $registeredDomain = getRegisteredDomain($domain);

                    $sub_domain = '_acme-challenge.'.$domain;

                    $part = str_replace('.'.$registeredDomain, '', $sub_domain);

                    //Domain should be the registered domain, for example, speedupwebsite.info instead of  mobile.estate.speedupwebsite.info and  TXT record name/host: should be  _acme-challenge.mobile.estate instead of   _acme-challenge

                    //DNS API connect

                    //Remove DNS provider records other than $domain
                    $dns_provider = array_reduce($this->dns_provider, function ($v, $w) use (&$registeredDomain) {
                        return $v ? $v : (\in_array($registeredDomain, $w['domains'], true) ? $w : false);
                    });

                    //If no DNS provider found for this domain, use default settings, so that we can manually set DNS TXT record
                    //and sleep execution until the record propagates out
                    //This code enables making entry OPTIONAL if domain registrar/DNS service provider is other than the supported providers by this app

                    if (!$dns_provider) {
                        $dns_provider = [
                            'name' => false,
                            'dns_provider_takes_longer_to_propagate' => true,
                            'domains' => $registeredDomain,
                            'server_ip' => $this->server_ip
                        ];
                    }
                    else{
                        $dns_provider['server_ip'] = $this->server_ip;
                    }

                    $dnsapi = new DnsApi($dns_provider, $this->cPanel);
                    $result = $dnsapi->setTxt($registeredDomain, $part, $dns_txt_record, $this->contact);

                    //DNS TXT record needs time to propagate. So, delay the execution for 5 minutes
                    $this->logger->log('Execution sleeping for 2 minutes.');
                    sleep(120);
                    $this->logger->log('Execution resumed after 2 minutes of sleep.');

                    //Loop to check TXT propagation status
                    if ($dns_provider['dns_provider_takes_longer_to_propagate']) {
                        $this->logger->log('Now check whether the TXT record has been propagated.');

                        $propagated = false;

                        //waiting loop
                        do {
                            $result = dns_get_record($sub_domain, DNS_TXT);

                            //Remove domain records other than $dns_txt_record
                            $txt_details = array_reduce($result, function ($v, $w) use (&$dns_txt_record) {
                                return $v ? $v : ($w['txt'] === $dns_txt_record ? $w : false);
                            });

                            if (null !== $txt_details) {
                                if ($txt_details['txt'] === $dns_txt_record) {
                                    $propagated = true;
                                    $this->logger->log("TXT record ${dns_txt_record} has been propagated successfully.");
                                }
                            }

                            if (!$propagated) {
                                $this->logger->log("TXT record ${dns_txt_record} has NOT been propagated till now, sleeping for 2 minutes.");
                                sleep(120);
                            }
                        } while (!$propagated);
                    } else {
                        //First, verify internally. Then send to LE server to verify

                        $result = dns_get_record($sub_domain, DNS_TXT);

                        //Remove domain records other than $dns_txt_record
                        $txt_details = array_reduce($result, function ($v, $w) use (&$dns_txt_record) {
                            return $v ? $v : ($w['txt'] === $dns_txt_record ? $w : false);
                        });

                        if (null === $txt_details || $txt_details['txt'] !== $dns_txt_record) {
                            $this->logger->log("TXT record ${dns_txt_record} for ${sub_domain} has NOT been propagated till now. Please check whether the TXT record was set correctly. You may also set 'dns_provider_takes_longer_to_propagate' => true and try again.");

                            continue;
                        }
                    }
                }

                $this->logger->log('Sending request to challenge');

                $result['status'] = $response2['status'];

                $ended = !('pending' === $result['status']);

                // waiting loop
                do {
                    if (empty($result['status']) || 'invalid' === $result['status'] || 400 === $result['status']) {
                        throw new \RuntimeException('Verification ended with error: '.json_encode($result));
                    }

                    // send request to challenge
                    $result = $this->signedRequestV2(
                                            $challenge['url'],
                                            [
                                                //"resource" => "challenge",
                                                'type' => $this->challenge,
                                                'keyAuthorization' => $payload,
                                                'token' => $challenge['token'],
                                            ]
                                        );

                    if ('valid' === $result['status']) {
                        $ended = true;

                        if ('http-01' === $this->challenge) {
                            @unlink($tokenPath);
                        }

                        ++$number_of_validated_domains;
                    }

                    if (!$ended) {
                        $this->logger->log('Verification pending, sleeping 1 second');
                        sleep(1);
                    }
                } while (!$ended);

                $this->logger->log("Verification ended with status: ${result['status']} for the domain ".$domain);
            }
        } //end foreach challenges

        //Proceed to issue SSL only if total number of domains = total number of validated domains
        if (\count($response['authorizations']) === $number_of_validated_domains) {
            // requesting certificate

            $domainPath = $this->factory->getDomainPath($domains[0]);

            //Overwrite private key, CSR, certificate files if exists already

            // generate private key for domain
            $this->factory->generateKey($domainPath, $this->key_size);

            // load domain key
            $privateDomainKey = $this->factory->readPrivateKey($domainPath.DS.'private.pem');

            $csr = $reuseCsr && is_file($domainPath.DS.'csr_last.csr') ?
                    $this->factory->getCsrContent($domainPath.DS.'csr_last.csr') :
                        $this->factory->generateCSR($privateDomainKey, $domains, $this->key_size);

            // request certificates creation
            $result = $this->signedRequestV2(
            $response['finalize'],
            ['csr' => $csr]
            );

            if (200 !== $this->client->getLastCode()) {
                throw new \RuntimeException('Invalid response code: '.$this->client->getLastCode().', '.json_encode($result));
            }

            $location = $result['certificate'];

            // waiting loop
            $certificates = [];
            while (1) {
                //$this->client->getLastLinks();

                $result = $this->client->get($location);

                $this->logger->log('Location value: '.$location);

                if (202 === $this->client->getLastCode()) {
                    $this->logger->log('Certificate generation pending, sleeping 1 second');
                    sleep(1);
                } elseif (200 === $this->client->getLastCode()) {
                    $this->logger->log('Got certificate! YAY!');

                    $certificates = explode("\n\n", $result);

                    break;
                } else {
                    throw new \RuntimeException("Can't get certificate: HTTP code ".$this->client->getLastCode());
                }
            }

            if (empty($certificates)) {
                throw new \RuntimeException('No certificates generated');
            }

            $this->logger->log('Saving Certificate (CRT) certificate.pem');
            file_put_contents($domainPath.DS.'certificate.pem', $certificates[0]);

            $this->logger->log('Saving (CABUNDLE) cabundle.pem');
            file_put_contents($domainPath.DS.'cabundle.pem', $certificates[1]);

            $this->logger->log('Saving fullchain.pem');
            file_put_contents($domainPath.DS.'fullchain.pem', $result);

            $this->logger->log("Done!!!! Let's Encrypt ACME V2 SSL certificate successfully issued!!");

            return true;
        }
        //SSL certificate can't be issued
        $this->logger->log("Sorry, SSL certificate can't be issued to ".$domains[0].'. '.(\count($response['authorizations']) - $number_of_validated_domains).' domains was not validated.');

        return false;
    }

    /**
     * Method to revoke SSL certifiate.
     *
     * @param string $domain
     * @param string $domainPath
     */
    public function revokeCert($domain, $domainPath)
    {
        //Try to revoke only if the SSL files exist
        if (is_dir($domainPath)) {
            $cert_pem = file_get_contents($domainPath.'certificate.pem');

            $begin = $this->factory->get_cert_begin($cert_pem);
            $end = $this->factory->get_cert_end($cert_pem);

            $cert_pem = substr($cert_pem, strpos($cert_pem, $begin) + \strlen($begin));
            $cert_pem = substr($cert_pem, 0, strpos($cert_pem, $end));

            $cert_decoded = base64_decode($cert_pem, true);

            //Get the API URL for revoke cert
            $revokeCertUrl = $this->client->getUrl('revokeCert');

            $urlParts = explode('/', $revokeCertUrl);

            //request to revoke certificate
            $result = $this->signedRequest(
                            $revokeCertUrl,
                            ['url' => end($urlParts), 'certificate' => Base64UrlSafeEncoder::encode($cert_decoded), 'reason' => 1],
                            $domainPath.DS.'private.pem'
                        );

            if (200 === $this->client->getLastCode()) {
                $this->logger->log('Certificate revocation for '.$domain.' successful with ACME V2!!');

                //Delete the SSL files and the directory
                $this->factory->deleteDir($domainPath);
            } else {
                $this->logger->log('Sorry, there was a problem to revoke the SSL certificate for '.$domain.' with ACME V2');
            }
        } else {
            //No SSL file is there
            $this->logger->log('No SSL certificate files found for '.$domain.' with ACME V2. The SSL certificate may be revoked already.');
        }
    }

    /**
     * Method to change Let's Encrypt account key / account key roll-over.
     *
     * @throws \RuntimeException
     */
    public function keyChange()
    {
        // generate and save new key pair for the account

        $new_key_path = \dirname($this->accountKeyPath).DS.'tmp';

        //Generate new key if not exist
        if (!is_file($new_key_path.DS.'private.pem') && !is_file($new_key_path.DS.'public.pem')) {
            $this->factory->generateKey($new_key_path, $this->key_size);
        }

        $privateKey_new = $this->factory->readPrivateKey($new_key_path.DS.'private.pem');

        $details_new = openssl_pkey_get_details($privateKey_new);

        $jwk_new = [
            'e' => Base64UrlSafeEncoder::encode($details_new['rsa']['e']),
            'kty' => 'RSA',
            'n' => Base64UrlSafeEncoder::encode($details_new['rsa']['n']),
        ];

        //old key / current account key
        $privateKey_old = $this->factory->readPrivateKey($this->accountKeyPath);

        $details_old = openssl_pkey_get_details($privateKey_old);

        $jwk_old = [
            'e' => Base64UrlSafeEncoder::encode($details_old['rsa']['e']),
            'kty' => 'RSA',
            'n' => Base64UrlSafeEncoder::encode($details_old['rsa']['n']),
        ];

        //Get the API URL for keyChange
        $keyChangeUrl = $this->client->getUrl('keyChange');

        $urlParts = explode('/', $keyChangeUrl);

        $protected_inner = [
            'alg' => 'RS256',
            'jwk' => $jwk_new,
            'url' => $keyChangeUrl,
        ];

        $protected_inner64 = Base64UrlSafeEncoder::encode(json_encode($protected_inner));

        //The URL for account being modified.

        $account = $this->kid;

        $payload_inner = [
            'account' => $account,
            'newKey' => $jwk_new,
            'oldKey' => $jwk_old,
        ];

        $payload_inner64 = Base64UrlSafeEncoder::encode(str_replace('\\/', '/', json_encode($payload_inner)));

        openssl_sign($protected_inner64.'.'.$payload_inner64, $signature_inner_New, $privateKey_new, 'SHA256');

        $signature_inner_New64 = Base64UrlSafeEncoder::encode($signature_inner_New);

        $payload_outer = [
            'url' => end($urlParts),
            'protected' => $protected_inner64,
            'payload' => $payload_inner64,
            'signature' => $signature_inner_New64,
        ];

        // request certificates creation
        $result = $this->signedRequestV2($keyChangeUrl, $payload_outer);

        if (200 === $this->client->getLastCode()) {
            $this->logger->log('Account Key Change (Roll-over) was successful with ACME V2.');

            // Delete old key and rename new account key

            unlink(\dirname($this->accountKeyPath).DS.'private.pem');
            unlink(\dirname($this->accountKeyPath).DS.'public.pem');

            if (!copy($new_key_path.DS.'private.pem', \dirname($this->accountKeyPath).DS.'private.pem')) {
                $this->logger->log('Failed to move Private key from temporary directory to the default location.');
            } else {
                unlink($new_key_path.DS.'private.pem');
            }

            if (!copy($new_key_path.DS.'public.pem', \dirname($this->accountKeyPath).DS.'public.pem')) {
                $this->logger->log('Failed to move Public key from temporary directory to the default location.');
            } else {
                unlink($new_key_path.DS.'public.pem');
            }
        } else {
            throw new \RuntimeException('Invalid response code: '.$this->client->getLastCode().', '.json_encode($result));
        }
    }

    private function postNewReg($key_path)
    {
        $this->logger->log('Sending registration to letsencrypt server');

        $data = [
            'termsOfServiceAgreed' => true,
        ];

        //Add 'mailto:' with email id
        if ($this->contact) {
            $contact_array = [];
            foreach ($this->contact as $contact) {
                $contact_array[] = 'mailto:'.$contact;
            }

            $data['contact'] = $contact_array;
        }

        $newAccountUrl = $this->client->getUrl('newAccount');

        return $this->signedRequest(
            $newAccountUrl,
            $data,
            $key_path
            );
    }

    private function signedRequest($uri, array $payload, $key_path)
    {
        $privateKey = $this->factory->readPrivateKey($key_path);
        $details = openssl_pkey_get_details($privateKey);

        $header = [
            'alg' => 'RS256',
            'jwk' => [
                'kty' => 'RSA',
                'n' => Base64UrlSafeEncoder::encode($details['rsa']['n']),
                'e' => Base64UrlSafeEncoder::encode($details['rsa']['e']),
            ],
        ];

        $protected = $header;
        $protected['nonce'] = $this->getLastNonce();
        $protected['url'] = $uri;

        $payload64 = Base64UrlSafeEncoder::encode(str_replace('\\/', '/', json_encode($payload)));

        $protected64 = Base64UrlSafeEncoder::encode(json_encode($protected));

        openssl_sign($protected64.'.'.$payload64, $signed, $privateKey, 'SHA256');

        $signed64 = Base64UrlSafeEncoder::encode($signed);

        $data = [
            //'header' => $header,
            'protected' => $protected64,
            'payload' => $payload64,
            'signature' => $signed64,
        ];

        $this->logger->log("Sending signed request to ${uri}");

        return $this->client->post($uri, json_encode($data));
    }

    private function signedRequestV2($uri, array $payload)
    {
        $privateKey = $this->factory->readPrivateKey($this->accountKeyPath);
        $details = openssl_pkey_get_details($privateKey);

        $header = [
            'alg' => 'RS256',
            /* "jwk" => array(
             "kty" => "RSA",
                "n" => Base64UrlSafeEncoder::encode($details["rsa"]["n"]),
                "e" => Base64UrlSafeEncoder::encode($details["rsa"]["e"]),
            ), */
            'kid' => $this->kid,
        ];

        $protected = $header;
        $protected['nonce'] = $this->getLastNonce();
        $protected['url'] = $uri;

        $payload64 = Base64UrlSafeEncoder::encode(str_replace('\\/', '/', json_encode($payload)));

        $protected64 = Base64UrlSafeEncoder::encode(json_encode($protected));

        openssl_sign($protected64.'.'.$payload64, $signed, $privateKey, 'SHA256');

        $signed64 = Base64UrlSafeEncoder::encode($signed);

        $data = [
            //'header' => $header,
            'protected' => $protected64,
            'payload' => $payload64,
            'signature' => $signed64,
        ];

        $this->logger->log("Sending signed request to ${uri}");

        return $this->client->post($uri, json_encode($data));
    }

    private function getLastNonce()
    {
        if (preg_match('~Replay\-Nonce: (.+)~i', $this->client->lastHeader, $matches)) {
            return trim($matches[1]);
        }

        $newNonceUrl = $this->client->getUrl('newNonce');

        $this->client->curl('HEAD', $newNonceUrl);

        return $this->getLastNonce();
    }
}
