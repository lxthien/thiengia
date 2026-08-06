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

class Login
{
    public $app_settings;
    public $config_file_path;
    public $mysqli;
    public $factory;

    public function __construct($config_file_path, $mysqli)
    {
        $this->config_file_path = $config_file_path;
        $this->mysqli = $mysqli;

        if (\defined('APP_SETTINGS_PATH')) {
            $settings = file_get_contents(APP_SETTINGS_PATH);
            $this->app_settings = json_decode($settings, true);
        }

        $this->factory = new Factory();
    }

    public function login()
    {
        //Define variables and initialize with empty values
        $email = $password = null;
        $email_err = $password_err = $csrf_err = null;

        //Processing form data when form is submitted
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $token = trim($_POST['csrf']);
            $token = filter_var($token, FILTER_SANITIZE_STRING);

            $csrfVerified = $this->factory->verifyCsrfToken('login', $token);

            if (!$csrfVerified) {
                $csrf_err = "Sorry! This form's security token expired. Please submit again. Don't wait more than 15 minutes after loading the form.";
            }

            $email = trim($_POST['email']);
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);

            //Check if email is empty
            if (empty($email)) {
                $email_err = 'Please enter your email.';
            } else {
                //Validate email by pattern

                if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                    //email is valid
                    $email_err = 'Please enter a valid email.';
                }
            }

            $password = trim($_POST['password']);
            $password = filter_var($password, FILTER_SANITIZE_STRING);

            //Check if password is empty
            if (empty($password)) {
                $password_err = 'Please enter your password.';
            }

            $mysqli = $this->mysqli;

            //Validate credentials

            if ($csrfVerified && empty($email_err) && empty($password_err)) {
                //Prepare select statement
                $sql = 'SELECT email, password FROM users WHERE email = ?';

                if ($stmt = $mysqli->prepare($sql)) {
                    //Bind variables to the prepared statement as parameters
                    $stmt->bind_param('s', $param_email);

                    //Set parameters
                    $param_email = $email;

                    // Attempt to execute the prepared statement
                    if ($stmt->execute()) {
                        // Store result
                        $stmt->store_result();

                        // Check if username exists, if yes then verify password
                        if (1 === $stmt->num_rows) {
                            // Bind result variables
                            $stmt->bind_result($email, $hashed_password);
                            if ($stmt->fetch()) {
                                if (password_verify($password, $hashed_password)) {
                                    /* Password is correct, so start a new session if not exists and
                                     save the username to the session */
                                    //start session
                                    if (!session_id()) {
                                        session_start();
                                    }

                                    $_SESSION['email'] = $email;

                                    header('location: index.php');
                                    exit;
                                }
                                // Display an error message if password is not valid
                                $password_err = 'The password you entered is not valid';
                            }
                        } else {
                            // Display an error message if username doesn't exist
                            $email_err = 'No account found with this email: '.$email;
                        }
                    } else {
                        echo 'Oops! Something went wrong. Please try again later.';
                    }
                }

                // Close statement
                $stmt->close();
            }

            // Close connection
            $mysqli->close();
        } ?>

