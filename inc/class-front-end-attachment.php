<?php
/**
 * Front-end Attachment Class
 */

// Exit if accessed directly
defined( 'ABSPATH' ) or die;

if ( class_exists( 'BP_Attachment' ) ) :
/**
 * The Attachments class
 *
 * @see https://codex.buddypress.org/plugindev/bp_attachment/
 */
class Front_End_Attachment extends BP_Attachment {
	/**
	 * The constuctor
	 */
	public function __construct() {
		parent::__construct( array(
			'action'               => 'front_end_upload',
			'file_input'           => 'front-end-upload',
			'base_dir'             => 'front-end',
			'required_wp_files'    => array( 'file', 'image' ),
		) );
	}

	/**
	 * Set the directory when uploading a file.
	 */
	public function upload_dir_filter( $upload_dir = array() ) {

		// Set the subdir.
		$subdir  = '/' . bp_loggedin_user_id();

		/**
		 * Filters the upload directory..
		 */
		return apply_filters( 'front_end_attachment_upload_dir', array(
			'path'    => $this->upload_path . $subdir,
			'url'     => $this->url . $subdir,
			'subdir'  => $subdir,
			'basedir' => $this->upload_path,
			'baseurl' => $this->url,
			'error'   => false
		), $upload_dir );
	}

	/**
	 * Build script datas for the Uploader UI
	 */
	public function script_data() {
		// Get default script data
		$script_data = parent::script_data();

		$script_data['bp_params'] = array(
			'item_id' => bp_loggedin_user_id(),
		);

		// Include our specific css
		$script_data['extra_css'] = array( 'fe-attachments-style' );

		// Include our specific js
		$script_data['extra_js']  = array( 'fe-attachments-script' );

		return apply_filters( 'front_end_attachment_script_data', $script_data );
	}
}
endif;
