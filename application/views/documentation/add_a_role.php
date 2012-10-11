<?php if( ! defined('BASEPATH') ) exit('No direct script access allowed');
/**
 * Community Auth - Documentation of Adding a Role
 *
 * Community Auth is an open source authentication application for CodeIgniter 2.1.3
 *
 * @package     Community Auth
 * @author      Robert B Gottier
 * @copyright   Copyright (c) 2011 - 2012, Robert B Gottier. (http://brianswebdesign.com/)
 * @license     BSD - http://http://www.opensource.org/licenses/BSD-3-Clause
 * @link        http://community-auth.com
 */
?>

<h1>Adding a New Role</h1>
<p>
	Adding to or subtracting a role from Community Auth requires creating and editing some files, and the goal of this tutorial will be to show you how to do it quickly. I am going to walk you through the process of adding a "vendor" role to a untouched installation of Community Auth. Installation is not covered in this tutorial, and I'm going to assume you have it Community Auth up and running, and that everything is working as it should.
</p>
<h2>Add Vendor Profile Table to Database</h2>
<p>
	A vendor is a wholesaler or distributor of merchandise. As we create our vendor profile, we will give the vendor a couple of fields that are unique to their profile, "business_name" and "business_type". In the example application all profile tables include a profile image, and Community Auth requires that "first_name" and "last_name" fields exist. I'm going to keep it simple during this tutorial, so I'm not going to add any other fields.
</p>
<p>
	Take a look at the SQL below. You can run it on your database or create it manually. I'm using MySQL and no support is given for other database types.
</p>
<div class="doc_code">
	<pre class="brush: php; toolbar: false;">
