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

use FreeSslDotTech\FreeSSLAuto\Logger;

class AcmeV1
{
    public $le_live = 'https://acme-v01.api.letsencrypt.org'; //live
    public $le_staging = 'https://acme-staging.api.letsencrypt.org'; //staging
    public $ca;
    public $contact = [];
    public $is_staging;
    public $license;
    public $certificatesBaseDir;
    public $key_size;

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
     * @param string $challenge
     * @param int    $key_size
     */
    public function __construct($certificatesBaseDir, array $contact, $is_staging, $challenge, $key_size)
    {
        $this->is_staging = $is_staging;
        //choose the appropriate Let's Encrypt API endpoint
        $this->ca = ($this->is_staging ? $this->le_staging : $this->le_live);

        $this->challenge = $challenge;

        $this->contact = $contact;
        $this->logger = new Logger();
        $this->client = new Client($this->ca);

        $this->key_size = $key_size;

        $factory = new Factory($certificatesBaseDir, 1, $this->is_staging);
        $this->factory = $factory;
        $this->certificatesDir = $factory->getCertificatesDir();

        $this->accountKeyPath = $this->certificatesDir.DS.'_account'.DS.'private.pem';
        $this->kid = is_file(\dirname($this->accountKeyPath).DS.'kid.txt') ? file_get_contents(\dirname($this->accountKeyPath).DS.'kid.txt') : '';

        //Get terms-of-service URL
        $meta = $this->client->getUrl('meta');
        $this->license = $meta['terms-of-service'];

        //inialize the account registered with Let's Encrypt
        if (!is_file($this->accountKeyPath)) {
            // generate and save new private key for account

            $this->logger->log('Starting new account registration');
            $this->factory->generateKey(\dirname($this->accountKeyPath), $this->key_size);
            $response = $this->postNewReg();

            $this->kid = $this->client->getLastLocation();
            $this->logger->log('kid: '.$this->kid);

            //Save kid in a txt file
            file_put_contents(\dirname($this->accountKeyPath).DS.'kid.txt', $this->kid);

            if ('valid' === $response['status']) {
                $this->logger->log('Congrats! New account registered successfully.');
            } else {
                $this->logger->log('Sorry, there was a problem to register the account. Please try again.');

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
     *
     * @return bool
     */
    public function obtainSsl(array $domains, $webRootDir, $reuseCsr, $countryCode, $state, $organization)
    {
        $this->webRootDir = $webRootDir;
        $this->factory->countryCode = $countryCode;
        $this->factory->state = $state;
        $this->factory->organization = $organization;

        $this->logger->log('Starting SSL certificate generation process with ACME V1');

        $privateAccountKey = $this->factory->readPrivateKey($this->accountKeyPath);
        $accountKeyDetails = openssl_pkey_get_details($privateAccountKey);

        // start domains authentication

        $number_of_validated_domains = 0;

        foreach ($domains as $domain) {
            // 1. getting available authentication options

            $this->logger->log("Requesting challenge for ${domain}");

            $new_authz_url = $this->client->getUrl('new-authz');

            $urlParts = explode('/', $new_authz_url);

            $response = $this->signedRequest(
                $new_authz_url,
                ['resource' => end($urlParts), 'identifier' => ['type' => $this->challenge, 'value' => $domain]]
                );

            if (empty($response['challenges'])) {
                throw new \RuntimeException("HTTP Challenge for ${domain} is not available. Whole response: ".json_encode($response));
            }

            $self = $this;
            $challenge = array_reduce($response['challenges'], function ($v, $w) use (&$self) {
                return $v ? $v : ($w['type'] === $self->challenge ? $w : false);
            });
            if (!$challenge) {
                throw new \RuntimeException("HTTP Challenge for ${domain} is not available. Whole response: ".json_encode($response));
            }

            $this->logger->log("Got challenge token for ${domain}");
            $location = $this->client->getLastLocation();

            if ('valid' === $challenge['status']) {
                //Domain ownership already verified. Skip the verification process
                $this->logger->log("Domain (${domain}) already verified. Skip the verification process...");
                ++$number_of_validated_domains;
            } else {
                // 2. saving authentication token for verification

                $directory = $this->webRootDir.DS.'.well-known'.DS.'acme-challenge';
                $tokenPath = $directory.DS.$challenge['token'];

                if (!file_exists($directory) && !@mkdir($directory, 0755, true)) {
                    throw new \RuntimeException("Couldn't create directory to expose challenge: ${tokenPath}");
                }

                $header = [
                    // need to be in precise order!
                    'e' => Base64UrlSafeEncoder::encode($accountKeyDetails['rsa']['e']),
                    'kty' => 'RSA',
                    'n' => Base64UrlSafeEncoder::encode($accountKeyDetails['rsa']['n']),
                ];
                $payload = $challenge['token'].'.'.Base64UrlSafeEncoder::encode(hash('sha256', json_encode($header), true));

                $uri = "http://${domain}/.well-known/acme-challenge/${challenge['token']}";

                if (!file_put_contents($tokenPath, $payload)) {
                    $this->logger->log("Sorry, token for ${domain} was NOT SAVED at ${tokenPath} due to some issue. Please make a directory '.well-known' (with permission 0755) in ".$this->webRootDir.' and try again.');
                } else {
                    $this->logger->log("Token for ${domain} successfully saved at ${tokenPath} and should be available at ${uri}");
                }

                chmod($tokenPath, 0644);

                // 3. verification process

                // 3. a. First, verify internally. Then send to LE server to verify

                $payload_from_uri = @file_get_contents($uri);

                if ($payload === $payload_from_uri) {
                    $this->logger->log('Sending request to challenge');

                    // send request to challenge
                    $result = $this->signedRequest(
                                        $challenge['uri'],
                                        [
                                            'resource' => 'challenge',
                                            'type' => $this->challenge,
                                            'keyAuthorization' => $payload,
                                            'token' => $challenge['token'],
                                        ]
                                        );

                    // waiting loop
                    do {
                        if (empty($result['status']) || 'invalid' === $result['status']) {
                            throw new \RuntimeException('Verification ended with error: '.json_encode($result));
                        }
                        $ended = !('pending' === $result['status']);

                        if (!$ended) {
                            $this->logger->log('Verification pending, sleeping 1 second');
                            sleep(1);
                        }

                        $result = $this->client->get($location);
                    } while (!$ended);

                    $this->logger->log("Verification ended with status: ${result['status']}");

                    if ('valid' === $result['status']) {
                        @unlink($tokenPath);
                        ++$number_of_validated_domains;
                    }
                } else {
                    $this->logger->log("Payload content (${payload}) does not match the content of ${uri}. Either ${domain} is not pointed to this server or unavailable over HTTP due to some server-side issue. Please fix this issue and try again later.");
                }
            }
        }

        //Proceed to issue SSL only if total number of domains = total number of validated domains
        if (\count($domains) === $number_of_validated_domains) {
            // requesting certificate
            // ----------------------
            $domainPath = $this->factory->getDomainPath(reset($domains));

            // Overwrite private key, CSR, certificate files if exists already
            // generate private key for domain
            $this->factory->generateKey($domainPath, $this->key_size);

            // load domain key
            $privateDomainKey = $this->factory->readPrivateKey($domainPath.DS.'private.pem');

            $this->client->getLastLinks();

            $csr = $reuseCsr && is_file($domainPath.DS.'csr_last.csr') ?
                $this->factory->getCsrContent($domainPath.DS.'csr_last.csr') :
                    $this->factory->generateCSR($privateDomainKey, $domains, $this->key_size);

            $new_cert_url = $this->client->getUrl('new-cert');

            $urlParts = explode('/', $new_cert_url);

            // request certificates creation
            $result = $this->signedRequest(
                                $new_cert_url,
                                ['resource' => end($urlParts), 'csr' => $csr]
                                );
            if (201 !== $this->client->getLastCode()) {
                throw new \RuntimeException('Invalid response code: '.$this->client->getLastCode().', '.json_encode($result));
            }
            $location = $this->client->getLastLocation();

            // waiting loop
            $certificates = [];
            while (1) {
                $this->client->getLastLinks();

                $result = $this->client->get($location);

                $this->logger->log('Location value: '.$location);

                if (202 === $this->client->getLastCode()) {
                    $this->logger->log('Certificate generation pending, sleeping 1 second');
                    sleep(1);
                } elseif (200 === $this->client->getLastCode()) {
                    $this->logger->log('Got certificate! YAY!');
                    $certificates[] = $this->factory->parsePemFromBody($result);

                    foreach ($this->client->getLastLinks() as $link) {
                        $this->logger->log("Requesting chained cert at ${link}");
                        $result = $this->client->get($link);
                        $certificates[] = $this->factory->parsePemFromBody($result);
                    }

                    break;
                } else {
                    throw new \RuntimeException("Can't get certificate: HTTP code ".$this->client->getLastCode());
                }
            }

            if (empty($certificates)) {
                throw new \RuntimeException('No certificates generated');
            }

            $this->logger->log('Saving fullchain.pem');
            file_put_contents($domainPath.DS.'fullchain.pem', implode("\n", $certificates));

            $this->logger->log('Saving Certificate (CRT) certificate.pem');
            file_put_contents($domainPath.DS.'certificate.pem', array_shift($certificates));

            $this->logger->log('Saving (CABUNDLE) cabundle.pem');
            file_put_contents($domainPath.DS.'cabundle.pem', implode("\n", $certificates));

            $this->logger->log("Done!!!! Let's Encrypt ACME V1 SSL Certificate successfully issued!!");

            return true;
        }
        //SSL certificate can't be issued
        $this->logger->log("Sorry, SSL certificate can't be issued to ".$domains[0].'. '.(\count($domains) - $number_of_validated_domains).' domains was not validated.');

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

            $revoke_cert_url = $this->client->getUrl('revoke-cert');

            $urlParts = explode('/', $revoke_cert_url);

            //request certificates creation
            $result = $this->signedRequest(
                $revoke_cert_url,
                ['resource' => end($urlParts), 'certificate' => Base64UrlSafeEncoder::encode($cert_decoded), 'reason' => 1]
                );

            if (200 === $this->client->getLastCode()) {
                $this->logger->log("Certificate revocation for ${domain}. successful with Let's Encrypt ACME V1!!");

                //Delete the SSL files and the directory
                $this->factory->deleteDir($domainPath);
            } else {
                $this->logger->log('Sorry, there was a problem to revoke the SSL certificate for '.$domain.' with ACME V1');
            }
        } else {
            //No SSL file is there
            $this->logger->log('No SSL certificate files found for '.$domain.' with ACME V1. The SSL certificate may be revoked already.');
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

        $protected_inner = [
            'alg' => 'RS256',
            'jwk' => $jwk_new,
        ];

        $protected_inner64 = Base64UrlSafeEncoder::encode(json_encode($protected_inner));

        /*The URL of the account being modified. The content of
         * this field is the string value provided in the Location header field
         * in response to the new-account request that created the account.
         */

        $account = $this->kid;

        $payload_inner = [
            'account' => $account,
            'newKey' => $jwk_new,
        ];

        $payload_inner64 = Base64UrlSafeEncoder::encode(str_replace('\\/', '/', json_encode($payload_inner)));

        openssl_sign($protected_inner64.'.'.$payload_inner64, $signature_inner_new, $privateKey_new, 'SHA256');

        $signature_inner_new64 = Base64UrlSafeEncoder::encode($signature_inner_new);

        $key_change_url = $this->client->getUrl('key-change');

        $urlParts = explode('/', $key_change_url);

        $payload_outer = [
            'resource' => end($urlParts),
            'protected' => $protected_inner64,
            'payload' => $payload_inner64,
            'signature' => $signature_inner_new64,
        ];

        // request certificates creation
        $result = $this->signedRequest($key_change_url, $payload_outer);

        if (200 === $this->client->getLastCode()) {
            $this->logger->log('Account Key Change (Roll-over) was successful with ACME V1.');

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

    private function postNewReg()
    {
        $this->logger->log('Sending registration to letsencrypt server');

        $new_reg_url = $this->client->getUrl('new-reg');

        $urlParts = explode('/', $new_reg_url);

        $data = ['resource' => end($urlParts), 'agreement' => $this->license];
        if (!$this->contact) {
            $data['contact'] = $this->contact;
        }

        return $this->signedRequest(
            $new_reg_url,
            $data
            );
    }

    private function signedRequest($uri, array $payload)
    {
        $privateKey = $this->factory->readPrivateKey($this->accountKeyPath);
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
        $protected['nonce'] = $this->client->getLastNonce();
        $protected['url'] = $uri;

        $payload64 = Base64UrlSafeEncoder::encode(str_replace('\\/', '/', json_encode($payload)));

        $protected64 = Base64UrlSafeEncoder::encode(json_encode($protected));

        openssl_sign($protected64.'.'.$payload64, $signed, $privateKey, 'SHA256');

        $signed64 = Base64UrlSafeEncoder::encode($signed);

        $data = [
            'protected' => $protected64,
            'payload' => $payload64,
            'signature' => $signed64,
        ];

        $this->logger->log("Sending signed request to ${uri}");

        return $this->client->post($uri, json_encode($data));
    }
}
