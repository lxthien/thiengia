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

class Layout
{
    public function __construct()
    {
    }

    public function header()
    {
        ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="FreeSSL Auto - complete automation of Let's Encrypt SSL in shared hosting cPanel">
<meta name="author" content="SpeedUpWebsite.info">

<title>FreeSSL Auto - complete automation of Let's Encrypt SSL in shared hosting cPanel</title>
            
  <!-- Bootstrap CSS -->
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <!-- bootstrap theme -->
  <link href="css/bootstrap-theme.css" rel="stylesheet">
  <!--external css-->
  <!-- font icon -->
  <link href="css/elegant-icons-style.css" rel="stylesheet" />
  
  <link href="css/font-awesome-5.1.0.min.css" rel="stylesheet" />
    
  <link href="css/widgets.css" rel="stylesheet">
  <link href="css/style.css" rel="stylesheet">
  <link href="css/style-responsive.css" rel="stylesheet" />
  
  <link href="css/jquery-ui-1.10.4.min.css" rel="stylesheet">
            
  <!-- javascripts -->
  <script src="js/jquery.js"></script>
  <script src="js/jquery-ui-1.10.4.min.js"></script>
  <script src="js/jquery-1.8.3.min.js"></script>
  <script type="text/javascript" src="js/jquery-ui-1.9.2.custom.min.js"></script>
  
  <!-- bootstrap JS -->
  <script src="js/bootstrap.min.js"></script>
  
  <!-- nice scroll -->
  <script src="js/jquery.scrollTo.min.js"></script>
  <script src="js/jquery.nicescroll.js" type="text/javascript"></script>

    <!--custome script for all page-->
    <script src="js/scripts.js"></script>
    
    <script src="js/jquery-jvectormap-1.2.2.min.js"></script>
    <script src="js/jquery-jvectormap-world-mill-en.js"></script>
    
    <script src="js/jquery.autosize.min.js"></script>
    <script src="js/jquery.placeholder.min.js"></script>
    <script src="js/gdp-data.js"></script>
    <script src="js/morris.min.js"></script>
    <script src="js/sparklines.js"></script>
    
    <script src="js/jquery.slimscroll.min.js"></script>
   
</head>

<body>
<!-- container section start -->
<section id="container" class="">

<!--header start-->
<header class="header dark-bg">
<div class="toggle-nav">
<div class="icon-reorder tooltips" data-original-title="Toggle Navigation" data-placement="bottom"><i class="icon_menu"></i></div>
</div>

<!--logo start-->
<a href="https://freessl.tech" target="_blank" class="logo">FreeSSL.tech <span class="lite">Auto</span></a>

<!--logo end-->


<div class="nav search-row top-menu">
<p>Auto installs Free SSL certificate in shared hosting cPanel. <br />Powered by <a href="https://letsencrypt.org" target="_blank">Let's Encrypt</a>, <a href="https://speedify.tech" target="_blank">SpeedUpWebsite.info</a> and <a href="https://getwww.me" target="_blank">GetWww.me</a></p>
    
      </div>
        
      <div class="top-nav notification-row">
    
        <!-- notificatoin dropdown start-->
        <ul class="nav pull-right top-menu">

        <!-- facebook, twitter -->
        <li id="task_notificatoin_bar">                  
            <span id="tweet-button"><i class="fab fa-twitter"></i>&nbsp;&nbsp;Tweet</span>
            <span id="fb-share-button"><i class="fab fa-facebook-f"></i>&nbsp;&nbsp;Share</span>
            <span id="fb-like-button"><i class="fab fa-facebook-f"></i>&nbsp;&nbsp;Like</span>
        </li>

          <!-- user login dropdown start-->
          <li class="dropdown">
            <a data-toggle="dropdown" class="dropdown-toggle" href="#">
                            <span class="profile-ava">
                                <i class="fas fa-user"></i>
                            </span>
                            <span class="username">&nbsp;<?php echo $_SESSION['email']; ?></span>
                            <b class="caret"></b>
                        </a>
            <ul class="dropdown-menu extended logout">
              <div class="log-arrow-up"></div>
              <li class="eborder-top">
                <a href="index.php?action=update-profile"><i class="fas fa-user-edit"></i> Update Profile</a>
              </li>
              <li>
                <a href="index.php?action=change-password"><i class="fas fa-key"></i> Change Password</a>
              </li>
                                
              <li>
                <a href="login.php?action=logout"><i class="fas fa-unlock"></i> Log Out</a>
              </li>
              <li>
                <a href="https://freessl.tech/documentation-free-ssl-certificate-automation" target="_blank"><i class="fas fa-book"></i> Documentation</a>
              </li>
                                
            </ul>
          </li>
          <!-- user login dropdown end -->
        </ul>
        <!-- notificatoin dropdown end-->
      </div>
    </header>
    <!--header end-->
    <?php
    }

