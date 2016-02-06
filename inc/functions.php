<?php
/**
 * Functions
 */

// Exit if accessed directly
defined( 'ABSPATH' ) or die;

/**
 * Allow contributors to upload Media
 */
function fe_attachments_map_meta_caps( $caps, $cap, $user_id, $args ) {
	if ( 'upload_files' === $cap && 'contributor' === buddypress()->core->front_end_attachments->can ) {
		$caps = array( 'edit_posts' );
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'fe_attachments_map_meta_caps', 10, 4 );

/**
 * Allow any user to upload BP Attachment
 */
function fe_attachments_bp_map_meta_caps( $caps, $cap, $user_id, $args ) {
	if ( 'upload_files' === $cap && 'member' === buddypress()->core->front_end_attachments->can && is_user_logged_in() ) {
		$caps = array( 'exist' );
	}

	return $caps;
}
add_filter( 'bp_map_meta_caps', 'fe_attachments_bp_map_meta_caps', 10, 4 );

/**
 * Filter the attachments query.. to display loggedin user's media only
 * or attachments having the front_end_public status.
 *
 * It's for a demo, do not use this on a live server :)
 */
function fe_attachments_query_attachments_args( $query = array() ) {
	if ( ! isset( $query['post_type'] ) || 'attachment' !== $query['post_type'] || ! buddypress()->core->front_end_attachments->filter_query ) {
		return $query;
	}

	if ( isset( $query['post_mime_type'] ) ) {
		if ( 'fe_mine' === $query['post_mime_type'] || 'fe_attachments' === $query['post_mime_type'] ) {
			$type                    = $query['post_mime_type'];
			$query['post_mime_type'] = '';

			if ( 'fe_mine' === $type ) {
				$uploaded_by_me = true;
			} else {
				$query['post_status'] = 'front_end_public';
			}
		}
	}

	// Only display contributor's media or other roles ones if requested
	if ( ! current_user_can( 'publish_posts' ) || ! empty( $uploaded_by_me ) ) {
		$query['author'] = get_current_user_id();
	}

	return $query;
}
add_filter( 'ajax_query_attachments_args', 'fe_attachments_query_attachments_args', 10, 1 );
add_filter( 'request',                     'fe_attachments_query_attachments_args', 10, 1 );

function fe_attachments_media_view_settings( $settings, $post_id ) {
	if ( ! buddypress()->core->front_end_attachments->filter_query ) {
		return $settings;
	}

	if ( ! empty( $settings['mimeTypes'] ) && is_array( $settings['mimeTypes'] ) && current_user_can( 'publish_posts' ) ) {
		$custom_stati = array(
			'fe_mine' => __( 'Uploaded by me', 'front-end-attachments' ),
		);

		if ( buddypress()->core->front_end_attachments->use_bp_attachment ) {
			$custom_stati['fe_attachments'] = __( 'Front end submitted', 'front-end-attachments' );
		}

		$settings['mimeTypes'] = array_merge( $settings['mimeTypes'], $custom_stati );
	}

	return $settings;
}
add_filter( 'media_view_settings', 'fe_attachments_media_view_settings', 10, 2 );

/**
 * Assuming you have defined your attachment class
 */
function front_end_attachments_handle_upload() {
	$is_html4 = false;
	if ( ! empty( $_POST['html4' ] ) ) {
		$is_html4 = true;
	}

	if ( empty( $_POST['bp_params'] ) || empty( $_POST['bp_params']['item_id'] ) ) {
		return;
	}

	// Init the BuddyPress parameters
	$bp_params = (array) $_POST['bp_params'];

	// Check the nonce
	check_admin_referer( 'bp-uploader' );

	// Capability check
	if ( ! current_user_can( 'upload_files' ) ) {
		bp_attachments_json_response( false, $is_html4 );
	}

	// Let's get ready to upload a new front end attachment
	$front_end_attachment = new Front_End_Attachment();
	$file = $front_end_attachment->upload( $_FILES );

	/**
	 * If there's an error during the upload process
	 * stop..
	 */
	if ( ! empty( $result['error'] ) ) {
		bp_attachments_json_response( false, $is_html4 );
	} else {
		$name_parts = pathinfo( $file['file'] );

		// Construct the attachment array
		$attachment = array(
			'post_mime_type' => $file['type'],
			'guid'           => $file['url'],
			'post_title'     => $name_parts['filename'],
			'post_status'    => 'front_end_public',
		);

		// Force the status of the Attachment's post type to be our custom one
		add_filter( 'wp_insert_attachment_data', 'front_end_attachments_set_status', 10, 2 );

		// Save the data
		$id = wp_insert_attachment( $attachment, $file['file'], 0 );

		// Remove the filter
		remove_filter( 'wp_insert_attachment_data', 'front_end_attachments_set_status', 10, 2 );

		if ( ! is_wp_error( $id ) ) {
			wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file['file'] ) );

			// Finally return file to the editor
			bp_attachments_json_response( true, $is_html4, array(
				'name' => esc_html( $name_parts['filename'] ),
				'icon' => wp_get_attachment_thumb_url( $id ),
				'url'  => esc_url_raw( $file['url'] ),
			) );
		}
	}
}
add_action( 'wp_ajax_front_end_upload', 'front_end_attachments_handle_upload' );

