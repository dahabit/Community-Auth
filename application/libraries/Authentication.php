<?php if( ! defined('BASEPATH') ) exit('No direct script access allowed');
/**
 * Community Auth - Authentication Library
 *
 * Community Auth is an open source authentication application for CodeIgniter 2.1.2
 *
 * @package     Community Auth
 * @author      Robert B Gottier
 * @copyright   Copyright (c) 2011 - 2012, Robert B Gottier. (http://brianswebdesign.com/)
 * @license     BSD - http://http://www.opensource.org/licenses/BSD-3-Clause
 * @link        http://community-auth.com
 */

class Authentication
{
	/**
	 * The CodeIgniter super object
	 *
	 * @var object
	 * @access public
	 */
	public $CI;

	/**
	 * An array of all user roles where key is 
	 * level (int) and value is the role name (string)
	 *
	 * @var array
	 * @access public
	 */
	public $roles;

	/**
	 * An array of all user levels where key is 
	 * role name (string) and value is level (int)
	 *
	 * @var array
	 * @access public
	 */
	public $levels;

	/**
	 * The status of a login attempt
	 *
	 * @var bool
	 * @access public
	 */
	public $login_error = FALSE;

	/**
	 * The hold status for the IP, posted username, or posted email address
	 *
	 * @var bool
	 * @access public
	 */
	public $on_hold = FALSE;

	/**
	 * A large number holding the user ID, last login time, 
	 * and last modified time for a logged-in user
	 *
	 * @var int
	 * @access private
	 */
	private $auth_identifier;

	// --------------------------------------------------------------

	/**
	 * Class constructor
	 */
	public function __construct()
	{
		$this->CI =& get_instance();

		// Make roles available by user_level (int) => role name (string)
		$this->roles = config_item('levels_and_roles');

		// Make levels available by role name (string) => user_level (int)
		$this->levels = array_flip( $this->roles );

		// Get the auth identifier from the session if it exists
		$this->auth_identifier = $this->CI->session->userdata('auth_identifier');
	}

	// --------------------------------------------------------------

	/**
	 * Present a login form if the user is not logged in. 
	 * 
	 * @param   mixed  int if user level by number or an array if user level by name(s)
	 * @return  mixed  either an array of login status data or FALSE
	 */
	public function user_status( $required_level = 0 )
	{
		$string      = $this->CI->input->post('login_string');
		$password    = $this->CI->input->post('login_pass');
		$form_token  = $this->CI->input->post('login_token');
		$flash_token = $this->CI->session->flashdata('login_token');

		// If the request resembles a login attempt in any way
		if(
			$string      !== FALSE OR 
			$password    !== FALSE OR 
			$form_token  !== FALSE OR 
			$flash_token !== FALSE 
		)
		{
			// Log as long as error logging threshold allows for debugging
			log_message(
				'debug',
				"\n string      = " . $string .
				"\n password    = " . $password .
				"\n form_token  = " . $form_token .
				"\n flash_token = " . $flash_token
			);
		}

		// Check to see if a user is already logged in
		if( $this->auth_identifier )
		{
			// Check login, and return user's data or FALSE if not logged in
			if( $auth_data = $this->check_login( $required_level ) )
			{
				return $auth_data;
			}
		}

		// If this is a login attempt, all values must not be empty
		else if( 
			$string      !== FALSE && 
			$password    !== FALSE && 
			$form_token  !== FALSE && 
			$flash_token !== FALSE 
		)
		{
			// Verify that the form token and flash session token are the same
			if( $form_token == $flash_token )
			{
				// Attempt login with posted values and return either the user's data, or FALSE
				if( $auth_data = $this->login( $required_level, $string, $password ) )
				{
					return $auth_data;
				}
			}
		}

		/**
		 * If a login string and password were posted, and the form token 
		 * and flash token were not set, then we treat this as a failed login
		 * attempt.
		 */
		else if(
			$string      !== FALSE && 
			$password    !== FALSE
		)
		{
			// Log the error
			$this->log_error( $this->CI->security->xss_clean( $string ) );

			$this->login_error = TRUE;
		}

		return FALSE;
	}

	// --------------------------------------------------------------

