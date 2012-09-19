<?php if( ! defined('BASEPATH') ) exit('No direct script access allowed');
/**
 * Community Auth - Create User View
 *
 * Community Auth is an open source authentication application for CodeIgniter 2.1.2
 *
 * @package     Community Auth
 * @author      Robert B Gottier
 * @copyright   Copyright (c) 2011 - 2012, Robert B Gottier. (http://brianswebdesign.com/)
 * @license     BSD - http://http://www.opensource.org/licenses/BSD-3-Clause
 * @link        http://community-auth.com
 */
?>

<h1>User Creation Tool</h1>

<?php
	if( isset( $validation_passed, $user_created ) )
	{
		echo '
			<div class="feedback confirmation">
				<p class="feedback_header">
					The new user has been successfully created.
				</p>
			</div>
		';
	}
	else if( isset( $validation_errors ) )
	{
		echo '
			<div class="feedback error_message">
				<p class="feedback_header">
					User Creation Contained The Following Errors:
				</p>
				<ul>
					' . $validation_errors . '
				</ul>
				<p>
					USER NOT CREATED
				</p>
			</div>
		';
	}
?>

<?php echo form_open( '', array( 'class' => 'std-form', 'style' => 'margin-top:24px;' ) ); ?>
	<div class="form-column-left">
		<fieldset>
			<legend>User Information:</legend>
			<div class="form-row">

				<?php
					// USERNAME LABEL AND INPUT ***********************************
					echo form_label('Username','user_name',array('class'=>'form_label'));

					echo input_requirement('*');

					$input_data = array(
						'name'		=> 'user_name',
						'id'		=> 'user_name',
						'class'		=> 'form_input alpha_numeric',
						'value'		=> set_value('user_name'),
						'maxlength'	=> MAX_CHARS_4_USERNAME,
					);

					echo form_input( $input_data );

				?>

			</div>
			<div class="form-row">

				<?php
					// PASSWORD LABEL AND INPUT ***********************************
					echo form_label('Password','user_pass',array('class'=>'form_label'));

					echo input_requirement('*');

					$input_data = array(
						'name'		=> 'user_pass',
						'id'		=> 'user_pass',
						'class'		=> 'form_input password',
						'value'		=> set_value('user_pass'),
					);

					echo form_password( $input_data );
				?>

			</div>
			<div class="form-row">

				<?php
					// SHOW PASSWORD CHECKBOX
					echo form_label('Show Password','show-password',array('class'=>'form_label'));

					echo input_requirement();

					$checkbox_data = array(
						'id' => 'show-password'
					);

					echo form_checkbox( $checkbox_data );
				?>

			</div>
			<div class="form-row">

				<?php
					// EMAIL ADDRESS LABEL AND INPUT ******************************
					echo form_label('Email Address','user_email',array('class'=>'form_label'));

					echo input_requirement('*');

					$input_data = array(
						'name'		=> 'user_email',
						'id'		=> 'user_email',
						'class'		=> 'form_input max_chars',
						'value'		=> set_value('user_email'),
						'maxlength'	=> '255',
					);

					echo form_input( $input_data );
				?>

			</div>
			<div class="form-row">

				<?php
					// USER LEVEL SELECTION ***************************************
					echo form_label('Select User Level','user_level',array('class'=>'form_label'));

					echo input_requirement();

					foreach( config_item('account_types') as $num => $text){
						//There can only be one Sudo
						//Users can only create accounts of lesser account level than there own
						$admin_level_number = array_search( 'Admin', $this->authentication->account_types );

						if( $num < $auth_level && $num != $admin_level_number )
						{
							$options[$num] = $text;
						}
					}

					echo form_dropdown('user_level', $options, set_value('user_level', '1'), 'id="user_level" class="form_select"');
				?>

			</div>
			<div class="form-row">

				<?php
					// FIRST NAME LABEL AND INPUT ***********************************
					echo form_label('First Name','first_name',array('class'=>'form_label'));

					echo input_requirement('*');

					$input_data = array(
						'name'		=> 'first_name',
						'id'		=> 'first_name',
						'class'		=> 'form_input first_name',
						'value'		=> set_value('first_name'),
						'maxlength'	=> '20',
					);

					echo form_input($input_data);

				?>

			</div>
			<div class="form-row">

				<?php
					// LAST NAME LABEL AND INPUT ***********************************
					echo form_label('Last Name','last_name',array('class'=>'form_label'));

					echo input_requirement('*');

					$input_data = array(
						'name'		=> 'last_name',
						'id'		=> 'last_name',
						'class'		=> 'form_input last_name',
						'value'		=> set_value('last_name'),
						'maxlength'	=> '20',
					);

					echo form_input($input_data);

				?>

			</div>
			<div class="form-row">

				<?php
					// LICENSE NUMBER LABEL AND INPUT ***********************************
					echo form_label('License Number','license_number',array('class'=>'form_label'));

					echo input_requirement('*');

					$input_data = array(
						'name'		=> 'license_number',
						'id'		=> 'license_number',
						'class'		=> 'form_input alpha_numeric',
						'value'		=> set_value('license_number'),
						'maxlength'	=> '8',
					);

					echo form_input($input_data);

				?>

			</div>
		</fieldset>
		<div class="form-row">
			<div id="submit_box">

				<?php
					// SUBMIT BUTTON **************************************************************
					$input_data = array(
						'name'		=> 'form_submit',
						'id'		=> 'submit_button',
						'value'		=> 'Create User'
					);
					echo form_submit($input_data);
				?>

			</div>
		</div>
	</div>
</form>

<?php
/* End of file create_user.php */
/* Location: /application/views/create_user.php */