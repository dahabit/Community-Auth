<?php if( ! defined('BASEPATH') ) exit('No direct script access allowed');
/**
 * Community Auth - Init Controller
 *
 * Community Auth is an open source authentication application for CodeIgniter 2.1.2
 *
 * @package     Community Auth
 * @author      Robert B Gottier
 * @copyright   Copyright (c) 2011 - 2012, Robert B Gottier. (http://brianswebdesign.com/)
 * @license     BSD - http://http://www.opensource.org/licenses/BSD-3-Clause
 * @link        http://community-auth.com
 */

class Init extends MY_Controller {

	/**
	 * Admin's user level
	 *
	 * @var int
	 * @access private
	 */
	private $admin_user_level;

	/**
	 * Errors to display in the view
	 *
	 * @var array
	 * @access private
	 */
	private $error_message_stack = array();

	/**
	 * The tables in the database
	 *
	 * @var array
	 * @access private
	 */
	private $tables              = array();

	// --------------------------------------------------------------

	/**
	 * Class constructor
	 */
	public function __construct()
	{
		parent::__construct();

		// Force encrypted connection
		$this->force_ssl();

		// Load resources
		$this->load->model('user_model');
		$this->load->library('csrf');

		// Get the Admin user level number from the authentication config
		$account_types = config_item('account_types');

		while( $role = current( $account_types ) )
		{
			if( $role == 'Admin' )
			{
				$this->admin_user_level = key( $account_types );

				break;
			}

			next( $account_types );
		}

		// Use special template
		$this->template = 'templates/installation_template';

		// Get tables in database
		$this->tables = $this->db->list_tables();
	}

	// --------------------------------------------------------------

	/**
	 * Population of database tables, creation of Admin, and creation of test users.
	 */
	public function index()
	{
		// Check if script totally disabled
		if( ! config_item('disable_installer') )
		{
			// Check if a valid form submission has been made
			if( $this->csrf->token_match )
			{
				if( 
					// If there are already tables created
					( ! $this->input->post('populate_database') && count( $this->tables ) > 0 ) OR

					// If there are no tables, but tables to be created
					$this->input->post('populate_database')
				)
				{
					// Run the validation
					$this->load->library('form_validation');
					$this->form_validation->set_error_delimiters('<li>', '</li>');

					// The form validation class doesn't allow for multiple config files, so we do it the old fashion way
					$this->config->load( 'form_validation/init/install' );
					$this->form_validation->set_rules( config_item('install_rules') );

					// If the post validates
					if( $this->form_validation->run() !== FALSE )
					{
						// Check if tables to be created
						$tables = set_value('populate_database');

						// Check if admin to be created
						$admin = set_value('admin');

						// Check if test users to be created
						$users = set_value('users');

						// Apply the test user password now because admin creation resets the form validation class
						$test_users_pass = set_value('test_users_pass');

						if( ! empty( $tables ) )
						{
							// Create the tables
							$tables_status = $this->_populate_database();
						}

						if( ! empty( $admin ) )
						{
							// Create the admin
							$this->_create_admin();
						}

						if( ! empty( $users ) )
						{
							// Create the test users
							$this->_create_test_users( $test_users_pass );
						}

						//kill set_value() since we won't need it
						$this->form_validation->unset_field_data('*');
					}

					// If validation failed
					else
					{
						// show errors
						$view_data['error_message_stack'] = validation_errors();

						// do not repopulate with data that did not validate
						$error_array = $this->form_validation->get_error_array();

						foreach( $this->input->post() as $k => $v )
						{
							if( array_key_exists( $k, $error_array ))
							{
								//kill set_value()
								$this->form_validation->unset_field_data( $k );
							}
						}
					}
				}
				else
				{
					$this->error_message_stack[] = '<li>You Must First Populate the Database Before Creating Users.</li>';
				}
			}

			// If a valid form submission has not been made, show error
			else if( ! empty( $_POST ) )
			{
				$this->error_message_stack[] = '<li>No Token Match</li>';
			}

			// If there are already tables created, or if the tables were just created
			if( count( $this->tables ) > 0 OR ( isset( $tables_status ) && $tables_status === TRUE ) )
			{
				$view_data['tables_installed'] = TRUE;

				// Check if Admin created
				$query = $this->db->get_where( 
					config_item( 'user_table' ), 
					array( 'user_level' => $this->admin_user_level ) 
				);

				$view_data['admin_created'] = ( $query->num_rows() > 0 ) ? TRUE : FALSE;

				// Check how many non-Admin users exist
				$this->db->where( 'user_level !=', $this->admin_user_level );

				$view_data['basic_user_count'] = $this->db->count_all_results( config_item( 'user_table' ) );
			}
			else
			{
				$view_data['tables_installed'] = FALSE;

				$view_data['admin_created']    = FALSE;
			}

			if( ! empty( $this->error_message_stack ) )
			{
				$view_data['error_message_stack'] = $this->error_message_stack;
			}

			$data = array(
				'title'     => 'Community Auth Installation',
				'no_robots' => 1,
				'javascripts' => array(
					'js/jquery.char-limiter-3.0.0.js',
					'js/default-char-limiters.js',
					'js/init/install.js'
				),
				'content'   => $this->load->view( 'init/install', $view_data, TRUE )
			);

			$this->load->view( $this->template, $data );
		}
		else
		{
			show_404();
		}
	}