	/**
	 * Test post of login form 
	 * 
	 * @param   mixed   either int if user level by number or an array if user level by name(s) 
	 * @param   string  the posted username or email address
	 * @param   string  the posted password
	 * @return  mixed   either an array of login status data or FALSE
	 */
	private function login( $required_level = 0, $user_string, $user_pass )
	{
		/**
		 * Validate the posted username / email address and password.
		 */
		$this->CI->load->library('form_validation');
		$this->CI->config->load( 'form_validation/auth/login' );
		$this->CI->form_validation->set_rules( config_item('login_rules') );

		if( $this->CI->form_validation->run() !== FALSE )
		{
			// Check if IP, username or email address is already on hold.
			$this->on_hold = $this->current_hold_status();

			if( ! $this->on_hold )
			{
				// Get user table data if username or email address matches a record
				if( $auth_data = $this->CI->auth_model->get_auth_data( $user_string ) )
				{
					if(
						// Check if user is banned
						( $auth_data->user_banned === '1' )

						// Check if the posted password matches the one in the row
						OR ( ! $this->check_passwd( $auth_data->user_pass, $auth_data->user_salt, $user_pass ) )

						// Check if the user is of high enough level to be here
						OR ( is_int( $required_level ) && $auth_data->user_level < $required_level )
						OR ( is_array( $required_level ) && ! in_array( $this->roles[$auth_data->user_level], $required_level ) )
					)
					{
						// Login failed ...
						log_message(
							'debug',
							"\n user is banned             = " . ( $auth_data->user_banned === 1 ? 'yes' : 'no' ) .
							"\n password in database       = " . $auth_data->user_pass .
							"\n posted/hashed password     = " . $this->hash_passwd( $user_pass, $auth_data->user_salt ) . 
							"\n required level             = " . $required_level . 
							"\n user level in database     = " . $auth_data->user_level . 
							"\n user level equivalant role = " . $this->roles[$auth_data->user_level]
						);
					}
					else
					{
						// Store login time in database and cookie
						$login_time = time();

						// Update user record in database
						$this->CI->auth_model->login_update( $auth_data->user_id, $login_time );

						/**
						 * Since the session cookie needs to be able to use
						 * the secure flag, we want to hold some of the user's 
						 * data in another cookie. For instance, the `user_name` 
						 * is used to have a logout button on standard HTTP pages.
						 *
						 * auth_model->get_auth_data() responds with some user profile
						 * data, and you might add other data that is not sensitive. 
						 * Please do not add sensitive data to the http user cookie.
						 */
						$http_user_cookie = array(
							'name'   => config_item('http_user_cookie_name'),
							'value'  => $this->CI->session->serialize_data( array(
								'_user_name'  => $auth_data->user_name,
								'_first_name' => $auth_data->first_name,
								'_last_name'  => $auth_data->last_name
							) ),
							'domain' => config_item('cookie_domain'),
							'path'   => config_item('cookie_path'),
							'prefix' => config_item('cookie_prefix'),
							'secure' => FALSE
						);

						// Check if remember me requested, and set cookie if yes
						if( config_item('allow_remember_me') && $this->CI->input->post('remember_me') )
						{
							$remember_me_cookie = array(
								'name'   => config_item('remember_me_cookie_name'),
								'value'  => config_item('remember_me_expiration') + time(),
								'expire' => config_item('remember_me_expiration'),
								'domain' => config_item('cookie_domain'),
								'path'   => config_item('cookie_path'),
								'prefix' => config_item('cookie_prefix'),
								'secure' => FALSE
							);

							$this->CI->input->set_cookie( $remember_me_cookie );

							// Make sure the CI session cookie doesn't expire on close
							$this->CI->session->sess_expire_on_close = FALSE;
							$this->CI->session->sess_expiration = config_item('remember_me_expiration');

							// Set the expiration of the http user cookie
							$http_user_cookie['expire'] = config_item('remember_me_expiration') + time();
						}
						else
						{
							// Unless remember me is requested, the http user cookie expires when the browser closes.
							$http_user_cookie['expire'] = 0;
						}

						$this->CI->input->set_cookie( $http_user_cookie );

						// Set CI session cookie
						$this->CI->session->set_userdata( 
							'auth_identifier',
							$this->create_auth_identifier(
								$auth_data->user_id,
								$auth_data->user_modified,
								$login_time
							)
						);

						// Send the auth data back to the controller
						return $auth_data;
					}
				}
				else
				{
					// Login failed ...
					log_message(
						'debug',
						"\n NO MATCH FOR USERNAME OR EMAIL DURING LOGIN ATTEMPT"
					);
				}
			}
			else
			{
				// Login failed ...
				log_message(
					'debug',
					"\n IP, USERNAME, OR EMAIL ADDRESS ON HOLD"
				);
			}
		}
		else
		{
			// Login failed ...
			log_message(
				'debug',
				"\n LOGIN ATTEMPT DID NOT PASS FORM VALIDATION"
			);
		}

		// Log the error
		$this->log_error( $this->CI->security->xss_clean( $user_string ) );

		$this->login_error = TRUE;
		
		return FALSE;
	}

