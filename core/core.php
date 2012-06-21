<?php
/**
* Classifieds Core Class
**/

global $__classifieds_core;

if ( !class_exists('Classifieds_Core') ):
class Classifieds_Core {

	/** @var plugin version */
	var $plugin_version = CF_VERSION;
	/** @var plugin database version */
	var $plugin_db_version = CF_DB_VERSION;
	/** @var string $plugin_url Plugin URL */
	var $plugin_url    = CF_PLUGIN_URL;
	/** @var string $plugin_dir Path to plugin directory */
	var $plugin_dir    = CF_PLUGIN_DIR;
	/** @var string $plugin_prefix Plugin prefix */
	var $plugin_prefix = 'cf_';
	/** @var string $text_domain The text domain for strings localization */
	var $text_domain   = 'classifieds';
	/** @var string $post_type Plugin post type */
	var $post_type     = 'classifieds';
	/** @var array $taxonomies Post taxonomies */
	var $taxonomy_objects;
	/** @var array $taxonomies Post taxonomies */
	var $taxonomy_names;
	/** @var array $custom_fields The custom fields associated with this post type */
	var $custom_fields = array();
	/** @var string $custom_fields_prefix The custom fields DB prefix */
	var $custom_fields_prefix = '_ct_';
	/** @var string $options_name The name of the plugin options entry in DB */
	var $options_name  = 'classifieds_options';
	/** @var string User role */
	var $user_role = 'cf_member';
	/** @var boolean True if submitted form is valid. */
	var $form_valid = true;
	/** @var boolean True if BuddyPress is active. */
	var $bp_active;
	/** @var boolean Login error flag */
	var $login_error;
	/** @var boolean The current user */
	var $current_user;
	/** @var string Current user credits */
	var $user_credits;
	/** @var boolean flag whether to flush all plugin data on plugin deactivation */
	var $flush_plugin_data = false;
	/** @var string/int Current page for pagination (used in query)*/
	var $cf_page;
	/** @var string/int Current number of pages for pagination (uses in query)*/
	var $cf_pages ='';
	/** @var string/int Current maximum range of page links to show in pagination pagination (used in query)*/
	var $cf_range = 4;
	/** @var string/bool Whether to display pagination at the top of the page*/
	var $cf_pagination_top;
	/** @var string/bool Whether to display pagination at the bottom of the page*/
	var $cf_pagination_bottom;
	/** @var string/int Current maximum number of ads to show per page (used in query)*/
	var $cf_ads_per_page = 10;

	/** @var int classifieds_page_id the Classifieds default page ID number. Track by ID so the page permalink and slug may be internationalized */
	var $classifieds_page_id = 0;
	/** @var string classifieds_page_slug the Classifieds page slug. Track by ID so the page permalink and slug may be internationalized */
	var $classifieds_page_slug = '';
	/** @var string classifieds_page_name the Classifieds default page name for templates. Track by ID so the page permalink and slug may be internationalized */
	var $classifieds_page_name = 'classifieds';

	/** @var int the My Classifieds default page ID number. Track by ID so the page permalink and slug may be internationalized */
	var $my_classifieds_page_id = 0;
	/** @var string the My Classifieds page slug. Track by ID so the page permalink and slug may be internationalized */
	var $my_classifieds_page_slug = '';
	/** @var string classifieds_page_name the Classifieds default page name for templates. Track by ID so the page permalink and slug may be internationalized */
	var $my_classifieds_page_name = 'my-classifieds';

	/** @var int the Checkout default page ID number. Track by ID so the page permalink and slug may be internationalized */
	var $checkout_page_id = 0;
	/** @var string the My Classifieds page slug. Track by ID so the page permalink and slug may be internationalized */
	var $checkout_page_slug = '';
	/** @var string classifieds_page_name the Classifieds default page name for templates. Track by ID so the page permalink and slug may be internationalized */
	var $checkout_page_name = 'checkout';

	var $use_credits = false;
	var $use_paypal = false;
	var $use_authorizenet = false;

	var $use_free = false;
	var $use_annual = false;
	var $use_one_time = false;


	/**
	* Constructor. Old style
	*
	* @return void
	**/
	function Classifieds_Core() { __construct(); }

	/**
	* Constructor.
	*
	* @return void
	**/
	function __construct(){

		/* Hook the entire class to WordPress init hook */
		//		add_action( 'init', array( &$this, 'init' ) );

		/* Initiate class variables from core class */
		add_action( 'init', array( &$this, 'init' ) );

		/* Register activation hook */
		register_activation_hook( $this->plugin_dir . 'loader.php', array( &$this, 'plugin_activate' ) );
		/* Register deactivation hook */
		register_deactivation_hook( $this->plugin_dir . 'loader.php', array( &$this, 'plugin_deactivate' ) );
		/* Add theme support for post thumbnails */
		add_theme_support( 'post-thumbnails' );

		/* Create neccessary pages */
		add_action( 'wp_loaded', array( &$this, 'create_default_pages' ) );
		/* Setup roles and capabilities */
		add_action( 'wp_loaded', array( &$this, 'roles' ) );
		/* Schedule expiration check */
		add_action( 'wp_loaded', array( &$this, 'schedule_expiration_check' ) );
		/* Add template filter */
		add_filter( 'single_template', array( &$this, 'get_single_template' ) ) ;
		/* Add template filter */
		add_filter( 'page_template', array( &$this, 'get_page_template' ) ) ;
		/* Add template filter */
		add_filter( 'taxonomy_template', array( &$this, 'get_taxonomy_template' ) ) ;
		/* template for cf-author page */
		add_action( 'template_redirect', array( &$this, 'get_cf_author_template' ) );
		/* Handle login requests */
		add_action( 'template_redirect', array( &$this, 'handle_login_requests' ) );
		/* Handle all requests for checkout */
		add_action( 'template_redirect', array( &$this, 'handle_checkout_requests' ) );
		/* Handle all requests for contact form submission */
		add_action( 'template_redirect', array( &$this, 'handle_contact_form_requests' ) );
		/* Check expiration dates */
		add_action( 'check_expiration_dates', array( &$this, 'check_expiration_dates_callback' ) );
		/* Set signup credits for new users */
		add_action( 'user_register', array( &$this, 'set_signup_user_credits' ) );
		/** Map meta capabilities */
		add_filter( 'map_meta_cap', array( &$this, 'map_meta_cap' ), 11, 4 );
		/** Show only user's classifieds on classifieds posttype page*/
		add_filter( 'parse_query',  array( &$this, 'show_only_c_user_classifieds' ) );

		/* filter for $wp_query on classifieds page - it is necessary that the other plug-ins have not changed it in these pages */
		add_filter( 'pre_get_posts', array( &$this, 'pre_get_posts_for_classifieds' ), 101 );


	}

