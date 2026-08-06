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

namespace FreeSslDotTech\FreeSSLAuto\Admin;

use FreeSslDotTech\FreeSSLAuto\Acme\Factory as AcmeFactory;
use FreeSslDotTech\FreeSSLAuto\cPanel\cPanel;
use FreeSslDotTech\FreeSSLAuto\FreeSSLAuto;

class Admin
{
    public $app_settings;
    public $config_file_path;
    public $mysqli;
    public $factory;
    public $acmeFactory;
    public $app_cron_path;
    public $add_new_cron_base;
    public $csrf_err = "Sorry! This form's security token expired. Please submit again. Don't wait more than 15 minutes after loading the form.";

    public function __construct($config_file_path, $mysqli)
    {
        $this->config_file_path = $config_file_path;
        $this->mysqli = $mysqli;

        if (\defined('APP_SETTINGS_PATH')) {
            $settings = file_get_contents(APP_SETTINGS_PATH);
            $this->app_settings = json_decode($settings, true);
        }

        $this->factory = new Factory();

        //initialize the Acme Factory class
        $this->acmeFactory = new AcmeFactory($this->app_settings['homedir'].'/'.$this->app_settings['certificate_directory'], $this->app_settings['acme_version'], $this->app_settings['is_staging']);

        $this->app_cron_path = str_replace('FreeSSLAuto'.DS.'src'.DS.'Admin', '', __DIR__).'cron.php';

        $this->add_new_cron_base = "0 0 * * * php -q {$this->app_cron_path}";
    }

    public function index()
    {
        ?>
       <!--page title and breadcrumb start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="fas fa-lock"></i> FreeSSL.tech Auto : Dashboard</h3>
            <ol class="breadcrumb">
              <li><i class="fas fa-home"></i>Dashboard</li>
              
            </ol>
          </div>
        </div>
        <!--page title and breadcrumb end-->

		<?php if (!\defined('APP_SETTINGS_PATH')) {
            ?>
		
				<div class="alert alert-danger">              
              		Please start with the Basic settings. This app will display other settings options based on your Basic settings.
              	</div>
		
		<?php
        } ?>


        <div class="row">

          <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="panel panel-default">
              <div class="panel-heading">
                <h2><i class="fas fa-cogs"></i> <strong>Settings</strong></h2>
                <div class="panel-actions">                  
                  <a href="#" class="btn-minimize"><i class="fa fa-chevron-up"></i></a>                  
                </div>
              </div>
              
              <div class="panel-body">
                
            <div class="row">
          <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
          <a href="index.php?action=settings-basic">
            <div class="info-box blue-bg">
              <i class="fas fa-power-off"></i>
              <div class="count">Basic</div>
              <div class="title">Settings *</div>
            </div>
            </a>
            <!--/.info-box-->
          </div>
          <!--/.col-->

			<?php
                  if (\defined('APP_SETTINGS_PATH')) {
                      if ($this->app_settings['is_cpanel']) {
                          ?>

          <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
          <a href="index.php?action=settings-cpanel">
            <div class="info-box brown-bg">
              <i class="fab fa-cpanel"></i>
              <div class="count">cPanel</div>
              <div class="title">Settings *</div>
            </div>
            </a>
            <!--/.info-box-->
          </div>
          <!--/.col-->

          <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
          <a href="index.php?action=settings-cpanel-domains-to-exclude">
            <div class="info-box dark-bg">
              <i class="fas fa-minus-circle"></i>
              <div class="count-large-3">Exclude Domains</div>
              <div class="title">Optional</div>
            </div>
            </a>
            <!--/.info-box-->
          </div>
          <!--/.col-->
          
           <?php
                      } else {
                          ?>

       <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
          <a href="index.php?action=settings-domains">
            <div class="info-box brown-bg">
              <i class="fas fa-globe"></i>
              <div class="count">Domains</div>
              <div class="title">Settings *</div>
            </div>
            </a>
            <!--/.info-box-->
          </div>
          <!--/.col-->

          <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
          <a href="index.php?action=settings-add-domain">
            <div class="info-box dark-bg">
              <i class="fas fa-plus-circle"></i>
              <div class="count-large-2">Add Domains</div>
              <div class="title">Settings *</div>
            </div>
            </a>
            <!--/.info-box-->
          </div>
          <!--/.col-->   
 
        
        <?php
                      } ?>
        
        <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
          <a href="index.php?action=settings-add-cron">
            <div class="info-box success">
              <i class="far fa-calendar-alt"></i>
              <div class="count-large-2">Add Cron Job</div>
              <div class="title">Settings *</div>
            </div>
            </a>
            <!--/.info-box-->
          </div>
          <!--/.col-->

        </div>
        <!--/.row-->        
              
              
              <div class="row">
              
              <?php if (2 === $this->app_settings['acme_version'] && $this->app_settings['use_wildcard']) {
                          ?>
              
              <!-- DNS Service Provider -->
              
               <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
                  <a href="index.php?action=settings-dns-service-providers">
                    <div class="info-box dark-bg">
                      <i class="fas fa-server"></i>
                      <div class="count-large-3">DNS Service</div>
                      <div class="title">Providers <br />Settings *</div>
                    </div>
                    </a>
                    <!--/.info-box-->
                  </div>
                  <!--/.col-->
              
              <!-- Else part of this will be in 4th column and then 2nd column will be 1st etc.. -->                          
              
             <?php
                      } ?>
             
             
             <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
                  <a href="index.php?action=issue-free-ssl">
                    <div class="info-box success">
                      <i class="fas fa-lock"></i>
                      <div class="count-large-2">Issue Free SSL</div>
                      <div class="title">optional, not for wildcard ssl</div>
                    </div>
                    </a>
                    <!--/.info-box-->
                  </div>
                  <!--/.col-->
             
             
             <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
              <a href="index.php?action=change-le-account-key" onclick="return confirm('Do you really want to change your Let\'s Encrypt account key?');">
                <div class="info-box blue-bg">
                  <i class="fas fa-sync-alt"></i>
                  <div class="count-large-3">Change Let's Encrypt Key</div>
                  <div class="title">optional</div>
                </div>
                </a>
                <!--/.info-box-->
              </div>
              <!--/.col-->
              
              
              <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
              <a href="index.php?action=revoke-ssl">
                <div class="info-box red-bg">
                  <i class="fas fa-times-circle"></i>
                  <div class="count-large-2">Revoke SSL</div>
                  <div class="title">optional</div>
                </div>
                </a>
                <!--/.info-box-->
              </div>
              <!--/.col-->
             
             <?php
                  } ?>
             
              
               <div class="col-lg-3 col-md-3 col-sm-12 col-xs-12">
                  <a href="https://freessl.tech/documentation-free-ssl-certificate-automation" target="_blank">
                    <div class="info-box brown-bg">
                      <i class="fas fa-book"></i>
                      <div class="count-docs">Documentation</div>
                      <div class="title">FreeSSL.tech Auto</div>
                    </div>
                    </a>
                    <!--/.info-box-->
                  </div>
                  <!--/.col-->   
                  
             
            </div>
            * = Mandatory Settings
          </div> 
          
        </div>
        
</div>
<div>

</div>
    <?php
    }

    public function basicSettings()
    {
        ?>
      <!--page title and breadcrumb start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="fas fa-cogs" aria-hidden="true"></i>Settings - Basic</h3>
            <ol class="breadcrumb">
              <li><i class="fas fa-home"></i><a href="index.php"> Dashboard</a></li>
              <li><i class="fas fa-power-off"></i> Settings - Basic</li>
            </ol>
          </div>
        </div>
  <!--page title and breadcrumb end-->

<?php

$countries = file_get_contents(__DIR__.'/country_code.json');
        $countries_array = json_decode($countries, true);

        if (\defined('APP_SETTINGS_PATH')) {
            $app_settings = $this->app_settings;
        } else {
            $app_settings = [];
        }

        //Processing form data when form is submitted
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $email_err = $csrf_err = $use_wildcard_err = null;

            $token = trim($_POST['csrf']);
            $token = filter_var($token, FILTER_SANITIZE_STRING);

            $csrfVerified = $this->factory->verifyCsrfToken('settings-basic', $token);

            if (!$csrfVerified) {
                $csrf_err = $this->csrf_err;
            }

            // SANITIZE user inputs

            if (isset($_POST['free_ssl_provider_ca'])) {
                $app_settings['free_ssl_provider_ca'] = $this->factory->sanitize_string($_POST['free_ssl_provider_ca']);
            }
            
            //$app_settings['acme_version'] = (int) filter_var($_POST['acme_version'], FILTER_SANITIZE_NUMBER_INT);

            $app_settings['acme_version'] = FSA_DEFAULT_ACME_VERSION;
            
            $app_settings['use_wildcard'] = (bool) filter_var($_POST['use_wildcard'], FILTER_SANITIZE_NUMBER_INT);

            if (1 === $app_settings['acme_version'] && $app_settings['use_wildcard']) {
                $use_wildcard_err = "Do you really want to issue wildcard SSL? Then please select Let's Encrypt ACME version 2.";
            }

            $app_settings['is_staging'] = (bool) filter_var($_POST['is_staging'], FILTER_SANITIZE_NUMBER_INT);

            //Validate email by pattern
            if (filter_var($_POST['admin_email'], FILTER_VALIDATE_EMAIL)) {
                //email is valid
                $app_settings['admin_email'][0] = filter_var(trim($_POST['admin_email']), FILTER_SANITIZE_EMAIL);
            } else {
                $email_err = 'Please enter a valid email.';
                $app_settings['admin_email'][0] = trim($_POST['admin_email']);
            }

            $app_settings['country_code'] = $this->factory->sanitize_string($_POST['country_code']);

            $app_settings['state'] = $this->factory->sanitize_string($_POST['state']);

            $app_settings['organization'] = $this->factory->sanitize_string($_POST['organization']);

            //remove space before and after
            $homedir = trim($_POST['homedir']);

            $homedir = filter_var($homedir, FILTER_SANITIZE_STRING);

            $app_settings['homedir'] = rtrim($homedir, '\/'); //remove / at the end

            $certificate_directory = $this->factory->sanitize_string($_POST['certificate_directory']);

            $app_settings['certificate_directory'] = str_replace('/', '', $certificate_directory); //remove all /

            $app_settings['days_before_expiry_to_renew_ssl'] = (int) filter_var($_POST['days_before_expiry_to_renew_ssl'], FILTER_SANITIZE_NUMBER_INT);

            $app_settings['is_cpanel'] = (bool) filter_var($_POST['is_cpanel'], FILTER_SANITIZE_NUMBER_INT);
            
            $app_settings['server_ip'] = $this->factory->sanitize_string($_POST['server_ip']);

            $app_settings['using_cdn'] = (bool) filter_var($_POST['using_cdn'], FILTER_SANITIZE_NUMBER_INT);

            $app_settings['key_size'] = (int) filter_var($_POST['key_size'], FILTER_SANITIZE_NUMBER_INT);

            $app_settings['agree_to_le_terms'] = $this->factory->sanitize_string($_POST['agree_to_le_terms']);

            $app_settings['agree_to_freessl_tech_tos_pp'] = $this->factory->sanitize_string($_POST['agree_to_freessl_tech_tos_pp']);

            //make entry for APP_SETTINGS_PATH in config.php if no error

            if ($csrfVerified && empty($use_wildcard_err) && empty($email_err)) {
                if (\defined('APP_SETTINGS_PATH')) {
                    // update APP_SETTINGS_PATH
                    // load the data of the config file and delete the last 2 lines from the array
                    $lines = file($this->config_file_path);

                    $last = \count($lines) - 1;
                    unset($lines[$last]);

                    // write the new data to the file
                    $fp = fopen($this->config_file_path, 'wb');
                    fwrite($fp, implode('', $lines));
                    fclose($fp);
                }

                $app_settings_path = $app_settings['homedir'].'/'.$app_settings['certificate_directory'].'/settings/settings.json';

                $entry = <<<ENTRY
define('APP_SETTINGS_PATH', '${app_settings_path}');
ENTRY;

                if (!file_put_contents($this->config_file_path, $entry, FILE_APPEND)) {
                    echo "<div class='alert alert-danger' role='alert'>Could not write to the config.php file ({$this->config_file_path}). Please check that you have write permission to that directory and try again.</div>";
                }

                $settingsDirectory = \dirname($app_settings_path);

                if (!is_dir($settingsDirectory)) {
                    @mkdir($settingsDirectory, 0700, true);
                }

                if (!is_dir($settingsDirectory)) {
                    throw new \RuntimeException("Can't create directory ${settingsDirectory}. Please manually create this directory, grant permission 0700 and try again.");
                }

                // encode in json and make entry in settings.json

                if (!file_put_contents($app_settings_path, json_encode($app_settings))) {
                    echo "<div class='alert alert-danger' role='alert'>Could not write to the settings.json file (".APP_SETTINGS_PATH.'). Please check that you have write permission to that directory and try again.</div>';
                } else {
                    //if is_cpanel is true but cpanel password is not set, redirect to cPanel settings page

                    if ($app_settings['is_cpanel'] && !isset($app_settings['password'])) {
                        //cPanel

                        //Save confirmation msg in session before redirect
                        $_SESSION['message'] = [
                            'type' => 'success',
                            'message' => 'Settings have been saved successfully! Now please provide your cPanel login details. This is required for complete automation.',
                        ];

                        $this->factory->redirect('index.php?action=settings-cpanel');
                    } elseif (!$app_settings['is_cpanel'] && !isset($app_settings['all_domains'])) {
                        //others control panel

                        //Save confirmation msg in session before redirect
                        $_SESSION['message'] = [
                            'type' => 'success',
                            'message' => 'Settings have been saved successfully! Now please provide your domain details. This is required for automation.',
                        ];

                        $this->factory->redirect('index.php?action=settings-add-domain');
                    } else {
                        //Save confirmation msg in session before redirect
                        $_SESSION['message'] = [
                            'type' => 'success',
                            'message' => 'Your settings have been saved successfully!',
                        ];

                        $this->factory->redirect('index.php');
                    }
                }
            }
        }
        //GET request - fill the form with saved data if settings.txt exist?>


<div class="row">
          <div class="col-lg-12">
            <section class="panel">
              <!--  <header class="panel-heading">
                Please fill in the following form and click Save
              </header>  -->
              <div class="panel-body">
              