    public function footer()
    {
        ?>
</section>
<!-- Footer start -->
<div class="text-center">   
	<p>&copy; Copyright 2018 <a href="https://www.linkedin.com/in/anindyasm" target="_blank">Anindya Sundar Mandal</a>, All rights reserved.</p>
   
	<p>Powered by <a href="https://speedify.tech" target="_blank">SpeedUpWebsite.info</a>, <a href="https://getwww.me" target="_blank">GetWww.me</a> and <a href="https://letsencrypt.org" target="_blank">Let's Encrypt</a>.</p> 
</div>
</section>
    <!--main content end-->
  </section>
  <!-- container section end -->
</body>
</html>    
    <?php
    }

    public function sidebar()
    {
        ?>
<!--sidebar start-->
    <aside>
      <div id="sidebar" class="nav-collapse ">
        <!-- sidebar menu start-->
        <ul class="sidebar-menu">
          <li class="active">
            <a class="" href="index.php">
                          <i class="fas fa-home"></i>
                          <span>Dashboard</span>
                      </a>
          </li>
          
          
          
          <li class="sub-menu">
            <a href="javascript:;" class="">
                          <i class="fas fa-cogs"></i>
                          <span>Settings</span>
                          <span class="menu-arrow arrow_carrot-right"></span>
                      </a>
            <ul class="sub">
              <li><a class="" href="index.php?action=settings-basic"><i class="fas fa-power-off"></i> Basic</a></li>
              
              <?php

                  if (\defined('APP_SETTINGS_PATH')) {
                      $settings = file_get_contents(APP_SETTINGS_PATH);
                      $app_settings = json_decode($settings, true);

                      if ($app_settings['is_cpanel']) {
                          ?>
              <li><a class="" href="index.php?action=settings-cpanel"><i class="fab fa-cpanel" style="font-size: 35px;"></i> cPanel</a></li>
              
              <li><a class="" href="index.php?action=settings-cpanel-domains-to-exclude"><i class="fas fa-minus-circle"></i> Exclude Domains</a></li>
              
              <?php
                      } else {
                          ?>
              
              <li><a class="" href="index.php?action=settings-domains"><i class="fas fa-globe"></i> Domains</a></li>
              
              <li><a class="" href="index.php?action=settings-add-domain"><i class="fas fa-plus-circle"></i> Add Domain</a></li>
              
              <?php
                      } ?>
              
              <?php if (2 === $app_settings['acme_version'] && $app_settings['use_wildcard']) {
                          ?>
              
              <li><a class="" href="index.php?action=settings-dns-service-providers"><i class="fas fa-server"></i> DNS Service</a></li>
              
              <?php
                      } ?>
              
              <li><a class="" href="index.php?action=settings-add-cron"><i class="far fa-calendar-alt"></i> Add Cron Job</a></li>
              
              <?php
                  } ?>
              
            </ul>
          </li>
                   
          <?php if (\defined('APP_SETTINGS_PATH')) {
                      ?>
          
          <li>
            <a class="" href="index.php?action=issue-free-ssl">
                          <i class="fas fa-lock"></i>
                          <span>Issue Free SSL</span>
                      </a>
          </li>
          
          <li>
            <a class="" href="index.php?action=change-le-account-key" onclick="return confirm('Do you really want to change your Let\'s Encrypt account key?');">
                          <i class="fas fa-sync-alt"></i>
                          <span>Change LE Key</span>
                      </a>

          </li>
          
          <li>
            <a class="" href="index.php?action=revoke-ssl">
                          <i class="fas fa-times-circle"></i>
                          <span>Revoke SSL</span>
                      </a>

          </li>
          
          <?php
                  } ?>
          
          <li class="sub-menu">
            		<a href="javascript:;" class="">
                          <i class="fas fa-user"></i>
                          <span>Profile</span>
                          <span class="menu-arrow arrow_carrot-right"></span>
                      </a>
                
                <ul class="sub">
                  <li><a href="index.php?action=update-profile"><i class="fas fa-user-edit"></i> Update Profile</a></li>
                  <li>
                	<a href="index.php?action=change-password"><i class="fas fa-key"></i> Change Password</a>
              		</li>
              
                  <li>
                    <a href="login.php?action=logout"><i class="fas fa-unlock"></i> Log Out</a>
                  </li>
                  <li>
                    <a href="https://freessl.tech/documentation-free-ssl-certificate-automation" target="_blank"><i class="fas fa-book"></i> Documentation</a>
                  </li>                    
                </ul>
          </li>
        </ul>
        <!-- sidebar menu end-->
      </div>
    </aside>
    <!--sidebar end-->
    <!--main content start-->
  <section id="main-content">
    <section class="wrapper">        
    <?php
    }

