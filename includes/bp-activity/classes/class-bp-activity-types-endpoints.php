<?php
defined( 'ABSPATH' ) || exit;

/**
 * Activity endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Activity_Types_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'buddypress/v1';
		$this->rest_base = 'types';
	}

	/**
	 * Register the plugin routes.
	 *
	 * @since 0.1.0
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => $this->get_collection_params(),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );
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
			'title'      => 'types',
			'type'       => 'object',

			'properties' => array(
				'type' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Tag name.', 'buddypress' ),
					'type'        => 'string',
				),
				'name' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Display name.', 'buddypress' ),
					'type'        => 'string',
				),
			)
		);

		return $schema;
	}

	/**
	 * Get the query params for collections of plugins.
	 *
	 * @since 0.1.0
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params                       = parent::get_collection_params();
		$params['context']['default'] = 'view';

		$params['scope'] = array(
			'description'       => __( 'Filter by scope.', 'buddypress' ),
			'default'           => 'just-me',
			'type'              => 'string',
			'enum'				=> array('just-me', 'friends', 'group'),
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

		return $params;
	}

	/**
	 * Retrieve activities.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Request List of activity object data.
	 */
	public function get_items( $request ) {
		if (isset($request['scope']) && $request['scope'] == 'group') {
			// get the list of default group tags 
			$retval = array(
				array(
					'type' => 'tag_zaji',
					'name' => '杂记'
				),
			);	
		} else {
			$retval = array(
				array(
					'type' => 'tag_zaji',
					'name' => '杂记'
				),
				array(
					'type' => 'tag_origin',
					'name' => '原创'
				),
				array(
					'type' => 'tag_food',
					'name' => '美食'
				),
				array(
					'type' => 'tag_trip',
					'name' => '旅游'
				),
				array(
					'type' => 'tag_finance',
					'name' => '财务'
				),
				array(
					'type' => 'tag_others',
					'name' => '其他'
				),
			);
		}
		
		return rest_ensure_response( $retval );
	}

	/**
	 * Check if a given request has access to activity items.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check( $request ) {
		// TODO: handle private activities etc
		return true;
	}

	/**
	 * Check if a given request has access create users
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return boolean
	 */
	public function create_item_permissions_check( $request ) {
		/*if ( ! current_user_can( 'create_users' ) ) {
			return new WP_Error( 'rest_cannot_create_user', __( 'Sorry, you are not allowed to create resource.' ), array( 'status' => rest_authorization_required_code() ) );
		}*/
		return true;
	}

	/**
	 * Prepares activity data for return as an object.
	 *
	 * @since 0.1.0
	 *
	 * @param stdClass $activity Activity data.
	 * @param WP_REST_Request $request
	 * @param boolean $is_raw Optional, not used. Defaults to false.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $activity, $request, $is_raw = false ) {
		$data = array(
			'author_id'             => $activity->user_id,
			'author_name'           => $activity->display_name,
			'avatar_url'            => bp_core_fetch_avatar('html=false&item_id=' . $activity->user_id),
			'component'             => $activity->component,
			'content'               => $activity->content,
			'date'                  => $this->prepare_date_response( $activity->date_recorded ),
			'id'                    => $activity->id,
			'link'                  => $activity->primary_link,
			'parent'                => $activity->type === 'activity_comment' ? $activity->item_id : 0,
			'prime_association'     => $activity->item_id,
			'secondary_association' => $activity->secondary_item_id,
			'status'                => $activity->is_spam ? 'spam' : 'published',
			'title'                 => $activity->action,
			'type'                  => activity_type_to_chinese($activity->type),
		);

		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $activity ) );

		/**
		 * Filter an activity value returned from the API.
		 *
		 * @param array           $response
		 * @param WP_REST_Request $request Request used to generate the response.
		 */
		return apply_filters( 'rest_prepare_buddypress_activity_value', $response, $request );
	}
}