              <div class="panel panel-primary">
                  <div class="panel-heading">Please fill in the following form and click Save</div>
                  <div class="panel-content">Please provide your preferences.</div>
                </div>
              
                <div class="form">
                  <form class="form-validate form-horizontal " id="settings-basic" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=settings-basic">
                    
                    <div class="form-group">
                        <label class="control-label col-lg-6" for="acme_version">ACME version (2 is better)</label>
                        <div class="col-lg-6">
                            <select class="form-control m-bot15" name="acme_version" disabled>
                                    <option<?php echo (isset($app_settings['acme_version']) && 2 === $app_settings['acme_version']) ? ' selected' : null; ?>>2</option>
                                    <option<?php echo (isset($app_settings['acme_version']) && 1 === $app_settings['acme_version']) ? ' selected' : null; ?>>1</option>                                              
                            </select>                      
                    	</div>
                    </div>
                  	
                  	<div class="form-group <?php echo (!empty($use_wildcard_err)) ? 'has-error' : ''; ?>">
                    <label class="control-label col-lg-6" for="use_wildcard">Do you want to use wildcard SSL instead of separate SSL for each sub-domains?</label>
                    <div class="col-lg-6">
                      <select class="form-control m-bot15" name="use_wildcard">
                                              <option value="0"<?php echo (isset($app_settings['use_wildcard']) && false === $app_settings['use_wildcard']) ? ' selected' : null; ?>>No</option>
                                              <option value="1"<?php echo (isset($app_settings['use_wildcard']) && true === $app_settings['use_wildcard']) ? ' selected' : null; ?>>Yes</option>
                                           </select>
                      		<span class="help-block"><?php echo isset($use_wildcard_err) ? $use_wildcard_err : null; ?></span>
                    	</div>
                    	<span class="col-lg-12">Please note that wildcard SSL needs additional settings with DNS. You'll need to set DNS TXT records manually if your Domain registrar/ DNS service provider is other than<br /> 
                    	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Godaddy, Namecheap, and Cloudflare. <strong>If you don't know what DNS TXT record is, we strongly recommend selecting 'No'.</strong></span>
                  	</div>
                  	
                  	<div class="form-group">
                    <label class="control-label col-lg-6" for="is_staging">Do you want to issue real SSL certificate (LIVE)?</label>
                    <div class="col-lg-6">
                    <select class="form-control m-bot15" name="is_staging">
                    						  <option value="0"<?php echo (isset($app_settings['is_staging']) && false === $app_settings['is_staging']) ? ' selected' : null; ?>>Yes</option>
                                              <option value="1"<?php echo (isset($app_settings['is_staging']) && true === $app_settings['is_staging']) ? ' selected' : null; ?>>No</option>                                              
                                           </select>
                      
                    	</div>
                  	</div>
                  	
                  	<div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
                      <label for="admin_email" class="control-label col-lg-6">Your email id. Let's Encrypt need this to register your account.<span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class="form-control " id="email" name="admin_email" type="email" required="required" value="<?php echo isset($app_settings['admin_email'][0]) ? $app_settings['admin_email'][0] : (isset($_SESSION['email']) ? $_SESSION['email'] : null); ?>" />
                      <span class="help-block"><?php echo isset($email_err) ? $email_err : null; ?></span>
                      </div>
                    </div>
                  	
                  	
                  	<div class="form-group">
                    <label class="control-label col-lg-6" for="country_code">Your Country</label>
                    <div class="col-lg-6">
                    <select class="form-control m-bot15" name="country_code" required="required">
                    						<?php foreach ($countries_array as $country) {
            ?>
                                              <option value="<?php echo $country['Code']; ?>"<?php echo (isset($app_settings['country_code']) && $app_settings['country_code'] === $country['Code']) ? ' selected' : null; ?>><?php echo $country['Name']; ?></option>
                                             <?php
        } ?>
                                           </select>
                      
                    	</div>
                  	</div>
                  	
                    
                    <div class="form-group ">
                      <label for="state" class="control-label col-lg-6">State <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class=" form-control" id="state" name="state" type="text" required="required" value="<?php echo isset($app_settings['state']) ? $app_settings['state'] : null; ?>" />
                      </div>
                    </div>
                    
                    <div class="form-group ">
                      <label for="organization" class="control-label col-lg-6">Organization</label>
                      <div class="col-lg-6">
                        <input class=" form-control" id="organization" name="organization" type="text" value="<?php echo isset($app_settings['organization']) ? $app_settings['organization'] : null; ?>" />
                      </div>
                    </div>
                    
                    <div class="form-group ">
                      <label for="homedir" class="control-label col-lg-6">Home directory of your server. Don't use a trailing slash. <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class="form-control " id="homedir" name="homedir" type="text" value="<?php echo isset($app_settings['homedir']) ? $app_settings['homedir'] : (isset($_SERVER['HOME']) ? $_SERVER['HOME'] : null); ?>" placeholder="/home/username" required="required" />
                      </div>
                      <span class="col-lg-12">It should be a directory that is NOT accessible to the public. Your private keys and cPanel password will be saved here. This directory should be writable.</span>
                    </div>
                    
                    <div class="form-group ">
                      <label for="certificate_directory" class="control-label col-lg-6">Provide the name of the directory where private keys and SSL certificate will be saved. <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class="form-control " id="certificate_directory" name="certificate_directory" type="text" value="<?php echo isset($app_settings['certificate_directory']) ? $app_settings['certificate_directory'] : null; ?>" placeholder="Don't use 'ssl' - it is a reserved directory by cPanel." required="required" />
                      </div>
                      <span class="col-lg-12">Don't include '/' before or after. This directory will be kept in the Home Directory of your web hosting account.</span>
                    </div>
                    
                   
                   <div class="form-group">
                    <label class="control-label col-lg-6" for="days_before_expiry_to_renew_ssl">Number of days before the expiry date do you want to renew the SSL</label>
                    <div class="col-lg-6">
                    <select class="form-control m-bot15" name="days_before_expiry_to_renew_ssl">
                    
                    <?php
                    $day_selected = isset($app_settings['days_before_expiry_to_renew_ssl']) ? $app_settings['days_before_expiry_to_renew_ssl'] : 30; ?>
                    						<?php for ($day = 5; $day <= 30; ++$day) {
                        ?>
                                              <option<?php echo $day === $day_selected ? ' selected' : null; ?>><?php echo $day; ?></option>
                                             <?php
                    } ?>
                                           </select>
                      
                    	</div>
                  	</div>
                  	
                  	<div class="form-group">
                    <label class="control-label col-lg-6" for="is_cpanel">Is your web hosting control panel cPanel? <span class="required">*</span></label>
                    <div class="col-lg-6">
                    <select class="form-control m-bot15" name="is_cpanel" required="required">
                                              <option value="0"<?php echo (isset($app_settings['is_cpanel']) && false === $app_settings['is_cpanel']) ? ' selected' : null; ?>>No</option>
                                              <option value="1"<?php echo (isset($app_settings['is_cpanel']) && true === $app_settings['is_cpanel']) ? ' selected' : null; ?>>Yes</option>
                                           </select>
                      
                    	</div>
                  	</div>
                  	
                  	<div class="form-group">
                    <label class="control-label col-lg-6" for="server_ip">IP Address of this server <span class="required">*</span></label>
                    	<div class="col-lg-6">
                    		<input class="form-control " id="server_ip" name="server_ip" type="text" value="<?php echo isset($app_settings['server_ip']) ? $app_settings['server_ip'] : (isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null); ?>" placeholder="IP Address of this server" required="required" />                      
                    	</div>
                  	</div>
                  	
                  	<div class="form-group">
                    <label class="control-label col-lg-6" for="using_cdn">Are you using Cloudflare or any other CDN? <span class="required">*</span></label>
                    <div class="col-lg-6">
                    <select class="form-control m-bot15" name="using_cdn" required="required">
                                              <option value="0"<?php echo (isset($app_settings['using_cdn']) && false === $app_settings['using_cdn']) ? ' selected' : null; ?>>No</option>
                                              <option value="1"<?php echo (isset($app_settings['using_cdn']) && true === $app_settings['using_cdn']) ? ' selected' : null; ?>>Yes</option>
                                           </select>
                      
                    	</div>
                  	</div>
                  	
                  	<div class="form-group">
                    <label class="control-label col-lg-6" for="key_size">SSL Key Size</label>
                    <div class="col-lg-6">
                    <select class="form-control m-bot15" name="key_size">
                                              <option<?php echo (isset($app_settings['key_size']) && 2048 === $app_settings['key_size']) ? ' selected' : null; ?>>2048</option>
                                              <option<?php echo (isset($app_settings['key_size']) && 3072 === $app_settings['key_size']) ? ' selected' : null; ?>>3072</option>
                                              <option<?php echo (isset($app_settings['key_size']) && 4096 === $app_settings['key_size']) ? ' selected' : null; ?>>4096</option>
                                           </select>
                      
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
                      <input type="hidden" name="csrf" value="<?php echo $this->factory->getCsrfToken('settings-basic'); ?>">
                      <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
                    </div>
                    
                    <div class="form-group">
                      <div class="col-lg-offset-6 col-lg-6">
                        <button class="btn btn-primary" type="submit">Save</button>
                        
                        <a class="btn btn-default" type="button" href="#" onclick="window.history.go(-1); return false;">Cancel</a>
                        
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </section>
          </div>
        </div>
        <!-- page end-->
        