    public function message()
    {
        ?>
 <!-- display success or error message -->	
	<?php

    if (isset($_SESSION['message'])) {
        ?>
	<br />
    	<?php $msg = $_SESSION['message']; ?>
    		<?php if ('error' === $msg['type']) {
            ?>
        		<div class="alert alert-danger" role="alert">        	
      				<?php echo $msg['message']; ?>
    			</div>
    		<?php
        } ?>
    		
    		<?php if ('success' === $msg['type']) {
            ?>
        		<div class="alert alert-success" role="alert">        	
      				<?php echo $msg['message']; ?>
    			</div>
    		<?php
        } ?>
    		
    		<?php if ('code' === $msg['type']) {
            ?>
        		<pre>
        			<?php echo $msg['message']; ?>
    			</pre>
        	<?php
        } ?>
        	
        	<?php unset($_SESSION['message']); ?>
    	
<?php
    } ?>
 <?php
    }

    public function headerLogin()
    {
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="FreeSSL Auto - complete automation of Let's Encrypt SSL in shared hosting cPanel">
  <meta name="author" content="SpeedUpWebsite.info">
  
  <title>Login Page | FreeSSL.tech Auto</title>

  <!-- Bootstrap CSS -->
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <!-- bootstrap theme -->
  <link href="css/bootstrap-theme.css" rel="stylesheet">
  <!--external css-->
  <!-- font icon -->
  <link href="css/elegant-icons-style.css" rel="stylesheet" />
  <link href="css/font-awesome-5.1.0.min.css" rel="stylesheet" />
  <!-- Custom styles -->
  <link href="css/style.css" rel="stylesheet">
  <link href="css/style-responsive.css" rel="stylesheet" />

  <!-- HTML5 shim and Respond.js IE8 support of HTML5 -->
  <!--[if lt IE 9]>
    <script src="js/html5shiv.js"></script>
    <script src="js/respond.min.js"></script>
    <![endif]-->
    
</head>
<body class="login-img3-body">
  <div class="container">
     <?php
    }

    public function footerLogin()
    {
        ?>
         <div class="text-right">
      <p style="color: #ffffff; margin-top: 15px;">Powered by <a href="https://letsencrypt.org" target="_blank" class="white">Let's Encrypt</a>, <a href="https://speedify.tech" target="_blank" class="white">SpeedUpWebsite.info</a> and <a href="https://getwww.me" target="_blank" class="white">GetWww.me</a>.</p>
    </div>
  </div>


</body>

</html> 
     <?php
    }

    public function headerInstall()
    {
        ?>
      <!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="author" content="FreeSSL.tech">
  
  <title>Install FreeSSL.tech Auto</title>

  <!-- Bootstrap CSS -->
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <!-- bootstrap theme -->
  <link href="css/bootstrap-theme.css" rel="stylesheet">
  <!--external css-->
  <!-- font icon -->
  <link href="css/elegant-icons-style.css" rel="stylesheet" />
  <link href="css/font-awesome-5.1.0.min.css" rel="stylesheet" />
  <!-- Custom styles -->
  <link href="css/style.css" rel="stylesheet">
  <link href="css/style-responsive.css" rel="stylesheet" />

  <!-- HTML5 shim and Respond.js IE8 support of HTML5 -->
  <!--[if lt IE 9]>
    <script src="js/html5shiv.js"></script>
    <script src="js/respond.min.js"></script>
    <![endif]-->
    
</head>

<body class="login-img3-body installation-body">

  <div class="container">
	<div class="login-form installation-form">
   	  <div class="login-wrap" id="installation">  
     <?php
    }

    public function footerInstall()
    {
        ?>
         </div>
         <p style="color: green;"><a href="https://freessl.tech/ap/contact-us" target="_blank">Help / Support</a></p>
    			
			</div>
       		</div>
        	<div class="text-right">
              <p style="color: #ffffff; margin: 15px 25px 25px;">Powered by <a href="https://letsencrypt.org" target="_blank" class="white">Let's Encrypt</a>, <a href="https://speedify.tech" target="_blank" class="white">SpeedUpWebsite.info</a> and <a href="https://getwww.me" target="_blank" class="white">GetWww.me</a>.</p>
            </div>
          </div>
        </body>
        </html>
     <?php
    }
}