<form class="login-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
    
      <div class="login-wrap">
      
      <h1 class="text-center" style="color: green;">Login</h1>
      
        <p class="login-img"><i class="icon_lock_alt" style="color: green;"></i></p>
        <div class="input-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
          <span class="input-group-addon">@</span>
          <input type="email" class="form-control" placeholder="Email" name="email" required="required" value="<?php echo isset($email) ? $email : null; ?>" autofocus>          
          <span style="color: red;"><?php echo isset($email_err) ? $email_err : null; ?></span>
        </div>
                
        <div class="input-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
          <span class="input-group-addon"><i class="icon_key_alt"></i></span>
          <input type="password" class="form-control" placeholder="Password" name="password" required="required">          
          <span style="color: red;"><?php echo isset($password_err) ? $password_err : null; ?></span>
        </div>
                
        <div class="input-group <?php echo (!empty($csrf_err)) ? 'has-error' : ''; ?>">
          <input type="hidden" name="csrf" value="<?php echo $this->factory->getCsrfToken('login'); ?>">
          <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
        </div>
                                
        <span class="pull-right" style="margin-bottom: 8px;"> <a href="login.php?action=forgot-password"> Forgot Password?</a></span><br />
            
        <button class="btn btn-success btn-lg btn-block" type="submit">Login</button>
        
      </div>
    </form>
     
     <?php
    }

    public function logout()
    {
        // Unset all of the session variables
        session_unset();

        // Destroy the session.
        session_destroy();

        // Redirect to login page
        header('location: login.php');
        exit;
    }

    public function forgotPassword()
    {
        $display_form = true;

        $message = null;

        //Processing form data when form is submitted
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            //Define variable and initialize with empty value
            $email_err = $csrf_err = null;

            $token = trim($_POST['csrf']);
            $token = filter_var($token, FILTER_SANITIZE_STRING);

            $csrfVerified = $this->factory->verifyCsrfToken('forgot-password', $token);

            if (!$csrfVerified) {
                $csrf_err = "Sorry! This form's security token expired. Please submit again. Don't wait more than 15 minutes after loading the form.";
            }

            $email = trim($_POST['email']);
            $email = filter_var($email, FILTER_SANITIZE_EMAIL);

            //Check if email is empty
            if (empty($email)) {
                $email_err = 'Please enter your email id';
            } else {
                //Validate email by pattern

                if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
                    //email is valid
                    $email_err = 'Please enter a valid email address';
                }
            }

            $mysqli = $this->mysqli;

            //Validate credentials
            if ($csrfVerified && empty($email_err)) {
                //Prepare select statement
                $sql = 'SELECT id FROM users WHERE email = ?';

                if ($stmt = $mysqli->prepare($sql)) {
                    //Bind variables to the prepared statement as parameters
                    $stmt->bind_param('s', $email);

                    // Attempt to execute the prepared statement
                    if ($stmt->execute()) {
                        // Store result
                        $stmt->store_result();

                        // Check if username exists, if yes then verify password
                        if (1 === $stmt->num_rows) {
                            // Bind result variables
                            $stmt->bind_result($id);

                            if ($stmt->fetch()) {
                                //Generate password reset token
                                $token = $this->factory->passwordResetTokenGenerator();

                                $currentDate = date('Y-m-d H:i:s');

                                //Make the database entry

                                $sql = 'UPDATE users SET pwd_reset_token = ?, pwd_reset_token_creation_date = ? WHERE id = ?';

                                if ($stmt = $mysqli->prepare($sql)) {
                                    //Bind variables to the prepared statement as parameters
                                    $stmt->bind_param('sss', $token, $currentDate, $id);

                                    //Attempt to execute the prepared statement
                                    if ($stmt->execute()) {
                                        //SUCCESS - send email and display message

                                        $subject = 'Password Reset token for FreeSSL.tech Auto';

                                        $httpHost = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

                                        //check port

                                        $scheme = ($_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';

                                        $appBaseUrl = $scheme.$httpHost.htmlspecialchars($_SERVER['PHP_SELF']);

                                        $passwordResetUrl = $appBaseUrl.'?action=reset-password&token='.$token;

                                        //Email body
                                        $body = '<html><body>';
                                        $body .= 'Please click on the link below to reset the password for FreeSSL.tech Auto<br />';
                                        $body .= "<a href='${passwordResetUrl}'>${passwordResetUrl}</a><br /><br />";

                                        $body .= "This link will expires in 30 minutes.<br />
                                              If you haven't asked to reset your password, please ignore this email.<br /><br />
                                                
                                                Do not reply to this email.<br /><br />
                                                --------------<br />
                                                FreeSSL.tech Auto<br />
                                                Powered by <a href='https://letsencrypt.org'>Let's Encrypt</a>, <a href='https://speedify.tech'>SpeedUpWebsite.info</a> and <a href='https://getwww.me'>GetWWW.me</a><br /><br />
                                                </body>
                                                </html>";

                                        // Send email to the user.

                                        $to = $email;

                                        // Set content-type header
                                        $headers = [];
                                        $headers[] = 'MIME-Version: 1.0';
                                        $headers[] = 'Content-type: text/html; charset=iso-8859-1';
                                        $headers[] = 'From:noreply@'.$httpHost;

                                        // Send the email
                                        if (mail($to, $subject, $body, implode("\r\n", $headers))) {
                                            //Success
                                            $display_form = false;

                                            $message = "A password reset link has been sent to your email address: ${to}. Please check your inbox.<br />";
                                        } else {
                                            //email sending failed
                                            $message = "Sorry, there was an issue sending password reset link to your email address ${to}. Please try again!<br />";
                                        }
                                    } else {
                                        $message = 'Sorry! The token was not set in database due to an error.<br />';
                                        $message .= $stmt->error;
                                    }
                                }
                            }
                        } else {
                            // Display an error message if username doesn't exist

                            $email_err = 'No account found with this email: '.$email;
                        }
                    } else {
                        $message = 'Oops! Something went wrong. Please try again later.';
                    }
                }

                // Close statement
                $stmt->close();
            }

            // Close connection
            $mysqli->close();
        } ?>

