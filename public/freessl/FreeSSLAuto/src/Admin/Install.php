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

use mysqli;

class Install
{
    public $app_settings;
    public $config_file_path;
    public $factory;

    public function __construct($config_file_path)
    {
        $this->config_file_path = $config_file_path;

        $this->factory = new Factory();
    }

    /**
     * Step 1 of the installation script.
     */
    public function stepOne()
    {
        // Define variables and initialize with null values
        $dbname = $uname = $pwd = $dbhost = null;
        $dbname_err = $uname_err = $dbhost_err = $pwd_err = $db_connect_err = $csrf_err = null;

        // Processing form data when form is submitted
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $token = trim($_POST['csrf']);
            $token = filter_var($token, FILTER_SANITIZE_STRING);
            
            $csrfVerified = $this->factory->verifyCsrfToken('install-step-1', $token);

            if (!$csrfVerified) {
                $csrf_err = "Sorry! This form's security token expired. Please submit again. Don't wait more than 15 minutes after loading the form.";
            }

            $dbname = trim($_POST['dbname']);
            $uname = trim($_POST['uname']);
            $pwd = trim($_POST['pwd']);
            $dbhost = trim($_POST['dbhost']);

            // Validate database name
            if (empty($dbname)) {
                $dbname_err = 'Please enter a database name.';
            }

            // Validate username
            if (empty($uname)) {
                $uname_err = 'Please enter a user name for the database.';
            }

            // Password validation not required. Because, password may be empty (in case of installation in PC)

            // Validate database host
            if (empty($dbhost)) {
                $dbhost_err = 'Please enter the database host.';
            }

            // Attempt to connect to MySQL database
            $mysqli = @new mysqli($_POST['dbhost'], $_POST['uname'], $_POST['pwd'], $_POST['dbname']);

            // Check connection
            if ($mysqli->connect_error) {
                $db_connect_err = "Oops! {$mysqli->connect_error}. <br />Please check the database details carefully and try again.
                Contact your hosting provider if this error persists.";
            }

            // Check input errors before inserting in database
            if ($csrfVerified && empty($dbname_err) && empty($uname_err) && empty($dbhost_err) && empty($db_connect_err)) {
                //Save database details in session

                $_SESSION['dbname'] = $dbname;
                $_SESSION['uname'] = $uname;
                $_SESSION['pwd'] = $pwd;
                $_SESSION['dbhost'] = $dbhost;

                //START DB import

                $query = '';
                $lines = file(__DIR__.'/db.sql');

                foreach ($lines as $line) {
                    $startWith = substr(trim($line), 0, 2);
                    $endWith = substr(trim($line), -1, 1);

                    if (empty($line) || '--' === $startWith || '/*' === $startWith || '//' === $startWith) {
                        continue;
                    }

                    $query .= $line;

                    //end of the query
                    if (';' === $endWith) {
                        mysqli_query($mysqli, $query) || die('Error performing query \'<strong>'.$query.'\': '.mysqli_error($mysqli).'<br /><br />');

                        //reset query variable
                        $query = '';
                    }
                }

                echo 'The SQL file imported successfully<br /><br />';

                //END DB import

                // Close connection
                $mysqli->close();

                //redirect to step 2
                $this->factory->redirect('install.php?step=2');
            }
        } else {
            //Get request
            if ($_SERVER['SERVER_PORT'] != 443) {
                ?>
            
              <div class="alert alert-danger text-center">              
              		This page is not protected with HTTPS and you are going to provide password over an unencrypted connection. We recommend generating a <a href="tmp-ssl.php" target="_blank">Free SSL Certificate for <strong><?php echo $_SERVER['SERVER_NAME']; ?></strong> with a single click</a>, installing the SSL on this server and then access this page over HTTPS.<br /><br />
              		<a href="tmp-ssl.php" target="_blank" class="btn btn-default">Only 30 seconds! Please click here to generate a free SSL certificate in 30 seconds!!</a>
              </div>
            
        <?php
            }
        } ?>
      
        <h1 class="text-center" style="color: green; font-weight: bold;">Install FreeSSL.tech Auto</h1>
        
        <p>Please enter your database connection details to install <a href="https://freessl.tech" target="_blank">FreeSSL.tech Auto.</a></p>
        
        <p>Please contact with your web hosting company for any help regarding your database.</p>
        
        
        <?php if (null !== $db_connect_err) {
            ?>
        <div class="alert alert-danger" role="alert">
  			<?php echo $db_connect_err; ?>
		</div>
		<?php
        } ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
        
                
            <div class="form-group <?php echo (!empty($dbname_err)) ? 'has-error' : ''; ?>">
                <label for="dbname">Database Name</label>
                <input type="text" name="dbname" class="form-control" placeholder="Database Name you want to use for this app" value="<?php echo isset($dbname) ? $dbname : null; ?>" required="required">
                <span class="help-block"><?php echo isset($dbname_err) ? $dbname_err : null; ?></span>
            </div>    
            