	// --------------------------------------------------------------

	/**
	 * Verify if user already logged in. 
	 * 
	 * @param   mixed   either int if user level by number or an array if user level by name(s)
	 * @return  mixed   either an array of login status data or FALSE
	 */
	public function check_login( $required_level = 0 )
	{
		// Check that the auth identifier is not empty
		if( ! $this->auth_identifier )
		{
			return FALSE;
		}

		// Get the last user modification time from the session
		$user_last_mod = $this->expose_user_last_mod( $this->auth_identifier );

		// Get the user ID from the session
		$user_id = $this->expose_user_id( $this->auth_identifier );

		// Get the last login time from the session
		$login_time = $this->expose_login_time( $this->auth_identifier );

		/*
		 * Check database for matching user record:
		 * 1) last user modification time matches
		 * 2) user ID matches
		 * 3) login time matches ( not applicable if multiple logins allowed )
		 */
		$auth_data = $this->CI->auth_model->check_login_status( $user_last_mod, $user_id, $login_time );

		// If the query produced a match
		if( $auth_data !== FALSE )
		{
			if(
				// Check if the user is banned
				( $auth_data->user_banned === '1' )

				/*
				 * If multiple logins are disallowed, 
				 * check that the user agent string 
				 * is the same as one that logged in
				 */
				OR ( config_item('disallow_multiple_logins') && md5( $this->CI->input->user_agent() ) != $auth_data->user_agent_string )

				// Check that the user is of high enough level to be here
				OR ( is_int( $required_level ) && $auth_data->user_level < $required_level )
				OR ( is_array( $required_level ) && ! in_array( $this->roles[$auth_data->user_level], $required_level ) )
			)
			{
				// Logged in check failed ...
				log_message(
					'debug',
					"\n user is banned                  = " . ( $auth_data->user_banned === 1 ? 'yes' : 'no' ) .
					"\n disallowed multiple logins      = " . ( config_item('disallow_multiple_logins') ? 'true' : 'false' ) .
					"\n hashed user agent               = " . md5( $this->CI->input->user_agent() ) . 
					"\n user agent from database        = " . $auth_data->user_agent_string . 
					"\n required level                  = " . $required_level . 
					"\n user level in database          = " . $auth_data->user_level . 
					"\n user level in database (string) = " . $this->roles[$auth_data->user_level]
				);
			}
			else
			{
				// Send the auth data back to the controller
				return $auth_data;
			}
		}
		else
		{
			// Auth Data === FALSE because no user matching in DB ...
			log_message(
				'debug',
				"\n last user modification time from session = " . $user_last_mod . 
				"\n user id from session                     = " . $user_id . 
				"\n last login time from session             = " . $login_time . 
				"\n disallowed multiple logins               = " . ( config_item('disallow_multiple_logins') ? 'true' : 'false' )
			);
		}

		// Unset session
		$this->CI->session->unset_userdata('auth_identifier');

		return FALSE;
	}

	// --------------------------------------------------------------

	/**
	 * Gets the hold status for the user's IP,
	 * posted username or posted email address
	 * Post variable for email address is different 
	 * for login vs recovery, hence the lone bool parameter.
	 * 
	 * @param   bool   if check is from recovery (FALSE if from login)
	 * @return  bool
	 */
	public function current_hold_status( $recovery = FALSE )
	{
		// Clear holds that have expired
		$this->CI->auth_model->clear_expired_holds();

		// Check to see if the IP or posted username/email-address is now on hold
		return $this->CI->auth_model->check_holds( $recovery );
	}

	// --------------------------------------------------------------

	/**
	 * Create the auth identifier, which contains 
	 * the user ID and last modification time.
	 * 
	 * @param   int  the user ID 
	 * @param   int  an epoch time that the user account was last modified
	 * @return  int  the auth identifier
	 */
	public function create_auth_identifier( $user_id, $user_modified, $login_time )
	{
		$umod_split = str_split( $user_modified , 5 );

		$login_time_split = str_split( $login_time , 5 );

		return $login_time_split[0] .
			rand(0,9) .
			$umod_split[1] .
			rand(0,9) .
			$user_id .
			rand(0,9) .
			$umod_split[0] .
			rand(0,9) .
			rand(0,9) .
			$login_time_split[1];
	}