    <?php
    }

    public function cpanelSettings()
    {
        ?>
     <!--page title and breadcrumb start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="fab fa-cpanel" style="font-size: 75px; float: center;"></i> Settings - cPanel</h3>
            <ol class="breadcrumb">
              <li><i class="fas fa-home"></i><a href="index.php"> Dashboard</a></li>
              <li><i class="fab fa-cpanel" style="font-size: 15px;"></i> Settings - cPanel</li>
            </ol>
          </div>
        </div>
  <!--page title and breadcrumb end-->

<?php

if (\defined('APP_SETTINGS_PATH')) {
    $app_settings = $this->app_settings;

    if (isset($app_settings['password'])) {
        $app_settings['password'] = $this->factory->decryptText($app_settings['password']);
    }
} else {
    $app_settings = [];
}

        //Processing form data when form is submitted
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $confirm_password_err = $csrf_err = null;

            $token = trim($_POST['csrf']);
            $token = filter_var($token, FILTER_SANITIZE_STRING);

            $csrfVerified = $this->factory->verifyCsrfToken('settings-cpanel', $token);

            if (!$csrfVerified) {
                $csrf_err = $this->csrf_err;
            }

            // SANITIZE user inputs

            //use parse_url only if https:// and/or :2083 exist in url
            if (false !== strpos($_POST['cpanel_host'], 'https://') || false !== strpos($_POST['cpanel_host'], ':2083')) {
                $cpanel_host = parse_url($this->factory->sanitize_string($_POST['cpanel_host']));

                $app_settings['cpanel_host'] = $cpanel_host['host'];
            } else {
                $app_settings['cpanel_host'] = $this->factory->sanitize_string($_POST['cpanel_host']);
            }

            $app_settings['username'] = $this->factory->sanitize_string($_POST['username']);

            $password = trim($_POST['password']);

            $confirm_password = trim($_POST['confirm_password']);

            if ($password !== $confirm_password) {
                $confirm_password_err = 'Password did not match.';
                unset($app_settings['password'], $app_settings['confirm_password']);
            } else {
                $app_settings['password'] = $this->factory->encryptText($password);
            }

            if ($csrfVerified && empty($confirm_password_err)) {
                // encode in json and make entry in settings.json if no error

                if (!file_exists(APP_SETTINGS_PATH)) {
                    mkdir(\dirname(APP_SETTINGS_PATH), 0777, true);
                }

                if (!file_put_contents(APP_SETTINGS_PATH, json_encode($app_settings))) {
                    echo "<div class='alert alert-danger' role='alert'>Could not write to the settings.json file (".APP_SETTINGS_PATH.'). Please check that you have write permission to that directory and try again.</div>';
                } else {
                    //Redirect to DNS settings if wildcard required
                    if (2 === $app_settings['acme_version'] && $app_settings['use_wildcard'] && !isset($app_settings['dns_provider'])) {
                        //Save confirmation msg in session before redirect
                        $_SESSION['message'] = [
                            'type' => 'success',
                            'message' => 'Your cPanel settings have been saved successfully! Now please provide your DNS Service Provider details.',
                        ];

                        $this->factory->redirect('index.php?action=settings-add-dns-service-provider');
                    } else {
                        //Save confirmation msg in session before redirect
                        $_SESSION['message'] = [
                            'type' => 'success',
                            'message' => 'Your cPanel settings have been saved successfully!',
                        ];

                        $this->factory->redirect('index.php?action=index');
                    }
                }
            }
        }
        //GET request - fill the form with saved data if settings exist?>


<div class="row">
          <div class="col-lg-12">
            <section class="panel">
              <!-- <header class="panel-heading">
                 Please fill in the following form and click Save
              </header>  -->
              <div class="panel-body">
              
              <?php if ($_SERVER['SERVER_PORT'] != 443) {
            ?>
              
              <div class="alert alert-danger">              
              	This page is not protected with HTTPS and you are going to provide your cPanel credentials over an unencrypted connection. We recommend generating a <a href="tmp-ssl.php" target="_blank">Free SSL Certificate for <strong><?php echo $_SERVER['SERVER_NAME']; ?></strong> with a single click</a>, installing it on this server and then continue with this cPanel settings page.<br />
              	You'll need less than 30 seconds to generate this free SSL. <a href="tmp-ssl.php" target="_blank">Please click here!</a>
              </div>
              <br />
              
              <?php
        } ?>
              
              <div class="panel panel-primary">
                  <div class="panel-heading">Please provide your cPanel login details and click Save</div>
                  <div class="panel-content">This app will use cPanel API to fetch all of your domain details and to auto install SSL certificate.</div>
                </div>
              
              
                <div class="form">
                  <form class="form-validate form-horizontal " id="settings-basic" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=settings-cpanel">
                    
                    
                    <div class="form-group ">
                      <label for="cpanel_host" class="control-label col-lg-6">Your cPanel host/login URL. <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class=" form-control" id="cpanel_host" name="cpanel_host" type="text" required="required" value="<?php echo isset($app_settings['cpanel_host']) ? 'https://'.$app_settings['cpanel_host'].':2083' : null; ?>" placeholder="e.g:     https://cloud.speedupwebsite.info:2083" />
                      </div>
                    </div>
                    
                    <div class="form-group ">
                      <label for="username" class="control-label col-lg-6">The username you use to log in your cPanel <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class=" form-control" id="username" name="username" type="text" required="required" value="<?php echo isset($app_settings['username']) ? $app_settings['username'] : null; ?>" placeholder="username"/>
                      </div>
                    </div>
                    
                    <div class="form-group ">
                      <label for="password" class="control-label col-lg-6">Password of your cPanel <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class="form-control " id="password" name="password" type="password" value="<?php echo isset($app_settings['password']) ? $app_settings['password'] : null; ?>" placeholder="" required="required" />
                      </div>
                      
                    </div>
                    
                    <div class="form-group <?php echo (!empty($confirm_password_err)) ? 'has-error' : ''; ?>">
                      <label for="confirm_password" class="control-label col-lg-6">Confirm Password <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class="form-control " id="confirm_password" name="confirm_password" type="password" value="<?php echo isset($app_settings['password']) ? $app_settings['password'] : null; ?>" placeholder="" required="required" />
                      <span class="help-block"><?php echo isset($confirm_password_err) ? $confirm_password_err : null; ?></span>
                      </div>                      
                    </div>
                    
                    <div class="input-group <?php echo (!empty($csrf_err)) ? 'has-error' : ''; ?>">
                      <input type="hidden" name="csrf" value="<?php echo $this->factory->getCsrfToken('settings-cpanel'); ?>">
                      <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
                    </div>
                    
                    <div class="form-group">
                      <div class="col-lg-offset-6 col-lg-6">
                        <button class="btn btn-primary" type="submit">Save</button>
                        
                        <a class="btn btn-default" type="button" href="#" onclick="window.history.go(-1); return false;">Cancel</a>
                        
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </section>
          </div>
        </div>
        <!-- page end-->        
    <?php
    }

    public function cpanelExcludeDomainsSettings()
    {
        ?>
      <!--page title and breadcrumb start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="fas fa-cogs"></i>Settings - Exclude Domains/Sub-domains</h3>
            <ol class="breadcrumb">
              <li><i class="fas fa-home"></i> <a href="index.php">Dashboard</a></li>
              <li><i class="fas fa-minus-circle"></i> Settings - Exclude Domains/Sub-domains</li>
            </ol>
          </div>
        </div>
  <!--page title and breadcrumb end-->

<?php

if (\defined('APP_SETTINGS_PATH')) {
    $app_settings = $this->app_settings;
} else {
    $app_settings = [];
}

        $cpanel = new cPanel($app_settings['cpanel_host'], $app_settings['username'], $app_settings['password']);

        //Fetch all domains in the cPanel
        $all_domains = $cpanel->allDomains();

        //Processing form data when form is submitted
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $csrf_err = null;

            $token = trim($_POST['csrf']);
            $token = filter_var($token, FILTER_SANITIZE_STRING);

            $csrfVerified = $this->factory->verifyCsrfToken('cpanel-domains-to-exclude', $token);

            if (!$csrfVerified) {
                $csrf_err = $this->csrf_err;
            }

            // SANITIZE user inputs

            $domains_array = [];

            if (isset($_POST['domains'])) {
                foreach ($_POST['domains'] as $domain) {
                    $domains_array[] = $this->factory->sanitize_string($domain);
                }
            }

            $app_settings['domains_to_exclude'] = $domains_array;

            if ($csrfVerified) {
                // encode in json and make entry in settings.json

                if (!file_exists(APP_SETTINGS_PATH)) {
                    mkdir(\dirname(APP_SETTINGS_PATH), 0777, true);
                }

                if (!file_put_contents(APP_SETTINGS_PATH, json_encode($app_settings))) {
                    echo "<div class='alert alert-danger' role='alert'>Could not write to the settings.json file (".APP_SETTINGS_PATH.'). Please check that you have write permission to that directory and try again.</div>';
                } else {
                    //if is_cpanel is true but cpanel password is not set, redirect to cPanel settings page

                    //Save confirmation msg in session before redirect
                    $_SESSION['message'] = [
                        'type' => 'success',
                        'message' => 'Your domains/sub-domains exclution settings have been saved successfully!',
                    ];

                    $this->factory->redirect('index.php?action=index');
                }
            }
        }
        //GET request - fill the form with saved data if settings.txt exist?>


