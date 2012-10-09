<?php if( ! defined('BASEPATH') ) exit('No direct script access allowed');
/**
 * Community Auth - MY_Controller
 *
 * Community Auth is an open source authentication application for CodeIgniter 2.1.3
 *
 * @package     Community Auth
 * @author      Robert B Gottier
 * @copyright   Copyright (c) 2011 - 2012, Robert B Gottier. (http://brianswebdesign.com/)
 * @license     BSD - http://http://www.opensource.org/licenses/BSD-3-Clause
 * @link        http://community-auth.com
 */

class MY_Controller extends CI_Controller
{
	/**
	 * The logged-in user's user ID
	 *
	 * @var string
	 * @access public
	 */
	public $auth_id;

	/**
	 * The logged-in user's username
	 *
	 * @var string
	 * @access public
	 */
	public $auth_user_name;

	/**
	 * The logged-in user's first name
	 *
	 * @var string
	 * @access public
	 */
	public $auth_first_name;

	/**
	 * The logged-in user's last name
	 *
	 * @var string
	 * @access public
	 */
	public $auth_last_name;

	/**
	 * The logged-in user's authentication account type by number
	 *
	 * @var string
	 * @access public
	 */
	public $auth_level;

	/**
	 * The logged-in user's authentication account type by name
	 *
	 * @var string
	 * @access public
	 */
	public $auth_role;

	/**
	 * The location of the main template view which most other views get nested inside
	 *
	 * @var string
	 * @access public
	 */
	public $template = 'templates/main_template';

	/**
	 * The logged-in user's authentication data,
	 * which is their user table record, but could
	 * be whatever you want it to be if you modify 
	 * the queries in the auth model.
	 *
	 * @var object
	 * @access private
	 */
	private $auth_data;

	/**
	 * Either 'https' or 'http' depending on the current environment
	 *
	 * @var string
	 * @access public
	 */
	public $protocol = 'http';

	// --------------------------------------------------------------