	// --------------------------------------------------------------

	/**
	 * Reveal the user ID hiding within the auth identifier
	 * 
	 * @param   int  the auth identifier
	 * @return  int  the user ID
	 */
	public function expose_user_id( $auth_identifier )
	{
		$temp = substr( $auth_identifier , 12 );

		return substr_replace( $temp , '' , -13 );
	}

	// --------------------------------------------------------------

	/**
	 * Reveal the last modification time hiding within the auth identifier
	 * 
	 * @param   int  the auth identifier
	 * @return  int  the user's last modified data
	 */
	public function expose_user_last_mod( $auth_identifier )
	{
		return substr( $auth_identifier , -12 , 5 ) . substr( $auth_identifier , 6 , 5 );
	}

	// --------------------------------------------------------------

	/**
	 * Reveal the login time hiding within the auth identifier
	 * 
	 * @param   int  the auth identifier
	 * @return  int  the user's last login time
	 */
	public function expose_login_time( $auth_identifier )
	{
		return substr( $auth_identifier , 0 , 5 ) . substr( $auth_identifier , -5 , 5 );
	}

	// --------------------------------------------------------------

	/**
	 * Insert details of failed login attempt into database
	 * 
	 * @param  string  the username or email address used to attempt login
	 */
	public function log_error( $string )
	{
		// Clear up any expired rows in the login errors table
		$this->CI->auth_model->clear_login_errors();

		// Insert the error
		$data = array(
			'username_or_email' => $string,
			'IP_address'        => $this->CI->input->ip_address(),
			'time'              => time()
		);

		$this->CI->auth_model->create_login_error( $data );

		$this->CI->auth_model->check_login_attempts( $string );
	}

	// --------------------------------------------------------------

	/**
	 * Log the user out
	 */
	public function logout()
	{
		// Get the user ID from the session
		$user_id = $this->expose_user_id( $this->auth_identifier );

		// Delete last login time from user record
		$this->CI->auth_model->logout( $user_id );

		if( config_item('delete_session_cookie_on_logout') )
		{
			// Completely delete the session cookie
			delete_cookie( config_item('sess_cookie_name') );
		}
		else
		{
			// Unset auth identifier
			$this->CI->session->unset_userdata('auth_identifier');
		}

		$this->CI->load->helper('cookie');

		// Delete remember me cookie
		delete_cookie( config_item('remember_me_cookie_name') );

		// Delete the http user cookie
		delete_cookie( config_item('http_user_cookie_name') );
	}

	// --------------------------------------------------------------

	/**
	 * Hash Password
	 *
	 * @param  string  The raw (supplied) password
	 * @param  string  The random salt
	 */
	public function hash_passwd( $password, $random_salt )
	{
		/**
		 * bcrypt is the preferred hashing for passwords, but
		 * is only available for PHP 5.3+. Even in a PHP 5.3+ 
		 * environment, we have the option to use PBKDF2; just 
		 * set the PHP52_COMPATIBLE_PASSWORDS constant located 
		 * in config/constants.php to 1.
		 */
		if( CRYPT_BLOWFISH == 1 && PHP52_COMPATIBLE_PASSWORDS === 0 )
		{
			return crypt( $password . config_item('encryption_key'), '$2a$09$' . $random_salt . '$' );
		}

		// Fallback to PBKDF2 if bcrypt not available
		$this->CI->load->helper('pbkdf2');

		/**
		 * Key length (param #5) set at 30 so that pbkdf2() 
		 * returns a string which has a length that matches 
		 * the length of the `user_pass` field (60 chars).
		 */
		return pbkdf2( 'sha256', $password . config_item('encryption_key'), $random_salt, 4096, 30, FALSE );
	}

	// --------------------------------------------------------------

	/**
	 * Check Password
	 *
	 * @param  string  The hashed password 
	 * @param  string  The random salt
	 * @param  string  The raw (supplied) password
	 */
	public function check_passwd( $hash, $random_salt, $password )
	{
		if( $hash === $this->hash_passwd( $password, $random_salt ) )
		{
			return TRUE;
		}

		return FALSE;
	}

	// --------------------------------------------------------------

	/**
	 * Make Random Salt
	 */
	public function random_salt()
	{
		return md5( mt_rand() );
	}

	// --------------------------------------------------------------
}

/* End of file Authentication.php */
/* Location: /application/libraries/Authentication.php */ 