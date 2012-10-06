<?php if( ! defined('BASEPATH') ) exit('No direct script access allowed');
/**
 * Community Auth - Register Controller
 *
 * Community Auth is an open source authentication application for CodeIgniter 2.1.2
 *
 * @package     Community Auth
 * @author      Robert B Gottier
 * @copyright   Copyright (c) 2011 - 2012, Robert B Gottier. (http://brianswebdesign.com/)
 * @license     BSD - http://http://www.opensource.org/licenses/BSD-3-Clause
 * @link        http://community-auth.com
 */

class Register extends MY_Controller {

	/**
	 * Class constructor
	 */
	public function __construct()
	{
		parent::__construct();

		// Force encrypted connection
		$this->force_ssl();
	}

	// --------------------------------------------------------------

	/**
	 * Registration form
	 */
	public function index()
	{
		// Load resources
		$this->load->library('csrf');
		$this->load->model('registration_model');

		$reg_mode = $this->registration_model->get_reg_mode();

		// Check to see if there was a registration submission
		if( $this->csrf->token_match )
		{
			// If mode #1, registration allows for instant user creation without verification or approval.
			if( $reg_mode == 1 )
			{
				$_POST['user_level'] = 1;

				$this->load->model('user_model');
				$this->user_model->create_user( 'customer', array() );
			}

			/*
			 * If Mode #2 or #3 store temporary registration data and send email.
			 *
			 * Registration mode 2 uses verification by email.
			 * Registration mode 3 uses approval by admin.
			 */
			else if( $reg_mode == 2 || $reg_mode == 3 )
			{
				// Store the data
				if( $registration_id = $this->registration_model->set_pending() )
				{
					$this->load->library('email');
					$this->config->load('email');

					// Send email to registrant to confirm email address
					if( $reg_mode == 2 )
					{
						$this->email->quick_email(
							// Sender's Email Address
							config_item('no_reply_email_address'),
							// Sender's Name
							WEBSITE_NAME,
							// Recipient's Email Address
							set_value('user_email'),
							// Subject of Email
							WEBSITE_NAME . ' - Registration - ' . date("M j, Y"),
							// Email Template
							'email_templates/registration-confirmation-registrant',
							// Template View Data
							array( 'registration_id' => $registration_id )
						);
					}

					// Send email to admin to inform of pending registration
					else
					{
						$this->email->quick_email(
							// Sender's Email Address
							config_item('no_reply_email_address'),
							// Sender's Name
							WEBSITE_NAME,
							// Recipient's Email Address
							config_item('registration_review_email_address'),
							// Subject of Email
							WEBSITE_NAME . ' - Registration - ' . date("M j, Y"),
							// Email Template
							'email_templates/registration-notification-admin'
						);
					}
				}
			}
		}

		// If for some reason the user is already logged in
		$this->is_logged_in();

		// Send registration mode to view
		$view_data['reg_mode'] = $reg_mode;

		// Ouput alert-bar message if cookies not enabled
		$this->check_cookies_enabled();

		$data = array(
			'title' => WEBSITE_NAME . ' - Account Registration',
			'content' => $this->load->view( 'register/registration_form', $view_data, TRUE ),

			// Load the show password script
			'javascripts' => array(
				'js/jquery.passwordToggle-1.1.js',
				'js/jquery.char-limiter-3.0.0.js',
				'js/default-char-limiters.js'
			),

			// Use the show password script
			'extra_head' => '
				<script>
					$(document).ready(function(){
						$("#show-password").passwordToggle({target:"#user_pass"});
					});
				</script>
			'
		);

		$this->load->view( $this->template, $data );
	}

	// --------------------------------------------------------------

	/**
	 * Registration Settings
	 */
	public function settings()
	{
		// Only the admin can change registration modes
		if( $this->require_role('admin') )
		{
			// Load resources
			$this->load->library('csrf');
			$this->load->model('registration_model');

			// If there was a form submission to change modes
			if( $this->csrf->token_match )
			{
				// Change the mode and display the confirmation message
				if( $this->registration_model->set_reg_mode( (int) $this->input->post('reg_setting') ) === TRUE )
				{
					$view_data['confirmation'] = TRUE;
				}
			}

			// Get the new mode for display purposes
			$view_data['reg_setting'] = $this->registration_model->get_reg_mode();

			$data['content'] = $this->load->view( 'register/settings', $view_data, TRUE );

			$this->load->view( $this->template, $data );
		}
	}