	/**
	* Initiate variables.
	*
	* @return void
	**/
	function init() {

		//Loads "classifieds-[xx_XX].mo" language file from the "languages" directory
		load_plugin_textdomain( $this->text_domain, null, 'classifieds/languages/' );

		/* Set Taxonomy objects and names */
		$this->taxonomy_objects = get_object_taxonomies( $this->post_type, 'objects' );
		$this->taxonomy_names   = get_object_taxonomies( $this->post_type, 'names' );
		/* Get all custom fields values with their ID's as keys */
		$custom_fields = get_site_option('ct_custom_fields');
		if ( is_array( $custom_fields ) ) {
			foreach ( $custom_fields as $key => $value ) {
				if ( in_array( $this->post_type, $value['object_type'] ) );
				$this->custom_fields[$key] = $value;
			}
		}
		/* Assign key 'duration' to predifined Custom Field ID */
		$this->custom_fields['duration'] = '_ct_selectbox_4cf582bd61fa4';
		/* Set current user */
		$this->current_user = wp_get_current_user();
		/* Set current user credits */
		$this->user_credits = get_user_meta( $this->current_user->ID, 'cf_credits', true );

		// Get pagination settings
		$options = $this->get_options('general');
		$this->cf_range = (is_numeric($options['pagination_range'])) ? intval($options['pagination_range']) : 4;
		$this->cf_ads_per_page = (is_numeric($options['ads_per_page'])) ? intval($options['ads_per_page']) : 10;
		$this->cf_pagination_top = ( ! empty($options['pagination_top']));
		$this->cf_pagination_bottom = ( ! empty($options['pagination_bottom']));

		//How do we sell stuff
		$options = $this->get_options('payment_types');
		$this->use_free = (! empty($options['free']));
		if (! $this->use_free) { //Can't use gateways if it's free.
			$this->use_paypal = (! empty($options['paypal']));
			if ($this->use_paypal){ //make sure the api fields have something in them
				$this->use_paypal = (! empty($options['api_username'])) && (! empty($options['api_password'])) && (! empty($options['api_signature']));
			}
			
			$this->use_authorizenet = (! empty($options['authorizenet']));
			
			$options = $this->get_options('payments');
			
			$this->use_credits = (! empty($options['enable_credits']));
			$this->use_annual = (! empty($options['enable_annual']));
			$this->use_once = (! empty($options['enable_once']));
		}

	}

	/**
	* filter for $wp_query on classifieds page - it is necessary that the other plug-ins have not changed it in these pages
	*
	* @return void
	**/
	function pre_get_posts_for_classifieds() {
		global $wp_query;

		if ( isset( $wp_query->query_vars['post_type'][0] ) && 'classifieds' == $wp_query->query_vars['post_type'][0] ) {
			$wp_query->query_vars['cat']            = '';
			$wp_query->query_vars['category__in']   = array();
			$wp_query->query_vars['showposts']      = '';
		}
	}

	/**
	* Update plugin versions
	*
	* @return void
	**/
	function plugin_activate() {
		/* Update plugin versions */
		$versions = array( 'versions' => array( 'version' => $this->plugin_version, 'db_version' => $this->plugin_db_version ) );
		$options = get_site_option( $this->options_name );
		$options = ( isset( $options['versions'] ) ) ? array_merge( $options, $versions ) : $versions;
		update_site_option( $this->options_name, $options );
	}

	/**
	* Deactivate plugin. If $this->flush_plugin_data is set to "true"
	* all plugin data will be deleted
	*
	* @return void
	*/
	function plugin_deactivate() {
		/* if $this->flush_plugin_data is set to true it will delete all plugin data */
		if ( $this->flush_plugin_data ) {
			delete_option( $this->options_name );
			delete_site_option( $this->options_name );
			delete_site_option( 'ct_custom_post_types' );
			delete_site_option( 'ct_custom_taxonomies' );
			delete_site_option( 'ct_custom_fields' );
			delete_site_option( 'ct_flush_rewrite_rules' );
		}
	}

