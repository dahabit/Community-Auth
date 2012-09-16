<?php if( ! defined('BASEPATH') ) exit('No direct script access allowed');
/**
 * Community Auth - MY_Encrypt Library
 *
 * Community Auth is an open source authentication application for CodeIgniter 2.1.2
 *
 * @package     Community Auth
 * @author      Robert B Gottier
 * @copyright   Copyright (c) 2011 - 2012, Robert B Gottier. (http://brianswebdesign.com/)
 * @license     BSD - http://http://www.opensource.org/licenses/BSD-3-Clause
 * @link        http://community-auth.com
 */

class MY_Encrypt extends CI_Encrypt {

	/**
	 * Constructor
	 *
	 * Simply determines whether the mcrypt library exists.
	 *
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Get Mcrypt cipher Value
	 *
	 * This method is extended only to set the default encryption to blowfish.
	 * This has only been chosen to cut down on the encrypted string length, as 
	 * the default, which is MCRYPT_RIJNDAEL_256 creates strings that are roughly 
	 * 10 times the length of the original string.
	 *
	 * @access	private
	 * @return	string
	 */
	function _get_cipher()
	{
		if ($this->_mcrypt_cipher == '')
		{
			$this->_mcrypt_cipher = MCRYPT_BLOWFISH;
		}

		return $this->_mcrypt_cipher;
	}

	// --------------------------------------------------------------------

}

// END MY_Encrypt class

/* End of file MY_Encrypt.php */
/* Location: ./application/libraries/MY_Encrypt.php */