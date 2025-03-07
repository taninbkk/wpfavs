<?php
/**
 * Plugin Name.
 *
 * @package   Wpfavs_Admin
 * @author    Your Name <email@example.com>
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2014 Your Name or Company Name
 */

/**
 * Plugin class. This class should ideally be used to work with the
 * administrative side of the WordPress site.
 *
 * If you're interested in introducing public-facing
 * functionality, then refer to `class-plugin-name.php`
 *
 * @TODO: Rename this class to a proper name for your plugin.
 *
 * @package Wpfavs_Admin
 * @author  Your Name <email@example.com>
 */
class Wpfavs_Admin {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.0.0';
	
	/**
	 * API Url to do the remote calls
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	public $api_url = 'http://wpfavs.com/';

	/**
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	var $plugin_slug = 'wpfavs';

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Slug of the plugin screen.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 * Holds the api key entered
	 *
	 * @since 1.0.0
	 * 
	 * @var string
	 */
	var $api_key = '';

	/**
	 * Holds the api key response(transient)
	 *
	 * @since 1.0.0
	 * 
	 * @var string
	 */
	var $api_key_reponse = '';

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {



		// Load admin style sheet and JavaScript.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		// Add the options page and menu item.
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( realpath( dirname( __FILE__ ) ) ) . $this->plugin_slug . '.php' );
		add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

		//Ajax actions
		add_action( 'wp_ajax_wpfav_apikey', array( $this, 'wpfav_apikey_cb' ) );
		add_action( 'wp_ajax_wpfav_quickkey', array( $this, 'wpfav_quickkey_cb' ) );

		//load options
		$this->load_wpfav_options();

	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		/*
		 * @TODO :
		 *
		 * - Uncomment following lines if the admin class should only be available for super admins
		 */
		/* if( ! is_super_admin() ) {
			return;
		} */

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	private function load_wpfav_options() {

		if( $this->screen_check() ) {

			$this->api_key 			= get_option( $this->plugin_slug . 'wpfav_apikey' );
			$this->quick_key 		= get_option( $this->plugin_slug . 'wpfav_quickkey' );
			$this->api_key_response = unserialize( get_transient( $this->plugin_slug . 'wpfav_apikey_response') );
			//we update installed plugins
			if( !empty( $this->api_key_response ) )
				$this->populate_file_path();

		}
	}

	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @TODO:
	 *
	 * - Rename "Wpfavs" to the name your plugin
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {


		if ( $this->screen_check() ) {
			wp_enqueue_style( $this->plugin_slug .'-admin-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), self::VERSION );
		}

	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 *
	 * @TODO:
	 *
	 * - Rename "Wpfavs" to the name your plugin
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {

		
		if ( $this->screen_check() ) {
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), self::VERSION );
			wp_localize_script( $this->plugin_slug . '-admin-script', 'wpfavs', array('ajax_url' =>  admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'wpfav-nonce' ) ) );
		}

	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {


		$this->plugin_screen_hook_suffix = add_submenu_page(
			'tools.php',
			__( 'Wp Favs', $this->plugin_slug ),
			__( 'Wp Favs', $this->plugin_slug ),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'display_plugin_admin_page' )
		);

	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {

		include_once( 'views/header.php' );

		//If we are running an action, we are running the plugin lists
		if( isset( $_GET['action'] ) )
		{

			include_once( 'views/run-list.php' );
			
		} else {

			include_once( 'views/main.php' );

		}	

		include_once( 'views/footer.php' );
	}

	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {

		return array_merge(
			array(
				'settings' => '<a href="' . admin_url( 'tools.php?page=' . $this->plugin_slug ) . '">' . __( 'Settings', $this->plugin_slug ) . '</a>'
			),
			$links
		);

	}

	/**
	 * Ajax function that gets the api key and            
	 * do the remote call to retrieve the wpfavs lists          
	 *
	 * @since    1.0.0
	 */
	public function wpfav_apikey_cb() {
			
		$nonce = $_POST['nonce'];
        if ( ! wp_verify_nonce( $nonce, 'wpfav-nonce' ) )
        	die ( 'Wrong nonce!');

        //apikey
        $wpfav_apikey = $_POST['api_key'];

        // Data to send to the API
		$api_params = array(
			'api_key' 		=> $wpfav_apikey,
			'wpfav_action'	=> 'wpfav_get_lists',
		);

		// Call the API
		$response = wp_remote_get( add_query_arg( $api_params, $this->api_url), array( 'timeout' => 15, 'sslverify' => false ) );

		
		// Make sure there are no errors
		if ( is_wp_error( $response ) ) {
			$error_string = $result->get_error_message();
  			echo self::message_box( $error_string );
  			die();
		}

		// Decode response
		$response = json_decode( wp_remote_retrieve_body( $response ), TRUE );

		//check for api errors
		if( isset( $response['error'] ) ) {
  			echo self::message_box( $response['error'] );
  			die();
		} 

		// If we made it to here let's save it and load our table class
		update_option( $this->plugin_slug . 'wpfav_apikey', $wpfav_apikey );
		set_transient( $this->plugin_slug . 'wpfav_apikey_response', serialize($response), 30 * DAY_IN_SECONDS );

		self::print_table( $response );

		die();
	}
	