<form class="login-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=forgot-password" method="post">
    
      <div class="login-wrap">
      	<h1 class="text-center" style="color: green;">Reset Password</h1>
           
        <p class="login-img"><i class="icon_lock_alt" style="color: green;"></i></p>
        
        <?php if (isset($message)) {
            ?>
        
        <p style="color: red;"><?php echo $message; ?></p>
        
        <?php
        } ?>
        
        <?php if ($display_form) {
            ?>
        
        <p style="color: #000000;">Enter your e-mail address below to reset password</p>
        
        <div class="input-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
          <span class="input-group-addon">@</span>
          <input type="email" class="form-control" placeholder="Email" name="email" required="required" value="<?php echo isset($email) ? $email : null; ?>" autofocus>          
          <span style="color: red;"><?php echo isset($email_err) ? $email_err : null; ?></span>
        </div>
        
        <div class="input-group <?php echo (!empty($csrf_err)) ? 'has-error' : ''; ?>">
          <input type="hidden" name="csrf" value="<?php echo $this->factory->getCsrfToken('forgot-password'); ?>">
          <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
        </div>
                        
        <button class="btn btn-success btn-lg btn-block" type="submit">Reset Password</button>
        
        <?php
        } ?>
        
      </div>
    </form>
      <?php
    }

    public function resetPassword()
    {
        $token = trim($_GET['token']);
        $token = filter_var($token, FILTER_SANITIZE_STRING);

        // Validate token length
        if (null !== $token && (!\is_string($token) || 32 !== \strlen($token))) {
            //set msg in session and redirect to forgot-password page

            $_SESSION['message'] = [
                'type' => 'error',
                'message' => 'Invalid token type or length. Please try again!',
            ];

            $this->factory->redirect('login.php?action=forgot-password');
        }

        $mysqli = $this->mysqli;

        //Prepare select statement
        $sql = 'SELECT id, pwd_reset_token_creation_date FROM users WHERE pwd_reset_token = ?';

        if ($stmt = $mysqli->prepare($sql)) {
            //Bind variables to the prepared statement as parameters
            $stmt->bind_param('s', $token);

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();

                // Check if result returned
                if (1 === $stmt->num_rows) {
                    // Bind result variables
                    $stmt->bind_result($id, $tokenCreationDate);

                    if ($stmt->fetch()) {
                        if ($stmt = $mysqli->prepare($sql)) {
                            $tokenCreationDate = strtotime($tokenCreationDate);

                            $currentDate = strtotime('now');

                            if ($currentDate - $tokenCreationDate > 30 * 60) {
                                //set msg in session and redirect to forgot-password page

                                $_SESSION['message'] = [
                                    'type' => 'error',
                                    'message' => 'Oops! The password reset token expired. Please try again!',
                                ];

                                $this->factory->redirect('login.php?action=forgot-password');
                            }
                        }
                    } else {
                        //set msg in session and redirect to forgot-password page

                        $_SESSION['message'] = [
                            'type' => 'error',
                            'message' => 'Oops! There is an error in database transaction. Please try again!',
                        ];

                        $this->factory->redirect('login.php?action=forgot-password');
                    }
                } else {
                    //set msg in session and redirect to forgot-password page

                    $_SESSION['message'] = [
                        'type' => 'error',
                        'message' => 'Oops! The password reset token is not valid or it may have expired. Please try again!',
                    ];

                    $this->factory->redirect('login.php?action=forgot-password');
                }
            }

            // Close statement
            $stmt->close();
        }

        //Processing form data when form is submitted
        if ('POST' === $_SERVER['REQUEST_METHOD']) {
            $new_password_err = $confirm_new_password_err = $csrf_err = null;

            $csrf = trim($_POST['csrf']);
            $csrf = filter_var($csrf, FILTER_SANITIZE_STRING);

            $csrfVerified = $this->factory->verifyCsrfToken('reset-password', $csrf);

            if (!$csrfVerified) {
                $csrf_err = "Sorry! This form's security token expired. Please submit again. Don't wait more than 15 minutes after loading the form.";
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
                    $confirm_new_password_err = 'Please re-enter the new password.';
                } else {
                    if ($new_password !== $confirm_new_password) {
                        $confirm_new_password_err = 'This did not match with the new password.';
                        //unset($new_password);
                        unset($confirm_new_password);
                    }
                }
            }

            //Change the password only if there is NO error
            if ($csrfVerified && empty($new_password_err) && empty($confirm_new_password_err)) {
                //Make database entry

                $sql = 'UPDATE users SET password = ?, pwd_reset_token = ?, pwd_reset_token_creation_date = ? WHERE id = ?';

                if ($stmt = $mysqli->prepare($sql)) {
                    //Create password hash
                    $param_password = password_hash($new_password, PASSWORD_DEFAULT);

                    $pwd_reset_token = null;

                    $pwd_reset_token_creation_date = null;

                    //Bind variables to the prepared statement as parameters
                    $stmt->bind_param('ssss', $param_password, $pwd_reset_token, $pwd_reset_token_creation_date, $id);

                    //Attempt to execute the prepared statement
                    if ($stmt->execute()) {
                        //SUCCESS

                        //Save confirmation msg in session before redirect
                        $_SESSION['message'] = [
                            'type' => 'success',
                            'message' => 'Congrats! You have successfully reset your password!',
                        ];

                        $this->factory->redirect('login.php');
                    } else {
                        echo 'Sorry! The password was not updated.<br /><br />';
                        echo $stmt->error;
                    }
                }

                // Close statement
                $stmt->close();

                // Close connection
                $mysqli->close();
            }
        } ?>