/**
 * Use a specific status to avoid the attachment to be listed into the Media Library
 */
function front_end_attachments_set_status( $data, $postarr ) {
	if ( isset( $postarr['post_status'] ) && 'front_end_public' === $postarr['post_status'] ) {
		$data['post_status'] = 'front_end_public';
	}

	return $data;
}

/**
 * BP Attachment Editor
 */
function front_end_attachments_editor() {
	// Bail if current user can't use it and if not in front end
	if ( ! current_user_can( 'upload_files' ) || ! buddypress()->core->front_end_attachments->use_bp_attachment ) {
		return;
	}

	// Enqueue Thickbox
	wp_enqueue_style ( 'thickbox' );
	wp_enqueue_script( 'thickbox' );

	// Temporary filters to add custom strings and settings
	add_filter( 'bp_attachments_get_plupload_l10n',             'front_end_attachments_editor_strings',  10, 1 );
	add_filter( 'bp_attachments_get_plupload_default_settings', 'front_end_attachments_editor_settings', 10, 1 );

	// Enqueue BuddyPress attachments scripts
	bp_attachments_enqueue_scripts( 'Front_End_Attachment' );

	// Remove the temporary filters
	remove_filter( 'bp_attachments_get_plupload_l10n',             'front_end_attachments_editor_strings',  10, 1 );
	remove_filter( 'bp_attachments_get_plupload_default_settings', 'front_end_attachments_editor_settings', 10, 1 );

	$url = remove_query_arg( array_keys( $_REQUEST ) );
	?>
	<a href="<?php echo esc_url( $url );?>#TB_inline?inlineId=front-end-attachments-modal" title="<?php esc_attr_e( 'Add file', 'front-end-attachments' );?>" id="front-end-attachments-btn" class="thickbox button">
		<?php echo esc_html_e( 'Add File', 'front-end-attachments' ); ?>
	</a>
	<div id="front-end-attachments-modal" style="display:none;">
		<?php /* Markup for the uploader */ ?>
			<div class="fe-attachments-uploader"></div>
			<div class="fe-attachments-uploader-status"></div>

		<?php bp_attachments_get_template_part( 'uploader' );
		/* Markup for the uploader */ ?>
	</div>
	<?php
}
add_action( 'wp_idea_stream_media_buttons', 'front_end_attachments_editor' );

/**
 * Add new strings
 */
function front_end_attachments_editor_strings( $strings = array() ) {
	$strings['fe_attachments'] = array(
		'insert' => esc_html__( 'Add File', 'front-end-attachments' ),
	);

	return $strings;
}

/**
 * Disable multiple uploads
 */
function front_end_attachments_editor_settings( $settings = array() ) {
	if ( isset( $settings['defaults'] ) && ! isset( $settings['defaults']['multi_selection'] ) ) {
		$settings['defaults']['multi_selection'] = false;
	}

	return $settings;
}

/**
 * Add plugin's template folder to WP Idea Stream template stack
 */
function front_end_attachments_add_wp_idea_stream_templates( $stack = array() ) {
	// Just before WP Idea Stream
	$stack[99] = trailingslashit( buddypress()->core->front_end_attachments->inc_dir . 'wp-idea-stream' );

	return $stack;
}
add_filter( 'wp_idea_stream_template_paths', 'front_end_attachments_add_wp_idea_stream_templates', 10, 1 );

/**
 * Specific WP Idea Stream Editor to allow the media buttons
 */
function front_end_attachments_editor_ideas_the_editor() {
	$args = array(
		'textarea_name' => 'wp_idea_stream[_the_content]',
		'wpautop'       => true,
		'media_buttons' => ! buddypress()->core->front_end_attachments->use_bp_attachment,
		'editor_class'  => 'wp-idea-stream-tinymce',
		'textarea_rows' => get_option( 'default_post_edit_rows', 10 ),
		'teeny'         => false,
		'dfw'           => false,
		'tinymce'       => true,
		'quicktags'     => false
	);

	// Temporarly filter the editor
	add_filter( 'mce_buttons', 'wp_idea_stream_teeny_button_filter', 10, 1 );
	?>

	<label for="wp_idea_stream_the_content"><?php esc_html_e( 'Description', 'wp-idea-stream' ) ;?> <span class="required">*</span></label>

	<?php
	do_action( 'wp_idea_stream_media_buttons' );
	wp_editor( wp_idea_stream_ideas_get_editor_content(), 'wp_idea_stream_the_content', $args );

	remove_filter( 'mce_buttons', 'wp_idea_stream_teeny_button_filter', 10, 1 );
}