<div class="row">
          <div class="col-lg-12">
            <section class="panel">
              <!--  <header class="panel-heading">
                Please fill in the following form and click Save
              </header>  -->
              <div class="panel-body">
              
              <div class="panel panel-primary">
                  <div class="panel-heading">Please select the domains/sub-domains you want to exclude</div>
                  <div class="panel-content">Following domains/add-on domains or sub-domains are being hosted on your cPanel. 
                  If you want SSL certificate for all of them, please skip these settings.</div>
                  
                  <div class="panel-content "><strong>Do you have any domain that currently not pointed to this hosting? Please either delete it from the cPanel or exclude it by selecting here. Otherwise, this app will throw an error.</strong></div>
                </div>
              
                <div class="form">
                  <form class="form-validate form-horizontal " id="settings-basic" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=settings-cpanel-domains-to-exclude">
                    
                  	<?php foreach ($all_domains as $domain) {
            ?>
                    <div class="form-group ">
                    	<div class="col-lg-1 col-sm-1">
                        	<input type="checkbox" style="width: 20px" class="checkbox form-control" id="domains" name="domains[]" value="<?php echo $domain['domain']; ?>" <?php echo (isset($app_settings['domains_to_exclude']) && \in_array($domain['domain'], $app_settings['domains_to_exclude'], true)) ? ' checked' : null; ?> />
                      	</div>
                      
                      <div class="col-lg-11 col-sm-11 text-left">
                      	<label for="domains" class="control-label"><?php echo $domain['domain']; ?>, <?php echo str_replace(' ', ', ', $domain['serveralias']); ?> </label>
                      </div>
                      
                    </div>
                    <?php
        } ?>
                    
                    <div class="input-group <?php echo (!empty($csrf_err)) ? 'has-error' : ''; ?>">
                      <input type="hidden" name="csrf" value="<?php echo $this->factory->getCsrfToken('cpanel-domains-to-exclude'); ?>">
                      <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
                    </div>
                    
                    <div class="form-group">
                      <div class="col-lg-offset-12 col-lg-12">
                        <button class="btn btn-primary" type="submit">Save</button>
                        
                        <a class="btn btn-default" type="button" href="#" onclick="window.history.go(-1); return false;">Cancel</a>
                        
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </section>
          </div>
        </div>
        <!-- page end-->
        
     <?php
    }

    public function domainsSettings()
    {
        ?>
     <!--page title and breadcrumb start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="fas fa-cogs" aria-hidden="true"></i>Settings - Domains</h3>
            <ol class="breadcrumb">
              <li><i class="fas fa-home"></i><a href="index.php">Dashboard</a></li>
              <li><i class="fas fa-globe"></i>Settings - Domains</li>
            </ol>
          </div>
        </div>
  <!--page title and breadcrumb end-->

<?php

if (\defined('APP_SETTINGS_PATH')) {
    $app_settings = $this->app_settings;
} ?>

<div class="row">
	<div class="col-lg-12 text-center">
		<a class="btn btn-primary" href="index.php?action=settings-add-domain"><i class="fa fa-plus" aria-hidden="true"></i> Add Domain</a>
		<br /><br />
	</div>
</div>

<div class="row">
          <div class="col-lg-12">
            <section class="panel">
              <!-- <header class="panel-heading">
                 Please fill in the following form and click Save
              </header>  -->
              <div class="panel-body">
              
              <div class="panel panel-primary">
                  <div class="panel-heading">Your domains</div>                  
              
             <?php if (isset($app_settings['all_domains'])) {
    ?>
              
             <?php if (\count($app_settings['all_domains']) > 0) {
        ?>
             
             	<div class="panel-content">This app will auto-generate free SSL certificates for the following domains.</div>
             </div>
             
             <div class="table-responsive">        
              <table class="table table-striped table-advance table-hover">
                <thead>
                  <tr>
                  	<th>ID</th>
                  	<th>Domain (CN)</th>
                    <th>Server Alias (SAN)</th>
                    <th>Document Root</th>
                    <th><i class="icon_cogs"></i> Action</th>                    
                  </tr>
                </thead>
                
                <tbody>
                <?php foreach ($app_settings['all_domains'] as $key => $domain) {
            ?>
                  <tr>
                  	<td><?php echo $key; ?></td>
                  	<td><?php echo $domain['domain']; ?></td>
                    <td><?php echo $domain['serveralias']; ?></td>                    
                    <td><?php echo $domain['documentroot']; ?></td>
                   	<td class="col-xs-2">
                      <div class="btn-group">
                        <a class="btn btn-success" href="index.php?action=settings-add-domain&id=<?php echo $key; ?>"><i class="fa fa-pencil" aria-hidden="true"></i> Update</a>
                        <a class="btn btn-danger" href="index.php?action=settings-delete-domain&id=<?php echo $key; ?>" onclick="return confirm('Do you really want to DELETE the domain?');"><i class="fa fa-trash-o" aria-hidden="true"></i> Delete</a>
                      </div>
                    </td>                   
                  </tr>
                  <?php
        } ?>
                </tbody>
              </table>
              </div>
              
              <?php
    } ?>
              
              <?php
} else {
        ?>
              <p><br /><br />You don't have any Domain. Please <a href="index.php?action=settings-add-domain">add domain</a> to auto-generate free SSL certificates.<br /><br /></p>
                            
              <?php
    } ?>
              <br /><br />
            </div>
            
            </section>
          </div>
        </div>
        <!-- page end-->
     
     <?php
    }

    public function addDomainSettings()
    {
        ?>
      <!--page title and breadcrumb start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="fas fa-cogs"></i>Settings : <?php echo isset($_GET['id']) ? 'Update' : 'Add'; ?> Domain</h3>
            <ol class="breadcrumb">
              <li><i class="fas fa-home"></i> <a href="index.php">Dashboard</a></li>
              <li><i class="fas fa-globe"></i> <a href="index.php?action=settings-domains">Domains</a></li>
              <li><?php echo isset($_GET['id']) ? "<i class='fas fa-edit'></i> Update" : "<i class='fas fa-plus-circle'></i> Add"; ?> Domain</li>
            </ol>
          </div>
        </div>
  <!--page title and breadcrumb end-->

<?php

if (\defined('APP_SETTINGS_PATH')) {
    $app_settings = $this->app_settings;
} else {
    $app_settings = [];
}

        //Processing form data when form is submitted
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $documentroot_err = $csrf_err = null;

            $token = trim($_POST['csrf']);
            $token = filter_var($token, FILTER_SANITIZE_STRING);

            $csrfVerified = $this->factory->verifyCsrfToken('add-domain', $token);

            if (!$csrfVerified) {
                $csrf_err = $this->csrf_err;
            }

            // Validate document root
            $documentroot = trim($_POST['documentroot']);

            $documentroot = filter_var($documentroot, FILTER_SANITIZE_STRING);

            if (empty($documentroot)) {
                $documentroot_err = 'Please enter document root of the domain.';
            }

            // SANITIZE user inputs

            $domain = $this->factory->sanitize_string($_POST['domain']);

            $serveralias = $this->factory->sanitize_string($_POST['serveralias']);

            //check $_POST['serveralias'] for comma. if exists replace with space
            $serveralias = str_replace([',', ', ', '  ', '   ', '    '], ' ', $serveralias);

            if (\strlen($_POST['id'])) {
                $id = (int) filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);

                $app_settings['all_domains'][$id] = [
                    'domain' => $domain,
                    'serveralias' => $serveralias,
                    'documentroot' => $documentroot,
                ];
            } else {
                $app_settings['all_domains'][] = [
                    'domain' => $domain,
                    'serveralias' => $serveralias,
                    'documentroot' => $documentroot,
                ];
            }

            if ($csrfVerified && empty($documentroot_err)) {
                // encode in json and make entry in settings.json if no error

                if (!file_exists(APP_SETTINGS_PATH)) {
                    mkdir(\dirname(APP_SETTINGS_PATH), 0777, true);
                }

                if (!file_put_contents(APP_SETTINGS_PATH, json_encode($app_settings))) {
                    echo "<div class='alert alert-danger' role='alert'>Could not write to the settings.json file (".APP_SETTINGS_PATH.'). Please check that you have write permission to that directory and try again.</div>';
                } else {
                    //Redirect to DNS settings if wildcard required
                    if (2 === $app_settings['acme_version'] && $app_settings['use_wildcard'] && !isset($app_settings['dns_provider'])) {
                        //Save confirmation msg in session before redirect
                        $_SESSION['message'] = [
                            'type' => 'success',
                            'message' => 'Your Domain details have been saved successfully! Now please provide your DNS Service Provider details.',
                        ];

                        $this->factory->redirect('index.php?action=settings-add-dns-service-provider');
                    } else {
                        //Save confirmation msg in session before redirect
                        $_SESSION['message'] = [
                            'type' => 'success',
                            'message' => 'Your Domain details have been saved successfully!',
                        ];

                        $this->factory->redirect('index.php?action=settings-domains');
                    }
                }
            }
        } else {
            //GET request - fill the form with saved data if settings.txt exist

            if (isset($_GET['id'])) {
                $id = (int) filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

                if (\defined('APP_SETTINGS_PATH')) {
                    $app_settings = $this->app_settings;

                    $domain = $app_settings['all_domains'][$id]['domain'];
                    $serveralias = $app_settings['all_domains'][$id]['serveralias'];
                    $documentroot = $app_settings['all_domains'][$id]['documentroot'];
                }
            }
        } ?>