<form class="login-form" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>?action=reset-password&token=<?php echo isset($token) ? $token : null; ?>" method="post">

<div class="login-wrap">

	   <h1 class="text-center" style="color: green;">Set New Password</h1>

        <p class="login-img"><i class="icon_lock_alt" style="color: green;"></i></p>
        
        
        <p style="color: green;">Enter your new password in the form below</p>
        
        <div class="input-group <?php echo (!empty($new_password_err)) ? 'has-error' : ''; ?>">
          <span class="input-group-addon"><i class="icon_key_alt"></i></span>
          <input type="password" class="form-control" placeholder="New Password" name="new_password" value="<?php echo isset($new_password) ? $new_password : null; ?>" required="required">          
          <span style="color: red;"><?php echo isset($new_password_err) ? $new_password_err : null; ?></span>
        </div>
                
        <div class="input-group <?php echo (!empty($confirm_new_password_err)) ? 'has-error' : ''; ?>">
          <span class="input-group-addon"><i class="icon_key_alt"></i></span>
          <input type="password" class="form-control" placeholder="Confirm New Password" name="confirm_new_password" required="required" value="<?php echo isset($confirm_new_password) ? $confirm_new_password : null; ?>">          
          <span style="color: red;"><?php echo isset($confirm_new_password_err) ? $confirm_new_password_err : null; ?></span>
        </div>
        
        <div class="input-group <?php echo (!empty($csrf_err)) ? 'has-error' : ''; ?>">
          <input type="hidden" name="csrf" value="<?php echo $this->factory->getCsrfToken('reset-password'); ?>">
          <span style="color: red;"><?php echo isset($csrf_err) ? $csrf_err : null; ?></span>
        </div>
                      
        <button class="btn btn-success btn-lg btn-block" type="submit">Set Password</button>
        
      </div>
</form>
     <?php
    }

    public function pageNotFound()
    {
        ?>
     <h1 class="text-center" style="font-weight: bold;"><br /><br /><br />Oops! The requested page was not found.<br /><br /><br /><br /><br /><br /><br /><br /></h1>
     <?php
    }
}
