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

class Email
{
    /**
     * Send an email.
     *
     * @param string $admin_email
     * @param array  $domains_array
     * @param bool   $ssl_installation_feature
     * @param bool   $ssl_installation_status
     * @param string $domainPath
     * @param string $homedir
     */
    public function sendEmail($admin_email, $domains_array, $ssl_installation_feature, $ssl_installation_status, $domainPath, $homedir)
    {
        $logger = new Logger();
        $domain = \is_array($domains_array) ? reset($domains_array) : $domains_array;

        $certificate = $domainPath.'certificate.pem';
        $private_key = $domainPath.'private.pem';
        $ca_bundle = $domainPath.'cabundle.pem';

        $cert_array = openssl_x509_parse(openssl_x509_read(file_get_contents($certificate)));
        $date = new DateTime('@'.$cert_array['validTo_time_t']);
        $expiry_date = $date->format('Y-m-d H:i:s').' '.date_default_timezone_get();
        $date = new DateTime();
        $now = $date->format('Y-m-d H:i:s').' '.date_default_timezone_get();

        $subjectAltName = str_replace('DNS:', '', $cert_array['extensions']['subjectAltName']);

        $issuerShort = $cert_array['issuer']['O'];
        $issuerFull = $cert_array['issuer']['CN'];

        //Email body
        $body = '<html><body>';
        $body_log = [];

        if ($ssl_installation_feature) {
            //SSL installation feature exists
            //Install SSL
            if ($ssl_installation_status) {
                //Send confirmation email to admin
                $subject = "FreeSSL.tech Auto installed ${issuerShort} SSL on ".$domain;
                $body .= "<h2><a href='https://freessl.tech'>FreeSSL.tech Auto</a> installed ${issuerShort} SSL on <a href='https://".$domain."'>".$domain.'</a></h2><br />';
                $body .= "Congrats! FreeSSL.tech Auto has successfully installed the ${issuerShort} SSL for <a href='https://".$domain."'>".$domain.'</a>. <br /><br />
                                <strong>No further action required by you.</strong><br /><br />
                                For your information, the SSL files have been saved at the locations given below (web hosting log in required to access).<br />';

                $body_log[] = 'No further action required by you';
                $body_log[] = 'The SSL files have been saved at the locations given below (web hosting log in required to access)';
            } else {
                $subject = "FreeSSL.tech Auto generated ${issuerShort} SSL for ".$domain;
                $body .= "<h2><a href='https://freessl.tech'>FreeSSL.tech Auto</a> generated ${issuerShort} SSL for ".$domain.'</h2><br />';
                $body .= "Congrats! FreeSSL.tech Auto has successfully generated the ${issuerShort} SSL for ".$domain.'. <br /><br />
                                <strong>But there was a problem installing the SSL.</strong><br /><br />
                                Please find the SSL files at the locations given below (web hosting log in required to access) and install SSL manually.<br />';

                $body_log[] = 'But there was a problem installing the SSL';
                $body_log[] = 'Please find the SSL files at the locations given below (web hosting log in required to access) and install SSL manually';
            }
        } else {
            //Send email with paths of SSL and CA bundle,
            //but do not attach private key. Send the location instead
            $subject = "FreeSSL.tech Auto generated ${issuerShort} SSL for ".$domain;
            $body .= "<h2><a href='https://freessl.tech'>FreeSSL.tech Auto</a> generated ${issuerShort} SSL for ".$domain.'</h2><br />';
            $body .= "Congrats! FreeSSL.tech Auto has successfully generated the ${issuerShort} SSL for ".$domain.". <br /><br />
                            <strong>But the SSL was not installed automatically, because you don't have SSL installation feature.</strong><br /><br />
                            Please find the SSL files at the locations given below (web hosting log in required to access) and install SSL manually with the help of your web hosting service provider. <em>It is recommended not to download the SSL files for security reason. Please copy the SSL locations and send the text to your web host.</em><br />";

            $body_log[] = "But the SSL was not installed automatically, because you don't have SSL installation feature";
            $body_log[] = 'Please find the SSL files at the locations given below (web hosting log in required to access) and install SSL manually with the help of your web hosting service provider. It is recommended not to download the SSL files for security reason. Please copy the SSL locations and send the text to your web host.';
        }
        //Common element of the email

        $body .= "<pre>
        Certificate (CRT): ${certificate}<br />
        Private Key (KEY): ${private_key}<br />
        Certificate Authority Bundle (CABUNDLE): ${ca_bundle}</pre><br />
        This SSL certificate has been issued for the following domain names:<br /><pre>
        ".$subjectAltName."<br /></pre>
        Expiry date: ${expiry_date}<br /><br />
        Issuer: ${issuerFull}<br /><br />
        This is a system generated email on ${now}.<br />
        Do not reply to this automated email.<br /><br />
        --------------<br />
        FreeSSL.tech Auto<br />
        Powered by <a href='https://letsencrypt.org'>Let's Encrypt</a>, <a href='https://speedify.tech'>SpeedUpWebsite.info</a> and <a href='https://getwww.me'>GetWWW.me</a><br /><br />
        </body>
        </html>";

        $body_log[] = "Certificate (CRT): ${certificate}";
        $body_log[] = "Private Key (KEY): ${private_key}";
        $body_log[] = "Certificate Authority Bundle (CABUNDLE): ${ca_bundle}";
        $body_log[] = "Expiry date: ${expiry_date}";

        $logger->log($subject);

        foreach ($body_log as $bl) {
            $logger->log($bl);
        }

        //Send email to all admin email id
        $to = implode(',', $admin_email);

        // Set content-type header
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=iso-8859-1';
        $headers[] = 'From:noreply@'.$domain;

        // Send the email
        if (mail($to, $subject, $body, implode("\r\n", $headers))) {
            $logger->log('Congratulations, email to admin was sent successfully!');
        } else {
            $logger->log('Sorry, there was an issue sending the email.');
        }
    }
}
