<?php
/**
 * Front-end Attachments
 *
 * @package   Front-end Attachments
 * @author    imath
 * @license   GPL-2.0+
 * @link      http://imathi.eu
 *
 * @buddypress-plugin
 * Plugin Name:       Front-end Attachments
 * Plugin URI:        https://github.com/imath/front-end-attachments
 * Description:       Another random Experiment...
 * Version:           1.0.0-alpha
 * Author:            imath
 * Author URI:        http://imathi.eu/
 * Text Domain:       front-end-attachments
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages/
 * GitHub Plugin URI: https://github.com/imath/front-end-attachments
 */

// Exit if accessed directly
defined( 'ABSPATH' ) or die;

if ( ! class_exists( 'Front_End_Attachments' ) ) :
/**
 * Main plugin Class
 */
class Front_End_Attachments {
	/**
	 * If you start me up..
	 */
	public static function start() {
		$bp = buddypress();

		if ( empty( $bp->core->front_end_attachments ) ) {
			$bp->core->front_end_attachments = new self;
		}

		return $bp->core->front_end_attachments;
	}

	/**
	 * Constructor method.
	 */
	public function __construct() {
		$this->setup_globals();
		$this->includes();
		$this->setup_hooks();
	}

	/**
	 * Set some globals
	 */
	private function setup_globals() {

		/** Plugin specific globals ***************************************************/

		$this->version    = '1.0.0-alpha';
		$this->domain     = 'front-end-attachments';
		$this->basename   = plugin_basename( __FILE__ );
		$this->plugin_dir = plugin_dir_path( __FILE__ );
		$this->plugin_url = plugin_dir_url( __FILE__ );
		$this->inc_dir    = trailingslashit( $this->plugin_dir . 'inc' );
		$this->inc_url    = trailingslashit( $this->plugin_url . 'inc' );
		$this->js_url     = trailingslashit( $this->inc_url . 'js'  );
		$this->css_url    = trailingslashit( $this->inc_url . 'css' );

		/** BuddyPress specific globals ***********************************************/

		// Required version
		$this->bp_version = '2.4.0';

		/** Plugin demo settings *****************************************************/
		$this->filter_query       =  true; // True to filter the attachments query
		$this->can                =  'contributor'; // member to use bp_map_meta_caps
		$this->use_bp_attachment  =  true; // true to use the BP Uploader UI
	}

	/**
	 * Checks BuddyPress version
	 */
	public function version_check() {
		// taking no risk
		if ( ! defined( 'BP_VERSION' ) ) {
			return false;
		}

		return version_compare( BP_VERSION, $this->bp_version, '>=' );
	}

	/**
	 * Include files
	 */
	private function includes() {
		require( $this->inc_dir . 'class-front-end-attachment.php' );
		require( $this->inc_dir . 'functions.php' );
	}

	/**
	 * Set Actions & filters
	 */
	private function setup_hooks() {
		/**
		 * Don't do anything if BuddyPress required version is not there
		 */
		if ( ! $this->version_check() ) {
			return;
		}

		// load the language..
		add_action( 'bp_init', array( $this, 'load_textdomain' ), 5 );

		// Register status
		add_action( 'bp_init', array( $this, 'register_status' ), 10 );

		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ), 10, 1 );
		add_action( 'wp_enqueue_scripts',    array( $this, 'register_scripts' ), 10, 1 );
	}

	/**
	 * Register a new post status
	 */
	public function register_status() {
		register_post_status( 'front_end_public', array(
			'label'                     => _x( 'Front end submitted', 'attachment status', 'front-end-attachments' ),
			'protected'                 => true,
			'show_in_admin_status_list' => false,
			'show_in_admin_all_list'    => false,
		) );
	}

	/**
	 * Register scripts
	 */
	public function register_scripts() {
		// Register the script
		wp_register_script(
			'fe-attachments-script',
			$this->js_url . 'script.js',
			array(),
			$this->version,
			true
		);

		wp_register_style(
			'fe-attachments-style',
			$this->css_url . 'style.css',
			array( 'dashicons' ),
			$this->version
		);

		if ( function_exists( 'wp_idea_stream_is_addnew' ) && wp_idea_stream_is_addnew() ) {
			wp_add_inline_style( 'wp-idea-stream-style', '
				#wp-wp_idea_stream_the_content-wrap {
					border: none;
				}
				#wp-wp_idea_stream_the_content-editor-container {
					border: 1px solid #ccc;
				}

				#wp-wp_idea_stream_the_content-media-buttons {
					padding: 10px 0;
				}

				#wp-wp_idea_stream_the_content-media-buttons #insert-media-button {
					padding: 0 10px 1px;
					border-radius: 0;
				}
			' );
		}
	}

	/**
	 * Loads the translation files if any
	 */
	public function load_textdomain() {
		// Traditional WordPress plugin locale filter
		$locale = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );
		load_plugin_textdomain( $this->domain, false, dirname( $this->basename ) . '/languages' );
	}
}
add_action( 'bp_include', array( 'Front_End_Attachments', 'start' ) );

endif;
