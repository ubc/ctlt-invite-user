<?php
/**
 * Plugin Name.
 *
 * @package   CTLT_Invite_User_Admin
 * @author    Enej Bajgoric 
 * @license   GPL-2.0+
 * @link      http://cms.ubc.ca
 * @copyright 2014 Centre for Teaching Learning and Technology - UBC
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * administrative side of the WordPress site.
 *
 * @package CTLT_Invite_User_Admin
 * @author  Enej Bajgoric 
 */
class CTLT_Invite_User_Admin {

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;
	
	protected static $my_invites = null;
	/**
	 * Slug of the plugin screen.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		/*
		 * Call $plugin_slug from public plugin class.
		 *
		 */
		$plugin = CTLT_Invite_User::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();
		
		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add the options page and menu item.
		add_action( 'admin_menu',array( $this, 'remove_add_user_menu' ) );
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . $this->plugin_slug . '.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );
		
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widgets' ) );
		
		add_action( 'admin_init', array( $this , 'redirect_add_user' ) );
		
		add_action( 'wpmu_options', array( $this, 'display_wpmu_options'), 1 );
		add_action( 'update_wpmu_options', array( $this, 'update_wpmu_options' ) );
	}
	
	/**
	 * add_dashboard_widgets function.
	 * 
	 * @access public
	 * @return void
	 */
	public function add_dashboard_widgets() {
		$c_user = wp_get_current_user();
		$invite_api = CTLT_Invitation_API::get_instance();
		
		// var_dump($invite_api);
		$this->my_invites = $invite_api::get_invites_by( "email", $c_user->user_email, 'ANY-BLOG'); 
		
		// var_dump($my_invites, $invite_api);
		if( !empty( $this->my_invites ) ){
			wp_add_dashboard_widget(
	             'invite',         // Widget slug.
	             'Pending Invitations',         // Title.
	             array( $this, 'dashboard_widget') // Display function.
	        );
        }
	}
	
	/**
	 * dashboard_widget function.
	 * 
	 * @access public
	 * @return void
	 */
	public function dashboard_widget(){
	
		echo "You have been invited to join:<br />";
		#echo "<table>"
		
		foreach( $this->my_invites as $invite ){
			echo "<div style='border-top:1px solid #EEE; margin:10px -12px 0; padding:0 12px;'><p>";
			switch_to_blog( $invite['blog_id'] );
				$site_url = site_url();
				$join_url 	 = CTLT_Invite_User::invite_url( $invite['hash'], 'invite_me'); 
				$decline_url = CTLT_Invite_User::invite_url( $invite['hash'], 'decline_invite'); 
			
				echo "<strong><a href='".$site_url."'>".get_bloginfo( 'name' )."</strong></a> as <em>".$invite['role']."</em></p>" ; 
				echo "<p><a href='".$join_url."' class='button button-primary'>Accept Invite</a> or <a href='".$decline_url."' >Decline</a>";
			
			restore_current_blog();
			
			echo "</p></div>";
		}
		#echo "</table>"
		
		
		
	}


	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}
	
	

	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @TODO:
	 *
	 * - Rename "CTLT_Invite_User" to the name your plugin
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_style( $this->plugin_slug .'-admin-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), CTLT_Invite_User::VERSION );
		}

	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * @TODO:
	 *
	 * - Rename "CTLT_Invite_User" to the name your plugin
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), CTLT_Invite_User::VERSION );
		}

	}
	
	/**
	 * remove_add_user_menu function.
	 * Removes the add new user menu
	 *
	 * @access public
	 * @return void
	 */
	public function remove_add_user_menu(){
		global $submenu, $menu;

        // 'Users Add New'. 
        if(!empty( $submenu['users.php'] ) ) {
        
          foreach( $submenu['users.php'] as $key => $sm) {
            if(__($sm[0]) == "Add New" || $sm[2] == "users-new.php") {
              unset( $submenu['users.php'][$key] );
              break;
            }
          }
          
        }
	}
	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {

		/*
		 * Add a settings page for this plugin to the Settings menu.
		 */
		$this->plugin_screen_hook_suffix = add_users_page(
			__( 'Invite User', $this->plugin_slug ),
			__( 'Invite User', $this->plugin_slug ),
			'add_users',
			$this->plugin_slug,
			array( $this, 'display_plugin_admin_page' )
		);

	}
	
	/**
	 * redirect_add_user function.
	 * redirect user that try to add new user via the old way 
	 * @access public
	 * @return void
	 */
	public function redirect_add_user(){
		global $pagenow;
		if( 'user-new.php' == $pagenow && !is_network_admin()  )
			wp_redirect( 'users.php?page='.$this->plugin_slug );
		
	}
	
	
	/**
	 * remove_user_from_blog function.
	 * This ensures that we delete the invite for that particular blog if we decide to remove the user once we have added them
	 * @access public
	 * @param mixed $user_id
	 * @param mixed $blog_id
	 * @return void
	 */
	public function remove_user_from_blog( $user_id, $blog_id ){
		
		
		// remove the invite 
		
	
	
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		
		include_once( CTLT_INVITE_USERS_PLUGIN_PATH. '/includes/class-ctlt-invitation-list-table.php' );
		include_once( CTLT_INVITE_USERS_PLUGIN_PATH.'/admin/views/admin.php' );
		
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {

		return array_merge(
			array(
				'invite-user' => '<a href="' . admin_url( 'users.php?page=' . $this->plugin_slug ) . '">' . __( 'Invite User', $this->plugin_slug ) . '</a>'
			),
			$links
		);

	}
	
	/**
	 * Process the invites retuns with html message to the admin
	 * 
	 * @param  string $raw_emails
	 * @param  string $raw_message
	 * @param  string $raw_role
	 * @return string $notices
	 */
	public function invite_emails( $raw_emails, $raw_message, $raw_role ) {
		
		$invite_api = CTLT_Invitation_API::get_instance();
		$hash = $invite_api->generate_hash();

		$role = $this->check_role( $raw_role );
		
		$invited_by_user = wp_get_current_user();
		$invited_by = $invited_by_user->display_name;
		
		$admin_message = get_site_option(  'ctlt_invite_email' );
		
		$message = $this->construct_email( $raw_message, $admin_message, $invited_by, $role, $hash );
		
		# construct email 
		$emails = $this->find_emails( $raw_emails );

		
		$notices = array();
		$send_emails = array();
		$html_notice = '';
		
		foreach( $emails  as $email){
			#checks if the user exists already or if the user was invited already as well. 
			$check_email = $this->check_email( $email, $role ); 

			if( "pass" == $check_email ) { 
				
				# add the invitation to the db
				$invite_api->insert_invite( $email, get_current_blog_id(), get_current_user_id(), $role, 0, $hash );
				$send_emails[] = $email;
				
			} else {
				$notices[] = $check_email;
			}
		}
		if( !empty( $send_emails ) ) {
			# send email
			$subject = $this->get_email_subject( $invited_by, $role, get_bloginfo( 'name' ) );
			
			add_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
			# send email 
			wp_mail( $send_emails, $subject, $message );
			remove_filter ( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );

			$html_notice = '<div class="updated below-h2"><p> Just invited <strong>'.implode(", ", $send_emails )."</strong> to join .</p></div>";
		}
		
		if( !empty( $notices ) ) {
			$html_notice .= '<div class="error below-h2"><p>'.implode( "<br />", $notices )."</p></div>";
		}
		
		return $html_notice;
	}

	/**
	 * Resend invites to the all the invites 
	 * 
	 */
	public function resend_invites( $invite ) {
		
		add_filter( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
		$notices = $error = $success = array();

		if( is_array( $invite ) ){
			foreach( $invite as $invite_id ){
				$notices[] = $this->_resend_invite( $invite_id );
			}
		} else {
			$notices[] = $this->_resend_invite( $invite );
		}

		remove_filter ( 'wp_mail_content_type', array( $this, 'set_html_content_type' ) );
		
		if( !empty( $notices ) ){
			foreach( $notices as $notice ){

				if( 'error' == isset( $notice['error'] ) )
					$error[] = $notice['error'];
				else
					$success[] = $notice['email']; 
			}
			$html_notice = '';
			if( !empty( $success ) )
				$html_notice = '<div class="updated below-h2"><p> Just resent email to  <strong>'.implode(", ", $success )."</strong> to join.</p></div>";
			
			if( !empty( $error ) ) 
				$html_notice .= '<div class="error below-h2"><p>'.implode( "<br />", $error )."</p></div>";
			
			echo $html_notice;
		}
	}
	/**
	 * Resent individual invite 
	 * @param  int $invite_id [description]
	 * @return array of notices to be displayed to the user
	 */
	private function _resend_invite( $invite_id ) {

		$invite_api = CTLT_Invitation_API::get_instance();
		$invite = $invite_api->get_invite_by( 'id' , (int) $invite_id, "ANY" );
		
		$send_email = $invite['email'];
		
		
		$role = $invite['role'];
		$hash = $invite['hash'];
		
		// don't send invite if the user rejected the invite or accpeted it
		if( "1" == $invite['status'] ) 
			return array( "error" => "Email to <strong>".$send_email. "</strong> wasn't send! They have accepted the invitation already.");
		
		if( "3" == $invite['status'] )
			return array( "error" => "Email to <strong>".$send_email. "</strong> wasn't send! They have rejected the invitation already." );
	
		$skip_db_check  = true;
		$check_email = $this->check_email( $send_email, $role,  $skip_db_check ); 

		if( "pass" != $check_email )
			return array( "error" => $check_email ); // don't send an email if the user doesn't one one or is already there.  
		
		
		$invited_by_user = get_user_by( "id", $invite['inviter_id'] );
		$invited_by = $invited_by_user->display_name;
		$admin_message = get_site_option(  'ctlt_invite_email' );
		$message = $this->construct_email( '', $admin_message,  $invited_by, $role, $hash );

		# send email
		$subject = $this->get_email_subject( $invited_by, $role, get_bloginfo( 'name' ) );
		
		# send email 
		wp_mail( $send_email, $subject, $message );

		return array( "email" => $send_email );

	}

	/**
	 * Sanitizes the role 
	 * @param  string $role
	 * @return string sanitizes string 
	 */
	public function check_role( $role ) {
		
		return ( in_array( $role, array( 'subscriber', 'contributor', 'author', 'editor', 'administrator' ) ) ? $role : 'subscriber');
	}

	/**
	 * Invite_email - send the 
	 * @param  string $email
	 * @return string - $message to the admin about the particular email
	 */
	public function check_email( $email, $role, $skip_db_check = false ) {

		// check if the email is valid
		if( !is_email( $email ) ){
			# notify the admin that the email is not valid 
			return "<strong>".$email ."</strong> is not a valid email.";
		}
		
		// check if the user is already member of the blog
			# don't email them. notify the admin that they are already part of their blog.
		$user = get_user_by( 'email', $email );
		if( function_exists( 'is_multisite' ) && is_multisite() ){
			if( $user->ID && is_user_member_of_blog( $user->ID, get_current_blog_id() ) ) // 
				return  "<strong>".$email ."</strong> is already registed as <strong>".$user->display_name."</strong>.";
		} else {
			
			if( $user->ID )
				return  "<strong>".$email ."</strong> is already registed as <strong>".$user->display_name."</strong>.";
		}
		if( $skip_db_check )
			return "pass";
		// check if the user already has in invitation
		# don't email them. notify the amin that they already have an invitation ask if they want to send it again.
		$invite_api = CTLT_Invitation_API::get_instance();
		$invite = $invite_api->get_invite_by( 'email', $email, 'ANY' );
		
		if( $invite )
			return  "<strong>".$email ."</strong> already has an invitation.";

		return "pass";

		# notify the admin that the invitation was sent. 

	}
	
	/**
	 * email text to be send out
	 * @param string $raw_message
	 * @param string $invited_by
	 * @param string $role role for which they were invited for
	 * @return string $message - the email that will be send out the the users
	 */
	public function construct_email( $raw_message = '', $raw_admin_message,  $invited_by , $role, $hash ) {

		$allows_html = array(
		    'a' => array(
		        'href' => array(),
		        'title' => array()
		    ),
		    'br' => array(),
		    'em' => array(),
		    'strong' => array(),
		    'p'	=> array()
		);
		# load the template
		$user_message = wp_kses( nl2br(  wp_unslash( $raw_message ) ) ,  $allows_html );
		$admin_message = wp_kses( nl2br( $raw_admin_message ),  $allows_html );
		
		$template  = file_get_contents( realpath( dirname(__FILE__) ).'/views/email.html', true );
		$blog_name = get_bloginfo( 'name' );
		
		$replace = array(
			'%email_subject%' 	=> $this->get_email_subject( $invited_by, $role, $blog_name ),
			'%invited_by%' 		=> $invited_by,
			'%role_job%' 		=> $this->role_job_text( $role ),
			'%blog_url%' 		=> site_url() ,
			'%blog_name%' 		=> $blog_name,
			'%role_permission%' => $this->role_permission_text( $role ),
			'%user_message%' 	=> $user_message,
			'%invitation_url%' 	=> CTLT_Invite_User::invite_url( $hash, 'invite_me'),
			'%admin_message%'	=> $admin_message
			);
		# replace strings. 
		$message = str_replace(array_keys($replace), array_values($replace), $template);
		# add the users message 

		return $message;

	}
	/**
	 * Generat the email subject
	 * @param  [type] $invited_by
	 * @param  [type] $role
	 * @param  [type] $blog_name
	 * @return string subject
	 */
	public function get_email_subject( $invited_by, $role, $blog_name ){
		$a_role = ( in_array( substr($role, 0, 1), array('a', 'e', 'i', 'o', 'u') ) ? "an ".$role : "a ".$role ); 
		return $invited_by . ' invited you to become '.$a_role.' on '.$blog_name;

	}
	/**
	 * role_permission_text 
	 * @param  string $role
	 * @return string text describing what the the role is able to do
	 */
	public function role_permission_text($role) { 

		switch( $role ){

			case 'subscriber':
				return 'As a subscriber you are able to view the content on the site.';
			break;

			case 'contributor':
				return 'As a contributor you will be able to create and edit your posts.';
			break;

			case 'author':
				return 'As an author you will be able to publish and edit your own posts as well as upload media.';
			break;

			case 'editor':
				return 'As an editor you will be able to publish and edit everyone posts and pages as well as upload media.';
			break;

			case 'administrator':
				return 'As an administrator you will be able to maintain the whole site, including content and settings.';
			break;

		}

	}
	/**
	 * [role_job_text description]
	 * @param  string $role
	 * @return string returns a single word action describing what the role can do
	 */
	public function role_job_text($role) { 

		switch( $role ){

			case 'subscriber':
				return 'view';
			break;

			case 'contributor':
				return 'contribute';
			break;

			case 'author':
				return 'author';
			break;

			case 'editor':
				return 'edit';
			break;

			case 'administrator':
				return 'administrate';
			break;

		}

	}
	
	/**
	 * parses out email addresses from the string
	 * @param  string $raw_emails
	 * @return array
	 */
	public function find_emails( $raw_emails ){
		
		$emails = array();
		$raw_emails = str_replace(";", ",", $raw_emails);
		
		#new lines get converted into 
		$raw_emails = trim( preg_replace('/\s+/', ',', $raw_emails ) );
		
		foreach(explode(',', $raw_emails ) AS $email) {
			$found_email = null;
			$email = trim($email);
			preg_match('/<(.*?)>/', $email, $find_email);
			$found_email = $find_email[1];
  			
			if( isset( $found_email ) && filter_var(  $found_email, FILTER_VALIDATE_EMAIL) && !in_array( $found_email, $emails)){
				$emails[] = $found_email;
			} else if( filter_var(  $email, FILTER_VALIDATE_EMAIL) && !in_array( $email, $emails) ) {
				$emails[] = $email;
			}

		}
		return $emails;
		
   }
   
   /**
    * display_wpmu_options function.
    * Displays options in the network settings page
    * @access public
    * @return void
    */
   public function display_wpmu_options(){	
   
   ?>
   <h3><?php _e( 'Invite User Message', 'ctlt-invite-user'  ); ?></h3>
   	<table class="form-table">
		<tr valign="top">
			<th scope="row"><label for="ctlt_invite_email"><?php _e( 'Invite Email', 'ctlt-invite-user' ) ?></label></th>
			<td>
				<textarea name="ctlt_invite_email" id="ctlt_invite_email" rows="5" cols="45" class="large-text"><?php echo esc_textarea( get_site_option( 'ctlt_invite_email' ) ) ?></textarea>
				<p class="description">
					<?php _e( 'Explain to your user what the process of being added to the site. This message will be added at the end of the email.', 'ctlt-invite-user' ); ?>
				</p>	
			</td>
		</tr>
	</table>
   	<?php
   }
   
   /**
    * update_wpmu_options function.
    * 
    * @access public
    * @return void
    */
   	public function update_wpmu_options() {
   		
   		// process that is involved in getting the account 
   		update_site_option( 'ctlt_invite_email', wp_unslash( $_POST['ctlt_invite_email'] ) );
   		
   	}
	
	/**
	 * set_html_content_type function.
	 * 
	 * @access public
	 * @return void
	 */
	public function set_html_content_type() {
		return 'text/html';
	}

}