			<div class="form-group <?php echo (!empty($uname_err)) ? 'has-error' : ''; ?>">
                <label for="uname">Username</label>
                <input type="text" name="uname" class="form-control" placeholder="Database User Name" value="<?php echo isset($uname) ? $uname : null; ?>" required="required">
                <span class="help-block"><?php echo isset($uname_err) ? $uname_err : null; ?></span>
            </div>
            
            <div class="form-group <?php echo (!empty($pwd_err)) ? 'has-error' : ''; ?>">
                <label for="pwd">Password</label>
                <input type="password" name="pwd" class="form-control" placeholder="Password of the Database User" value="<?php echo isset($pwd) ? $pwd : null; ?>">
                <span class="help-block"><?php echo isset($pwd_err) ? $pwd_err : null; ?></span>
            </div>
            
            <div class="form-group <?php echo (!empty($dbhost_err)) ? 'has-error' : ''; ?>">
                <label for="dbhost">Database Host</label>
                <input type="text" name="dbhost" class="form-control" placeholder="localhost" value="localhost" required="required">
                <span class="help-block"><?php echo isset($dbhost_err) ? $dbhost_err : null; ?></span>
                <span class="help-block">Please contact with your web hosting provider, if 'localhost' doesn't work</span>
                
            </div>
            
            <div class="input-group <?php echo (!empty($csrf_err)) ? 'has-error' : ''; ?>">
              <input type="hidden" name="csrf" value="<?php echo $this->factory->getCsrfToken('install-step-1'); ?>">
              <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
            </div>
            
            <button class="btn btn-success btn-install btn-lg" type="submit">Submit</button>
            