	/**
	 * Ajax function that gets the api key and            
	 * do the remote call to retrieve the wpfavs lists          
	 *
	 * @since    1.0.0
	 */
	public function wpfav_quickkey_cb() {
			
		$nonce = $_POST['nonce'];
        if ( ! wp_verify_nonce( $nonce, 'wpfav-nonce' ) )
        	die ( 'Wrong nonce!');

        //quickkey
        $wpfav_quickkey = $_POST['api_key'];

        // Data to send to the API
		$api_params = array(
			'api_key' 		=> $wpfav_quickkey,
			'wpfav_action'	=> 'wpfav_get_quick_list',
		);

		// Call the API
		$response = wp_remote_get( add_query_arg( $api_params, $this->api_url), array( 'timeout' => 15, 'sslverify' => false ) );


		// Make sure there are no errors
		if ( is_wp_error( $response ) ) {
			$error_string = $result->get_error_message();
  			echo self::message_box( $error_string );
  			die();
		}

		// Decode response
		$response = json_decode( wp_remote_retrieve_body( $response ), TRUE );

		//check for api errors
		if( isset( $response['error'] ) ) {
  			echo self::message_box( $response['error'] );
  			die();
		} 

		// If we made it to here let's save it and load our table class
		update_option( $this->plugin_slug . 'wpfav_quickkey', $wpfav_quickkey );

		self::print_table( $response );

		die();
	}

	/**
	 * Prints a wp table with all the wpfavs
	 * @param  array $columns columns that we are going to display
	 * @param  array $items   items that we are going to display
	 * @return void           prints the wp table
	 */
	public static function print_table ( $items ) {

		require_once( 'includes/class-wpfavs-table.php');

		$myList = new Wpfavs_Table( array('screen' => 'wpfavs' ) );

		$myList->prepare_items( $items );
		$myList->display(); 
	}

	/**
	 * Prints a wp table with all the plugins of the wpfav list
	 * @param  array $columns columns that we are going to display
	 * @param  array $items   items that we are going to display
	 * @return void           prints the wp table
	 */
	public static function print_plugins_table ( $items ) {

		require_once( 'includes/class-plugins-table.php');

		$myList = new Wpfavs_Plugins_Table( array('screen' => 'wpfavs' ) );

		$myList->prepare_items( $items );
		$myList->display(); 
	}

	/**
	 * Print wordpress boxes
	 * @param  string $type the type of box to display
	 * @param  string $text Text to be display in the box
	 * @return string       the box
	 */
	public static function message_box ( $text, $type = 'error' ) {
		return '<div id="message" class="' . $type . '"><p>' . $text . '</p></div>';
	}

	/**
	 * THANKS TO Thomas Griffin (thomasgriffinmedia.com) 
	 * from https://github.com/thomasgriffin/TGM-Plugin-Activation 
	 * for the following two functions
	 */
	
     /**
     * Set file_path key for each installed plugin.
     *
     * @since 1.0.0
     */
    protected function populate_file_path() {

        // Add file_path key for all plugins.
        foreach ( $this->api_key_response as $key => $wpfav ) {

        	foreach ( $wpfav['plugins'] as $p_key => $plugin ) {

            	$file_path = $this->_get_plugin_basename_from_slug( $plugin['slug'] );

            	$this->api_key_response[$key]['plugins'][$p_key]['file_path'] = $file_path;

            	if( empty( $file_path ) ) {

					$this->api_key_response[$key]['plugins'][$p_key]['status'] = 'not-installed';

				} elseif( is_plugin_active( $file_path ) ) {

					$this->api_key_response[$key]['plugins'][$p_key]['status'] = 'active';

				} else {

					$this->api_key_response[$key]['plugins'][$p_key]['status'] = 'inactive';
				}
        	}
        }

    }

    /**
     * Helper function to extract the file path of the plugin file from the
     * plugin slug, if the plugin is installed.
     *
     * @since 1.0.0
     *
     * @param string $slug Plugin slug (typically folder name) as provided by the developer.
     * @return string      Either file path for plugin if installed, or just the plugin slug.
     */
    protected function _get_plugin_basename_from_slug( $slug ) {
    	
    	if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}	

        $keys = array_keys( get_plugins() );

        foreach ( $keys as $key ) {
            if ( preg_match( '|^' . $slug .'/|', $key ) ) {
                return $key;
            }
        }

        return '';

    }
    /**	
     * We check that we are on the options page on our plugin
     * @return boolean True if we are in our plugin's page
     * @since 1.0.0
     */
    protected function screen_check() {


		if ( isset( $_GET['page'] ) && $this->plugin_slug == $_GET['page'] ) {
			return true;
		}	

		return false;
    }
}
