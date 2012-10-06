<?php if( ! defined('BASEPATH') ) exit('No direct script access allowed');
/**
 * Community Auth - Contact Controller
 *
 * Community Auth is an open source authentication application for CodeIgniter 2.1.2
 *
 * @package     Community Auth
 * @author      Robert B Gottier
 * @copyright   Copyright (c) 2011 - 2012, Robert B Gottier. (http://brianswebdesign.com/)
 * @license     BSD - http://http://www.opensource.org/licenses/BSD-3-Clause
 * @link        http://community-auth.com
 */

class Contact extends MY_Controller {

	/**
	 * OFFLINE is only set for the Community Auth website,
	 * because I don't want people contacting me there.
	 * You might customize an offline mode for another reason,
	 * but it not, then you may remove the functionality
	 * associated with this class member.
	 */
	private $offline = TRUE;

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
	 * Display the contact page
	 */
	public function index()
	{
		// Home page does not require login, but user_name and user_role displayed if logged in
		$this->is_logged_in();

		// Load Resources
		$this->load->library('csrf');

		// If POST
		if( $this->csrf->token_match )
		{
			// Run the validation
			$this->load->library('form_validation');
			$this->form_validation->set_error_delimiters('<li>', '</li>');

			// The form validation class doesn't allow for multiple config files, so we do it the old fashion way
			$this->config->load( 'form_validation/contact/contact' );
			$this->form_validation->set_rules( config_item('contact') );

			if( $this->offline === FALSE )
			{
				if( $this->form_validation->run() !== FALSE )
				{
					// Prepare form data for email template
					foreach( $this->form_validation->get_field_data() as $k => $v )
					{
						$view_data[$k] = $v['postdata'];
					}

					$this->load->library('email');
					$this->config->load('email');

					$this->email->quick_email(
						// Sender's Email Address
						config_item('no_reply_email_address'),
						// Sender's Name
						WEBSITE_NAME,
						// Recipient's Email Address
						config_item('contact_form_recipient_email_address'),
						// Subject of Email
						WEBSITE_NAME . ' - Contact Form Submission - ' . date("M j, Y - g:ia"),
						// Email Template
						'email_templates/contact',
						// Template View Data
						$view_data
					);

					$view_data['confirmation'] = 1;

					// Kill set_value() for all input, since we won't need it anymore
					$this->form_validation->unset_field_data('*');
				}
				else
				{
					// Display errors if they exist
					$view_data['error_message_stack'] = validation_errors();

					// Do not repopulate with data that did not validate
					foreach( $this->input->post() as $k => $v )
					{
						if( array_key_exists( $k, $this->form_validation->get_error_array() ))
						{
							// Kill set_value()
							$this->form_validation->unset_field_data( $k );
						}
					}
				}
			}
			else
			{
				$view_data['offline'] = 1;
			}
		}

		$data = array(
			'title' => WEBSITE_NAME . ' - Contact Us',
			'javascripts' => array(
				'js/jquery.char-limiter-3.0.0.js',
				'js/default-char-limiters.js'
			),
			'content' => $this->load->view( 'contact/contact', ( isset( $view_data ) ) ? $view_data : '', TRUE )
		);

		if( $this->offline )
		{
			$data['final_html'] = $this->load->view( 'contact/offline_modal', '', TRUE );
			$data['javascripts'][] = 'js/contact/contact.js';
		}

		$this->load->view( $this->template, $data );
	}

	// --------------------------------------------------------------
}

/* End of file contact.php */
/* Location: ./application/controllers/contact.php */