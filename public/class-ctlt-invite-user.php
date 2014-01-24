<?php
/**
 * CTLT_Invite_User
 *
 * @package   CTLT_Invite_User
 * @author    Enej Bajgoric 
 * @license   GPL-2.0+
 * @link      http://cms.ubc.ca
 * @copyright 2014 Centre for Teaching Learning and Technology - UBC
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * public-facing side of the WordPress site.
 *
 * @package CTLT_Invite_User
 * @author  Enej Bajgoric 
 */
class CTLT_Invite_User {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.0.0';
	
	
	
	/**
	 *
	 * Unique identifier for your plugin.
	 *
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'ctlt-invite-user';

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;
	
	
	/**
	 * rewrite_on
	 * 
	 * (default value: false)
	 * Tells us if we have a rewrite rules enabled on the site or not
	 * @since    1.0.0
	 * @access protected
	 */
	protected static $rewrite_on = false;

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		// Load plugin text domain
		
		
		if( in_array( get_site_option( 'registration' ), array( 'all', 'user' ) ) ) {
			add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
			add_action( 'init', array( $this, 'add_rewrite' ), 1  ); #
			add_action( 'template_redirect', array( $this, 'check_invite' ), 1  ); #
			
			
		}
				
		
	}
	
	public function add_rewrite(){
		$permalink_structure = get_option( 'permalink_structure' );
		$this->rewrite_on = ( empty( $permalink_structure ) ? false : true );
		
		add_rewrite_rule( '^invite/([^/]*)/([^/]*)/?','index.php?invite_hash=$matches[1]&invite_action=$matches[2]','top' );
    	add_rewrite_tag( '%invite_hash%','([^&]+)' );
    	add_rewrite_tag( '%invite_action%','([^&]+)' );
	
	}
	
	
	
	/**
	 * Delegates what happends when the user click on the invite url. 
	 * Invites url can be found in emails, entered manually, or clicked on dashboard.
	 * @return [type]
	 */
	public function check_invite(){
		
		global $wp_rewrite;
		
		$get_invite_hash 	= ( isset( $_GET['invite_hash'] ) ? $_GET['invite_hash'] : false );
		$get_invite_action 	= ( isset( $_GET['invite_action'] ) ? $_GET['iinvite_action'] : false );
		
		$hash_id = (  $this->rewrite_on ? get_query_var( 'invite_hash' ) : $get_invite_hash  );
		
		$action = (  $this->rewrite_on ? get_query_var( 'invite_action' ) : $get_invite_action );
		
		# doesn't pass the first check
		if( ! ( in_array( $action, array( 'decline_invite', 'invite_me' ) ) && $hash_id ) ){
			$this->depricated_start();
			return;	
		}
		
		# add the don't cache thing here so that supercache doesn't even cache this page.
		define( 'DONOTCACHEPAGE', 1 ); 
		
		$privacy = get_option( 'blog_public' );
		
		$site_url = site_url();
		$site_dash = admin_url();
		$site_name = get_bloginfo( 'name' );
		
		# hash is valid 
		$invite_api = CTLT_Invitation_API::get_instance();
		
		$hash = $invite_api->get_invite_by( 'hash', $hash_id, "ANY" );
		
		$end_menu = "<p>Continue to the <a href='".$site_url."'>Site</a> or <a href='".$site_dash."'>Dashboard</a>.</p>";
		
		
		if( $privacy < 0 )
			$end_private = "";
		else
			$end_private = $end_menu;
		
		
		if( empty( $hash ) ) {
			# sleep doesn't work since someone could just hit refresh and if the answer is not there right away they would try again
			# sleep( 10 ); // sleep for seconds to prevent people from trying to guess an hash
			$this->wp_die( "<p class='error'>Invitation Not Valid!</p>".$end_private, 'Invitation Invalid' ); 
				# show error but let them continou to the site. instead
		}
		
		if( $hash['status'] == '2') {
			$this->wp_die( "<p class='error'>Your invitation has expired!</p>".$end_private , 'Invitation Expired'); 
			# show error but let them continou to the site. instead
		}
		
		if( $hash['status'] == '3') {
			$this->wp_die( "<p class='error'>Your invitation has been canceled!</p>".$end_private, 'Invitation Canceled'  ); 
			# show error but let them continou to the site. instead
		}
		
		$accepted = '';
		if( $hash['status'] == '1') {
			$accepted = "<p class='nag'>Your invitation was already accepted!</p>"; 
			# show error but let them continou to the site. instead
		}
		
		// is user logged in
		if( is_user_logged_in() ) {
			
			$c_user =  wp_get_current_user();
			$blog_id = get_current_blog_id();
			
			if( is_user_member_of_blog( $c_user->ID, $blog_id ) ) {
				
				// do nothing they are already here. 
				# but maybe inform them that they 
				$this->wp_die( $accepted. "<p><em>". ucfirst( $c_user->display_name ) ."</em> is already a member of <strong><a href='".$site_url."'>".$site_name."</a>. </strong> </p>".$end_menu, 'Already a member of '.$site_name    ); 
				
				
			} else {
				
				
				switch( $action ){
				
					case 'invite_me':
						
						# add the user to blog with role
						if( !empty( $accepted ) ) # don't readd the user if they click on the invite link again
							add_user_to_blog( $blog_id, $c_user->ID, $hash['role'] );
						
						#update the invite db
						$invite_api->update_status( $hash['hash'], 1 ); #accepted
						
						#message 
						$this->wp_die( $accepted. "<p><big>Welcome ".$c_user->display_name."</big>
				<br />You have been just joined to <strong><a href='".$site_url."'>".$site_name."</a></strong> as ".$hash['role'].".</p>".$end_menu  , 'Joined '.$site_name); 
						
						
					break;
					case 'decline_invite':
					
						# update the invite db
						$invite_api->update_status( $hash['hash'], 3 ); #rejected
						$this->wp_die("<p><big>Hi ".$c_user->display_name."</big>
					<br />You declined your invitation to <strong><a href='".$site_url."'>".$site_name."</a></strong></p>", 'Declined Invitation'  ); 
						
					break;
				} // end of switch
			} // end of member not part of blog

		} else {
			
			# user needs to login first before we can add them
			wp_redirect( wp_login_url( self::invite_url( $hash_id, 'invite_me' ) ) );
		}
			
	}
	
	/**
	 * depricated_start function.
	 * This finction exits because of the old plugin that used to be here
	 * @access public
	 * @return void
	 */
	public function depricated_start() {
		
		// basically 
		if( isset( $_GET['invitation'] ) ){
			
			$invite_api = CTLT_Invitation_API::get_instance();
			
			$hash = $invite_api->get_invite_by( 'hash', $_GET['invitation'], "ANY-BLOG" );
			
			if( !empty( $hash ) ){
				$url = get_site_url( $hash['blog_id'] );
				
				// redirect to the propre place.
				wp_redirect( self::invite_url( $hash['hash'], 'invite_me', $url ) );
				die();
			}
			
			return;
		}
		return;
	}
	
	/**
	 * invite_url function.
	 * 
	 * @access public
	 * @static
	 * @param mixed $hash
	 * @param string $action (default: 'invite_me')
	 * @param mixed $url (default: null)
	 * @return void
	 */
	public static function invite_url( $hash, $action = 'invite_me', $url = null ){
		
		if( empty( $hash) )
			return false;
		
		if( empty( $url ) )
			$url = site_url();
		
		return $url.'?invite_hash='.$hash."&invite_action=".$action;
	
	}
	
	/**
	 * recreated the error message.
	 * 
	 * @access public
	 * @param mixed $html
	 * @param mixed $title
	 * @return void
	 */
	public function wp_die( $html, $title ){
		?>
		<!DOCTYPE html>
<!-- Ticket #11289, IE bug fix: always pad the error page with enough characters such that it is greater than 512 bytes, even after gzip compression abcdefghijklmnopqrstuvwxyz1234567890aabbccddeeffgghhiijjkkllmmnnooppqqrrssttuuvvwwxxyyzz11223344556677889900abacbcbdcdcededfefegfgfhghgihihjijikjkjlklkmlmlnmnmononpopoqpqprqrqsrsrtstsubcbcdcdedefefgfabcadefbghicjkldmnoepqrfstugvwxhyz1i234j567k890laabmbccnddeoeffpgghqhiirjjksklltmmnunoovppqwqrrxsstytuuzvvw0wxx1yyz2z113223434455666777889890091abc2def3ghi4jkl5mno6pqr7stu8vwx9yz11aab2bcc3dd4ee5ff6gg7hh8ii9j0jk1kl2lmm3nnoo4p5pq6qrr7ss8tt9uuvv0wwx1x2yyzz13aba4cbcb5dcdc6dedfef8egf9gfh0ghg1ihi2hji3jik4jkj5lkl6kml7mln8mnm9ono
-->
<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title><?php echo 	esc_html($title); ?></title>
	<style type="text/css">
		html {
			background: #eee;
		}
		body {
			background: #fff;
			color: #333;
			font-family: "Open Sans", sans-serif;
			margin: 2em auto;
			padding: 1em 2em;
			max-width: 700px;
			-webkit-box-shadow: 0 1px 3px rgba(0,0,0,0.13);
			box-shadow: 0 1px 3px rgba(0,0,0,0.13);
		}
		h1 {
			border-bottom: 1px solid #dadada;
			clear: both;
			color: #666;
			font: 24px "Open Sans", sans-serif;
			margin: 30px 0 0 0;
			padding: 0;
			padding-bottom: 7px;
		}
		#error-page {
			margin-top: 50px;
		}
		#error-page p {
			font-size: 14px;
			line-height: 1.5;
			margin: 25px 0 20px;
		}
		#error-page .error{
			border-left: 4px solid #DD3D36;
			margin-left:-2.3em;
			padding: 10px 2.4em;
			
		}
		#error-page .nag{
			border-left: 4px solid #FFBA00;
			margin-left:-2.3em;
			padding: 10px 2.4em;
		}
		#error-page code {
			font-family: Consolas, Monaco, monospace;
		}
		ul li {
			margin-bottom: 10px;
			font-size: 14px ;
		}
		a {
			color: #21759B;
			text-decoration: none;
		}
		a:hover {
			color: #D54E21;
		}
		.button {
			background: #f7f7f7;
			border: 1px solid #cccccc;
			color: #555;
			display: inline-block;
			text-decoration: none;
			font-size: 13px;
			line-height: 26px;
			height: 28px;
			margin: 0;
			padding: 0 10px 1px;
			cursor: pointer;
			-webkit-border-radius: 3px;
			-webkit-appearance: none;
			border-radius: 3px;
			white-space: nowrap;
			-webkit-box-sizing: border-box;
			-moz-box-sizing:    border-box;
			box-sizing:         border-box;

			-webkit-box-shadow: inset 0 1px 0 #fff, 0 1px 0 rgba(0,0,0,.08);
			box-shadow: inset 0 1px 0 #fff, 0 1px 0 rgba(0,0,0,.08);
		 	vertical-align: top;
		}

		.button.button-large {
			height: 29px;
			line-height: 28px;
			padding: 0 12px;
		}

		.button:hover,
		.button:focus {
			background: #fafafa;
			border-color: #999;
			color: #222;
		}

		.button:focus  {
			-webkit-box-shadow: 1px 1px 1px rgba(0,0,0,.2);
			box-shadow: 1px 1px 1px rgba(0,0,0,.2);
		}

		.button:active {
			background: #eee;
			border-color: #999;
			color: #333;
			-webkit-box-shadow: inset 0 2px 5px -3px rgba( 0, 0, 0, 0.5 );
		 	box-shadow: inset 0 2px 5px -3px rgba( 0, 0, 0, 0.5 );
		}

			</style>
</head>
<body id="error-page">
	<?php echo $html; ?>
</body>
</html>
	<?php die();
	
	
	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
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
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();
				}

				restore_current_blog();

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

				}

				restore_current_blog();

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();

	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private static function single_activate() {
		// @TODO: Define activation functionality here
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 */
	private static function single_deactivate() {
		// @TODO: Define deactivation functionality here
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );

	}
}