	/**
	* Get page by meta value
	*
	* @return int $page[0] /bool false
	*/
	function get_page_by_meta( $value ) {
		$post_statuses = array( 'publish', 'trash', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit' );
		foreach ( $post_statuses as $post_status ) {
			$args = array(
			'hierarchical'  => 0,
			'meta_key'      => 'classifieds_type',
			'meta_value'    => $value,
			'post_type'     => 'page',
			'post_status'   => $post_status
			);

			$page = get_pages( $args );

			if ( isset( $page[0] ) && 0 < $page[0]->ID )
			return $page[0];
		}

		return false;
	}

	/**
	* Create the default Classifieds pages.
	*
	* @return void
	**/
	function create_default_pages() {
		/* Create neccasary pages */

		$classifieds_page = $this->get_page_by_meta( 'classifieds' );
		$parent_id = ($classifieds_page && $classifieds_page->ID > 0) ? $classifieds_page->ID : 0;

		if ( empty($parent_id) ) {
			$current_user = wp_get_current_user();
			/* Construct args for the new post */
			$args = array(
			'post_title'     => 'Classifieds',
			'post_status'    => 'publish',
			'post_author'    => $current_user->ID,
			'post_type'      => 'page',
			'ping_status'    => 'closed',
			'comment_status' => 'closed'
			);
			$parent_id = wp_insert_post( $args );
			add_post_meta( $parent_id, "classifieds_type", $this->classifieds_page_name );
		}

		$this->classifieds_page_id = $parent_id; //Remember the number
		$this->classifieds_page_slug = $classifieds_page->post_name; //Remember the slug

		$classifieds_page = $this->get_page_by_meta( 'my_classifieds' );
		$page_id = ($classifieds_page && $classifieds_page->ID > 0) ? $classifieds_page->ID : 0;

		if ( empty($page_id) ) {
			$current_user = wp_get_current_user();
			/* Construct args for the new post */
			$args = array(
			'post_title'     => 'My Classifieds',
			'post_status'    => 'publish',
			'post_author'    => $current_user->ID,
			'post_type'      => 'page',
			'post_parent'    => $parent_id,
			'ping_status'    => 'closed',
			'comment_status' => 'closed'
			);
			$page_id = wp_insert_post( $args );
			add_post_meta( $page_id, "classifieds_type",  'my_classifieds' );
		}

		$this->my_classifieds_page_id = $page_id; // Remember the number
		$this->my_classifieds_page_slug = $classifieds_page->post_name; //Remember the slug

		$classifieds_page = $this->get_page_by_meta( 'checkout' );
		$page_id = ($classifieds_page && $classifieds_page->ID > 0) ? $classifieds_page->ID : 0;

		if ( empty($page_id) ) {
			$current_user = wp_get_current_user();
			/* Construct args for the new post */
			$args = array(
			'post_title'     => 'Checkout',
			'post_status'    => 'publish',
			'post_author'    => $current_user->ID,
			'post_type'      => 'page',
			'post_parent'    => $parent_id,
			'ping_status'    => 'closed',
			'comment_status' => 'closed',
			'menu_order'     => 1
			);
			$page_id = wp_insert_post( $args );
			add_post_meta( $page_id, "classifieds_type", 'checkout' );
		}

		$this->checkout_page_id = $page_id; // Remember the number
		$this->checkout_page_slug = $classifieds_page->post_name; //Remember the slug

	}

	/**
	* Process login request.
	*
	* @param string $username
	* @param string $password
	* @return object $result->errors
	**/
	function login( $username, $password ) {
		/* Check whether the required information is submitted */
		if ( empty( $username ) || empty( $password ) )
		return __( 'Please fill in the required fields.', $this->text_domain );
		/* Build the login credentials */
		$credentials = array( 'remember' => true, 'user_login' => $username, 'user_password' => $password );
		/* Sign the user in and get the result */
		$result = wp_signon( $credentials );
		if ( isset( $result->errors )) {
			if ( isset( $result->errors['invalid_username'] ))
			return $result->errors['invalid_username'][0];
			elseif ( isset( $result->errors['incorrect_password'] ))
			return $result->errors['incorrect_password'][0];
		}
	}

	/**
	* Add custom role for Classifieds members. Add new capabilities for admin.
	*
	* @global $wp_roles
	* @return void
	**/
	function roles() {
		global $wp_roles;
		if ( $wp_roles ) {
			/** @todo remove remove_role */
			$wp_roles->remove_role( $this->user_role );

			$wp_roles->add_role( $this->user_role, 'Classifieds Member', array(
			'publish_classifieds'       => true,
			'edit_classifieds'          => true,
			'edit_others_classifieds'   => false,
			'delete_classifieds'        => true,
			'delete_others_classifieds' => false,
			'read_private_classifieds'  => false,
			'edit_classified'           => true,
			'delete_classified'         => true,
			'read_classified'           => true,
			'upload_files'              => true,
			'assign_terms'              => true,
			'read'                      => true
			) );

			/* Set administrator roles */
			$wp_roles->add_cap( 'administrator', 'publish_classifieds' );
			$wp_roles->add_cap( 'administrator', 'edit_classifieds' );
			$wp_roles->add_cap( 'administrator', 'edit_others_classifieds' );
			$wp_roles->add_cap( 'administrator', 'delete_classifieds' );
			$wp_roles->add_cap( 'administrator', 'delete_others_classifieds' );
			$wp_roles->add_cap( 'administrator', 'read_private_classifieds' );
			$wp_roles->add_cap( 'administrator', 'edit_classified' );
			$wp_roles->add_cap( 'administrator', 'delete_classified' );
			$wp_roles->add_cap( 'administrator', 'read_classified' );
			$wp_roles->add_cap( 'administrator', 'assign_terms' );
		}
	}

	/**
	* Show only current user classifieds on page of classifieds posttype page.
	*
	* @return void
	**/
	function show_only_c_user_classifieds ( $wp_query ) {
		if ( strpos( $_SERVER[ 'REQUEST_URI' ], '/wp-admin/edit.php' ) !== false )
		if ( isset( $_GET['post_type'] ) && 'classifieds' == $_GET['post_type'] &&  !current_user_can( 'level_10' ) )
		$wp_query->set( 'author', get_current_user_id() );
	}

	/**
	* Map meta capabilities
	*
	* Learn more:
	* @link http://justintadlock.com/archives/2010/07/10/meta-capabilities-for-custom-post-types
	* @link http://wordpress.stackexchange.com/questions/1684/what-is-the-use-of-map-meta-cap-filter/2586#2586
	*
	* @param <type> $caps
	* @param <type> $cap
	* @param <type> $user_id
	* @param <type> $args
	* @return array
	**/
	function map_meta_cap( $caps, $cap, $user_id, $args ) {

		/* If editing, deleting, or reading a movie, get the post and post type object. */
		if ( 'edit_classified' == $cap || 'delete_classified' == $cap || 'read_classified' == $cap ) {
			$post = get_post( $args[0] );
			$post_type = get_post_type_object( $post->post_type );

			/* Set an empty array for the caps. */
			$caps = array();
		}

		/* If editing a movie, assign the required capability. */
		if ( 'edit_classified' == $cap ) {
			if ( $user_id == $post->post_author )
			$caps[] = $post_type->cap->edit_posts;
			else
			$caps[] = $post_type->cap->edit_others_posts;
		}

		/* If deleting a movie, assign the required capability. */
		elseif ( 'delete_classified' == $cap ) {
			if ( $user_id == $post->post_author )
			$caps[] = $post_type->cap->delete_posts;
			else
			$caps[] = $post_type->cap->delete_others_posts;
		}

		/* If reading a private movie, assign the required capability. */
		elseif ( 'read_classified' == $cap ) {

			if ( 'private' != $post->post_status )
			$caps[] = 'read';
			elseif ( $user_id == $post->post_author )
			$caps[] = 'read';
			else
			$caps[] = $post_type->cap->read_private_posts;
		}

		/* Return the capabilities required by the user. */
		return $caps;
	}

	/**
	* Insert/Update User
	*
	* @param string $email
	* @param string $first_name
	* @param string $last_name
	* @param string $billing The billing type for the user
	* @return NULL|void
	**/
	function update_user( $email, $first_name, $last_name, $billing, $credits, $order_info = '' ) {

		/* If user logged update it */
		if ( is_user_logged_in() ) {

			wp_update_user( array( 'ID' => get_current_user_id(), 'role' => $this->user_role ) );

			//saving information of transaction
			$cf_order = array(
			'billing'    => $billing,
			'order_info' => $order_info
			);
			//set time of end annual - 1 year after now
			if ( 'annual' == $billing ) {
				$now =  time();
				$cf_order['time_end_annual'] = mktime( date( 'H', $now ) , date( 'i', $now ), date( 's', $now ), date( 'n', $now ), date( 'j', $now ), date( 'Y', $now ) + 1 ) ;
			}
			update_user_meta( get_current_user_id(), 'cf_order', $cf_order );

			//Update credits only for none 'annual' or  'one_time' users
			if ( isset( $billing ) && 'credits' == $billing )
			$this->update_user_credits( $credits, get_current_user_id() );

			return;
		}


		/* If user exists update it */
		if ( email_exists( $user_email ) || is_user_logged_in() ) {
			$user = get_user_by( 'email', $user_email );
			if ( $user ) {
				wp_update_user( array( 'ID' => $user->ID, 'role' => $this->user_role ) );

				//saving information of transaction
				$cf_order = array(
				'billing'    => $billing,
				'order_info' => $order_info
				);
				//set time of end annual - 1 year after now
				if ( 'annual' == $billing ) {
					$now =  time();
					$cf_order['time_end_annual'] = mktime( date( 'H', $now ) , date( 'i', $now ), date( 's', $now ), date( 'n', $now ), date( 'j', $now ), date( 'Y', $now ) + 1 ) ;
				}
				update_user_meta( $user->ID, 'cf_order', $cf_order );

				//Update credits only for none 'annual' or  'one_time' users
				if ( isset( $billing ) && 'credits' == $billing )
				$this->update_user_credits( $credits, $user->ID );

				$credentials = array( 'remember'=>true, 'user_login' => $user->user_login, 'user_password' => $user->user_pass );
				wp_signon( $credentials );
				return;
			}
		}

		/* if user not exist create new */
		/* Variables */
		$user_login     = sanitize_user( strtolower( $first_name ));
		$user_email     = $email;
		$user_pass      = wp_generate_password();
		if ( username_exists( $user_login ) )
		$user_login .= '-' . sanitize_user( strtolower( $last_name ));
		if ( username_exists( $user_login ) )
		$user_login .= rand(1,9);

		$user_id = wp_insert_user( array(
		'user_login'   => $user_login,
		'user_pass'    => $user_pass,
		'user_email'   => $email,
		'display_name' => $first_name . ' ' . $last_name,
		'first_name'   => $first_name,
		'last_name'    => $last_name,
		'role'         => $this->user_role
		) ) ;
		if ( $user_id ) {

			//saving information of transaction
			$cf_order = array(
			'billing'    => $billing,
			'order_info' => $order_info
			);
			//set time of end annual - 1 year after now
			if ( 'annual' == $billing ) {
				$now =  time();
				$cf_order['time_end_annual'] = mktime( date( 'H', $now ) , date( 'i', $now ), date( 's', $now ), date( 'n', $now ), date( 'j', $now ), date( 'Y', $now ) + 1 ) ;
			}
			update_user_meta( $user_id, 'cf_order', $cf_order );

			//Update credits only for none 'annual' or  'one_time' users
			if ( isset( $billing ) && 'credits' == $billing )
			$this->update_user_credits( $credits, $user_id );

			wp_new_user_notification( $user_id, $user_pass );
			$credentials = array( 'remember'=> true, 'user_login' => $user_login, 'user_password' => $user_pass );
			wp_signon( $credentials );
		}
	}

	/**
	* Update or insert ad if no ID is passed.
	*
	* @param array $params Array of $_POST data
	* @param array|NULL $file Array of $_FILES data
	* @return int $post_id
	**/
	function update_ad( $params, $file = NULL ) {

		$current_user = wp_get_current_user();
		/* Construct args for the new post */
		$args = array(
		/* If empty ID insert Ad insetad of updating it */
		'ID'             => ( isset( $params['post_id'] ) ) ? $params['post_id'] : '',
		'post_title'     => $params['title'],
		'post_content'   => $params['description'],
		'post_status'    => $params['status'],
		'post_author'    => $current_user->ID,
		'post_type'      => $this->post_type,
		'ping_status'    => 'closed',
		'comment_status' => 'open'
		);
		/* Insert page and get the ID */
		$post_id = wp_insert_post( $args );
		if ( $post_id ) {
			/* Set object terms */
			foreach ( $params['terms'] as $taxonomy => $terms  )
			wp_set_object_terms( $post_id, $terms, $taxonomy );
			/* Set custom fields data */
			if ( is_array( $params['custom_fields'] ) ) {
				foreach ( $params['custom_fields'] as $key => $value )
				update_post_meta( $post_id, $key, $value );
			}
			/* Require WordPress utility functions for handling media uploads */
			require_once( ABSPATH . '/wp-admin/includes/media.php' );
			require_once( ABSPATH . '/wp-admin/includes/image.php' );
			require_once( ABSPATH . '/wp-admin/includes/file.php' );
			/* Upload the image ( handles creation of thumbnails etc. ), set featured image  */
			if ( empty( $file['image']['error'] )) {
				$thumbnail_id = media_handle_upload( 'image', $post_id );
				update_post_meta( $post_id, '_thumbnail_id', $thumbnail_id );
			}
			return $post_id;
		}
	}

	/**
	* Checking that curent user has full access for add ads without credits.
	*
	* @return boolean
	**/
	function is_full_access() {
		//todo: add checking of annual users - if canceled or time < now()

		//for admin
		if ( current_user_can('manage_options') )
		return true;

		if ( isset( $this->current_user->ID ) && 0 < $this->current_user->ID )
		$user_id = $this->current_user->ID;
		elseif ( function_exists( 'get_current_user_id' ) && 0 < get_current_user_id() )
		$user_id = get_current_user_id();
		else
		$user_id = 0;

		//for paid users
		$cf_order = get_user_meta( $user_id, 'cf_order', true );
		if ( isset( $cf_order['billing'] ) ) {
			if ( 'one_time' == $cf_order['billing'] && 'success' == $cf_order['order_info']['status'] ) {
				return true;
			} elseif ( 'annual' == $cf_order['billing'] && 'success' == $cf_order['order_info']['status'] && time() < $cf_order['time_end_annual'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	* Handle user login.
	*
	* @return void
	**/
	function handle_login_requests() {

		if ( isset( $_POST['login_submit'] ) )
		$this->login_error = $this->login( $_POST['username'], $_POST['password'] );
	}

	/**
	* Handle all checkout requests.
	*
	* @uses session_start() We need to keep track of some session variables for the checkout
	* @return NULL If the payment gateway options are not configured.
	**/
	function handle_checkout_requests() {

		/* Only handle request if on the proper page */
		if ( is_page('checkout') ) {
			/* Start session */
			if ( !session_id() )
			session_start();
			/* Get site options */
			$options = $this->get_options();
			/* Redirect if user is logged in */
			if ( is_user_logged_in() ) {
				/** @todo Set redirect */
				//wp_redirect( get_bloginfo('url') );
			}
			/* If no Payment type setup, disable the checkout process */
			if ( ! ($this->use_free || $this->use_paypal || $this->use_authorizenet) ) {
				/* Set the proper step which will be loaded by "page-checkout.php" */
				set_query_var( 'cf_step', 'disabled' );
				return;
			}

			/* If Terms and Costs step is submitted */
			if ( isset( $_POST['terms_submit'] ) ) {
				
				if($this->use_free){
					set_query_var( 'cf_step', 'cc_details' );
					return;
				}
				/* Validate fields */
				if ( empty( $_POST['tos_agree'] ) || empty( $_POST['billing'] ) ) {
					if ( empty( $_POST['tos_agree'] ))
					add_action( 'tos_invalid', create_function('', 'echo "class=\"error\"";') );
					if ( empty( $_POST['billing'] ))
					add_action( 'billing_invalid', create_function('', 'echo "class=\"error\"";') );
					/* Set the proper step which will be loaded by "page-checkout.php" */
					set_query_var( 'cf_step', 'terms' );
				} else {
					/* Set the proper step which will be loaded by "page-checkout.php" */
					set_query_var( 'cf_step', 'payment_method' );
				}
			}
			/* If login attempt is made */
			elseif ( isset( $_POST['login_submit'] ) ) {
				if ( isset( $this->login_error )) {
					add_action( 'login_invalid', create_function('', 'echo "class=\"error\"";') );
					/* Set the proper step which will be loaded by "page-checkout.php" */
					set_query_var( 'cf_step', 'pre_login' );
					/* Pass error params to "page-checkout.php" */
					set_query_var( 'cf_error', $this->login_error );
				} else {
					set_query_var( 'cf_step', 'terms' );
				}
			}
			/* If payment method is selected and submitted */
			elseif ( isset( $_POST['payment_method_submit'] )) {
				if ( $_POST['payment_method'] == 'paypal' ) {
					/* Initiate paypal class */
					$checkout = new Classifieds_Core_PayPal();
					/* Make API call */
					$result = $checkout->call_shortcut_express_checkout( $_POST['cost'] );
					/* Handle Success and Error scenarios */
					if ( $result['status'] == 'error' ) {
						/* Set the proper step which will be loaded by "page-checkout.php" */
						set_query_var( 'cf_step', 'api_call_error' );
						/* Pass error params to "page-checkout.php" */
						set_query_var( 'cf_error', $result );
					} else {
						/* Set billing and credits so we can update the user account later */
						$_SESSION['billing'] = $_POST['billing'];
						$_SESSION['credits'] = $_POST['credits'];
					}
				} elseif ( $_POST['payment_method'] == 'cc' ) {
					/* Set the proper step which will be loaded by "page-checkout.php" */
					set_query_var( 'cf_step', 'cc_details' );
				}
			}
			/* If direct CC payment is submitted */
			elseif ( isset( $_POST['direct_payment_submit'] ) ) {
				
				if($this->use_free){
					$this->update_user( $_POST['email'], $_POST['first_name'], $_POST['last_name'], $_POST['billing'], 0, $result );
					/* Set the proper step which will be loaded by "page-checkout.php" */
					set_query_var( 'cf_step', 'success' );
					return;
				}
				
				/* Initiate paypal class */
				$checkout = new Classifieds_Core_PayPal();
				/* Make API call */
				$result = $checkout->direct_payment( $_POST['total_amount'], $_POST['cc_type'], $_POST['cc_number'], $_POST['exp_date'], $_POST['cvv2'], $_POST['first_name'], $_POST['last_name'], $_POST['street'], $_POST['city'], $_POST['state'], $_POST['zip'], $_POST['country_code'] );
				/* Handle Success and Error scenarios */
				if ( $result['status'] == 'success' ) {
					/* Set the proper step which will be loaded by "page-checkout.php" */
					set_query_var( 'cf_step', 'direct_payment' );
				} else {
					/* Set the proper step which will be loaded by "page-checkout.php" */
					set_query_var( 'cf_step', 'api_call_error' );
					/* Pass error params to "page-checkout.php" */
					set_query_var( 'cf_error', $result );
				}
			}
			/* If PayPal has redirected us back with the proper TOKEN */
			elseif ( isset( $_REQUEST['token'] ) && !isset( $_POST['confirm_payment_submit'] ) && !isset( $_POST['redirect_my_classifieds'] ) ) {
				/* Initiate paypal class */
				$checkout = new Classifieds_Core_PayPal();
				/* Make API call */
				$result = $checkout->get_shipping_details();
				/* Handle Success and Error scenarios */
				if ( $result['status'] == 'success' ) {
					/* Set the proper step which will be loaded by "page-checkout.php" */
					set_query_var( 'cf_step', 'confirm_payment' );
					/* Pass transaction details params to "page-checkout.php" */
					set_query_var( 'cf_transaction_details', $result );
				} else {
					/* Set the proper step which will be loaded by "page-checkout.php" */
					set_query_var( 'cf_step', 'api_call_error' );
					/* Pass error params to "page-checkout.php" */
					set_query_var( 'cf_error', $result );
				}
			}
			/* If payment confirmation is submitted */
			elseif ( isset( $_POST['confirm_payment_submit'] ) ) {
				/* Initiate paypal class */
				$checkout = new Classifieds_Core_PayPal();
				/* Make API call */
				$result = $checkout->confirm_payment( $_POST['total_amount'] );
				/* Handle Success and Error scenarios */
				if ( $result['status'] == 'success' ) {
					/* Insert/Update User */
					$this->update_user( $_POST['email'], $_POST['first_name'], $_POST['last_name'], $_POST['billing'], $_POST['credits'], $result );
					/* Set the proper step which will be loaded by "page-checkout.php" */
					set_query_var( 'cf_step', 'success' );
				} else {
					/* Set the proper step which will be loaded by "page-checkout.php" */
					set_query_var( 'cf_step', 'api_call_error' );
					/* Pass error params to "page-checkout.php" */
					set_query_var( 'cf_error', $result );
				}
			}
			/* If transaction processed successfully, redirect to my-classifieds */
			elseif( isset( $_POST['redirect_my_classifieds'] ) ) {
				wp_redirect( get_permalink($this->my_classifieds_page_id) );
			}
			/* If no requests are made load default step */
			else {
				if ( is_user_logged_in() || isset( $_POST['new_account'] ) ) {
					/* Set the proper step which will be loaded by "page-checkout.php" */
					set_query_var( 'cf_step', 'terms' );
				} else {
					/* Set the proper step which will be loaded by "page-checkout.php" */
					set_query_var( 'cf_step', 'pre_login' );
				}
			}
		}
	}

	/**
	* Handles the request for the contact form on the single{}.php template
	**/
	function handle_contact_form_requests() {

		/* Only handle request if on single{}.php template and our post type */
		if ( get_post_type() == $this->post_type && is_single() ) {

			if ( isset( $_POST['contact_form_send'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'send_message' ) ) {
				if ( isset( $_POST['name'] ) && '' != $_POST['name'] &&
				isset( $_POST['email'] ) && '' != $_POST['email'] &&
				isset( $_POST['subject'] ) && '' != $_POST['subject'] &&
				isset( $_POST['message'] ) && '' != $_POST['message'] ) {

					global $post;

					$user_info  = get_userdata( $post->post_author );

					$body       = 'Hi %s, you have received message from:<br />
					<br />
					Name: %s <br />
					Email: %s <br />
					Subject: %s <br />
					Message: <br />
					%s
					<br />
					<br />
					<br />
					Classifieds link: %s
					';

					$tm_subject =  'Contact Request: %s [ %s ]';

					$to         = $user_info->user_email;
					$subject    = sprintf( __( $tm_subject, 'classifieds' ), $_POST['subject'], $post->post_title );
					$message    = sprintf( __( $body, 'classifieds' ), $user_info->user_nicename, $_POST['name'], $_POST['email'], $_POST['subject'], $_POST['message'], get_permalink( $post->ID ) );
					$headers    = "MIME-Version: 1.0\n" . "From: " . $_POST['name'] .  " <{$_POST['email']}>\n" . "Content-Type: text/html; charset=\"" . get_option( 'blog_charset' ) . "\"\n";

					wp_mail( $to, $subject, $message, $headers );
					wp_redirect( get_permalink( $post->ID ) . '?sent=1' );
				}
			}
		}
	}

	/**
	* Save custom fields data
	*
	* @param int $post_id The post id of the post being edited
	* @return NULL If there is autosave attempt
	**/
	function save_expiration_date( $post_id ) {
		/* prevent autosave from deleting the custom fields */
		if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
		return;
		/* Update  */
		if ( isset( $_POST[$this->custom_fields['duration']] ) ) {
			$date = $this->calculate_expiration_date( $post_id, $_POST[$this->custom_fields['duration']] );
			update_post_meta( $post_id, '_expiration_date', $date );
		} if ( isset( $_POST['custom_fields'][$this->custom_fields['duration']] ) ) {
			$date = $this->calculate_expiration_date( $post_id, $_POST['custom_fields'][$this->custom_fields['duration']] );
			update_post_meta( $post_id, '_expiration_date', $date );
		} elseif ( isset( $_POST['duration'] ) ) {
			$date = $this->calculate_expiration_date( $post_id, $_POST['duration'] );
			update_post_meta( $post_id, '_expiration_date', $date );
		}
	}

	/**
	* Get formated expiration date.
	*
	* @param int|string $post_id
	* @return string Date/Time formated string
	**/
	function get_expiration_date( $post_id ) {
		$date = get_post_meta( $post_id, '_expiration_date', true );
		if ( !empty( $date ) )
		return date( get_option('date_format'), $date );
		else
		return __( 'No expiration date set.', 'classifieds' );
	}

	/**
	* Calculate the Unix time stamp of the modified posts
	*
	* @param int|string $post_id
	* @param string $duration Valid value: "1 Week", "2 Weeks" ... etc
	* @return int Unix timestamp
	**/
	function calculate_expiration_date( $post_id, $duration ) {
		/** @todo Remove ugly hack { Update Content Types so they can have empty default values and required fields }*/
		if ( $duration == '----------' ) {
			$expiration_date = get_post_meta( $post_id, '_expiration_date', true );
			return $expiration_date;
		}
		/* Process normal request */
		$post = get_post( $post_id );
		$expiration_date = get_post_meta( $post_id, '_expiration_date', true );
		if ( empty( $expiration_date ) || $expiration_date < time() )
		$expiration_date = time();
		$date = strtotime( "+{$duration}", $expiration_date );
		return $date;
	}

	/**
	* Schedule expiration check for twice daily.
	*
	* @return void
	**/
	function schedule_expiration_check() {
		if ( !wp_next_scheduled( 'check_expiration_dates' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'check_expiration_dates' );
		}
	}

	/**
	* Check each post from the used post type and compare the expiration date/time
	* with the current date/time. If the post is expired update it's status.
	*
	* @return void
	**/
	function check_expiration_dates_callback() {
		$posts = get_posts( array( 'post_type' => $this->post_type, 'numberposts' => 0 ) );
		foreach ( $posts as $post ) {
			$expiration_date = get_post_meta( $post->ID, '_expiration_date', true );
			if ( empty( $expiration_date ) )
			$this->process_status( $post->ID, 'draft' );
			elseif ( $expiration_date < time() )
			$this->process_status( $post->ID, 'private' );
		}
	}

	/**
	* Sets initial credits amount.
	*
	* @param int $user_id
	* @return void
	**/
	function set_signup_user_credits( $user_id ) {
		$options = $this->get_options('payments');
		if ( $options['enable_credits'] == true ) {
			if ( !empty( $options['signup_credits'] ) )
			update_user_meta( $user_id, 'cf_credits', $options['signup_credits'] );
		}
	}

	/**
	* Get user credits.
	*
	* @return string User credits.
	**/
	function get_user_credits() {
		$credits = get_user_meta( get_current_user_id(), 'cf_credits', true );
		$credits_log = get_user_meta( get_current_user_id(), 'cf_credits_log', true );
		return ( empty( $credits ) ) ? 0 : $credits;
	}

	/**
	* Set user credits.
	*
	* @param string $credits Number of credits to add.
	* @param int|string $user_id
	* @return void
	**/
	function update_user_credits( $credits, $user_id = NULL ) {

		$user_id = (empty($user_id)) ? $this->current_user->ID : $user_id;
		$available_credits = get_user_meta( $user_id , 'cf_credits', true );
		$total_credits = ( $available_credits ) ? ( $available_credits + $credits ) : $credits;
		update_user_meta( $user_id, 'cf_credits', $total_credits );
		$this->update_user_credits_log( $credits, $user_id );
	}

	/**
	* Get the credits log of an user.
	*
	* @return string|array Log of credit events
	**/
	function get_user_credits_log() {
		$credits_log = get_user_meta( get_current_user_id(), 'cf_credits_log', true );
		if ( !empty( $credits_log ) )
		return $credits_log;
		else
		return __( 'No History', $this->text_domain );
	}

	/**
	* Log user credits activity.
	*
	* @param string $credits How many credits to log
	**/
	function update_user_credits_log( $credits, $user_id ) {
		$date = time();
		$credits_log = array( array(
		'credits'   => $credits,
		'date'      => $date
		));
		$user_meta = get_user_meta( $user_id, 'cf_credits_log', true );
		$user_meta = ( get_user_meta( $user_id, 'cf_credits_log', true ) ) ? array_merge( $user_meta, $credits_log ) : $credits_log;
		update_user_meta( $user_id, 'cf_credits_log', $user_meta );
	}

	/**
	* Return the number of credits based on the duration selected.
	*
	* @return int|string Number of credits
	**/
	function get_credits_from_duration( $duration ) {
		$options = $this->get_options('payments');

		if ( !isset( $options['credits_per_week'] ) || $this->use_free)
		$options['credits_per_week'] = 0;

		switch ( $duration ) {
			case '1 Week':
			return 1 * $options['credits_per_week'];
			case '2 Weeks':
			return 2 * $options['credits_per_week'];
			case '3 Weeks':
			return 3 * $options['credits_per_week'];
			case '4 Weeks':
			return 4 * $options['credits_per_week'];
		}
	}

	/**
	* Save plugin options.
	*
	* @param  array $params The $_POST array
	* @return die() if _wpnonce is not verified
	**/
	function save_options( $params ) {
		if ( wp_verify_nonce( $params['_wpnonce'], 'verify' ) ) {
			/* Remove unwanted parameters */
			unset( $params['_wpnonce'], $params['_wp_http_referer'], $params['save'] );
			/* Update options by merging the old ones */
			$options = $this->get_options();
			$options = array_merge( $options, array( $params['key'] => $params ) );
			update_option( $this->options_name, $options );
		} else {
			die( __( 'Security check failed!', $this->text_domain ) );
		}
	}

	/**
	* Get plugin options.
	*
	* @param  string|NULL $key The key for that plugin option.
	* @return array $options Plugin options or empty array if no options are found
	**/
	function get_options( $key = NULL ) {
		$options = get_option( $this->options_name );
		$options = is_array( $options ) ? $options : array();
		/* Check if specific plugin option is requested and return it */
		if ( isset( $key ) && array_key_exists( $key, $options ) )
		return $options[$key];
		else
		return $options;
	}

	/**
	* Process post status.
	*
	* @global object $wpdb
	* @param  string $post_id
	* @param  string $status
	* @return void
	**/
	function process_status( $post_id, $status ) {
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_status' => $status ), array( 'ID' => $post_id ), array( '%s' ), array( '%d' ) );
	}

	/**
	* Format date.
	*
	* @param int $date unix timestamp
	* @return string formatted date
	**/
	function format_date( $date ) {
		return date( get_option('date_format'), $date );
	}

	/**
	* Validate fields
	*
	* @param array $params $_POST data
	* @param array|NULL $file $_FILES data
	* @return void
	**/
	function validate_fields( $params, $file = NULL ) {
		if ( empty( $params['title'] ) || empty( $params['description'] ) || empty( $params['terms'] ) || empty( $params['status'] )) {
			$this->form_valid = false;
		}

		$options = $this->get_options( 'general' );

		//do image field not required
		if ( !isset( $options['field_image_req'] ) || '1' != $options['field_image_req'] )
		if ( $file['image']['error'] !== 0 ) {
			$this->form_valid = false;
		}

	}

	/**
	* Filter the template path to single{}.php templates.
	* Load from theme directory primary if it doesn't exist load from plugin dir.
	*
	* Learn more: http://codex.wordpress.org/Template_Hierarchy
	* Learn more: http://codex.wordpress.org/Plugin_API/Filter_Reference#Template_Filters
	*
	* @global <type> $post Post object
	* @param string Templatepath to filter
	* @return string Templatepath
	**/
	function get_single_template( $template ) {
		global $post;
		if ( ! file_exists( get_template_directory() . "/single-{$post->post_type}.php" )
		&& file_exists( "{$this->plugin_dir}ui-front/general/single-{$post->post_type}.php" ) )
		return "{$this->plugin_dir}ui-front/general/single-{$post->post_type}.php";
		else
		return $template;
	}


	/**
	* Filter the template path to page{}.php templates.
	* Load from theme directory primary if it doesn't exist load from plugin dir.
	*
	* Learn more: http://codex.wordpress.org/Template_Hierarchy
	* Learn more: http://codex.wordpress.org/Plugin_API/Filter_Reference#Template_Filters
	*
	* @global <type> $post Post object
	* @param string Templatepath to filter
	* @return string Templatepath
	**/
	function get_page_template( $template ) {
		global $post, $paged;

		//get page number for pagination
		if ( get_query_var('paged') ) {
			$paged = get_query_var('paged'); //Usually paged
		} elseif ( get_query_var('page') ) { //But if front page it's page
			$paged = get_query_var('page');
		} else {
			$paged = 1;
		}

		$this->cf_page = $paged;

		//Translate back to standard names.
		$name = $post->post_name;
		if($post->ID == $this->classifieds_page_id) $name = $this->classifieds_page_name;
		if($post->ID == $this->my_classifieds_page_id) $name = $this->my_classifieds_page_name;
		if($post->ID == $this->checkout_page_id) $name = $this->checkout_page_name;

		if ( ! file_exists( get_template_directory() . "/page-{$name}.php" ) && file_exists( "{$this->plugin_dir}ui-front/general/page-{$name}.php" ) ){
			return "{$this->plugin_dir}ui-front/general/page-{$name}.php";
		} else {
			return $template;
		}
	}

	/**
	*Get Template for classifieds author page
	**/
	function get_cf_author_template() {
		global $wp_query;

		if ( '' != get_query_var( 'cf_author_name' ) || isset( $_REQUEST['cf_author'] ) && '' != $_REQUEST['cf_author'] )  {

			if ( file_exists(get_template_directory() . '/author.php')){
				load_template( get_template_directory() . '/author.php' );
			} else {
				load_template( "{$this->plugin_dir}ui-front/general/author.php" );
			}
			exit();
		}
	}

	/**
	* Filter the template path to taxonomy{}.php templates.
	* Load from theme directory primary if it doesn't exist load from plugin dir.
	*
	* Learn more: http://codex.wordpress.org/Template_Hierarchy
	* Learn more: http://codex.wordpress.org/Plugin_API/Filter_Reference#Template_Filters
	*
	* @global <type> $post Post object
	* @param string Templatepath to filter
	* @return string Templatepath
	**/
	function get_taxonomy_template( $template ) {
		
		$taxonomy = get_query_var('taxonomy');
		$term = get_query_var('term');

		if ( "classifieds_categories" != $taxonomy && "classifieds_tags" != $taxonomy )
		return;


		/* Check whether the files doesn't exist in the active theme directory,
		* also check for file to load in our general template directory */
		if ( ! file_exists( get_template_directory() . "/taxonomy-{$taxonomy}-{$term}.php" )
		&& file_exists( "{$this->plugin_dir}ui-front/general/taxonomy-{$taxonomy}-{$term}.php" ) )
		return "{$this->plugin_dir}ui-front/general/taxonomy-{$taxonomy}-{$term}.php";
		elseif ( ! file_exists( get_template_directory() . "/taxonomy-{$taxonomy}.php" )
		&& file_exists( "{$this->plugin_dir}ui-front/general/taxonomy-{$taxonomy}.php" ) )
		return "{$this->plugin_dir}ui-front/general/taxonomy-{$taxonomy}.php";
		elseif ( ! file_exists( get_template_directory() . "/taxonomy.php" )
		&& file_exists( "{$this->plugin_dir}ui-front/general/taxonomy.php" ) )
		return "{$this->plugin_dir}ui-front/general/taxonomy.php";
		else
		return $template;
	}

	/**
	* Renders a section of user display code.  The code is first checked for in the current theme display directory
	* before defaulting to the plugin
	*
	* @param  string $name Name of the admin file(without extension)
	* @param  string $vars Array of variable name=>value that is available to the display code(optional)
	* @return void
	**/
	function render_front( $name, $vars = array() ) {
		/* Construct extra arguments */
		foreach ( $vars as $key => $val )
		$$key = $val;
		/* Include templates */

		$result = get_template_directory() . "/{$name}.php";
		if ( file_exists( $result ) ){
			include($result);
			return;
		}

		$result = "{$this->plugin_dir}ui-front/buddypress/members/single/classifieds/{$name}.php";
		if ( file_exists( $result ) && $this->bp_active ){
			include($result);
			return;
		}

		$result = "{$this->plugin_dir}ui-front/general/{$name}.php";

		if ( file_exists( $result ) ) {
			include($result);
			return;
		}

		echo "<p>Rendering of template $result {$name}.php failed</p>";
	}

	/**
	* Redirect using JavaScript. Useful if headers are already sent.
	*
	* @param string $url The URL to which the function should redirect
	**/
	function js_redirect( $url, $silent = false ) {
		if(! $silent ):
		?>
		<p><?php _e( 'You are being redirected. Please wait.', $this->text_domain );  ?></p>
		<img src="<?php echo $this->plugin_url .'/ui-front/general/images/loader.gif'; ?>" alt="<?php _e( 'You are being redirected. Please wait.', $this->text_domain );  ?>" />
		<?php endif; ?>
		<script type="text/javascript">//<![CDATA[
			window.location = '<?php echo $url; ?>';	//]]>
		</script>
		<?php
	}

	/**
	* Display pagination on classifids pages.
	**/
	function cf_display_pagination( $pag_id ) {
		global $wp_query, $paged;

		if ($pag_id =='top' && ! $this->cf_pagination_top) return '';
		if ($pag_id =='bottom' && ! $this->cf_pagination_bottom) return '';

		ob_start();

		/* Display navigation to next/previous pages when applicable */
		if ( $wp_query->max_num_pages > 1 ) :
		?>

		<div id="nav-<?php echo $pag_id; ?>" class="navigation">

			<?php if ( class_exists( 'PageNavi_Core' ) ) : //If they have the plugin
			?>
			<!-- WP-PageNavi - pagination -->
			<?php wp_pagenavi();

			else:

			//Do fancy pagination

			$showitems = ($this->cf_range * 2)+1;

			if(empty($paged)) $paged = 1;
			if($this->cf_pages == '') {
				$this->cf_pages = $wp_query->max_num_pages;
				if(!$this->cf_pages){
					$this->cf_pages = 1;
				}
			}

			if(1 != $this->cf_pages) :
			?>

			<div class="pagination"><!--begin pagination-->
				<span><?php echo sprintf( __('Page %1$d of %2$d',$this->text_domain), $paged, $this->cf_pages); ?></span>

				<?php if($paged > 2 && $paged > $this->cf_range+1 && $showitems < $this->cf_pages): ?>
				<a href="<?php get_pagenum_link(1); ?>">&laquo;<?php _e('First',$this->text_domain); ?></a>
				<?php endif; ?>

				<?php if($paged > 1 && $showitems < $this->cf_pages) : ?>
				<a href="<?php echo get_pagenum_link($paged - 1); ?>">&lsaquo;<?php _e('Previous',$this->text_domain); ?></a>
				<?php endif; ?>

				<?php for ($i=1;$i <= $this->cf_pages;$i++) :
				if (1 != $this->cf_pages && ( !($i >= $paged + $this->cf_range + 1 || $i <= $paged - $this->cf_range - 1) || $this->cf_pages <= $showitems )):
				echo ($paged == $i) ? '<span class="current">' . $i . '</span>' : '<a href="' . get_pagenum_link($i) . '" class="inactive">' . $i . '</a>';
				endif;
				endfor;

				if ($paged < $this->cf_pages && $showitems < $this->cf_pages) : ?>
				<a href="<?php echo get_pagenum_link($paged + 1); ?>"><?php _e('Next',$this->text_domain); ?>&rsaquo;</a>
				<?php endif; ?>


				<?php if ($paged < $this->cf_pages - 1 &&  $paged + $this->cf_range - 1 < $this->cf_pages && $showitems < $this->cf_pages): ?>
				<a href="<?php echo get_pagenum_link($this->cf_pages); ?>"><?php _e('Last', $this->text_domain); ?>&raquo;</a>
				<?php endif; ?>

			</div> <!--end pagination-->
			<?php
			endif; // end 1 != $this->cf_pages

			endif;  //end if ( class_exists( PageNavi_Core ) )
			echo "</div>\n";
			endif; //end $wp_query->max_num_pages > 1 )
			$pagination = ob_get_contents();
			ob_end_clean();
			echo apply_filters( 'cf_pagination', $pagination );
		}
	}

	endif;

	?>