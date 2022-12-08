<?php
/**
 * This recipe creates Member Numbers
 
 * Change the generate_member_number function if your member number needs to be in a certain format.
 * Member numbers are generated when users are registered or when the membership account page is accessed for the first time.
 * 
 * You can add this recipe to your site by creating a custom plugin
 * or using the Code Snippets plugin available for free in the WordPress repository.
 * Read this companion article for step-by-step directions on either method.
 * https://www.paidmembershipspro.com/create-a-plugin-for-pmpro-customizations/
 */


//Generate member_number when a user is registered.
function generate_member_number($user_id)
{
	$member_number = get_user_meta($user_id, "member_number", true);
		
	//if no member number, create one
	if(empty($member_number))
	{
		global $wpdb;
		
		//this code gets the last member number used and increments it, adding a prefix based on level at checkout
		while(empty($member_number))
		{
			global $pmpro_level;
			
			//if no level try to get from user_id
			if(empty($pmpro_level))
				$pmpro_level = pmpro_getMembershipLevelForUser($user_id);
						
			//if no level, we can't get their number, bail
			if(empty($pmpro_level))
				return false;
			
			$level_id = $pmpro_level->id;
						
			//get type - prefixs must be the same lenth - if you change from 4, you will need to modify line 46 (5)
			if($pmpro_level->id == 1)
				$member_type = 'NSBCE-NN';
			elseif($pmpro_level->id == 2)
				$member_type = 'NSBCE-SM';
            elseif($pmpro_level->id == 3)
                $member_type = 'NSBCE-FM';
            elseif($pmpro_level->id == 4)
                $member_type = 'NSBCE-AM';
            elseif($pmpro_level->id == 6)
                $member_type = 'NSBCE-CM';                               
			else
				$member_type = 'NSBCE-MM';

			//get number
			$r = $wpdb->get_var("SELECT meta_value FROM $wpdb->usermeta WHERE meta_key = 'member_number' ORDER BY umeta_id DESC LIMIT 1");
			if(empty($r))
				$number = 1;
			else
				$number = intval(substr($r, 6)) + 1;  //Change 5 if your character cound changes. Count+1

			//put them together
			$member_number = $member_type . '-' . str_pad($number, 6, '0', STR_PAD_LEFT);
		}

		//save to user meta
		update_user_meta($user_id, "member_number", $member_number);
		
		//return $member_number; moved to after the bracket
	}
	return $member_number;
}
add_action('user_register', 'generate_member_number');

//get or generate a member number
function get_member_number($user_id) {
	return generate_member_number($user_id);
}

//Show it on the membership account page.
function pmpromn_pmpro_account_bullets_bottom()
{
	if(is_user_logged_in())
	{
		global $current_user;

		$member_number = get_member_number($current_user->ID);

		//show it
		if(!empty($member_number))
		{
		?>
		<li><strong><?php _e("Member Number", "pmpro");?>:</strong> <?php echo $member_number?></li>
		<?php
		}
	}
}
add_action('pmpro_account_bullets_bottom', 'pmpromn_pmpro_account_bullets_bottom');
add_action('pmpro_invoice_bullets_bottom', 'pmpromn_pmpro_account_bullets_bottom');

//show member_number in confirmation emails
function pmpromn_pmpro_email_filter($email)
{
	global $wpdb;

	//only update admin confirmation emails
	if(strpos($email->template, "checkout") !== false)
	{
		if(!empty($email->data) && !empty($email->data['user_login']))
		{
			$user = get_user_by("login", $email->data['user_login']);
			if(!empty($user) && !empty($user->ID))
			{
				$member_number = get_member_number($user->ID);

				if(!empty($member_number))
					$email->body = str_replace("<p>Membership Level", "<p>Member Number:" . $member_number . "</p><p>Membership Level", $email->body);
			}
		}
	}

	return $email;
}
add_filter("pmpro_email_filter", "pmpromn_pmpro_email_filter", 30, 2);

//Add 'Member Number' Column to Members List Header
function mn_pmpro_memberslist_extra_cols_header($theusers)
{
	?>
	<th><?php _e('Member Number', 'pmpro');?></th>
	<?php
}
add_action('pmpro_memberslist_extra_cols_header', 'mn_pmpro_memberslist_extra_cols_header');
//Add 'Member Number' Column to Members List Rows
function mn_pmpro_memberslist_extra_cols_body($theuser)
{
?>
<td>
	<?php 
		$member_number = get_member_number($theuser->ID);
		echo $member_number
	?>
</td>
<?php
}
add_action('pmpro_memberslist_extra_cols_body', 'mn_pmpro_memberslist_extra_cols_body');

/*
	Add Member Number to memberslist CSV export
*/
//add the column
function mn_pmpro_members_list_csv_extra_columns($columns) {
	$columns["membernumber"] = "mn_pmpro_members_list_csv_memberkey";
	
	return $columns;
}
add_filter("pmpro_members_list_csv_extra_columns", "mn_pmpro_members_list_csv_extra_columns", 10);
//call back to get the member key
function mn_pmpro_members_list_csv_memberkey($user) {
	$membernumber = get_member_number($user->ID);
	return $membernumber;
}
/*
	Add Member Number to the edit user page
*/
function mn_user_profile($user) {
?>
<h3><?php _e("Member Number", "mn"); ?></h3>
<table class="form-table">
<tr>
	<th><label for="membernumber"><?php _e('Member Number', 'pmpromk');?></label></th>
	<td><?php echo get_member_number($user->ID);?></td>
</tr>
</table>
<?php
}
add_action('show_user_profile', 'mn_user_profile');
add_action('edit_user_profile', 'mn_user_profile');