	// --------------------------------------------------------------

	/**
	 * Review of pending registrations
	 */
	public function pending_registrations()
	{
		// Admin or manager login required
		if( $this->require_role('admin,manager') )
		{
			// Load resources
			$this->load->library('csrf');
			$this->load->model('registration_model');

			$reg_mode = $this->registration_model->get_reg_mode();

			// Check the registration mode is set for admin or email approval
			if( $reg_mode === '2' || $reg_mode === '3' )
			{
				// If registration mode is #2 or #3 and there was a approval or delete submission
				if( $this->csrf->token_match )
				{
					// Create an array of registration IDs to either approve or delete
					$ids = array();

					foreach( $this->input->post() as $k => $v )
					{
						if( strpos($k,'selected_') !== FALSE )
						{
							$ids[] = $v;
						}
					}

					// If the ID(s) are to be approved
					if( $this->input->post('approve') )
					{
						// Create user accounts and get email addresses to send email to new user
						$email_addresses = $this->registration_model->approve( $ids );

						// Send each new user an email
						if( $email_addresses !== FALSE )
						{
							$this->load->library('email');
							$this->config->load('email');

							foreach( $email_addresses as $email_address )
							{
								$this->email->quick_email(
									// Sender's Email Address
									config_item('no_reply_email_address'),
									// Sender's Name
									WEBSITE_NAME,
									// Recipient's Email Address
									$email_address,
									// Subject of Email
									WEBSITE_NAME . ' - Registration Approved - ' . date("M j, Y"),
									// Email Template
									'email_templates/registration-approved-user'
								);
							}
						}
					}

					// If the ID(s) are to be deleted, simply delete them from the temp data table
					else if( $this->input->post('delete') )
					{
						$this->registration_model->delete( $ids );
					}
				}

				$view_data['admin_mode'] = 1;

				// If no registrations are pending, display message
				if( $view_pending = $this->registration_model->view_pending() )
				{
					$view_data['pending_regs'] = $view_pending;
				}
				else
				{
					$view_data['no_pending_que'] = '<p>No Pending Registrations</p>';
				}
			}

			// Show message that the registration mode is not appropriate for processing pending registrations
			else
			{
				$view_data['reg_mode_mismatch'] = '<p>Registration mode mismatch</p>';
			}

			$data = array(
				'javascripts' => array(
					'js/jquery.tablesorter.js',
					'js/register/pending_registrations.js'
				),
				'content' => $this->load->view( 'register/show_pending', $view_data, TRUE )
			);

			$this->load->view( $this->template, $data );
		}

	}

	// --------------------------------------------------------------

	/**
	 * Confirmation, by email, to verify the registrant. 
	 * 
	 * @param  int  the registration ID 
	 */
	public function email_confirmation( $email_conf='' )
	{
		// Load resources
		$this->load->model('registration_model');

		// Check that the registration mode is set to verify registrations by email
		if( $this->registration_model->get_reg_mode() == '2' && $email_conf != '' )
		{

			// If there is a pending registration associated with the registration ID in the URL
			if( $this->registration_model->approve_by_email( (int) $email_conf ) !== FALSE )
			{
				$confirmed_by_email = 1;
			}
			else
			{
				$registration_closed = 1;
			}
		}
		else
		{
			$registration_closed = 1;
		}

		// For whatever reason, show the user as logged in if they are
		$this->is_logged_in();

		// If an email confirmation was successful, show a message
		if( isset( $confirmed_by_email ) )
		{
			$data['content'] = '<p>Thank you for registering. You may now login</p>';
		}

		// If an email confirmation was not successful, or if the registration mode is not right, display message
		if( isset( $registration_closed ) )
		{
			$data['content'] = '<p>Error during registration process. Please contact us via phone.</p>';
		}

		$this->load->view( $this->template, $data );
	}

	// --------------------------------------------------------------

}

/* End of file register.php */
/* Location: /application/controllers/register.php */