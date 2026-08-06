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
 * @since      Available since Release 1.0.0
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

    // Initialize the session
    if (!session_id()) {
        session_start();
    }

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        die("Unfortunately, this app is not compatible with Windows. It works on Linux hosting.");
    }

    //Display all error
    error_reporting(E_ALL);

    //Set display_errors ON if it is OFF by default
    if (!ini_get('display_errors')) {
        ini_set('display_errors', '1');
    }

    if (!defined('PHP_VERSION_ID') || PHP_VERSION_ID < 50400) {
        die("You need at least PHP 5.4.0\n");
    }

    if (!extension_loaded('openssl')) {
        die("You need OpenSSL extension enabled with PHP\n");
    }

    if (!extension_loaded('curl')) {
        die("You need Curl extension enabled with PHP\n");
    }

    if (!extension_loaded('mysqli')) {
        die("You need Mysqli extension enabled with PHP\n");
    }
    
    if (!ini_get('allow_url_fopen')) {
        die("You need to set PHP directive allow_url_fopen = On. Please contact your web hosting company for help.");
    }
    
    // Define Directory Separator to make the default DIRECTORY_SEPARATOR short
    define('DS', DIRECTORY_SEPARATOR);

?>

<!DOCTYPE html>
    <html lang="en">
    
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Quick SSL - FreeSSL.tech Auto">
    <meta name="author" content="Speedify.tech">
        
    <title>FreeSSL.tech Auto</title>
    
    <!-- Bootstrap CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <!-- bootstrap theme -->
    <link href="css/bootstrap-theme.css" rel="stylesheet">
    <!--external css-->
    <!-- font icon -->
    <link href="css/elegant-icons-style.css" rel="stylesheet" />
    <link href="css/font-awesome-5.1.0.min.css" rel="stylesheet" />    
    <!-- owl carousel -->
    <link rel="stylesheet" href="css/owl.carousel.css" type="text/css">
    <link href="css/jquery-jvectormap-1.2.2.css" rel="stylesheet">
    <!-- Custom styles -->    
    <link href="css/widgets.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="css/style-responsive.css" rel="stylesheet" />    
    <link href="css/jquery-ui-1.10.4.min.css" rel="stylesheet">
    
    </head>
    
    <body>
    <!-- container section start -->
    <section id="container" class="">
    
    <!--main content start-->
  <section id="">
    <section class="wrapper">
        
    <div class="row">
    <div class="col-lg-12">
    <section class="panel">
    
    <div class="panel-body">

