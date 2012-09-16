<?php if( ! defined('BASEPATH') ) exit('No direct script access allowed');
/**
 * Community Auth - MY_Email
 *
 * Community Auth is an open source authentication application for CodeIgniter 2.1.2
 *
 * @package     Community Auth
 * @author      Robert B Gottier
 * @copyright   Copyright (c) 2011 - 2012, Robert B Gottier. (http://brianswebdesign.com/)
 * @license     BSD - http://http://www.opensource.org/licenses/BSD-3-Clause
 * @link        http://community-auth.com
 */
 
class MY_Email extends CI_Email {

	/**
	 * Send an email to a single recipient by calling a single function 
	 * 
	 * @param  string  the email address of the sender
	 * @param  string  the name of the sender
	 * @param  string  the email address of the recipient
	 * @param  string  the subject of the email
	 * @param  string  the name of the email template ( a view )
	 * @param  array   an optional array of view_data to inject into the email template
	 */
	public function quick_email( 
		$from_email, 
		$from_name, 
		$recipient_email, 
		$subject, 
		$email_template, 
		$template_data = array() 
	)
	{
		global $CI;

		$template_data['from_email']      = $from_email;
		$template_data['from_name']       = $from_name;
		$template_data['recipient_email'] = $recipient_email;
		$template_data['subject']         = $subject;
		$template_data['content']         = $CI->load->view( $email_template, $template_data, TRUE );

		$built_message = $CI->load->view( 'email_templates/email-boilerplate.php', $template_data, TRUE );

		// Send email if not development environment
		if( ENVIRONMENT != 'development' )
		{
			$this->from( $from_email , $from_name );
			$this->to( $recipient_email );
			$this->subject( $subject );
			$this->message( $built_message );
			$this->send();
		}

		// Log email if development environment
		else
		{
			$CI->load->helper('file');

			write_file( APPPATH . 'logs/email/' . microtime(TRUE) . '.html', $built_message );
		}

		// Reset for second email
		$this->clear();
	}

	// --------------------------------------------------------------

}

/* End of file MY_Email.php */
/* Location: ./application/libraries/MY_Email.php */