<div class="row">
          <div class="col-lg-12">
            <section class="panel">
              <!-- <header class="panel-heading">
                 Please fill in the following form and click Save
              </header>  -->
              <div class="panel-body">
              
              <div class="panel panel-primary">
                  <div class="panel-heading">Please <?php echo isset($_GET['id']) ? 'update' : 'provide'; ?> your domain details and click Save</div>
                  <div class="panel-content">This app needs domain details to auto-generate SSL certificate.</div>
                </div>
                            
                <div class="form">
                  <form class="form-validate form-horizontal " id="settings-domain-details" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=settings-add-domain">
                                        
                    <div class="form-group ">
                      <label for="domain" class="control-label col-lg-6">Domain. It will be Common Name (CN) of the SSL <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class="form-control" id="domain" name="domain" type="text" required="required" value="<?php echo isset($domain) ? $domain : null; ?>" placeholder="e.g.   speedupwebsite.info" />
                      </div>
                    </div>
                    
                    <div class="form-group ">
                      <label for="serveralias" class="control-label col-lg-6">Server Alias, <strong>saperated by space</strong>, if you have multiple server alias pointing to the same document root. All of these must be accessible over HTTP. These will be Subject Alternative Name (SAN) of the SSL. <span class="required">*</span></label>
                      <div class="col-lg-6">                        
                        <textarea class="form-control" id="serveralias" name="serveralias" required="required" rows="4" placeholder="www.speedupwebsite.info mail.speedupwebsite.info" ><?php echo isset($serveralias) ? $serveralias : null; ?></textarea>
                        
                      </div>
                    </div>
                    
                    <div class="form-group <?php echo (!empty($documentroot_err)) ? 'has-error' : ''; ?>">
                      <label for="documentroot" class="control-label col-lg-6">Document Root <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class="form-control " id="documentroot" name="documentroot" type="text" value="<?php echo isset($documentroot) ? $documentroot : null; ?>" placeholder="/home/username/public_html" required="required" />
                      	<span class="help-block"><?php echo isset($documentroot_err) ? $documentroot_err : null; ?></span>
                      </div>                      
                    </div> 
                    
                    <input type="hidden" name="id" value="<?php echo isset($id) ? $id : null; ?>"> 
                    
                    <div class="input-group <?php echo (!empty($csrf_err)) ? 'has-error' : ''; ?>">
                      <input type="hidden" name="csrf" value="<?php echo $this->factory->getCsrfToken('add-domain'); ?>">
                      <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
                    </div>                  
                    
                    <div class="form-group">
                      <div class="col-lg-offset-6 col-lg-6">
                        <button class="btn btn-primary" type="submit">Save</button>
                        
                        <a class="btn btn-default" type="button" href="#" onclick="window.history.go(-1); return false;">Cancel</a>
                        
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </section>
          </div>
        </div>
        <!-- page end-->
      
      <?php
    }

    public function deleteDomainSettings()
    {
        ?>
     <!--page title and breadcrumb start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="fas fa-cogs" aria-hidden="true"></i>Settings - Delete Domain</h3>
            <ol class="breadcrumb">
              <li><i class="fas fa-home"></i><a href="index.php">Dashboard</a></li>
              <li><i class="fas fa-cogs"></i>Settings - Delete Domain</li>
            </ol>
          </div>
        </div>
  <!--page title and breadcrumb end-->

<?php

if (isset($_GET['id'])) {
    $id = (int) filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

    if (\defined('APP_SETTINGS_PATH')) {
        $app_settings = $this->app_settings;

        //delete the all_domains array which key is $id
        unset($app_settings['all_domains'][$id]);

        //Save the updated app settings

        if (!file_put_contents(APP_SETTINGS_PATH, json_encode($app_settings))) {
            echo "<div class='alert alert-danger' role='alert'>Could not write to the settings.json file (".APP_SETTINGS_PATH.'). Please check that you have write permission to that directory and try again.</div>';
        } else {
            //Save confirmation msg in session before redirect
            $_SESSION['message'] = [
                'type' => 'success',
                'message' => "The domain (ID: ${id}) has been deleted successfully!",
            ];

            $this->factory->redirect('index.php?action=settings-domains');
        }
    }
}
    }

    public function dnsServiceProvidersSettings()
    {
        ?>
     <!--page title and breadcrumb start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="fas fa-cogs" aria-hidden="true"></i>Settings : DNS Service Providers</h3>
            <ol class="breadcrumb">
              <li><i class="fas fa-home"></i> <a href="index.php">Dashboard</a></li>
              <li><i class="fas fa-server"></i> Settings : DNS Service Providers</li>
            </ol>
          </div>
        </div>
  <!--page title and breadcrumb end-->

<?php

if (\defined('APP_SETTINGS_PATH')) {
    $app_settings = $this->app_settings;
}

        //Check if 'acme_version' => 2 and 'use_wildcard' => true
        if (2 !== $app_settings['acme_version'] || !$app_settings['use_wildcard']) {
            ?>
<div class="alert alert-danger" role="alert">        	
      	This feature requires <?php echo (2 !== $app_settings['acme_version']) ? "Let's Encrypt ACME version is set to <strong>2</strong> and " : null; ?> <?php echo true !== $app_settings['use_wildcard'] ? "'use wildcard SSL' is set to <strong>Yes</strong>." : null; ?>
      	<br />But you have set a different value. Please <a href="index.php?action=settings-basic">click here</a> to set the required value.
</div>
<p><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /></p>
<?php
        } else {
            ?>

<div class="row">
	<div class="col-lg-12 text-center">
		<a class="btn btn-primary" href="index.php?action=settings-add-dns-service-provider"><i class="fa fa-plus" aria-hidden="true"></i> Add DNS Service Provider</a>
		<br /><br />
	</div>
</div>

<div class="row">
          <div class="col-lg-12">
            <section class="panel">
              <!-- <header class="panel-heading">
                 Please fill in the following form and click Save
              </header>  -->
              <div class="panel-body">
              
              <div class="panel panel-primary">
                  <div class="panel-heading">DNS Service Providers</div>                  
              	  <div class="panel-content">
              	  	<strong>DNS Service Provider details required only if you want to issue Wildcard SSL certificate.</strong> You may add multiple DNS Service Providers, if applicable.
               	  </div>
              
             <?php if (isset($app_settings['dns_provider']) && \count($app_settings['dns_provider']) > 0) {
                ?>
                          	
             </div>
             
             <div class="table-responsive">        
              <table class="table table-striped table-advance table-hover">
                <thead>
                  <tr>
                  	<th>ID</th>
                  	<th>DNS Service Providers</th>
                    <th>Domain Names</th>
                    <th>API Identifier</th>
                    <th><i class="icon_cogs"></i> Action</th>
                  </tr>
                </thead>
                
                <tbody>
                <?php foreach ($app_settings['dns_provider'] as $key => $provider) {
                    ?>
                  <tr>
                  	<td><?php echo $key; ?></td>
                  	<td><?php echo (false === $provider['name']) ? 'Others' : $provider['name']; ?></td>
                    <td><?php echo implode(', ', $provider['domains']); ?></td>                    
                    <td><?php echo (false === $provider['name']) ? null : ('cPanel' === $provider['name'] ? 'https://'.$app_settings['cpanel_host'].':2083 <br />username: '.$app_settings['username'] : $provider['api_identifier']); ?></td>
                   	<td class="col-xs-2">
                      <div class="btn-group">
                        <a class="btn btn-success" href="index.php?action=settings-add-dns-service-provider&id=<?php echo $key; ?>"><i class="fa fa-pencil" aria-hidden="true"></i> Update</a>
                        <a class="btn btn-danger" href="index.php?action=settings-delete-dns-provider&id=<?php echo $key; ?>" onclick="return confirm('Do you really want to DELETE the domain?');"><i class="fa fa-trash-o" aria-hidden="true"></i> Delete</a>
                      </div>
                    </td>                   
                  </tr>
                  <?php
                } ?>
                </tbody>
              </table>
              </div>
              <?php
            } else {
                ?>
              <p><br /><br />You don't have any DNS Service Provider entry here. Please <a href="index.php?action=settings-add-dns-service-provider">add DNS Service Provider</a> to issue wildcard SSL certificates.<br /><br /></p>
              
              <?php
            } ?>
              
              	  <div class="panel-content"><strong>Who is my DNS Service Provider?</strong></div>
                  
                  <div class="panel-content"><i>Ans: Generally, your web hosting is your DNS Service Provider. If your web hosting control panel is cPanel, please make an entry here. But, if you are using DNS hosting or the basic DNS management service of your domain name registrar while hosting the website on a different server,  the DNS hosting provider or domain registrar is your DNS Service Provider.</i></div>
                  
              <br /><br />
            </div>
            
            </section>
          </div>
        </div>
        <!-- page end-->
<?php
        } ?>
     <?php
    }

    public function addDnsServiceProvidersSettings()
    {
        ?>
       <script type="text/javascript">    
        $(document).ready(function() {
        	var name = $("#name").val();
        	
        	if(name == "cPanel"){
        		$("div#api_identifier").hide();
        	 	$("div#api_credential").hide();
        	 	$("div#confirm_api_credential").hide();
    		}
    		    		
        	if(name == "0"){
        		$("div#api_identifier").hide();
        	 	$("div#api_credential").hide();
        	 	$("div#confirm_api_credential").hide();
        	 	$("div#dns_provider_takes_longer_to_propagate").hide();
    		}
        	            
        	$("#name").change(function(){
            	var name = $("#name").val();

            	if(name == "cPanel"){
            		$("div#api_identifier").hide();
            	 	$("div#api_credential").hide();
            	 	$("div#confirm_api_credential").hide();
            	 	$("div#dns_provider_takes_longer_to_propagate").show();        	 	
        		}
            	else{
        		
            	if(name == "0"){
            		$("div#api_identifier").hide();
            	 	$("div#api_credential").hide();
            	 	$("div#confirm_api_credential").hide();
            	 	$("div#dns_provider_takes_longer_to_propagate").hide();
        		}
            	else{
            		$("div#api_identifier").show();
            	 	$("div#api_credential").show();
            	 	$("div#confirm_api_credential").show();
            	 	$("div#dns_provider_takes_longer_to_propagate").show();
            	}
            	
            	}
        	});
        	
    });    
</script>

<!--page title and breadcrumb start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="fas fa-cogs" aria-hidden="true"></i>Settings : <?php echo isset($_GET['id']) ? 'Update' : 'Add'; ?> DNS Service Provider</h3>
            <ol class="breadcrumb">
              <li><i class="fas fa-home"></i> <a href="index.php">Dashboard</a></li>
              <li><i class="fas fa-server"></i> <a href="index.php?action=settings-dns-service-providers">DNS Service Provider</a></li>
              <li><?php echo isset($_GET['id']) ? "<i class='fas fa-edit'></i> Update" : "<i class='far fa-plus-square'></i> Add"; ?> DNS Service Provider</li>
            </ol>
          </div>
        </div>
  <!--page title and breadcrumb end-->

<?php

if (\defined('APP_SETTINGS_PATH')) {
    $app_settings = $this->app_settings;
} else {
    $app_settings = [];
}

        //Processing form data when form is submitted
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $api_identifier_err = $api_credential_err = $confirm_api_credential_err = $domains_err = $csrf_err = null;

            $token = trim($_POST['csrf']);
            $token = filter_var($token, FILTER_SANITIZE_STRING);

            $csrfVerified = $this->factory->verifyCsrfToken('add-dns-provider', $token);

            if (!$csrfVerified) {
                $csrf_err = $this->csrf_err;
            }

            // Validate domains
            $domains = trim($_POST['domains']);
            if (empty($domains)) {
                $domains_err = 'Please enter the domains registered with this domain registrar.';
            }

            // SANITIZE user inputs

            $name = trim($_POST['name']);
            $name = filter_var($name, FILTER_SANITIZE_STRING);
            $name = ('0' === $name) ? false : $name;

            $domains = str_replace(' ', '', $this->factory->sanitize_string($_POST['domains']));

            if (\strlen($_POST['id'])) {
                $id = (int) filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);
            }

            $dns_provider_takes_longer_to_propagate = (bool) filter_var($_POST['dns_provider_takes_longer_to_propagate'], FILTER_SANITIZE_NUMBER_INT);

            //Validate api_identifier and api_credential if $name !== false

            if (false !== $name) {
                if ('cPanel' !== $name) {
                    $api_identifier = trim($_POST['api_identifier']);
                    $api_identifier = filter_var($api_identifier, FILTER_SANITIZE_STRING);

                    $api_credential = trim($_POST['api_credential']);
                    $api_credential = filter_var($api_credential, FILTER_SANITIZE_STRING);

                    $confirm_api_credential = trim($_POST['confirm_api_credential']);
                    $confirm_api_credential = filter_var($confirm_api_credential, FILTER_SANITIZE_STRING);

                    // Validate api_identifier
                    if (empty($api_identifier)) {
                        $api_identifier_err = 'Please enter API Identifier. It may be API key or API email or API user name.';
                    }

                    // Validate api_credential
                    if (empty($api_credential)) {
                        $api_credential_err = 'Please enter API Credential. It may be API secret or API key, if the API Identifier is an email id.';
                    }

                    // Validate confirm_api_credential
                    if (empty($confirm_api_credential)) {
                        $confirm_api_credential_err = 'Please retype API Credential. It may be API secret or API key, if the API Identifier is an email id.';
                    }

                    if ($api_credential !== $confirm_api_credential) {
                        $confirm_api_credential_err = 'API Credential did not match.';
                        unset($api_credential, $confirm_api_credential);
                    } else {
                        $dns_provider = [
                            'name' => $name,
                            'api_identifier' => $api_identifier,
                            'api_credential' => $this->factory->encryptText($api_credential),
                            'dns_provider_takes_longer_to_propagate' => $dns_provider_takes_longer_to_propagate,
                            'domains' => explode(',', $domains),
                        ];
                    }
                } else {
                    //DNS service provider is cPanel
                    $dns_provider = [
                        'name' => $name,
                        'dns_provider_takes_longer_to_propagate' => $dns_provider_takes_longer_to_propagate,
                        'domains' => explode(',', $domains),
                    ];
                }
            } else {
                $dns_provider = [
                    'name' => $name,
                    'dns_provider_takes_longer_to_propagate' => true,
                    'domains' => explode(',', $domains),
                ];
            }

            //Save only if there is NO error
            if ($csrfVerified && empty($domains_err) && empty($api_identifier_err) && empty($api_credential_err) && empty($confirm_api_credential_err)) {
                if (\strlen($_POST['id'])) {
                    $id = (int) filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT);

                    $app_settings['dns_provider'][$id] = $dns_provider;
                } else {
                    $app_settings['dns_provider'][] = $dns_provider;
                }

                // encode in json and make entry in settings.json if no error

                if (!file_exists(APP_SETTINGS_PATH)) {
                    mkdir(\dirname(APP_SETTINGS_PATH), 0777, true);
                }

                if (!file_put_contents(APP_SETTINGS_PATH, json_encode($app_settings))) {
                    echo "<div class='alert alert-danger' role='alert'>Could not write to the settings.json file (".APP_SETTINGS_PATH.'). Please check that you have write permission to that directory and try again.</div>';
                } else {
                    //redirect to the cron job page if not set

                    //cron initialization
                    $output = shell_exec('crontab -l');

                    //check if the cron job was already added
                    if (false === strpos($output, $this->add_new_cron_base)) {
                        //Save confirmation msg in session before redirect
                        $_SESSION['message'] = [
                            'type' => 'success',
                            'message' => 'Your DNS Service Provider details have been saved successfully! Now please Add Daily Cron Job to issue/renew the SSL certificates automatically.',
                        ];

                        //redirect to cron job page
                        $this->factory->redirect('index.php?action=settings-add-cron');
                    } else {
                        //Save confirmation msg in session before redirect
                        $_SESSION['message'] = [
                            'type' => 'success',
                            'message' => 'Your DNS Service Provider details have been saved successfully!',
                        ];

                        $this->factory->redirect('index.php?action=settings-dns-service-providers');
                    }
                }
            }
        } else {
            //GET request - fill the form with saved data if settings.txt exist

            if (isset($_GET['id'])) {
                $id = (int) filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

                $name = $app_settings['dns_provider'][$id]['name'];

                if (false !== $name) {
                    if ('cPanel' !== $name) {
                        $api_identifier = $app_settings['dns_provider'][$id]['api_identifier'];
                        $api_credential = $this->factory->decryptText($app_settings['dns_provider'][$id]['api_credential']);
                    }

                    $dns_provider_takes_longer_to_propagate = $app_settings['dns_provider'][$id]['dns_provider_takes_longer_to_propagate'];
                }

                $domains = implode(',', $app_settings['dns_provider'][$id]['domains']);
            }
        } ?>