CREATE TABLE IF NOT EXISTS `vendor_profiles` (
  `user_id` int(10) unsigned NOT NULL,
  `first_name` varchar(20) NOT NULL,
  `last_name` varchar(20) NOT NULL,
  `business_name` varchar(30) NOT NULL,
  `business_type` varchar(30) NOT NULL,
  `profile_image` text,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;</pre>
</div>
<h2>Add Vendor Profile Table to DB Tables Config</h2>
<p>
	Open up /application/config/db_tables.php and add the following line:
</p>
<div class="doc_code">
	<pre class="brush: php; toolbar: false;">
$config['vendor_profiles_table'] = 'vendor_profiles';</pre>
</div>
<h2>Add New Level &amp; Role to Authentication Config</h2>
<p>
	Open /application/config/authentication.php. The second configuration setting is Levels and Roles. Add a new element to the array for our vendor. Use '2' as the key and 'vendor' as the value.
</p>
<div class="doc_code">
	<pre class="brush: php; toolbar: false;">
$config['levels_and_roles'] = array(
	'1' => 'customer',
	'2' => 'vendor',
	'6' => 'manager',
	'9' => 'admin'
);</pre>
</div>
<h2>User Creation</h2>
<p>
	We need to be able to create new vendors. In the example application, user creation is limited to employees, and both manager and admin have a level higher than the vendor's level, so both will be able to create the new vendor once we do some work. 
</p>
<h3>Creation Form</h3>
<p>
	The form that is filled out during user creation is a nested view, and all roles (except admin) need one. The views are located at /application/views/administration/create_user/. The filename of the views are "create_" + the user role + ".php". I took the customer's file and saved it as create_vendor.php. The vendor profile doesn't have the address, city, state, and zip fields that the customer profile has, so I'm going to delete those fields and replace them with the business name and business type fields.
</p>
<h3>Validation Rules for Creation</h3>
<p>
	Character limiters are provided for client side validation, and are set up as classes for the form elements. For the vendor's business name and business type fields I used "max_chars". Server side validation is where we truly validate the input from the form, so open up /application/config/form_validation/administration/create_user/create_customer.php, and save it as create_vendor.php. The filename of the user creation validation files are "create_" + the user role + ".php", which is the same name as the view.
</p>
<p>
	Make sure the configuration setting in create_vendor.php is named "vendor_creation_rules". Delete the rules for address, city, state, and zip. Add rules for the business name and business type.
</p>
<p>
	You should now be able to create a vendor.
</p>
<h2>User Update</h2>
<p>
	We need to be able to update the vendor. Forunately, most of the work we did to be able to create a vendor is nearly identical to what we will need to do to be able to update the vendor.
</p>
<p>
	Open /application/views/administration/update_user/update_customer.php and save as update_vendor.php. Make the changes to form fields that are specific to the vendor. (Remove address, city, state, and zip, then add business name and business type)
</p>
<p>
	Setting up the form validation for the vendor update is a little tricky, because all profiles currently share the same config file, located at /application/config/form_validation/user/user_update.php In this file you will see where customer, manager and admin have their own validation rules. I'm going to copy the customer's update rules and modify them for the vendor. Remember the vendor doesn't have an address, city, state, or zip, but does have the business name and business type. Make sure when you copy the rules that the name of the configuration setting is "vendor_update_rules". Go down to the bottom of the file and put in the following code where you see the other lines just like them:
</p>
<div class="doc_code">
	<pre class="brush: php; toolbar: false;">
// This is actually all on one line, but is too wide to display on this page
$config['self_update_vendor'] = array_merge( 
	$config['self_update_rules'], 
	$config['vendor_update_rules'] 
);

// This is actually all on one line, but is too wide to display on this page
$config['update_user_vendor'] = array_merge( 
	$config['update_user_rules'], 
	$config['vendor_update_rules'] 
);</pre>
</div>
<p>
	The good news is that when we get to the next section, "self update", the validation is already done.
</p>
<h2>Self Update</h2>
<p>
	There's nothing about making the self update form that you haven't done already. Open up /application/views/user/self_update/self_update_customer.php and rename it to self_update_vendor.php. Find the address, city, state, and zip fields and delete them. Add in fields for the business name and business type.
</p>
<h3>Profile Image Upload Configuration</h3>
<p>
	The profile image upload on the "My Profile" page and the Custom Uploader use ajax to upload the image, and authentication is performed on those requests. Unless you remove the image upload functionality for the vendor, you'll need to open /application/config/uploads_manager.php and add vendor to the "authentication_profile_image" and "authentication_custom_uploader" configuration settings.
</p>
<div class="doc_code">
	<pre class="brush: php; toolbar: false;">
$config['authentication_profile_image'] = 'admin,manager,vendor,customer';

// ... space between configuration settings ...

$config['authentication_custom_uploader'] = 'admin,manager,vendor,customer';</pre>
</div>
<p>
	Login as the vendor you created, and you should be able to update yourself.
</p>
<h2>Summary</h2>
<p>
	We ended up modifying 4 files and creating 4 new ones:
</p>
<table class="simple_table">
	<thead>
		<tr>
			<th>Filename</th>
			<th>New / Existing</th>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td>/application/config/db_tables.php</td>
			<td>Existing</td>
		</tr>
		<tr class="odd">
			<td>/application/config/authentication.php</td>
			<td>Existing</td>
		</tr>
		<tr>
			<td>/application/views/administration/create_user/create_vendor.php</td>
			<td>New</td>
		</tr>
		<tr class="odd">
			<td>/application/config/form_validation/administration/create_user/create_vendor.php</td>
			<td>New</td>
		</tr>
		<tr>
			<td>/application/views/administration/update_user/update_vendor.php</td>
			<td>New</td>
		</tr>
		<tr class="odd">
			<td>/application/config/form_validation/user/user_update.php</td>
			<td>Existing</td>
		</tr>
		<tr>
			<td>/application/views/user/self_update/self_update_vendor.php</td>
			<td>New</td>
		</tr>
		<tr class="odd">
			<td>/application/config/uploads_manager.php</td>
			<td>Existing</td>
		</tr>
	</tbody>
</table>
<p>
	When you set up the roles in your application, you will probably have a lot of different fields and validation that needs to be done. Actual time to create each user will probably depend on how complex your profiles are. Having created a new user for Community Auth's example application, I hope you'll feel confident that you can do it again.
</p>

<?php

/* End of file add_a_role.php */
/* Location: /application/views/documentation/add_a_role.php */