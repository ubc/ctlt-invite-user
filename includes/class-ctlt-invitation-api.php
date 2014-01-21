<?php 



/**
 * CTLT_Invitation_API
 * Help us interact with the cwl_invitations table 
 * 
 */
class CTLT_Invitation_API{

	const DB_TABLE = "cwl_invitation"; //legacy table name
	
	const STATUS_WAITING 	= 0;
	const STATUS_CONFIRMED 	= 1;
	const STATUS_EXPIRED 	= 2;
	const STATUS_REJECTED 	= 3; # *new - the user doesn't want to join your site. 
	
	const DB_VERSION = "1.2"; # db version of DB_TABLE
	
	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;
	/**
	 * 
	 */
	function __construct() {

	}
 	
 	/**
 	 * get_instance function.
 	 * 
 	 * @access public
 	 * @static
 	 * @return $instance
 	 */
 	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;

			// do the install check 
			self::install();
		}

		return self::$instance;
	}

	/**
     * return back the invitation by eather hash or email or blog
     * @param  string $type
     * @param  [type] $hash
     * @param  [type] $status
     * @return [type] 
     */
	public static function get_invite_by( $type = 'hash', $value, $status=self::STATUS_WAITING, $blog_id = null ) {
		global $wpdb;
		
		if( $blog_id == null)
			$blog_id = get_current_blog_id();
		
		$type = (  in_array( $type, self::$instance->get_db_columns() ) ? $type : "hash" );$
		$value_type = ( is_int( $value ) ? '%d' : '%s' );
		
		if( $status  == 'ANY') {
			$sql =  $wpdb->prepare( "SELECT * FROM ".$wpdb->base_prefix .self::DB_TABLE." WHERE ".$type."=".$value_type." AND blog_id=%d", $value, $blog_id );
		} elseif( $status == 'ANY-BLOG') {
			$sql = $wpdb->prepare( "SELECT * FROM ".$wpdb->base_prefix .self::DB_TABLE." WHERE ".$type."=".$value_type." AND status=0", $value );
		} else {
			$sql = $wpdb->prepare( "SELECT * FROM ".$wpdb->base_prefix .self::DB_TABLE." WHERE ".$type."=".$value_type." AND status=%s AND blog_id=%d", $value, $status, $blog_id );
		}	
		
		return $wpdb->get_row( $sql , ARRAY_A  );
		
	}

    /**
     * return back the invitation by eather hash or email or blog_id
     * @param  string $type
     * @param  $string $hash
     * @param  int $status
     * @return array  
     */
	public function get_invites_by( $type = 'hash', $value, $status=self::STATUS_WAITING ) {
		global $wpdb;
		
		$type = (  in_array( $type, self::$instance->get_db_columns() ) ? $type : "hash" );
		$value_type = ( is_int( $value ) ? '%d' : '%s' );
		
		if( $status  == 'ANY' ) {
			$sql =  $wpdb->prepare( "SELECT * FROM ".$wpdb->base_prefix .self::DB_TABLE." WHERE ".$type."=".$value_type, $value );
		} else {
			$sql = $wpdb->prepare( "SELECT * FROM ".$wpdb->base_prefix .self::DB_TABLE." WHERE ".$type."=".$value_type." AND status=%s ", $value, $status );
		}
		
		
		return $wpdb->get_results( $sql, ARRAY_A );
	}
	/**
	 * returns the invitations 
	 * @param string $order_by 
	 * @param   string $order
	 * 
	 */
	public function get_invites( $order_by = 'timestamp' , $order = 'DESC' ){

		global $wpdb;
		$order_by = (  in_array($order_by, self::$instance->get_db_columns() ) ? $order_by : "timestamp" );
		$order = ( in_array( $order, array( 'ASC', 'DESC' ) ) ? $order : 'DESC' );

		$blog_id = get_current_blog_id();
		$sql = $wpdb->prepare( "SELECT * FROM ".$wpdb->base_prefix .self::DB_TABLE." WHERE blog_id=%d ORDER BY ".$order_by." ".$order , $blog_id  );
		
		return $wpdb->get_results( $sql , ARRAY_A );

	}

	/**
	 * get the status from an int to 
	 * @param  string $status status id 
	 * @return string status in a string format
	 */
	public static function get_status( $status ) {
	
		switch( (int)$status ){
			
			case self::STATUS_WAITING:
				return "waiting";
			
			case self::STATUS_CONFIRMED:
				return "added";
			
			case self::STATUS_EXPIRED:
				return "expired";
			
			case self::STATUS_REJECTED:
				return "rejected";
		}
	}
	/**
	 * return an array of 
	 * @return array database columns 
	 */
	public function get_db_columns(){

		return array( 
				'id',
				'timestamp',
				'expiration',
				'email',
				'inviter_id',
				'blog_id',
				'hash',
				'role',
				'status' );
	}

    /**
     * add the invite to the database
     * @param  string $emails
     * @param  int $blog_id
     * @param  int $inviter_id
     * @param  string $role
     * @return bool
     */
	public function insert_invite( $email, $blog_id=null, $inviter_id=null, $role='subscriber', $status = self::STATUS_WAITING, $hash ) {

		global $wpdb;
		# no go if you don't have an email
		if( empty( $email ) ) return false; 

		if( empty($blog_id) )
			$blog_id = get_current_blog_id();
		
		if( empty( $inviter_id ) )
			$inviter_id = get_current_user_id();
		
		$current_time = date('Y-m-d H:i:s', time() );
		# insert into db
		return $wpdb->query( $wpdb->prepare( 
			"
				INSERT INTO ".$wpdb->base_prefix .self::DB_TABLE."
				( email, blog_id, inviter_id, timestamp, hash, role, status )
				VALUES ( %s, %d, %d, %s, %s, %s, %s )
			", 
		        array( $email, $blog_id, $inviter_id, $current_time, $hash, $role, $status ) 
		) );
	}
	
	/**
	 * update_status function.
	 * 
	 * @access public
	 * @param mixed $hash
	 * @param int $status (default: self::STATUS_WAITING)
	 * @return void
	 */
	public function update_status( $hash,  $status = self::STATUS_WAITING ) {

		global $wpdb;
		
		$status = (int)$status;
		
		$sql = $wpdb->prepare("
			UPDATE ".$wpdb->base_prefix .self::DB_TABLE." 
			SET status = %d
			WHERE hash = %s 
			", $status, 
		return $wpdb->query( $sql );

	}
	/**
	 * Delete one invite
	 * @param  int $id id of the invite
	 * @return bool     was the query succesful
	 */
	public function delete_invite( $id ) {
		global $wpdb;
		$blog_id = get_current_blog_id();
		return $wpdb->delete( $wpdb->base_prefix .self::DB_TABLE , array( 'id' => $id, 'blog_id' => $blog_id ), array( '%d', '%d' )  );

	}
	/**
	 * Delete multiple ids
	 * @param  array $ids array of invite ids
	 * @return bool      was the query successful
	 */
	public function delete_invites( $ids ){

		global $wpdb;
		$blog_id = get_current_blog_id();
		$i = 0;
		$find_ids = array();
		while( isset( $ids[$i] ) ) {
			$find_ids[] = sprintf("( %d,  %d )", $ids[$i], $blog_id );
			$i++;
		}
		
		if( empty($find_ids) )
			return false;

		return $wpdb->query( "DELETE FROM ".$wpdb->base_prefix .self::DB_TABLE." WHERE (id,blog_id) IN (". implode( ",", $find_ids ). ")" );
	}
	/**
	 * Generates a random hash value
	 * @return string random hash
	 */
	public function generate_hash() {
		return wp_generate_password( 32, false, false );
	}

	/**
     * install check if the plugin is installed. if not, install invitation table. 
     * 
     * @access public
     * @return void
     */
    public function install()
    {
        global $wpdb;
        $installed_ver = get_site_option( "ubc_invitation_db_version" );

        if( version_compare(self::DB_VERSION, $installed_ver) ) {
        
            $sql = "CREATE TABLE " . $wpdb->base_prefix .self::DB_TABLE . " (
                   id mediumint(9) NOT NULL AUTO_INCREMENT,
                   timestamp datetime NULL,
                   expiration datetime NULL,
                   email VARCHAR(100) NOT NULL,
                   inviter_id bigint(20) DEFAULT '0' NOT NULL,
                   blog_id bigint(20) DEFAULT '0' NOT NULL,
                   hash VARCHAR(32) NOT NULL,
                   role VARCHAR(32) NOT NULL,
                   status TINYINT DEFAULT '0' NOT NULL,
                   UNIQUE KEY id (id),
                   KEY `email` (`email`),
                   KEY `hash_status` (`hash`,`status`),
                   KEY `blog_id` (`blog_id`)
                       )";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
            update_site_option( "ubc_invitation_db_version", self::DB_VERSION );
            //add_option("ubc_invitation_db_version", $ubc_invitation_db_version);
        }
    }

    public function uninstall(){

    	# Delete the table if you delete the plugin via the admin
    	# don't delete the table if you just deactive the plugin
    	# Delete the site_option "ubc_invitation_db_version" 

    }
}