<div class="row">
          <div class="col-lg-12">
            <section class="panel">
              <!-- <header class="panel-heading">
                 Please fill in the following form and click Save
              </header>  -->
              <div class="panel-body">
              
              <?php if ($_SERVER['SERVER_PORT'] != 443) {
            ?>
              
              <div class="alert alert-danger">              
              	This page is not protected with HTTPS and you are going to provide API credentials of your DNS Service Provider over an unencrypted connection. We recommend generating a <a href="tmp-ssl.php" target="_blank">Free SSL Certificate for <strong><?php echo $_SERVER['SERVER_NAME']; ?></strong> with a single click</a>, installing it on this server and then continue with this settings page.<br />
              	You'll need less than 30 seconds to generate this free SSL. <a href="tmp-ssl.php" target="_blank">Please click here!</a>
              </div>
              <br />
              
              <?php
        } ?>
              
              <div class="panel panel-primary">
                  <div class="panel-heading">Please <?php echo isset($_GET['id']) ? 'update' : 'provide'; ?> your DNS Service Provider details and click Save</div>
                                    
                  <div class="panel-content">This app needs your DNS Service Provider details to automatically set DNS TXT record to verify your domain in order to issue wildcard SSL. Supported DNS API: cPanel, GoDaddy, Namecheap and Cloudflare.</div>
                  
                  <div class="panel-content"><strong>If your DNS Service provider is other than cPanel, GoDaddy, Namecheap and Cloudflare, you may skip making entry.</strong> <!-- This app will send you an automated email in order to manually set DNS TXT record, when required. That email will contain the DNS TXT record details, of course. --></div>
                </div>
                            
                <div class="form">
                  <form class="form-validate form-horizontal " id="settings-domain-details" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=settings-add-dns-service-provider">
                                        
                    <div class="form-group ">
                      <label for="domain" class="control-label col-lg-6">Name of the DNS Service Provider <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <select class="form-control m-bot15" name="name" id="name">
                        					  <option<?php echo (isset($name) && 'cPanel' === $name) ? ' selected' : null; ?> value="cPanel">cPanel</option>
                                              <option<?php echo (isset($name) && 'GoDaddy' === $name) ? ' selected' : null; ?> value="GoDaddy">GoDaddy</option>
                                              <option<?php echo (isset($name) && 'Namecheap' === $name) ? ' selected' : null; ?> value="Namecheap">Namecheap</option>
                                              <option<?php echo (isset($name) && 'Cloudflare' === $name) ? ' selected' : null; ?> value="Cloudflare">Cloudflare</option>
                                              <option<?php echo (isset($name) && false === $name) ? ' selected' : null; ?> value="0">Others</option>
                                           </select>
                      </div>
                    </div>
                    
                    
                    <!-- conditional display start -->
                    
                    <div class="form-group <?php echo (!empty($api_identifier_err)) ? 'has-error' : ''; ?>" id="api_identifier">
                      <label for="api_identifier" class="control-label col-lg-6">API Identifier <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class="form-control " id="api_identifier" name="api_identifier" type="text" value="<?php echo isset($api_identifier) ? $api_identifier : null; ?>" placeholder="API key or API email or API user name" />
                      	<span class="help-block"><?php echo isset($api_identifier_err) ? $api_identifier_err : null; ?></span>
                      </div>                      
                    </div>
                    
                    <div class="form-group <?php echo (!empty($api_credential_err)) ? 'has-error' : ''; ?>" id="api_credential">
                      <label for="api_credential" class="control-label col-lg-6">API Credential <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class="form-control " id="api_credential" name="api_credential" type="password" value="<?php echo isset($api_credential) ? $api_credential : null; ?>" placeholder="API secret. Or key, if API Identifier is an email id" />
                      	<span class="help-block"><?php echo isset($api_credential_err) ? $api_credential_err : null; ?></span>
                      </div>                      
                    </div>
                    
                    <div class="form-group <?php echo (!empty($confirm_api_credential_err)) ? 'has-error' : ''; ?>" id="confirm_api_credential">
                      <label for="confirm_api_credential" class="control-label col-lg-6">Confirm API Credential <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class="form-control " id="confirm_api_credential" name="confirm_api_credential" type="password" value="<?php echo isset($api_credential) ? $api_credential : null; ?>" placeholder="Retype API secret or key, if API Identifier is an email id" />
                      	<span class="help-block"><?php echo isset($confirm_api_credential_err) ? $confirm_api_credential_err : null; ?></span>
                      </div>                      
                    </div>
                    
                    <div class="form-group" id="dns_provider_takes_longer_to_propagate">
                    <label class="control-label col-lg-6" for="dns_provider_takes_longer_to_propagate">Does the DNS Service Provider take longer than 2 minutes to propagate?</label>
                    <div class="col-lg-6">
                      <select class="form-control m-bot15" name="dns_provider_takes_longer_to_propagate" id="dns_provider_takes_longer_to_propagate">
                                              <option value="0"<?php echo (isset($dns_provider_takes_longer_to_propagate) && false === $dns_provider_takes_longer_to_propagate) ? ' selected' : null; ?>>No</option>
                                              <option value="1"<?php echo (isset($dns_provider_takes_longer_to_propagate) && true === $dns_provider_takes_longer_to_propagate) ? ' selected' : null; ?>>Yes</option>
                                           </select>
                      
                    	</div>
                    	<span class="col-lg-12">By default this app waits 2 minutes before an attempt to verify DNS-01 challenge. But if your DNS Service provider takes more time to propagate out, set this Yes.<br /> 
                    	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Please keep in mind, depending on the propagation status of the DNS TXT record, this settings may put the app waiting for hours.</span>
                  	</div>
                   <!-- conditional display end --> 
                    
                    <div class="form-group <?php echo (!empty($domains_err)) ? 'has-error' : ''; ?>">
                      <label for="domains" class="control-label col-lg-6">Domain names managed by this DNS Service Provider (separated by comma) - <strong>don't include www. or any sub-domain</strong> <span class="required">*</span></label>
                      <div class="col-lg-6">
                        	<input class=" form-control" id="domains" name="domains" type="text" required="required" value="<?php echo isset($domains) ? $domains : null; ?>" placeholder="separated by comma, don't include www. or any sub-domain" />
                      </div>
                      <span class="help-block"><?php echo isset($domains_err) ? $domains_err : null; ?></span>
                    </div>                    
                    
                    <input type="hidden" name="id" value="<?php echo isset($id) ? $id : null; ?>">                   
                    
                    <div class="input-group <?php echo (!empty($csrf_err)) ? 'has-error' : ''; ?>">
                      <input type="hidden" name="csrf" value="<?php echo $this->factory->getCsrfToken('add-dns-provider'); ?>">
                      <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
                    </div>
                    
                    <div class="form-group">
                      <div class="col-lg-offset-6 col-lg-6">
                        <button class="btn btn-primary" type="submit" id="submit">Save</button>
                        
                        <a class="btn btn-default" type="button" href="#" onclick="window.history.go(-1); return false;">Cancel</a>
                        
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </section>
          </div>
        </div>
        <!-- page end-->
        
        
     <?php
    }

    public function deleteDnsProvidersSettings()
    {
        ?>
      <!--page title and breadcrumb start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="fas fa-cogs" aria-hidden="true"></i>Settings - Delete DNS Service Provider</h3>
            <ol class="breadcrumb">
              <li><i class="fas fa-home"></i><a href="index.php">Dashboard</a></li>
              <li><i class="fas fa-cogs"></i>Settings - Delete DNS Service Provider</li>
            </ol>
          </div>
        </div>
  <!--page title and breadcrumb end-->

<?php

if (isset($_GET['id'])) {
    $id = (int) filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

    if (\defined('APP_SETTINGS_PATH')) {
        $app_settings = $this->app_settings;

        //delete the dns_provider array which key is $id
        unset($app_settings['dns_provider'][$id]);

        //Save the updated app settings

        if (!file_put_contents(APP_SETTINGS_PATH, json_encode($app_settings))) {
            echo "<div class='alert alert-danger' role='alert'>Could not write to the settings.json file (".APP_SETTINGS_PATH.'). Please check that you have write permission to that directory and try again.</div>';
        } else {
            //Save confirmation msg in session before redirect
            $_SESSION['message'] = [
                'type' => 'success',
                'message' => "The DNS Service Provider (ID: ${id}) has been deleted successfully!",
            ];

            $this->factory->redirect('index.php?action=settings-dns-service-providers');
        }
    }
}
    }

    public function addCronJobSettings()
    {
        ?>
       <!--page title and breadcrumb start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="far fa-calendar-alt"></i> Settings : Add Cron Job</h3>
            <ol class="breadcrumb">
              <li><i class="fas fa-home"></i> <a href="index.php">Dashboard</a></li>
              <li><i class="far fa-calendar-alt"></i> Settings : Add Cron Job</li>
            </ol>
          </div>
        </div>
  <!--page title and breadcrumb end-->

<?php

//Processing form data when form is submitted
if ('POST' === $_SERVER['REQUEST_METHOD']) {
    $email_err = $csrf_err = null;

    $token = trim($_POST['csrf']);
    $token = filter_var($token, FILTER_SANITIZE_STRING);

    $csrfVerified = $this->factory->verifyCsrfToken('add-cron', $token);

    if (!$csrfVerified) {
        $csrf_err = $this->csrf_err;
    }

    if ($csrfVerified) {
        //START create cron job

        //cron initialization
        $output = shell_exec('crontab -l');

        $cron_file = __DIR__.'/crontab.txt';

        $add_new_cron_base = $this->add_new_cron_base;

        $email = trim($_POST['email']);

        if (!empty($email)) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                //Email is valid.

                $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);

                //check if $output contain "MAILTO="
                if (false === strpos($output, 'MAILTO=')) {
                    //$output doesn't contain it. add 'MAILTO="user@gmail.com"' at beginning of $output

                    $output = 'MAILTO="'.$email.'"\n'.$output;
                }
            } else {
                $email_err = 'Please enter a valid email.';
                $email = trim($_POST['email']);
            }

            $add_new_cron = $add_new_cron_base;
        } else {
            //notification not required
            //add  >/dev/null 2>&1 at the end of the cron job
            $add_new_cron = $add_new_cron_base.' >/dev/null 2>&1';
        }

        //check if the cron job was already added
        if (false === strpos($output, $add_new_cron_base)) {
            $add_cron = trim($output.$add_new_cron);

            if (file_put_contents($cron_file, $add_cron.PHP_EOL)) {
                $output = [];

                //$return_var = 1 means error. $return_var = 0 means success.
                exec("crontab ${cron_file}", $output, $return_var);

                if (1 === $return_var) {
                    //Save error msg in session before redirect
                    $_SESSION['message'] = [
                        'type' => 'error',
                        'message' => 'Sorry, the cron job was not added due to an error. Please try again or add cron job by log into your cPanel or web hosting control panel.<br />'.implode('<br />', $output),
                    ];
                } elseif (0 === $return_var) {
                    //Save confirmation msg in session before redirect
                    $_SESSION['message'] = [
                        'type' => 'success',
                        'message' => 'Cron job has been added successfully!<br />'.implode('<br />', $output),
                    ];
                }
            } else {
                //Save error msg in session before redirect
                $_SESSION['message'] = [
                    'type' => 'error',
                    'message' => 'Sorry, the cron job was not added. Please try again or add cron job by log into your cPanel or web hosting control panel.',
                ];
            }
        } else {
            //Save confirmation msg in session before redirect
            $_SESSION['message'] = [
                'type' => 'success',
                'message' => 'The cron job was added already.',
            ];
        }

        unlink($cron_file);
        //END create cron job

        $this->factory->redirect('index.php?action=index');
    }
} ?>


