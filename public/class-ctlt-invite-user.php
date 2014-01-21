<?php
/**
 * Plugin Name.
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
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );
		if( in_array( get_site_option( 'registration' ), array( 'all', 'user' ) ) ) {
			add_action( 'init', array( $this, 'check_invite' ), 1  ); #
		}
		
		
		
	}

	/**
	 * Delegates what happends when the user click on the invite url. 
	 * Invites url can be found in emails, entered manually, or clicked on dashboard.
	 * @return [type]
	 */
	public function check_invite(){
		$redirect_url = false;
		
		# doesn't pass the first check
		if( ! ( isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'decline_invite', 'invite_me' ) ) && isset( $_GET['hash'] ) ) )
			return;
			
		$privacy = get_option( 'blog_public' );
		$site_url = site_url();
		$site_dash = admin_url();
		$site_name = get_bloginfo( 'name' );
		
		# hash is valid 
		$invite_api = CTLT_Invitation_API::get_instance();
		
		$hash = $invite_api->get_invite_by( 'hash', $_GET['hash'], "ANY" );
		
		$end_menu = "<p>Continue to the <a href='".$site_url."'>Site</a> or <a href='".$site_dash."'>Dashboard</a>.</p>";
		
		if( $privacy < 0 )
			$end_private = "";
		else
			$end_private = $end_menu;
		
		
		if( empty( $hash ) ) {
			# sleep doesn't work since someone could just hit refresh and if the answer is not there right away they would try again
			# sleep( 10 ); // sleep for seconds to prevent people from trying to guess an hash
			wp_die( "<p class='error'>Invitation Not Valid!</p>".$end_private ); 
				# show error but let them continou to the site. instead
		}
		
		if( $hash['status'] == '2') {
			wp_die( "<p class='error'>Your invitation has expired!</p>".$end_private ); 
			# show error but let them continou to the site. instead
		}
		
		if( $hash['status'] == '3') {
			wp_die( "<p class='error'>Your invitation has been canceled!</p>".$end_private  ); 
			# show error but let them continou to the site. instead
		}
		
		$accepted = '';
		if( $hash['status'] == '1') {
			$accepted = "<p>Your invitation was already accepted!</p>"; 
			# show error but let them continou to the site. instead
		}
		
		// is user logged in
		if( is_user_logged_in() ) {
			
			$c_user =  wp_get_current_user();
			$blog_id = get_current_blog_id();
			
			if( is_user_member_of_blog( $c_user->ID, $blog_id ) ){
				// do nothing they are already here. 
				# but maybe inform them that they 
				wp_die( $accepted. "<p><em>". ucfirst( $c_user->display_name ) ."</em> is already a member of <strong><a href='".$site_url."'>".$site_name."</a>. </strong> </p>".$end_menu    ); 
			} else {
				
				
				switch( $_GET['action'] ){
					case 'invite_me':
						# add the user to blog with role
						add_user_to_blog( $blog_id, $c_user->ID, $hash['role'] );
						
						#update the invite db
						
						#message 
						wp_die( $accepted. "<p><big>Welcome ".$c_user->display_name."</big>
				<br />You have been just joined to <strong><a href='".$site_url."'>".$site_name."</a></strong> as ".$hash['role'].".</p>".$end_menu   ); 
						
						
					break;
					case 'decline_invite':
					
						# update the invite db
						$invite_api->update_status( $hash['hash'], 3 ); #rejected
						wp_die("<p><big>Hi ".$c_user->display_name."</big>
				<br />You declined your invitation <strong><a href='".$site_url."'>".$site_name."</a></strong></p>".$end_menu   ); 
						
						
					break;
				
				}
		
				# tell the user 
				# add the suer o
				add_user_to_blog( $blog_id, $user_id, $hash['role'] );
				wp_redirect( site_url() );
				
				wp_die("<p><big>Welcome ".$c_user->display_name."</big>
				<br />You have been just added to <strong><a href='".$site_url."'>".$site_name."</a></strong></p>".$end_menu   ); 
			}

		} else {
			# user needs to login first before we can add them
			wp_redirect( wp_login_url( site_url( '?action=invite_me&hash='.$_GET['hash'] ) ) );
		}
			
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

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'assets/css/public.css', __FILE__ ), array(), self::VERSION );
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'assets/js/public.js', __FILE__ ), array( 'jquery' ), self::VERSION );
	}
}
