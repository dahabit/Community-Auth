<?php if( ! defined('BASEPATH') ) exit('No direct script access allowed');
/**
 * Community Auth - CSRF Library
 *
 * Community Auth is an open source authentication application for CodeIgniter 2.1.2
 *
 * @package     Community Auth
 * @author      Robert B Gottier
 * @copyright   Copyright (c) 2011 - 2012, Robert B Gottier. (http://brianswebdesign.com/)
 * @license     BSD - http://http://www.opensource.org/licenses/BSD-3-Clause
 * @link        http://community-auth.com
 */

class csrf
{
	/**
	 * The name of the CSRF token
	 *
	 * @var string
	 * @access public
	 */
	public $token_name    = 'token';

	/**
	 * The actual value of the CSRF token
	 *
	 * @var mixed
	 * @access public
	 */
	public $token         = FALSE;

	/**
	 * The value of the posted token
	 *
	 * @var mixed
	 * @access public
	 */
	public $posted_token  = FALSE;

	/**
	 * The value of the current CSRF token
	 *
	 * @var mixed
	 * @access public
	 */
	public $current_token = FALSE;

	/**
	 * Whether or not the posted token matches the current token
	 *
	 * @var bool
	 * @access public
	 */
	public $token_match   = FALSE;

	/**
	 * The CodeIgniter super object
	 *
	 * @var object
	 * @access private
	 */
	private $CI;

	/**
	 * The current scheme / protocol
	 *
	 * @var string
	 * @access private
	 */
	private $scheme = 'http';

	/**
	 * Class constructor
	 */
	public function __construct( $config=array() )
	{
		if( ! empty( $_SERVER['HTTPS'] ) && strtolower( $_SERVER['HTTPS'] ) !== 'off' )
		{
			// Set the current scheme / protocol
			$this->scheme = 'https';
		}

		$this->CI =& get_instance();

		// Load session library if not loaded
		$this->CI->load->library('session');

		if( ! empty( $config ) )
		{
			if( isset( $config['token_name'] ) )
			{
				$this->token_name = $config['token_name'];
			}
		}

		// If request is a HTTP POST
		if( $this->CI->input->post( $this->token_name ) )
		{
			// Set the posted_token variable to the value of the posted token
			$this->posted_token = $this->CI->input->post( $this->token_name );

			// If we can use normal session flashdata
			if( 
				config_item('cookie_secure') == FALSE OR
				( config_item('cookie_secure') == TRUE && $this->scheme == 'https' )
			)
			{
				// Set the current_token variable to the value of the current token
				$this->current_token = $this->CI->session->flashdata( $this->token_name );
			}

			// Use our own flashdata cookie, "csrfFd", if we can't use normal session flashdata
			else
			{
				// If the cookie is set
				if( get_cookie( config_item('csrfFd_cookie_name') ) )
				{
					// Make variables from the cookie's value
					list( $csrfFd_token_value, $user_agent, $time ) = explode( '>>>', get_cookie('csrfFd') );

					if( 
						// If the user agent matches the one that set the cookie
						$user_agent == trim(substr($this->CI->input->user_agent(), 0, 120)) && 

						// If the cookie has not expired
						$time + config_item('csrfFd_expiration') > time() 
					)
					{
						// Set the current_token variable
						$this->current_token = $csrfFd_token_value;
					}
				}
			}

			if(
				// If there is a posted token
				$this->posted_token !== FALSE

				// And if there is a flashdata token
				&& $this->current_token !== FALSE

				// And if the posted token equals the flashdata token
				&& $this->posted_token == $this->current_token
			)
			{
				$this->token_match = TRUE;
			}
		}

		// Generate a new token
		$this->generate_token();
	}

	// --------------------------------------------------------------

	/**
	 * Generate a token
	 */
	public function generate_token()
	{
		// Create a unique token
		$this->token = md5(uniqid() . microtime() . rand());

		// If we can use normal flashdata
		if( 
			config_item('cookie_secure') == FALSE OR
			( config_item('cookie_secure') == TRUE && $this->scheme == 'https' )
		)
		{
			// Add the token to the cookie, or change its value if it already exists
			$this->CI->session->set_flashdata( $this->token_name , $this->token);

			// If our csrfFd cookie is hanging around, delete it.
			delete_cookie( config_item('csrfFd_cookie_name') );
		}

		// Use our own flashdata cookie, "csrfFd"
		else
		{
			$fd_cookie = array(
				'name'   => config_item('csrfFd_cookie_name'),
				'value'  => $this->token . '>>>' . trim(substr($this->CI->input->user_agent(), 0, 120)) . '>>>' . time(),
				'expire' => config_item('csrfFd_expiration'),
				'domain' => config_item('cookie_domain'),
				'path'   => config_item('cookie_path'),
				'prefix' => config_item('cookie_prefix'),
				'secure' => FALSE
			);

			set_cookie( $fd_cookie );
		}
	}

	// --------------------------------------------------------------

	/**
	 * Reload the library
	 *
	 * Needed if CSRF loaded in controller constructor,
	 * because if authentication is needed, the login form
	 * needs a flashdata token with a special name.
	 */
	public function reload( $config = array() )
	{
		$this->token_name    = 'token';
		$this->token         = FALSE;
		$this->posted_token  = FALSE;
		$this->current_token = FALSE;
		$this->token_match   = FALSE;

		$this->__construct( $config );
	}

	// --------------------------------------------------------------
}

/* End of file csrf.php */
/* Location: /application/libraries/csrf.php */ 