	// --------------------------------------------------------------

	/**
	 * Population of database (table creation)
	 */
	private function _populate_database()
	{
		// Load db.sql file as string
		if( $sql = $this->load->view( 'sql/db', '', TRUE ) )
		{
			// Get the db connection platform
			$platform = $this->db->platform();

			// If mysqli or mysql
			if( $platform == 'mysqli' OR $platform == 'mysql' )
			{
				// Break the sql file into separate queries
				$queries = explode( ';', $sql );

				// Do each query
				foreach( $queries as $query )
				{
					$this->db->simple_query( trim( $query ) );
				}

				return TRUE;
			}

			// If not mysqli or mysql
			else
			{
				$this->error_message_stack[] = '<li>Database Platform Not Supported</li>';
			}
		}

		return FALSE;
	}

	// --------------------------------------------------------------

	/**
	 * Admin creation
	 */
	private function _create_admin()
	{
		// Reset the form validation class
		$this->form_validation->reset();

		// Set the admin user level
		$_POST['user_level'] = $this->admin_user_level;

		$this->user_model->create_user( array(), 'self_created' );
	}

	// --------------------------------------------------------------

	/**
	 * Creation of test users
	 */
	private function _create_test_users( $test_users_pass )
	{
		// Make sure the test users password is not empty
		if( ! empty( $test_users_pass ) )
		{
			// Get the array of test users
			$test_user_data = $this->_get_test_users_data();

			// Check if even one of the test users already exists
			$i = 0;
			foreach( $test_user_data as $user )
			{
				if( $i == 0 )
				{
					$this->db->where( 'user_name', $user[0] );
					$this->db->or_where( 'user_email', $user[1] );
				}
				else
				{
					$this->db->or_where( 'user_name', $user[0] );
					$this->db->or_where( 'user_email', $user[1] );
				}
				$i++;
			}
			$this->db->from( config_item('user_table') );
			$result = $this->db->count_all_results();

			// If none of the test users exist
			if( $result == 0 )
			{
				// Load the encryption library to encrypt the license number
				$this->load->library('encrypt');

				// Start test user's user IDs at 93062220
				$test_user_id = 93062220;

				foreach( $test_user_data as $user )
				{
					// Generate random user salt
					$user_salt = $this->authentication->random_salt();

					// Setup user record
					$user_data[] = array(
						'user_id'       => $test_user_id,
						'user_name'     => $user[0],
						'user_pass'     => $this->authentication->hash_passwd( $test_users_pass, $user_salt ),
						'user_salt'     => $user_salt,
						'user_email'    => $user[1],
						// The first 5 test users will be managers, and the rest are customers.
						'user_level'    => ( $test_user_id < 93062224 ) ? 6 : 1,
						'user_date'     => time(),
						'user_modified' => time()
					);

					// Setup profile record
					$profile_data[] = array(
						'user_id'        => $test_user_id,
						'first_name'     => $user[2],
						'last_name'      => $user[3],
						'license_number' => $this->encrypt->encode( $user[4] )
					);

					$test_user_id++;
				}

				// Insert the records
				$this->db->insert_batch( config_item('user_table'), $user_data );
				$this->db->insert_batch( config_item('profiles_table'), $profile_data );
			}
			else
			{
				$this->error_message_stack[] = '<li>Please Remove All Test Users Before Re-installing.</li>';
			}
		}
		else
		{
			$this->error_message_stack[] = '<li>All Test User Fields Must be Filled in to Create Test Users.</li>';
		}
	}

	// --------------------------------------------------------------

