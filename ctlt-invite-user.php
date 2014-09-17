<?php
/**
 * A way to invite multiple users to your site at a time by their email address
 *
 * @package   CTLT_Invite_User
 * @author    Enej Bajgoric 
 * @license   GPL-2.0+
 * @link      http://cms.ubc.ca
 * @copyright 2014 Centre for Teaching Learning and Technology - UBC
 *
 * @wordpress-plugin
 * Plugin Name:       CTLT Invite Users
 * Plugin URI:        http://github.com/ubc/ctlt-invite-user
 * Description:       A way to invite multiple users to your site at a time by their email address
 * Version:           1.0.1
 * Author:            Enej Bajgoric, Richard Tape
 * Author URI:        http://cms.ubc.ca
 * Text Domain:       ctlt-invite-user-locale
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/ubc/ctlt-invite-user
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*----------------------------------------------------------------------------*
 * Public-Facing Functionality
 *----------------------------------------------------------------------------*/

// define( 'CTLT_INVITE_USERS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CTLT_INVITE_USERS_PLUGIN_PATH', plugin_dir_path( __FILE__ ) . 'ctlt-invite-user/' );

require_once( CTLT_INVITE_USERS_PLUGIN_PATH . 'public/class-ctlt-invite-user.php' );
require_once( CTLT_INVITE_USERS_PLUGIN_PATH . 'includes/class-ctlt-invitation-api.php' );

/*
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 *

 */
register_activation_hook( __FILE__, array( 'CTLT_Invite_User', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CTLT_Invite_User', 'deactivate' ) );

/*

 */
add_action( 'plugins_loaded', array( 'CTLT_Invite_User', 'get_instance' ) );

/*----------------------------------------------------------------------------*
 * Dashboard and Administrative Functionality
 *----------------------------------------------------------------------------*/

/*
 * The code below is intended to to give the lightest footprint possible.
 */
if ( is_admin() && ( ! defined( 'DOING_AJAX' ) || ! DOING_AJAX ) ) {

	require_once( CTLT_INVITE_USERS_PLUGIN_PATH . 'admin/class-ctlt-invite-user-admin.php' );
	add_action( 'plugins_loaded', array( 'CTLT_Invite_User_Admin', 'get_instance' ) );

}