<div class="row">
          <div class="col-lg-12">
            <section class="panel">
              <!--  <header class="panel-heading">
                Please fill in the following form and click Save
              </header>  -->
              <div class="panel-body">
              
              <div class="panel panel-primary">
              
              <?php
              $common_text = "Daily Cron job will issue/renew the SSL certificates automatically. <strong>If your cPanel have SSL installation feature enabled, this app will install the issued SSL automatically.</strong> 
                  	That means complete automation of your free SSL certificates even in shared hosting cPanel. 
					If your cPanel doesn't have SSL installation feature, please contact your web hosting provider for SSL installation. 
                  	You will receive an automated email for issue/renewal of SSL. The email will contain the path to SSL files. You can 
                  	simply copy-paste path of SSL files and send it to your web hosting provider for installation.";

        //check whether shell_exec and exec function is enabled. check with function_exists
        //If not enabled, don't display the form.
        //instead, display tips - how to add cron

        if (\function_exists('shell_exec') && \function_exists('exec')) {
            ?>
              
                  <div class="panel-heading">Add Daily Cron Job - please click the button below</div>
                  
                  <div class="panel-content">
                  	This is a quick option to add a cron job which will run this app every day at 12:00 a.m. midnight. 
                  	If you want the cron job to run at a different time, please use the cron job option of your cPanel / web hosting control panel instead. Please remember to set the cron job once every day. 
                  	Path of the cron file: <strong><?php echo $this->app_cron_path; ?></strong>					
                  </div>
                  
                  <div class="panel-content">
                  	<?php echo $common_text; ?>
                  </div>
                  
                  <div class="panel-content">
                  	
                  <br /><br /></div>
                 
              
                <div class="form">
                  <form class="form-validate form-horizontal " id="settings-basic" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=settings-add-cron">
                     
                     <div class="form-group">                    	                     
                          <div class="col-lg-6 col-sm-6 text-right">
                          	<label for="notify" class="control-label">Notify me every time the cron job runs; to this email id </label>
                          </div>
                                                   
                          <div class="col-lg-6 col-sm-6 <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">                          	                    
                            <input class="form-control " id="email" name="email" type="email" value="<?php echo isset($email) ? $email : null; ?>" placeholder="Keep this blank if you don't want to receive notification email" />
                          	<span class="help-block"><?php echo isset($email_err) ? $email_err : null; ?></span>                      
                          </div>                   
                    </div>
                    
                    <div class="input-group <?php echo (!empty($csrf_err)) ? 'has-error' : ''; ?>">
                      <input type="hidden" name="csrf" value="<?php echo $this->factory->getCsrfToken('add-cron'); ?>">
                      <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
                    </div>
                                       
                    <div class="form-group">
                      <div class="col-lg-offset-6 col-lg-6">
                        <button class="btn btn-success btn-lg" type="submit">Add Daily Cron Job at 12:00 a.m. midnight</button>
                        
                        <a class="btn btn-default" type="button" href="#" onclick="window.history.go(-1); return false;">Cancel</a>
                        
                      </div>
                    </div>
                  </form>
                </div>
                
                <?php
        } else {
            //Either shell_exec or exec is not enabled?>
                
                <div class="panel-heading">Please Add Daily Cron Job</div>
                  
                  <div class="panel-content">
                  	Please log in to your cPanel / web hosting control panel and Add a Daily Cron Job that will run this app once everyday. 
                  	Path of the cron file: <strong><?php echo $this->app_cron_path; ?></strong>
                  	</div>
                  	
                  	<div class="panel-content">
                  	<?php echo $common_text; ?>
                   	</div>
                  	
                  	<div class="panel-content">                  	
                  	This app has a a quick option to add a cron job with one click, which will run this app every day at 12:00 a.m. midnight. 
                  	But you hosting account doesn't has <?php echo !\function_exists('shell_exec') ? "'shell_exec'," : null; ?> 
                  	<?php echo !\function_exists('exec') ? "'exec'" : null; ?> enabled. So please log in to your cPanel / web hosting control panel 
                  	and set up the cron job.                	
                  					
                  </div>
                                    
                  
                  <div class="panel-content">
                  	
                  <br /><br /><br /></div>
                
                
                <?php
        } ?>
              </div>
            </section>
          </div>
        </div>
        <!-- page end-->
        
    <?php
    }

    public function revokeSsl()
    {
        ?>
     <!--page title and breadcrumb start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="fas fa-times-circle"></i>Revoke SSL Certificate</h3>
            <ol class="breadcrumb">
              <li><i class="fas fa-home"></i> <a href="index.php">Dashboard</a></li>
              <li><i class="fas fa-times-circle"></i> Revoke SSL Certificate</li>
            </ol>
          </div>
        </div>
  <!--page title and breadcrumb end-->

<?php

if (\defined('APP_SETTINGS_PATH')) {
    $app_settings = $this->app_settings;
} else {
    $app_settings = [];
}

        //get the path of SSL files
        $certificates_directory = $this->acmeFactory->getCertificatesDir();

        //get the domains for which SSL is present in the directory
        $all_domains = $this->factory->getExistingSslList($certificates_directory);

        $display_form = true;

        //Processing form data when form is submitted
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $csrf_err = null;

            $token = trim($_POST['csrf']);
            $token = filter_var($token, FILTER_SANITIZE_STRING);

            $csrfVerified = $this->factory->verifyCsrfToken('revoke-ssl', $token);

            if (!$csrfVerified) {
                $csrf_err = $this->csrf_err;
            }

            // SANITIZE user inputs

            $domains_array = [];

            foreach ($_POST['domains'] as $domain) {
                $domains_array[] = $this->factory->sanitize_string($domain);
            }

            $app_settings['domains_to_revoke_cert'] = $domains_array;

            if ($csrfVerified) {
                $display_form = false;

                // encode in json and make entry in settings.json

                if (!file_exists(APP_SETTINGS_PATH)) {
                    mkdir(\dirname(APP_SETTINGS_PATH), 0777, true);
                }

                if (!file_put_contents(APP_SETTINGS_PATH, json_encode($app_settings))) {
                    echo "<div class='alert alert-danger' role='alert'>Could not write to the settings.json file (".APP_SETTINGS_PATH.'). Please check that you have write permission to that directory and try again.</div>';
                } else {
                    echo 'First step was executed successfully.<br /><br />';

                    // Revoke SSL - step 2

                    //define REVOKE_CERT true
                    \define('REVOKE_CERT', true);
                    //other constants should be false
                    \define('KEY_CHANGE', false);
                    \define('ISSUE_SSL', false);

                    $freeSsl = new FreeSSLAuto($app_settings);

                    //Run the App
                    $freeSsl->run();
                }
            }
        }

        if ($display_form) {
            //Display the form when required?>


<div class="row">
          <div class="col-lg-12">
            <section class="panel">
              <!--  <header class="panel-heading">
                Please fill in the following form and click Save
              </header>  -->
              <div class="panel-body">
              
              <div class="panel panel-primary">
                  <div class="panel-heading">Please select the domains/sub-domains for which you want to revoke SSL certificate</div>
                  
                  <div class="panel-content">This app issued SSL certificate for the following domains/sub-domains.                  
                   
                  If you want to revoke any of these SSL certificates, please select and click 'Revoke SSL' button.<br /><br /></div>
                  
              
                <div class="form">
                  <form class="form-validate form-horizontal " id="settings-basic" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=revoke-ssl">
                    
                  	<?php foreach ($all_domains as $domain) {
                ?>
                    <div class="form-group ">
                    	<div class="col-lg-1 col-sm-1">
                        	<input type="checkbox" style="width: 20px" class="checkbox form-control" id="domains" name="domains[]" value="<?php echo $domain; ?>" <?php echo (isset($domains_array) && \in_array($domain, $domains_array, true)) ? ' checked' : null; ?> />
                      	</div>
                      
                      <div class="col-lg-11 col-sm-11 text-left">
                      	<label for="domains" class="control-label"><?php echo $domain; ?></label>
                      </div>
                      
                    </div>
                    <?php
            } ?>
                    
                    <div class="input-group <?php echo (!empty($csrf_err)) ? 'has-error' : ''; ?>">
                      <input type="hidden" name="csrf" value="<?php echo $this->factory->getCsrfToken('revoke-ssl'); ?>">
                      <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
                    </div>
                    
                    <div class="form-group">
                      <div class="col-lg-offset-12 col-lg-12">
                        <button class="btn btn-primary" type="submit">Revoke SSL</button>
                        
                        <a class="btn btn-default" type="button" href="#" onclick="window.history.go(-1); return false;">Cancel</a>
                        
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </section>
          </div>
        </div>
        <!-- page end-->

     <?php
        }
    }

    public function keyChange()
    {
        //define KEY_CHANGE true
        \define('KEY_CHANGE', true);
        //other constants should be false
        \define('ISSUE_SSL', false);
        \define('REVOKE_CERT', false);

        $freeSsl = new FreeSSLAuto($this->app_settings);

        //Run the App
        $freeSsl->run();
    }

    public function changePassword()
    {
        ?>
     <!--page title and breadcrumb start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="fas fa-key"></i> Change Password</h3>
            <ol class="breadcrumb">
              <li><i class="fas fa-home"></i> <a href="index.php">Dashboard</a></li>
              <li><i class="fas fa-key"></i> Change Password</li>
            </ol>
          </div>
        </div>
  <!--page title and breadcrumb end-->

<?php

//Processing form data when form is submitted
if ('POST' === $_SERVER['REQUEST_METHOD']) {
    $current_password_err = $new_password_err = $confirm_new_password_err = $csrf_err = null;

    $token = trim($_POST['csrf']);
    $token = filter_var($token, FILTER_SANITIZE_STRING);

    $csrfVerified = $this->factory->verifyCsrfToken('change-password', $token);

    if (!$csrfVerified) {
        $csrf_err = $this->csrf_err;
    }

    $current_password = trim($_POST['current_password']);
    $current_password = filter_var($current_password, FILTER_SANITIZE_STRING);

    // Validate user input
    if (empty($current_password)) {
        $current_password_err = 'Please enter your current password.';
    }

    $new_password = trim($_POST['new_password']);
    $new_password = filter_var($new_password, FILTER_SANITIZE_STRING);

    // Validate new password
    if (empty($new_password)) {
        $new_password_err = 'Please enter new password.';
    } elseif (\strlen(trim($_POST['new_password'])) < 8) {
        $new_password_err = 'Password must have atleast 8 characters.';
    } else {
        $confirm_new_password = trim($_POST['confirm_new_password']);
        $confirm_new_password = filter_var($confirm_new_password, FILTER_SANITIZE_STRING);

        // Validate confirm new password
        if (empty($confirm_new_password)) {
            $confirm_new_password_err = 'Please re-enter the password.';
        } else {
            if ($new_password !== $confirm_new_password) {
                $confirm_new_password_err = 'New password did not match.';
                //unset($new_password);
                unset($confirm_new_password);
            }
        }
    }

    //Change the password only if there is NO error
    if ($csrfVerified && empty($current_password_err) && empty($new_password_err) && empty($confirm_new_password_err)) {
        //Prepare select statement
        $sql = 'SELECT password FROM users WHERE email = ?';

        $mysqli = $this->mysqli;

        if ($stmt = $mysqli->prepare($sql)) {
            //Bind variables to the prepared statement as parameters
            $stmt->bind_param('s', $_SESSION['email']);

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();

                // Check if username exists, if yes then verify password
                if (1 === $stmt->num_rows) {
                    // Bind result variables
                    $stmt->bind_result($hashed_password);

                    if ($stmt->fetch()) {
                        if (password_verify($current_password, $hashed_password)) {
                            // Current password is correct, so change the password

                            //Make database entry

                            $sql = 'UPDATE users SET password = ? WHERE email = ?';

                            if ($stmt = $mysqli->prepare($sql)) {
                                //Create password hash
                                $param_password = password_hash($new_password, PASSWORD_DEFAULT);

                                //Bind variables to the prepared statement as parameters
                                $stmt->bind_param('ss', $param_password, $_SESSION['email']);

                                //Attempt to execute the prepared statement
                                if ($stmt->execute()) {
                                    //SUCCESS

                                    //Save confirmation msg in session before redirect
                                    $_SESSION['message'] = [
                                        'type' => 'success',
                                        'message' => 'Your password has been changed successfully!',
                                    ];

                                    $this->factory->redirect('index.php');
                                } else {
                                    echo 'Sorry! The password was not updated.<br /><br />';
                                    echo $stmt->error;
                                }
                            }
                        } else {
                            // Display an error message if password is not valid
                            $current_password_err = 'The current password you entered is not valid.';
                            unset($current_password);
                        }
                    }
                } else {
                    // Display an error message if username doesn't exist
                    echo 'No account found with this email: '.$_SESSION['email'];
                }
            } else {
                echo 'Oops! Something went wrong. Please try again later.';
            }
        }

        // Close statement
        $stmt->close();

        // Close connection
        $mysqli->close();
    }
} ?>