        </form>
    <?php
    }

    public function stepTwo()
    {
        $display_form = true;

        $email = $password = $confirm_password = null;
        $email_err = $password_err = $confirm_password_err = $csrf_err = null;

        // Processing form data when form is submitted
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $token = trim($_POST['csrf']);
            $token = filter_var($token, FILTER_SANITIZE_STRING);

            $csrfVerified = $this->factory->verifyCsrfToken('install-step-2', $token);

            if (!$csrfVerified) {
                $csrf_err = "Sorry! This form's security token expired. Please submit again. Don't wait more than 15 minutes after loading the form.";
            }

            $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);

            // Validate email
            if (empty($email)) {
                $email_err = 'Please enter your email address.';
            } else {
                //Validate email by pattern
                if (filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                    //email is valid

                    //Check if email is exists in DB

                    $dbname = $_SESSION['dbname'];
                    $uname = $_SESSION['uname'];
                    $pwd = $_SESSION['pwd'];
                    $dbhost = $_SESSION['dbhost'];

                    $mysqli = new mysqli($dbhost, $uname, $pwd, $dbname);

                    //Check DB connection
                    if ($mysqli->connect_error) {
                        die('ERROR: Could not connect. '.$mysqli->connect_error);
                    }

                    //Prepare a select statement
                    $sql = 'SELECT id FROM users WHERE email = ?';

                    if ($stmt = $mysqli->prepare($sql)) {
                        //Bind variables to the prepared statement as parameters
                        $stmt->bind_param('s', $email);

                        //Set parameter already done

                        //Attempt to execute the prepared statement
                        if ($stmt->execute()) {
                            //store result
                            $stmt->store_result();

                            if (1 === $stmt->num_rows) {
                                $email_err = 'This email is already registered. Please provide a different email.';
                            }
                        } else {
                            echo 'Something went wrong. Please try again.';
                        }
                    }

                    //Close statement
                    $stmt->close();
                } else {
                    $email_err = 'Please enter a valid email.';
                }
            }

            $password = filter_var(trim($_POST['password']), FILTER_SANITIZE_STRING);

            // Validate password
            if (empty($password)) {
                $password_err = 'Please enter a password.';
            } elseif (\strlen(trim($_POST['password'])) < 8) {
                $password_err = 'Password must have atleast 8 characters.';
            } else {
                $confirm_password = filter_var(trim($_POST['confirm_password']), FILTER_SANITIZE_STRING);

                // Validate confirm password
                if (empty($confirm_password)) {
                    $confirm_password_err = 'Please re-enter the password.';
                } else {
                    if ($password !== $confirm_password) {
                        $confirm_password_err = 'Password did not match.';
                    }
                }
            }

            //Check only if there is NO input errors
            if ($csrfVerified && empty($email_err) && empty($password_err) && empty($confirm_password_err)) {
                //Make database entry

                //Prepare insert statement
                $sql = 'INSERT INTO users (email, password) VALUES (?, ?)';

                if ($stmt = $mysqli->prepare($sql)) {
                    //Bind variables to the prepared statement as parameters
                    $stmt->bind_param('ss', $param_email, $param_password);

                    //Set parameters
                    $param_email = $email;
                    //Create password hash
                    $param_password = password_hash($password, PASSWORD_DEFAULT);

                    //Attempt to execute the prepared statement
                    if ($stmt->execute()) {
                        //SUCCESS!
                        $display_form = false;

                        //Create the config/config.php file

                        $key = $this->factory->encryptionTokenGenerator();

                        $config_content = <<<CONFIG
<?php
/* Database credentials. Assuming you are running MySQL
server with default setting (user 'root' with no password) */
define('DB_NAME', '${dbname}');
define('DB_USERNAME', '${uname}');
define('DB_PASSWORD', '${pwd}');
define('DB_HOST', '${dbhost}');
define('KEY', '${key}');
                
/* Attempt to connect to MySQL database */
\$mysqli = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);
                
// Check connection
if(\$mysqli->connect_error){
    die('ERROR: Could not connect. ' . \$mysqli->connect_error);
}

CONFIG;

                        $configDirectory = \dirname($this->config_file_path);

                        if (!is_dir($configDirectory)) {
                            @mkdir($configDirectory, 0700, true);
                        }

                        if (!is_dir($configDirectory)) {
                            throw new \RuntimeException("Can't create directory ${configDirectory}. Please manually create this directory, grant permission 0700 and try again.");
                        }

                        if (!file_put_contents($this->config_file_path, $config_content)) {
                            echo "<h1>Could not write to the config.php file ({$this->config_file_path}). Please check that you have write permission to that directory and try again.</h1>";
                        } else {
                            session_unset();

                            session_destroy(); ?>
                               <!-- display success message -->            
                                <h1 class="text-center" style="color: green; font-weight: bold;">Installation Successfull!</h1>
                                
                                <p>Congrats! <strong>FreeSSL.tech Auto</strong> has been installed successfully and your admin account has been created.</p>
                                
                                <p>Now log in and automate SSL certificate creation.<br /></p>
                                
                                <a class="btn btn-success btn-install btn-lg btn-block" href="login.php">Click here to log in</a>
                                
                            </div>
                            <?php
                        }
                    } else {
                        echo 'Something went wrong. Please try again later.';
                    }
                }

                // Close statement
                $stmt->close();

                // Close connection
                $mysqli->close();
            }
        }

        if ($display_form) {
            ?>

        <h1 class="text-center" style="color: green; font-weight: bold;">Create Admin User Account</h1>
        
        <p>Please fill in the form below to create your admin account.</p>
        
        
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?step=2" method="post">
        
                
            <div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
                <label for="email">Your Email</label>
                <input type="email" name="email" class="form-control" placeholder="Please double-check your email address before submit" value="<?php echo isset($email) ? $email : (isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : null); ?>" required="required">
                <span class="help-block"><?php echo isset($email_err) ? $email_err : null; ?></span>
            </div>    
            
            
            <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
                <label for="password">Password</label>
                <input type="password" name="password" class="form-control" value="<?php echo isset($password) ? $password : null; ?>" placeholder="Password for your admin account" required="required">
                <span class="help-block"><?php echo isset($password_err) ? $password_err : null; ?></span>
            </div>
            
            <div class="form-group <?php echo (!empty($confirm_password_err)) ? 'has-error' : ''; ?>">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" value="<?php echo isset($confirm_password) ? $confirm_password : null; ?>" placeholder="Re-type the Password" required="required">
                <span class="help-block"><?php echo isset($confirm_password_err) ? $confirm_password_err : null; ?></span>
            </div>
            
            <div class="input-group <?php echo (!empty($csrf_err)) ? 'has-error' : ''; ?>">
              <input type="hidden" name="csrf" value="<?php echo $this->factory->getCsrfToken('install-step-2'); ?>">
              <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
            </div>
            
            <button class="btn btn-success btn-install btn-lg" type="submit">Submit</button>
                           
        </form>
        
    <?php
        }
    }

    public function pageNotFound()
    {
        ?>
      	<h1 class="text-center" style="font-weight: bold;"><br /><br /><br />Oops! The requested page was not found.<br /><br /><br /><br /><br /><br /><br /><br /></h1>
     <?php
    }
}
