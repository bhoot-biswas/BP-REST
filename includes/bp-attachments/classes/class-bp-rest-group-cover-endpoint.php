<?php
/**
 * BP REST: BP_REST_Attachments_Group_Cover_Endpoint class
 *
 * @package BuddyPress
 * @since 0.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Group Cover endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Attachments_Group_Cover_Endpoint extends WP_REST_Controller {

	/**
	 * Reuse some parts of the BP_REST_Groups_Endpoint class.
	 *
	 * @since 0.1.0
	 *
	 * @var $groups_endpoint
	 */
	protected $groups_endpoint;

	/**
	 * Hold the group ID.
	 *
	 * @since 0.1.0
	 *
	 * @var $group_id
	 */
	protected $group_id;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace       = bp_rest_namespace() . '/' . bp_rest_version();
		$this->rest_base       = buddypress()->groups->id;
		$this->groups_endpoint = new BP_REST_Groups_Endpoint();
	}

	/**
	 * Register the component routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<group_id>[\d]+)/cover', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => $this->get_item_collection_params(),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_item' ),
				'permission_callback' => array( $this, 'delete_item_permissions_check' ),
			),
			'schema' => array( $this, 'get_item_schema' ),
		) );
	}

	/**
	 * Fetch an existing group cover.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_item( $request ) {
		$cover = bp_core_fetch_avatar( array(
			'object'  => 'group',
			'type'    => $request['type'],
			'item_id' => (int) $this->group_id,
			'html'    => (bool) $request['html'],
			'alt'     => $request['alt'],
		) );

		if ( ! $cover ) {
			return new WP_Error( 'bp_rest_attachments_group_cover_no_image',
				__( 'Sorry, there was a problem fetching this group cover.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $cover, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group cover is fetched via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param string            $cover    The group cover.
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  The request sent to the API.
		 */
		do_action( 'bp_rest_attachments_group_cover_get_item', $cover, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to get a group cover.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function get_item_permissions_check( $request ) {
		$retval = true;

		/* if ( ! is_user_logged_in() ) {
			$retval = new WP_Error( 'bp_rest_authorization_required',
				__( 'Sorry, you need to be logged in to access this group cover.', 'buddypress' ),
				array(
					'status' => rest_authorization_required_code(),
				)
			);
		} */

		$group = $this->groups_endpoint->get_group_object( $request['group_id'] );

		if ( true === $retval && empty( $group->id ) ) {
			$retval = new WP_Error( 'bp_rest_group_invalid_id',
				__( 'Invalid group id.', 'buddypress' ),
				array(
					'status' => 404,
				)
			);
		}

		// Set group_id globally.
		$this->group_id = $group->id;

		/* if ( true === $retval && current_user_can( 'bp_moderate' ) ) {
			$retval = true;
		} else {
			if ( true === $retval && ! groups_is_user_admin( bp_loggedin_user_id(), $this->group_id ) ) {
				$retval = new WP_Error( 'bp_rest_authorization_required',
					__( 'Sorry, you cannot access this group cover.', 'buddypress' ),
					array(
						'status' => rest_authorization_required_code(),
					)
				);
			} */

		/* 	if ( true === $retval ) {
				$retval = true;
			}
		} */

		/**
		 * Filter the group cover `get_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_attachments_group_cover_get_item_permissions_check', $retval, $request );
	}

	/**
	 * Upload a group cover.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {

		// Get the image file via $_FILES.
		$files = $request->get_file_params();

		if ( empty( $files ) ) {
			return new WP_Error( 'bp_rest_attachments_group_cover_no_image_file',
				__( 'Sorry, you need an image file to upload.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		// Upload the avatar.
		$cover = $this->upload_group_cover_from_file( $files );
		if ( is_wp_error( $cover ) ) {
			return $cover;
		}

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $cover, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group cover is uploaded via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param stdClass          $cover    The group cover object.
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  The request sent to the API.
		 */
		do_action( 'bp_rest_attachments_group_cover_create_item', $cover, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to upload a group cover.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		$retval = $this->get_item_permissions_check( $request );

		/**
		 * Filter the group cover `create_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_attachments_group_cover_create_item_permissions_check', $retval, $request );
	}

	/**
	 * Delete an existing group cover.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_item( $request ) {
		$deleted = bp_core_delete_existing_avatar( array(
			'object'  => 'group',
			'item_id' => (int) $this->group_id,
		) );

		if ( ! $deleted ) {
			return new WP_Error( 'bp_rest_attachments_group_cover_delete_failed',
				__( 'Sorry, there was a problem deleting this group cover.', 'buddypress' ),
				array(
					'status' => 500,
				)
			);
		}

		$cover = bp_core_fetch_avatar(
			array(
				'object'  => 'group',
				'item_id' => $this->group_id,
				'html'    => false,
				'type'    => 'full',
			)
		);

		$retval = array(
			$this->prepare_response_for_collection(
				$this->prepare_item_for_response( $cover, $request )
			),
		);

		$response = rest_ensure_response( $retval );

		/**
		 * Fires after a group cover is deleted via the REST API.
		 *
		 * @since 0.1.0
		 *
		 * @param string            $cover    The default group cover.
		 * @param WP_REST_Response  $response The response data.
		 * @param WP_REST_Request   $request  The request sent to the API.
		 */
		do_action( 'bp_rest_attachments_group_cover_delete_item', $cover, $response, $request );

		return $response;
	}

	/**
	 * Checks if a given request has access to delete a group cover.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return bool|WP_Error
	 */
	public function delete_item_permissions_check( $request ) {
		$retval = $this->get_item_permissions_check( $request );

		/**
		 * Filter the group cover `delete_item` permissions check.
		 *
		 * @since 0.1.0
		 *
		 * @param bool|WP_Error   $retval  Returned value.
		 * @param WP_REST_Request $request The request sent to the API.
		 */
		return apply_filters( 'bp_rest_attachments_group_cover_delete_item_permissions_check', $retval, $request );
	}

	/**
	 * Prepares cover data to return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass|string $cover   Cover object or string with url or image with html.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $cover, $request ) {
		if ( is_string( $cover ) ) {
			$data = array(
				'image' => $cover,
			);
		} else {
			$data = array(
				'full'  => $cover->full,
				'thumb' => $cover->thumb,
			);
		}

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );

		/**
		 * Filter a group cover value returned from the API.
		 *
		 * @since 0.1.0
		 *
		 * @param WP_REST_Response  $response Response.
		 * @param WP_REST_Request   $request  Request used to generate the response.
		 */
		return apply_filters( 'bp_rest_attachments_group_cover_prepare_value', $response, $request );
	}

	/**
	 * Group cover upload from File.
	 *
	 * @since 0.1.0
	 *
	 * @param array $files $_FILES superglobal.
	 * @return stdClass|WP_Error
	 */
	protected function upload_group_cover_from_file( $files ) {

		// Setup some variables.
		$bp                = buddypress();
		$avatar_attachment = $this->avatar_attachment_instance();
		$group_id          = $this->group_id;
		$bp->groups->id    = $group_id;

		// Needed to avoid 'Invalid form submission' error.
		$_POST['action'] = $avatar_attachment->action;
		$avatar_original = $avatar_attachment->upload( $files, 'groups_avatar_upload_dir' );

		// Bail early in case of an error.
		if ( ! empty( $avatar_original['error'] ) ) {
			return new WP_Error( 'bp_rest_attachments_group_cover_upload_error',
				sprintf( __( 'Upload failed! Error was: %s.', 'buddypress' ), $avatar_original['error'] ),
				array(
					'status' => 500,
				)
			);
		}

		// Get image and bail early if there is an error.
		$image_file = $this->resize( $avatar_original['file'] );
		if ( is_wp_error( $image_file ) ) {
			return $image_file;
		}

		// If the uploaded image is smaller than the "full" dimensions, throw a warning.
		if ( $avatar_attachment->is_too_small( $image_file ) ) {
			return new WP_Error( 'bp_rest_attachments_group_cover_error',
				sprintf(
					__( 'You have selected an image that is smaller than recommended. For best results, upload a picture larger than %d x %d pixels.', 'buddypress' ),
					bp_core_avatar_full_width(),
					bp_core_avatar_full_height()
				),
				array(
					'status' => 500,
				)
			);
		}

		// Delete existing image if one exists.
		$this->delete_existing_image( $group_id );

		// Crop the profile photo accordingly and bail early in case of an error.
		$cropped = $this->crop_image( $image_file, $group_id );
		if ( is_wp_error( $cropped ) ) {
			return $cropped;
		}

		// Build response object.
		$avatar_object = new stdClass();
		foreach ( [ 'full', 'thumb' ] as $key_type ) {

			// Update path with an url.
			$url = str_replace( bp_core_avatar_upload_path(), '', $cropped[ $key_type ] );

			// Set image url to its size/type.
			$avatar_object->{$key_type} = bp_core_avatar_url() . $url;
		}

		return $avatar_object;
	}

	/**
	 * Resize image.
	 *
	 * @since 0.1.0
	 *
	 * @param string $file Image to resize.
	 * @return string|WP_Error
	 */
	protected function resize( $file ) {
		$bp          = buddypress();
		$upload_path = bp_core_avatar_upload_path();

		if ( ! isset( $bp->avatar_admin ) ) {
			$bp->avatar_admin = new stdClass();
		}

		// The Avatar UI available width.
		$ui_available_width = 0;

		// Try to set the ui_available_width using the avatar_admin global.
		if ( isset( $bp->avatar_admin->ui_available_width ) ) {
			$ui_available_width = $bp->avatar_admin->ui_available_width;
		}

		$resized = $this->avatar_attachment_instance()->shrink( $file, $ui_available_width );

		// We only want to handle one image after resize.
		if ( empty( $resized ) ) {
			$image_file = $file;
			$img_dir    = str_replace( $upload_path, '', $file );
		} else {
			$image_file = $resized['path'];
			$img_dir    = str_replace( $upload_path, '', $resized['path'] );
			@unlink( $file );
		}

		// Check for WP_Error on what should be an image.
		if ( is_wp_error( $img_dir ) ) {
			return new WP_Error( 'bp_rest_attachments_group_cover_upload_error',
				sprintf( __( 'Upload failed! Error was: %s', 'buddypress' ), $img_dir->get_error_message() ),
				array(
					'status' => 500,
				)
			);
		}

		return $image_file;
	}

	/**
	 * Crop image.
	 *
	 * @since 0.1.0
	 *
	 * @param string $image_file Image to crop.
	 * @param int    $group_id  Group ID.
	 * @return array|WP_Error
	 */
	protected function crop_image( $image_file, $group_id ) {
		$image          = getimagesize( $image_file );
		$avatar_to_crop = str_replace( bp_core_avatar_upload_path(), '', $image_file );

		// Get avatar full width and height.
		$full_height = bp_core_avatar_full_height();
		$full_width  = bp_core_avatar_full_width();

		// Default cropper coordinates.
		// Smaller than full-width: cropper defaults to entire image.
		if ( $image[0] < $full_width ) {
			$crop_left  = 0;
			$crop_right = $image[0];

		// Less than 2x full-width: cropper defaults to full-width.
		} elseif ( $image[0] < ( $full_width * 2 ) ) {
			$padding_w  = round( ( $image[0] - $full_width ) / 2 );
			$crop_left  = $padding_w;
			$crop_right = $image[0] - $padding_w;

		// Larger than 2x full-width: cropper defaults to 1/2 image width.
		} else {
			$crop_left  = round( $image[0] / 4 );
			$crop_right = $image[0] - $crop_left;
		}

		// Smaller than full-height: cropper defaults to entire image.
		if ( $image[1] < $full_height ) {
			$crop_top    = 0;
			$crop_bottom = $image[1];

		// Less than double full-height: cropper defaults to full-height.
		} elseif ( $image[1] < ( $full_height * 2 ) ) {
			$padding_h   = round( ( $image[1] - $full_height ) / 2 );
			$crop_top    = $padding_h;
			$crop_bottom = $image[1] - $padding_h;

		// Larger than 2x full-height: cropper defaults to 1/2 image height.
		} else {
			$crop_top    = round( $image[1] / 4 );
			$crop_bottom = $image[1] - $crop_top;
		}

		add_filter( 'bp_attachments_current_user_can', '__return_true' );

		// Crop the image.
		$cropped = $this->avatar_attachment_instance()->crop(
			array(
				'object'        => 'group',
				'avatar_dir'    => 'group-avatars',
				'item_id'       => $group_id,
				'original_file' => $avatar_to_crop,
				'crop_w'        => $crop_right,
				'crop_h'        => $crop_bottom,
				'crop_x'        => $crop_left,
				'crop_y'        => $crop_top,
			)
		);

		remove_filter( 'bp_attachments_current_user_can', '__return_false' );

		// Check for errors.
		if ( empty( $cropped['full'] ) || empty( $cropped['thumb'] ) || is_wp_error( $cropped['full'] ) || is_wp_error( $cropped['thumb'] ) ) {
			return new WP_Error( 'bp_rest_attachments_group_cover_crop_error',
				sprintf( __( 'There was a problem cropping the group cover.', 'buddypress' ) ),
				array(
					'status' => 500,
				)
			);
		}

		return $cropped;
	}

	/**
	 * Delete group's existing cover if one exists.
	 *
	 * @since 0.1.0
	 *
	 * @param int $group_id Group ID.
	 * @return void
	 */
	protected function delete_existing_image( $group_id ) {
		$object = 'group';

		// Get existing avatar.
		$existing_avatar = bp_core_fetch_avatar(
			array(
				'object'  => $object,
				'item_id' => $group_id,
				'html'    => false,
			)
		);

		// Check if the avatar exists before deleting.
		if ( ! empty( $existing_avatar ) ) {
			bp_core_delete_existing_avatar(
				array(
					'object'  => $object,
					'item_id' => $group_id,
				)
			);
		}
	}

	/**
	 * Return an instance of the BP_Attachment_Avatar class.
	 *
	 * @since 0.1.0
	 *
	 * @return BP_Attachment_Avatar
	 */
	protected function avatar_attachment_instance() {
		return new BP_Attachment_Avatar();
	}

	/**
	 * Get the plugin schema, conforming to JSON Schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'cover',
			'type'       => 'object',
			'properties' => array(
				'full'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Full size of the image file.', 'buddypress' ),
					'type'        => 'string',
				),
				'thumb'             => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Thumb size of the image file.', 'buddypress' ),
					'type'        => 'string',
				),
			),
		);

		return $schema;
	}

	/**
	 * Get the query params for the `get_item`.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_item_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['type'] = array(
			'description'       => __( 'Whether you would like the `full` or the smaller `thumb`.', 'buddypress' ),
			'default'           => 'thumb',
			'type'              => 'string',
			'enum'              => array( 'thumb', 'full' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['html'] = array(
			'description'       => __( 'Whether to return an <img> HTML element, vs a raw URL to a group cover.', 'buddypress' ),
			'default'           => false,
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['alt'] = array(
			'description'       => __( 'The alt attribute for the <img> element.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}
}
