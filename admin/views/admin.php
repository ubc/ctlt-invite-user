<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * @package   CTLT_Invite_User
 * @author    Enej Bajgoric 
 * @license   GPL-2.0+
 * @link      http://cms.ubc.ca
 * @copyright 2014 Centre for Teaching Learning and Technology - UBC
 */
?>
<div class="wrap">
	<?php 
	if ( isset( $_POST['nonce'] ) && wp_verify_nonce( $_POST['nonce'],'invite_user') ) {
	   
	   $invite_admin = new CTLT_Invite_User_Admin();

	   $notice = $invite_admin->invite_emails( $_POST['emails'], $_POST['message'], $_POST['role'] );
	   // explain to the user where the email was sent
	   echo $notice;

	}
	?>
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
	<form action="" method="post">
	<table class="form-table">
		<tbody>
			<tr valign="top">
				<th scope="row"><?php _e( 'Email Addresses', 'ctlt-invite-user-locale' ); ?></th>
				<td>
					<fieldset><legend class="screen-reader-text"><span>Email Addresses', 'ctlt-invite-user-locale' ); ?></span></legend>
					<textarea id="" name="emails" cols="80" rows="3" class="large-text" placeholder="name@example.com, buddy@example.com"></textarea>
					<span class="description"><?php _e( 'Enter up to 20 email addresses separated by commas.', 'ctlt-invite-user-locale' ); ?> </span>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Role</th>
				<td>
					<fieldset><legend class="screen-reader-text"><span>Role</span></legend>
					<select name="role">
						<option value="subscriber" >Subscriber - Viewer</option>
						<option value="contributor" >Contributor</option>
						<option value="author" >Author</option>
						<option value="editor" >Editor</option>
						<option value="administrator">Administrator</option>
					</select>
					</fieldset>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row">Message</th>
				<td >
					<fieldset><legend class="screen-reader-text"><span>Message</span></legend>
					<textarea id="" name="message" cols="80" rows="10" class="large-text" placeholder="Join my site!"></textarea>
					
					<span class="description"><?php _e( '(Optional) You can enter a custom message that will be included at the end of the invitation email send to the user', 'ctlt-invite-user-locale' ); ?></span>
					</fieldset>
				</td>
			</tr>
			
			<?php 
			$super_admin_message = get_site_option( 'ctlt_invite_email' );
			if( !empty( $super_admin_message ) && is_super_admin() ) { ?>
			<tr valign="top">
				<th scope="row">Appended Message <a href="<?php echo network_admin_url("settings.php#ctlt_invite_email"); ?>" title="Edit appended invite message">Edit</a></th>
				<td >
					<?php echo nl2br ( $super_admin_message ); ?>
					
				</td>
			</tr>
			
			<?php } ?>
			
			<tr valign="top">
				<th scope="row"></th>
				<td >
					<input class="button-primary" type="submit" name="Example" value="<?php _e( 'Send Invitation(s)', 'ctlt-invite-user-locale' ); ?>" />
				</td>
			</tr>
		</tbody>
	</table>
	
	<?php wp_nonce_field('invite_user','nonce'); ?>
	</form>
	<h2><?php _e( 'Sent Invitations', 'ctlt-invite-user-locale' ); ?></h2>
	<?php 

	//Create an instance of our package class...
    $invites_list_table = new CTLT_Invitation_List_Table();
    //Fetch, prepare, sort, and filter our data...
    $invites_list_table->prepare_items();

	?>
	<form id="invites-filter" method="get">
        <!-- For plugins, we also need to ensure that the form posts back to our current page -->
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
        <!-- Now we can render the completed list table -->
        <?php $invites_list_table->display() ?>
    </form>
    <?php 
    if( is_super_admin( ) ):
		echo "<br /><div class='alignright'>Invitation db version ";
		echo get_site_option( "ubc_invitation_db_version" ); 
		echo "</div>";
	endif;
	?>
</div> <!-- .wrap -->