	/**
	 * Class constructor
	 */
	public function __construct()
	{
		parent::__construct();

		/**
		 * If the production environment, FirePHP is disabled.
		 * This is handy because FirePHP debugging code can be left 
		 * within the application with no potential risks.
		 */
		if( ENVIRONMENT == 'production' && $this->load->is_loaded('fb') )
		{
			$this->fb->setEnabled( FALSE );
		}

		/**
		 * If not the production environment, load ChromePhp 
		 * for PHP debugging in Google's Chrome browser console.
		 */
		if( ENVIRONMENT != 'production' )
		{
			include APPPATH . 'libraries/ChromePhp.php';
		}

		/**
		 * Set no-cache headers so pages are never cached by the browser.
		 * This is necessary because if the browser caches a page, the 
		 * login or logout link and user specific data may not change when 
		 * the logged in status changes.
		 */
	 	header('Expires: Wed, 13 Dec 1972 18:37:00 GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
		header('Pragma: no-cache');

		/**
		 * By setting the protocol here, we don't have to test for it 
		 * everytime we want to know if we are in a secure environment or not.
		 */
		if( ! empty( $_SERVER['HTTPS'] ) && strtolower( $_SERVER['HTTPS'] ) !== 'off' )
		{
			$this->protocol = 'https';
		}

		$this->load->vars(
			array( 'protocol' => $this->protocol )
		);

		/**
		 * If the http user cookie is set, make user data available in views
		 */
		if( get_cookie( config_item('http_user_cookie_name') ) )
		{
			$http_user_data = $this->session->unserialize_data( get_cookie( config_item('http_user_cookie_name') ) );

			$this->load->vars( $http_user_data );
		}

		// Get Google Analytics tracking code
		$this->load->vars( array( 'tracking_code' => config_item('tracking_code') ) );

		// Warn if installer is not disabled
		$this->check_installer_disabled();

		//$this->output->enable_profiler();
	}

	// --------------------------------------------------------------

	/**
	 * Require a login by user of account type specified numerically.
	 * User assumes your priveledges are linear in relationship to account types.
	 * 
	 * @param  int    the minimum level of user required
	 * @param  mixed  either returns TRUE or doesn't return
	 */
	protected function require_min_level( $level )
	{
		if( $this->auth_data = $this->authentication->user_status( $level ) )
		{
			$this->_set_user_variables();

			return TRUE;
		}
		else
		{
			$this->_setup_login_form();
		}
	}

	// --------------------------------------------------------------

	/**
	 * Require a login by role in a specific group
	 * or groups, specified by group name(s).
	 * 
	 * @param  string  a group name or names as a comma separated string.
	 */
	protected function require_group( $group_names )
	{
		// Get all groups from config
		$groups = config_item('groups');

		// Get group(s) allowed to login
		$group_array = explode( ',', $group_names );

		// Trim off any space chars
		$group_array = array_map( 'trim', $group_array );

		// Initialize array of roles allowed to login
		$roles = array();

		// Add group members to roles array
		foreach( $group_array as $group )
		{
			// Turn group members into an array
			$temp_arr = explode( ',', $groups[$group] );

			// Merge array of group members with roles array
			$roles = array_merge( $roles, $temp_arr );
		}

		// Turn the array of roles into a comma seperated string
		$roles_string = implode( ',', $roles );

		// Try to login via require_role method
		return $this->require_role( $roles_string );
	}

	// --------------------------------------------------------------

	/**
	 * Require a login by user of a specific account type, specified by name(s).
	 * 
	 * @param  string  a comma seperated string of account types that are allowed.
	 * @param  mixed  either returns TRUE or doesn't return
	 */
	protected function require_role( $roles )
	{
		// Turn the roles string into an array or roles
		$role_array = explode( ',', $roles );

		// Trim off any space chars
		$role_array = array_map( 'trim', $role_array );

		if( $this->auth_data = $this->authentication->user_status( $role_array ) )
		{
			$this->_set_user_variables();

			return TRUE;
		}
		else
		{
			$this->_setup_login_form();
		}
	}

	// --------------------------------------------------------------

	/**
	 * Function used for allowing a login that isn't required. An example would be
	 * a optional login during checkout in an eCommerce application. Login isn't 
	 * mandatory, but useful because a user's account can be accessed.
	 *
	 * @return  mixed  either returns TRUE or doesn't return
	 */
	protected function optional_login()
	{
		if( $this->auth_data = $this->authentication->user_status( 0 ) )
		{
			$this->_set_user_variables();

			return TRUE;
		}
	}

	// --------------------------------------------------------------

	/**
	 * Function is an alias of verify_min_level, but with no arguments.
	 */
	protected function is_logged_in()
	{
		$this->verify_min_level();
	}

	// --------------------------------------------------------------

	/**
	 * Verify if user logged in by account type specified numerically.
	 * This is for use when login is not required, but beneficial.
	 * 
	 * @param   int    the minimum level of user to be verified.
	 * @return  mixed  either returns TRUE or doesn't return
	 */
	protected function verify_min_level( $level = 0 )
	{
		if( $this->auth_data = $this->authentication->check_login( $level ) )
		{
			$this->_set_user_variables();

			return TRUE;
		}
	}

	// --------------------------------------------------------------

	/**
	 * Verify if user logged in by account type specified by name(s).
	 * This is for use when login is not required, but beneficial.
	 * 
	 * @param   string  comma seperated string of account types that to be verified.
	 * @return  mixed   either returns TRUE or doesn't return
	 */
	protected function verify_role( $roles )
	{
		$role_array = explode( ',', $roles );

		if( $this->auth_data = $this->authentication->check_login( $role_array ) )
		{
			$this->_set_user_variables();

			return TRUE;
		}
	}

	// --------------------------------------------------------------

	/**
	 * Set variables related to authentication, for use in views / controllers.
	 */
	private function _set_user_variables()
	{
		// Set user specific variables to be available in controllers
		$this->auth_user_id    = $this->auth_data->user_id;
		$this->auth_user_name  = $this->auth_data->user_name;
		$this->auth_first_name = $this->auth_data->first_name;
		$this->auth_last_name  = $this->auth_data->last_name;
		$this->auth_level      = $this->auth_data->user_level;
		$this->auth_role       = $this->authentication->roles[$this->auth_data->user_level];
		$this->auth_email      = $this->auth_data->user_email;

		// Set user specific variables to be available in all views
		$data = array(
			'auth_user_id'    => $this->auth_user_id,
			'auth_user_name'  => $this->auth_user_name,
			'auth_first_name' => $this->auth_first_name,
			'auth_last_name'  => $this->auth_last_name,
			'auth_level'      => $this->auth_level,
			'auth_role'       => $this->auth_role,
			'auth_email'      => $this->auth_email
		);
		$this->load->vars($data);

		// Set user specific variables to be available as config items
		$this->config->set_item( 'auth_user_id',    $this->auth_user_id );
		$this->config->set_item( 'auth_user_name',  $this->auth_user_name );
		$this->config->set_item( 'auth_first_name', $this->auth_user_name );
		$this->config->set_item( 'auth_last_name',  $this->auth_user_name );
		$this->config->set_item( 'auth_level',      $this->auth_level );
		$this->config->set_item( 'auth_role',       $this->auth_role );
		$this->config->set_item( 'auth_email',      $this->auth_email );
	}

	// --------------------------------------------------------------

	/**
	 * Output the login form, or show message that max login attempts exceeded.
	 */
	private function _setup_login_form()
	{
		// Ouput alert-bar message if cookies not enabled
		$this->check_cookies_enabled('Cookies are required to login. Please enable cookies.');

		/**
		 * Check if IP, username, or email address on hold.
		 *
		 * If a malicious form post set the on_hold authentication class 
		 * member to TRUE, there'd be no reason to continue. Keep in mind that 
		 * since an IP address may legitimately change, we shouldn't do anything 
		 * drastic unless this happens more than an acceptable amount of times.
		 * See the 'deny_access' config setting in config/authentication.php
		 */
		if( $this->authentication->on_hold === TRUE )
		{
			$view_data['on_hold_message'] = 1;
		}

		// This check for on hold is for normal login attempts
		else if( $on_hold = $this->authentication->current_hold_status() )
		{
			$view_data['on_hold_message'] = 1;
		}

		// If not on hold, proceed with caution :)
		else
		{
			// Check if CSRF class already loaded
			if( $this->load->is_loaded('csrf') )
			{
				// If already loaded, reload it
				$this->csrf->reload( array( 'token_name' => 'login_token' ) );
			}
			else
			{
				// If not already loaded, load it
				$this->load->library('csrf', array( 'token_name' => 'login_token' ) );
			}
		}

		// Display a login error message if there was a form post
		if( $this->authentication->login_error === TRUE )
		{
			// Display a failed login attempt message
			$view_data['login_error_mesg'] = 1;
		}

		// Get form from authentication class / log failed login attempt if applicable
		$data = array(
			'title' => WEBSITE_NAME . ' - Login',
			'javascripts' => array(
				'js/jquery.passwordToggle-1.1.js',
				'js/jquery.char-limiter-3.0.0.js',
				'js/default-char-limiters.js'
			),
			'extra_head' => '
				<script>
					$(document).ready(function(){
						$("#show-password").passwordToggle({target:"#login_pass"});
					});
				</script>
			',
			'content' => $this->load->view( 'auth/login_form', ( isset( $view_data ) ) ? $view_data : '', TRUE )
		);

		$this->load->view('templates/main_template', $data);
	}

	// --------------------------------------------------------------

	/**
	 * Checks if logged in user is of a specific account type
	 * 
	 * @param   string  a comma seperated string of account types to check.
	 * @return  bool
	 */
	protected function is_role( $role = '' )
	{
		if( $role != '' && ! empty( $this->auth_role ) )
		{
			$role_array = explode( ',', $role );

			if( in_array( $this->auth_role, $role_array ) )
			{
				return TRUE;
			}
		}

		return FALSE;
	}

	// --------------------------------------------------------------

	/**
	 * Force the request to be redirected to HTTPS, or optionally show 404.
	 * A strong security policy does not allow for redirection.
	 */
	protected function force_ssl()
	{
		// Force SSL if available
		if( USE_SSL !== 0 && $this->protocol == 'http' )
		{
			// Allow redirect to the HTTPS page
			if( REDIRECT_TO_HTTPS !== 0 )
			{
				// Load string helper for trim_slashes function
				$this->load->helper('string');

				// 301 Redirect to the secure page
				header("Location: " . secure_site_url( trim_slashes( $this->uri->uri_string() ) ), TRUE, 301);
			}

			// Show a 404 error
			else
			{
				show_404();
			}

			exit;
		}
	}

	// --------------------------------------------------------------

	/**
	 * Check if cookies are enabled 
	 * 
	 * @param  string  custom message to display if cookies are not enabled
	 */
	protected function check_cookies_enabled( $message = '' )
	{
		$view_data['message'] = 'This site requires cookies for much of it\'s functionality. Please enable browser cookies.';

		if( $message != '' )
		{
			$view_data['message'] = $message;
		}

		$cookie_checker_js = $this->load->view('dynamic_js/cookie_checker', $view_data, TRUE );

		$this->load->vars( array( 'cookie_checker' => $cookie_checker_js ) );
	}

	// --------------------------------------------------------------

	/**
	 * If the installer is not disabled, a message bar will alert to disable the installer.
	 */
	protected function check_installer_disabled()
	{
		if( $this->uri->segment(1) != 'init' && config_item('disable_installer') === FALSE )
		{
			$this->load->vars( 
				array( 
					'extra_head' => '
						<script>
							$(document).ready(function(){
								$("#alert-bar")
									.html("The Installer IS NOT Disabled in The Authentication Config. You Must Disable it Immediately After Installation!")
									.css({\'background-color\':\'yellow\',\'color\':\'#bf1e2e\'})
									.show();
							});
						</script>
					' 
				) 
			);
		}
	}

}

/* End of file MY_Controller.php */
/* Location: /application/libraries/MY_Controller.php */