	/**
	 * Array of test users ( U.S. Presidents )
	 *
	 * Please note that the email addresses provided may or may not be 
	 * real email addresses. You should not use the test users outside 
	 * of the development environment so that you don't send 
	 * emails to people that may have one of these email addresses.
	 */
	private function _get_test_users_data()
	{
		return array(
			array('gwashing','gwashington@gmail.com','George','Washington','17891797'),
			array('jadams02','johnadams@hotmail.com','John','Adams','17971801'),
			array('tjeffers','thomasjefferson@msn.com','Thomas','Jefferson','18011809'),
			array('jmadison','jamesmadison@earthlink.net','James','Madison','18091817'),
			array('jmonroe5','jamesmonroe@yahoo.com','James','Monroe','18171825'),
			array('jqadams6','johnqadams@gmail.com','John','Adams','18251829'),
			array('ajackson','andrewjackson@yahoo.com','Andrew','Jackson','18291837'),
			array('mvburen8','martinvanburen@msn.com','Martin','Van Buren','18371841'),
			array('wharriso','williamharrison@yahoo.com','William','Harrison','18411841'),
			array('jtyler10','johntyler@hotmail.com','John','Tyler','18411845'),
			array('jkpolk11','jameskpolk@gmail.com','James','Polk','18451849'),
			array('ztaylor2','zacharytaylor@yahoo.com','Zachary','Taylor','18491850'),
			array('mfillmor','millardfillmore@gmail.com','Millard','Fillmore','18501853'),
			array('fpierce4','franklinpierce@yahoo.com','Franklin','Pierce','18531857'),
			array('jbuchana','jamesbuchanan@hotmail.com','James','Buchanan','18571861'),
			array('alincoln','abrahamlincoln@gmail.com','Abraham','Lincoln','18611865'),
			array('ajohnson','andrewjohnson@gmail.com','Andrew','Johnson','18651869'),
			array('ugrant18','ulyssesgrant@hotmail.com','Ulysses','Grant','18691877'),
			array('rhayes19','rutherfordbhayes@msn.com','Rutherford','Hayes','18771881'),
			array('jgarfiel','jamesgarfield@yahoo.com','James','Garfield','18811881'),
			array('caarthur','chesterarthur@msn.com','Chester','Arthur','18811885'),
			array('gclevela','grovercleveland@yahoo.com','Grover','Cleveland','18851889'),
			array('bharriso','benjaminharrison@gmail.com','Benjamin','Harrison','18891893'),
			array('gclevel2','grovercleveland@msn.com','Grover','Cleveland','18931897'),
			array('wmckinle','williammckinley@gmail.com','William','McKinley','18971901'),
			array('trooseve','theodoreroosevelt@msn.com','Theodore','Roosevelt','19011909'),
			array('whtaft27','williamhtaft@mac.com','William','Taft','19091913'),
			array('wwilson8','woodrowwilson@yahoo.com','Woodrow','Wilson','19131921'),
			array('wharding','warrenharding@gmail.com','Warren','Harding','19211923'),
			array('ccoolidg','calvincoolidge@hotmail.com','Calvin','Coolidge','19231929'),
			array('hhoover3','herberthoover@gmail.com','Herbert','Hoover','19291933'),
			array('frooseve','franklindroosevelt@yahoo.com','Franklin','Roosevelt','19331945'),
			array('htruman7','harrytruman@msn.com','Harry','Truman','19451953'),
			array('deisenho','dwighteisenhower@mac.com','Dwight','Eisenhower','19531961'),
			array('jkennedy','johnfkennedy@mac.com','John','Kennedy','19611963'),
			array('ljohnson','lyndonbjohnson@hotmail.com','Lyndon','Johnson','19631969'),
			array('rnixon98','richardnixon@hotmail.com','Richard','Nixon','19691974'),
			array('gford383','geraldford@mac.com','Gerald','Ford','19741977'),
			array('jcarter8','jimmycarter@aol.com','Jimmy','Carter','19771981'),
			array('rreagan7','ronaldreagan@gmail.com','Ronald','Reagan','19811989'),
			array('ghwbush1','georgehwbush@gmail.com','George','Bush','19891993'),
			array('bclinton','billclinton@yahoo.com','Bill','Clinton','19932001'),
			array('gwbush20','georgebush@gmail.com','George','Bush','20012009'),
			array('bobama66','theclown@whitehouse.gov','Barack','Obama','20092012')
		);
	}

	// --------------------------------------------------------------
}

/* End of file init.php */
/* Location: /application/controllers/init.php */