<?php

    $config_file_path = __DIR__.DS.'config'.DS.'config.php';

    // Check if wp-config.php has been created
    if (!file_exists($config_file_path)) {
        //header("location: install.php");
    } else {
        //Include config file
        require_once $config_file_path;
    }

    // Composer autoloading
    include __DIR__.DS.'vendor'.DS.'autoload.php';

    //define ISSUE_SSL true
    define('ISSUE_SSL', true);
    //other constants should be false
    define('KEY_CHANGE', false);
    define('REVOKE_CERT', false);

    use FreeSslDotTech\FreeSSLAuto\Admin\Factory;
    use FreeSslDotTech\FreeSSLAuto\FreeSSLAuto;

    $factory = new Factory();

    $displayForm = true;

 //Processing form data when form is submitted
    if ('POST' === $_SERVER['REQUEST_METHOD']) {
        $email_err = $csrf_err = null;

        $token = trim($_POST['csrf']);
        $token = filter_var($token, FILTER_SANITIZE_STRING);

        $csrfVerified = $factory->verifyCsrfToken('tmp-ssl', $token);

        if (!$csrfVerified) {
            $csrf_err = "Sorry! This form's security token expired. Please submit again. Don't wait more than 15 minutes after loading the form.";
        }

        $admin_email = [];

        //Validate email by pattern
        if (filter_var($_POST['admin_email'], FILTER_VALIDATE_EMAIL)) {
            //email is valid
            $admin_email[] = filter_var(trim($_POST['admin_email']), FILTER_SANITIZE_EMAIL);
        } else {
            $email_err = 'Please enter a valid email.';
            $admin_email[] = trim($_POST['admin_email']);
        }

        $agree_to_le_terms = $factory->sanitize_string($_POST['agree_to_le_terms']);

        $agree_to_freessl_tech_tos_pp = $factory->sanitize_string($_POST['agree_to_freessl_tech_tos_pp']);

        //Proceed only if there is no error
        if ($csrfVerified && empty($email_err)) {
            $displayForm = false;

            if (false === strpos($_SERVER['SERVER_NAME'], 'www.')) {
                $domain = $_SERVER['SERVER_NAME'];

                $serveralias = '';
            } else {
                $domain = str_replace('www.', '', $_SERVER['SERVER_NAME']);
                $serveralias = $_SERVER['SERVER_NAME'];
            }

            $appConfigTmp = [
                //Acme version
                //@value integer
                'acme_version' => 2,

                //Don't use wildcard SSL
                //@value boolean
                'use_wildcard' => false,

                //We need real SSL
                //@value boolean
                'is_staging' => false,

                //Admin email
                //@value array
                'admin_email' => $admin_email,

                //Country code of the admin
                //2 DIGIT ISO code
                //@value string
                'country_code' => '',

                //State of the admin
                //@value string
                'state' => '',

                //Organization of the admin
                //@value string
                'organization' => '',

                //Home directory of this server.
                //@value string
                'homedir' => isset($_SERVER['HOME']) ? $_SERVER['HOME'] : __DIR__,

                //Certificate directory
                //@value string
                'certificate_directory' => 'tmp-cert',

                //How many days before the expiry date you want to renew the SSL?
                //@value numeric
                'days_before_expiry_to_renew_ssl' => 30,

                //Is your web hosting control panel cPanel? For this case we set it to false
                //@value boolean
                'is_cpanel' => false,

                //Are you using cloudflare or any other CDN?
                //@value boolean
                'using_cdn' => true,

                //Key size of the SSL
                //@value integer
                'key_size' => 2048,
                
                'server_ip' => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null,

                //Set this domain details below
                //@value array
                'all_domains' => [
                    [
                        'domain' => $domain,
                        'serveralias' => $serveralias,
                        'documentroot' => $_SERVER['DOCUMENT_ROOT'],
                    ],
                ],

                // Exclution list
                //@value array
                'domains_to_exclude' => [],

                /* DNS provider details - required only if you want to issue Wildcard SSL.
         *
         * Please remember to set 'acme_version' => 2 and 'use_wildcard' => true as well
         */
                //@value array
                'dns_provider' => [
                    [
                        'name' => false, //Supported providers are GoDaddy, Namecheap, Cloudflare (please write as is)
                        //Write false if your DNS provider if not supported. In that case, you'll need to add the DNS TXT record manually. You'll receive the TXT record details by automated email. PLEASE NOTE THAT in such case you must set 'dns_provider_takes_longer_to_propagate' => true  //@value string or boolean
                        'api_identifier' => '', //API Key or email id or user name   //@value string
                        'api_credential' => '', //API secret. Or key, if api_identifier is an email id   //@value string
                        'dns_provider_takes_longer_to_propagate' => true, //By default this app waits 2 minutes before attempt to verify DNS-01 challenge. But if your DNS provider takes more time to propagate out, set this true. Please keep in mind, depending on the propagation status of your DNS server, this settings may put the app waiting for hours.  //@value boolean
                        'domains' => [], //Domains registered with this DNS provider   //@value array
                        'server_ip' => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null
                    ],
                ],
            ]; ?>
<div class="panel panel-primary">
    <div class="panel-heading">Generating SSL for <strong><?php echo $_SERVER['SERVER_NAME']; ?></strong>. Please wait...</div>    
    </div>
    
<?php

    $freeSsl = new FreeSSLAuto($appConfigTmp);

            //Run the App
            $freeSsl->run(); ?>    

</div>
  </section>
          </div>
        </div>
        <!-- page end-->
        
        </section>
        </section>
        </section>
        </body>
        </html>    
    
<?php
        }
    }

 if ($displayForm) {
     ?> 
   
    <div class="panel panel-primary">
    <div class="panel-heading">Generate SSL for <strong><?php echo $_SERVER['SERVER_NAME']; ?></strong> in less than 30 seconds</div>
    <div class="panel-content">Please provide your email id and click the 'Create SSL' button</div>
    </div>
    
    <div class="form">
    <form class="form-validate form-horizontal " id="tmp-ssl" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
    
                                
                  	<div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
                      <label for="admin_email" class="control-label col-lg-6">Your email id. Let's Encrypt need this to register your account.<span class="required">*</span></label>
                      <div class="col-lg-6">
                      <input class="form-control " id="email" name="admin_email" type="email" required="required" value="<?php echo isset($admin_email[0]) ? $admin_email[0] : null; ?>" />
                          <span class="help-block"><?php echo isset($email_err) ? $email_err : null; ?></span>
                      </div>
                    </div>
                  	
                  	                   
                    <div class="form-group ">
                      <label for="agree_to_le_terms" class="control-label col-lg-6 col-sm-6">I agree to the <a href="https://acme-v01.api.letsencrypt.org/terms" target="_blank">Let's Encrypt Subscriber Agreement</a> <span class="required">*</span></label>
                      <div class="col-lg-6 col-sm-6">
                        <input type="checkbox" style="width: 20px" class="checkbox form-control" id="agree_to_le_terms" name="agree_to_le_terms" required="required"<?php echo (isset($app_settings['agree_to_le_terms']) && 'on' === $app_settings['agree_to_le_terms']) ? ' checked' : null; ?> />
                      </div>
                    </div>
                    
                    
                    <div class="form-group ">
                      <label for="agree_to_freessl_tech_tos_pp" class="control-label col-lg-6 col-sm-6">I agree to the FreeSSL.tech <a href="https://freessl.tech/terms-of-service" target="_blank">Terms of Service</a> and <a href="https://freessl.tech/privacy-policy" target="_blank">Privacy Policy</a> <span class="required">*</span></label>
                      <div class="col-lg-6 col-sm-6">
                        <input type="checkbox" style="width: 20px" class="checkbox form-control" id="agree_to_freessl_tech_tos_pp" name="agree_to_freessl_tech_tos_pp" required="required"<?php echo (isset($app_settings['agree_to_freessl_tech_tos_pp']) && 'on' === $app_settings['agree_to_freessl_tech_tos_pp']) ? ' checked' : null; ?> />
                      </div>
                    </div>
                    
                    <div class="input-group <?php echo (!empty($csrf_err)) ? 'has-error' : ''; ?>">
                      <input type="hidden" name="csrf" value="<?php echo $factory->getCsrfToken('tmp-ssl', true); ?>">
                      <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
                    </div>
                    
                    <div class="form-group">
                      <div class="col-lg-offset-6 col-lg-6">
                        <button class="btn btn-primary" type="submit">Create SSL</button>
                        
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </section>
          </div>
        </div>
        <!-- page end-->
        
        </section>
        </section>
        </section>
        </body>
        </html>
<?php
 } ?>