<div class="row">
          <div class="col-lg-12">
            <section class="panel">
              <!-- <header class="panel-heading">
                 Please fill in the following form and click Save
              </header>  -->
              <div class="panel-body">
              
              <div class="panel panel-primary">
                  <div class="panel-heading">Change Your Password</div>
                  <div class="panel-content">Please fill in this form to change your password.</div>
                </div>
                            
                <div class="form">
                  <form class="form-validate form-horizontal " id="settings-domain-details" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=change-password">
                    
                    <div class="form-group <?php echo (!empty($current_password_err)) ? 'has-error' : ''; ?>" id="current_password">
                      <label for="current_password" class="control-label col-lg-6">Current Password <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class="form-control " id="current_password" name="current_password" type="password" value="<?php echo isset($current_password) ? $current_password : null; ?>" placeholder="Your current password" required="required" />
                      	<span class="help-block"><?php echo isset($current_password_err) ? $current_password_err : null; ?></span>
                      </div>                      
                    </div>
                    
                    <div class="form-group <?php echo (!empty($new_password_err)) ? 'has-error' : ''; ?>" id="new_password">
                      <label for="new_password" class="control-label col-lg-6">New Password <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class="form-control " id="new_password" name="new_password" type="password" value="<?php echo isset($new_password) ? $new_password : null; ?>" placeholder="Type new password" required="required" />
                      	<span class="help-block"><?php echo isset($new_password_err) ? $new_password_err : null; ?></span>
                      </div>                      
                    </div>
                    
                    <div class="form-group <?php echo (!empty($confirm_new_password_err)) ? 'has-error' : ''; ?>" id="confirm_new_password">
                      <label for="confirm_new_password" class="control-label col-lg-6">Confirm new Password <span class="required">*</span></label>
                      <div class="col-lg-6">
                        <input class="form-control " id="confirm_new_password" name="confirm_new_password" type="password" value="<?php echo isset($confirm_new_password) ? $confirm_new_password : null; ?>" placeholder="Retype the new password" required="required" />
                      	<span class="help-block"><?php echo isset($confirm_new_password_err) ? $confirm_new_password_err : null; ?></span>
                      </div>                      
                    </div>
                    
                    <div class="input-group <?php echo (!empty($csrf_err)) ? 'has-error' : ''; ?>">
                      <input type="hidden" name="csrf" value="<?php echo $this->factory->getCsrfToken('change-password'); ?>">
                      <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
                    </div>
                    
                    <div class="form-group">
                      <div class="col-lg-offset-6 col-lg-6">
                        <button class="btn btn-primary" type="submit" id="submit">Change Password</button>
                        
                        <a class="btn btn-default" type="button" href="#" onclick="window.history.go(-1); return false;">Cancel</a>
                        
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </section>
          </div>
        </div>
        <!-- page end-->
        
     
     <?php
    }

    public function updateProfile()
    {
        ?>
     <script type="text/javascript">    
        $(document).ready(function() {

        	$("form").submit(function(){

        		var email = $("input#email").val();        		

				if(email.length > 0){

					var password = $("input#current_password").val();

					if(password.length == 0){

						alert("Password is mandatory as you are going to change your email id");

						return false;

					}

					return confirm("Do you really want to change your login id to "+ email +"?");

				}

        	});	

    });    
</script>


<!--page title and breadcrumb start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="fas fa-user-edit"></i> Update Profile</h3>
            <ol class="breadcrumb">
              <li><i class="fas fa-home"></i><a href="index.php">Dashboard</a></li>
              <li><i class="fas fa-user-edit"></i> Update Profile</li>
            </ol>
          </div>
        </div>
  <!--page title and breadcrumb end-->

<?php

//Processing form data when form is submitted
if ('POST' === $_SERVER['REQUEST_METHOD']) {
    $full_name_err = $email_err = $current_password_err = $csrf_err = null;

    $redirect = false;

    $message = '';

    $token = trim($_POST['csrf']);
    $token = filter_var($token, FILTER_SANITIZE_STRING);

    $csrfVerified = $this->factory->verifyCsrfToken('update-profile', $token);

    if (!$csrfVerified) {
        $csrf_err = $this->csrf_err;
    }

    $full_name = trim($_POST['full_name']);
    $full_name = filter_var($full_name, FILTER_SANITIZE_STRING);

    $mysqli = $this->mysqli;

    // Update the Full name only if the field is filled in

    if ($csrfVerified && !empty($full_name)) {
        //Make the database entry

        $sql = 'UPDATE users SET full_name = ? WHERE email = ?';

        if ($stmt = $mysqli->prepare($sql)) {
            //Bind variables to the prepared statement as parameters
            $stmt->bind_param('ss', $full_name, $_SESSION['email']);

            //Attempt to execute the prepared statement
            if ($stmt->execute()) {
                //SUCCESS

                $redirect = true;

                $message .= 'Your Full name has been changed successfully!';
            } else {
                $redirect = false;

                echo 'Sorry! The Full name was not updated due to an error.<br /><br />';
                echo $stmt->error;
            }
        }
    }

    $email = trim($_POST['email']);
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);

    $current_password = trim($_POST['current_password']);
    $current_password = filter_var($current_password, FILTER_SANITIZE_STRING);

    // Update the email id only if the field is filled in

    if ($csrfVerified && !empty($email)) {
        //Update

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            //Email is valid.

            //Before update DB, check if the password is correct
            if (empty($current_password)) {
                $current_password_err = 'Password is mandatory, as you are going to change your email id.';
            } else {
                //Prepare select statement
                $sql = 'SELECT id, password FROM users WHERE email = ?';

                if ($stmt = $mysqli->prepare($sql)) {
                    //Bind variables to the prepared statement as parameters
                    $stmt->bind_param('s', $_SESSION['email']);

                    // Attempt to execute the prepared statement
                    if ($stmt->execute()) {
                        // Store result
                        $stmt->store_result();

                        // Check if username exists, if yes then verify password
                        if (1 === $stmt->num_rows) {
                            // Bind result variables
                            $stmt->bind_result($id, $hashed_password);

                            if ($stmt->fetch()) {
                                if (password_verify($current_password, $hashed_password)) {
                                    // Current password is correct, so change the password

                                    //Make the database entry

                                    $sql = 'UPDATE users SET email = ? WHERE id = ?';

                                    if ($stmt = $mysqli->prepare($sql)) {
                                        //Bind variables to the prepared statement as parameters
                                        $stmt->bind_param('ss', $email, $id);

                                        //Attempt to execute the prepared statement
                                        if ($stmt->execute()) {
                                            //SUCCESS

                                            $redirect = true;

                                            $_SESSION['email'] = $email;

                                            $message .= ' Your Email id (login id) has been changed successfully!';
                                        } else {
                                            $redirect = false;

                                            echo 'Sorry! The password was not updated due to an error.<br /><br />';
                                            echo $stmt->error;
                                        }
                                    }
                                } else {
                                    $redirect = false;

                                    // Display an error message if password is not valid
                                    $current_password_err = 'The current password you entered is not valid.';
                                    unset($current_password);
                                }
                            }
                        } else {
                            $redirect = false;

                            // Display an error message if username doesn't exist
                            echo 'No account found with this email: '.$_SESSION['email'];
                        }
                    } else {
                        $redirect = false;

                        echo 'Oops! Something went wrong. Please try again later.';
                    }
                }

                // Close statement
                $stmt->close();

                // Close connection
                $mysqli->close();
            }
        } else {
            $redirect = false;

            $email_err = 'Please enter a valid email.';
        }
    }

    if ($redirect) {
        //Save confirmation msg in session before redirect
        $_SESSION['message'] = [
            'type' => 'success',
            'message' => $message,
        ];

        $this->factory->redirect('index.php');
    }
} ?>


<div class="row">
          <div class="col-lg-12">
            <section class="panel">
              <!-- <header class="panel-heading">
                 Please fill in the following form and click Save
              </header>  -->
              <div class="panel-body">
              
              <div class="panel panel-primary">
                  <div class="panel-heading">Update Your Profile</div>
                  <div class="panel-content">Leave blank to keep either name or email id unchanged.</div>
                </div>
                            
                <div class="form">
                  <form class="form-validate form-horizontal " id="settings-domain-details" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=update-profile">
                    
                    <div class="form-group <?php echo (!empty($full_name_err)) ? 'has-error' : ''; ?>" id="full_name">
                      <label for="full_name" class="control-label col-lg-6">Full name </label>
                      <div class="col-lg-6">
                        <input class="form-control " id="full_name" name="full_name" type="text" value="<?php echo isset($full_name) ? $full_name : null; ?>" placeholder="Your full name" />
                      	<span class="help-block"><?php echo isset($full_name_err) ? $full_name_err : null; ?></span>
                      </div>                      
                    </div>
                    
                    <div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>" id="email">
                      <label for="email" class="control-label col-lg-6">Email id (login id)</label>
                      <div class="col-lg-6">
                        <input class="form-control " id="email" name="email" type="email" value="<?php echo isset($email) ? $email : null; ?>" placeholder="This will be your login id" />
                      	<span class="help-block"><?php echo isset($email_err) ? $email_err : null; ?></span>
                      </div>                      
                    </div>
                    
                    <div class="form-group <?php echo (!empty($current_password_err)) ? 'has-error' : ''; ?>" id="current_password">
                      <label for="current_password" class="control-label col-lg-6">Your Password (if you want to change your Email id)</label>
                      <div class="col-lg-6">
                        <input class="form-control " id="current_password" name="current_password" type="password" value="<?php echo isset($current_password) ? $current_password : null; ?>" placeholder="Required only if you want to change your Email id (login id)" />
                      	<span class="help-block"><?php echo isset($current_password_err) ? $current_password_err : null; ?></span>
                      </div>                      
                    </div>
                    
                    <div class="input-group <?php echo (!empty($csrf_err)) ? 'has-error' : ''; ?>">
                      <input type="hidden" name="csrf" value="<?php echo $this->factory->getCsrfToken('update-profile'); ?>">
                      <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
                    </div>
                    
                    <div class="form-group">
                      <div class="col-lg-offset-6 col-lg-6">
                        <button class="btn btn-primary" type="submit" id="submit">Update Profile</button>
                        
                        <a class="btn btn-default" type="button" href="#" onclick="window.history.go(-1); return false;">Cancel</a>
                        
                      </div>
                    </div>
                  </form>
                </div>
              </div>
            </section>
          </div>
        </div>
        <!-- page end-->
        
     
     <?php
    }

    public function pageNotFound()
    {
        ?>
     <h1 class="text-center" style="font-weight: bold;"><br /><br /><br />Oops! The requested page was not found.<br /><br /><br /><br /><br /><br /><br /><br /></h1>
     <?php
    }
}
