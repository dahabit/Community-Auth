<?php if( ! defined('BASEPATH') ) exit('No direct script access allowed');
/**
 * Community Auth - Custom Uploader Controller
 *
 * Community Auth is an open source authentication application for CodeIgniter 2.1.2
 *
 * @package     Community Auth
 * @author      Robert B Gottier
 * @copyright   Copyright (c) 2011 - 2012, Robert B Gottier. (http://brianswebdesign.com/)
 * @license     BSD - http://http://www.opensource.org/licenses/BSD-3-Clause
 * @link        http://community-auth.com
 */

class Custom_uploader extends MY_Controller {

	/**
	 * Class constructor
	 */
	public function __construct()
	{
		parent::__construct();

		// Force encrypted connection
		$this->force_ssl();

		// Load common resources
		$this->config->load('uploads_manager');
		$this->load->model('uploads_model');
	}

	// --------------------------------------------------------------

	/**
	 * Default method
	 */
	public function index()
	{
		$this->custom_gallery();
	}

	// --------------------------------------------------------------

	/**
	 * Uploader controls
	 */
	public function uploader_controls()
	{
		// Load resources
		$this->load->library('csrf');

		// Make sure anyone is logged in
		if( $this->require_min_level(1) )
		{
			// Get the uploader settings
			$view_data['uploader_settings'] = config_item('upload_configuration_custom_uploader');

			// Create a more human friendly version of the allowed_types
			$view_data['file_types'] = str_replace( '|', ' &bull; ', $view_data['uploader_settings']['allowed_types'] );

			// Get any existing images
			$view_data['images'] = $this->uploads_model->get_custom_uploader_images( $this->auth_user_id );

			$data = array(
				'javascripts' => array(
					'//ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js',
					'js/ajaxupload.js',
					'js/custom_uploader/uploader-controls.js',
				),
				'content' => $this->load->view('custom_uploader/uploader_controls', $view_data, TRUE)
			);

			$this->load->view( $this->template, $data );
		}
	}

	// --------------------------------------------------------------

	/**
	 * Update image order in the database
	 */
	public function update_image_order()
	{
		// Load resources
		$this->load->library('csrf');

		// Make sure anyone is logged in
		if( $this->require_min_level(1) )
		{
			if( $this->csrf->token_match )
			{
				if( $image_data = $this->input->post('image_data') )
				{
					$image_data = serialize( $image_data );

					if( $model_response = $this->uploads_model->save_image_data( $this->auth_user_id, $image_data ) )
					{
						$response['status']        = 'Image Order Updated';
						$response['token']         = $this->csrf->token;
						$response['ci_csrf_token'] = $this->security->get_csrf_hash();
					}
					else
					{
						$response['status'] = 'Error: Model Response = FALSE';
					}
				}
				else
				{
					$response['status'] = 'No Image Data';

					/**
					 * We need to update the tokens when there is no image data
					 * because when all images have been deleted, $image_data = FALSE
					 */
					$response['token']         = $this->csrf->token;
					$response['ci_csrf_token'] = $this->security->get_csrf_hash();
				}
			}
			else
			{
				$response['status'] = 'Error: No Token Match - ' . $this->csrf->posted_token . ' != ' . $this->csrf->current_token;
			}

			echo json_encode( $response );
		}
	}

	// --------------------------------------------------------------

	/**
	 * Delete image from filesystem and database
	 */
	public function delete_image()
	{
		// Make sure anyone is logged in
		if( $this->require_min_level(1) )
		{
			// Load resources
			$this->load->library('csrf');
			$this->load->helper('file');

			// Make sure the form token matches
			if( $this->csrf->token_match )
			{
				// Make sure we have the appropriate post variable
				if( $image_data = $this->input->post('src') )
				{
					// Make sure the user's directory appears in the posted 'src'
					$user_dir = $this->auth_user_id . '-' . md5( config_item('encryption_key') . $this->auth_user_id );

					if( strpos( $image_data, $user_dir ) !== FALSE )
					{
						// Remove the scheme and domain from the src to find the uploaded file
						$uploaded_file = FCPATH . str_replace( base_url(), '', $image_data );

						// Delete the file from the file system
						unlink( $uploaded_file );

						// Check the database for existing images data
						$query_data = $this->uploads_model->get_custom_uploader_images( $this->auth_user_id );

						// Unserialize the existing images data
						$arr = unserialize( $query_data->images_data );

						$temp = FALSE;

						// For each image in the existing images data
						foreach( $arr as $k => $v )
						{
							// If this isn't the image that we are deleting now
							if( $v != $image_data )
							{
								// Save it to a temp array
								$temp[] = $v;
							}
						}

						// Send the new images data to the model for record update
						if( $model_response = $this->uploads_model->save_image_data( $this->auth_user_id, serialize( $temp ) ) )
						{
							$response = array(
								'status'        => 'Image Deleted',
								'token'         => $this->csrf->token,
								'ci_csrf_token' => $this->security->get_csrf_hash()
							);
						}
						else
						{
							$response['status'] = 'Error: Model Response = FALSE';
						}
					}
					else
					{
						$response['status'] = 'Error: Image Path Not Verified';
					}
				}
				else
				{
					$response['status'] = 'Error: No Image Data';
				}
			}
			else
			{
				$response['status'] = 'Error: No Token Match';
			}

			echo json_encode( $response );
		}
	}

	// --------------------------------------------------------------

	/**
	 * Future home of the gallery.
	 */
	public function custom_gallery()
	{
		echo 'Nothing to see yet.';
	}

	// --------------------------------------------------------------
}

/* End of file custom_uploader.php */
/* Location: ./application/controllers/custom_uploader.php */