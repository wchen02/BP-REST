<?php
defined( 'ABSPATH' ) || exit;

/**
 * Activity endpoints.
 *
 * @since 0.1.0
 */
class BP_REST_Activity_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		$this->namespace = 'buddypress/v1';
		$this->rest_base = buddypress()->activity->id;
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
			array(
				'methods'         => WP_REST_Server::CREATABLE,
				'callback'        => array( $this, 'create_item' ),
				'permission_callback' => array( $this, 'create_item_permissions_check' ),
				'args'            => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
			),
			'schema' => array( $this, 'get_public_item_schema' ),
		) );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_item_permissions_check' ),
				'args'                => array(
					'context' => $this->get_context_param( array( 'default' => 'view' ) ),
				),
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
			'title'      => 'activity',
			'type'       => 'object',

			'properties' => array(
				'id' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'A unique alphanumeric ID for the object.', 'buddypress' ),
					'readonly'    => true,
					'type'        => 'integer',
				),

				'visibility'=> array(
					'context'     => array('edit' ),
					'description' => __( 'Set activity visibility', 'buddypress' ),
					'type'        => 'string',

					// onlyme, friends, grouponly
					'enum'        => array_keys(buddyboss_wall_get_visibility_lists()),
				),


				'prime_association' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of some other object primarily associated with this one.', 'buddypress' ),
					'type'        => 'integer',
				),

				'secondary_association' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID of some other object also associated with this one.', 'buddypress' ),
					'type'        => 'integer',
				),

				'author' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The ID for the creator of the object.', 'buddypress' ),
					'type'        => 'integer',
				),

				'link' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The permalink to this object on the site.', 'buddypress' ),
					'format'      => 'url',
					'type'        => 'string',
				),

				'component' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The BuddyPress component the object relates to.', 'buddypress' ),
					'type'        => 'string',
					'enum'        => bp_core_get_packaged_component_ids(),
				),

				'type' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'The activity type of the object.', 'buddypress' ),
					'type'        => 'string',
					// disable because our site hacks to use unregistered types
					//'enum'        => array_keys( bp_activity_get_types() ),
				),

				'title' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'HTML title of the object.', 'buddypress' ),
					'type'        => 'string',
				),

				'content' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'HTML content of the object.', 'buddypress' ),
					'type'        => 'string',
				),

				'date' => array(
					'description' => __( "The date the object was published, in the site's timezone.", 'buddypress' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view', 'edit' ),
				),

				'status' => array(
					'context'     => array( 'view', 'edit' ),
					'description' => __( 'Whether the object has been marked as spam or not.', 'buddypress' ),
					'type'        => 'string',
					'enum'        => array( 'published', 'spam' ),
				),

				'parent' => array(
					'description'  => __( 'The ID of the parent of the object.', 'buddypress' ),
					'type'         => 'integer',
					'context'      => array( 'view', 'edit' ),
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

		$params['exclude'] = array(
			'description'       => __( 'Ensure result set excludes specific IDs.', 'buddypress' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['include'] = array(
			'description'       => __( 'Ensure result set includes specific IDs.', 'buddypress' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['order'] = array(
			'description'       => __( 'Order sort attribute ascending or descending.', 'buddypress' ),
			'type'              => 'string',
			'default'           => 'desc',
			'enum'              => array( 'asc', 'desc' ),
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['after'] = array(
			'description'       => __( 'Limit result set to items published after a given ISO8601 compliant date.', 'buddypress' ),
			'type'              => 'string',
			'format'            => 'date-time',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['per_page'] = array(
			'description'       => __( 'Maximum number of results returned per result set.', 'buddypress' ),
			'default'           => 20,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['page'] = array(
			'description'       => __( 'Offset the result set by a specific number of pages of results.', 'buddypress' ),
			'default'           => 1,
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['author'] = array(
			'description'       => __( 'Limit result set to items created by specific authors.', 'buddypress' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['status'] = array(
			'default'           => 'published',
			'description'       => __( 'Limit result set to items with a specific status.', 'buddypress' ),
			'type'              => 'string',
			'enum'              => array( 'published', 'spam' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['primary_id'] = array(
			'description'       => __( 'Limit result set to items with a specific prime assocation.', 'buddypress' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['secondary_id'] = array(
			'description'       => __( 'Limit result set to items with a specific secondary assocation.', 'buddypress' ),
			'type'              => 'array',
			'default'           => array(),
			'sanitize_callback' => 'wp_parse_id_list',
		);

		$params['component'] = array(
			'description'       => __( 'Limit result set to items with a specific BuddyPress component.', 'buddypress' ),
			'type'              => 'string',
			'enum'              => bp_core_get_packaged_component_ids(),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['type'] = array(
			'description'       => __( 'Limit result set to items with a specific activity type.', 'buddypress' ),
			'type'              => 'string',
			// disable because our site hacks to use unregistered types
			//'enum'              => array_keys( bp_activity_get_types() ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);

		$params['search'] = array(
			'description'       => __( 'Limit result set to items that match this search query.', 'buddypress' ),
			'default'           => '',
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);

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
		$args = array(
			'exclude'           => $request['exclude'],
			'in'                => $request['include'],
			'page'              => $request['page'],
			'per_page'          => $request['per_page'],
			'primary_id'        => $request['primary_id'],
			'search_terms'      => $request['search'],
			'secondary_id'      => $request['secondary_id'],
			'sort'              => $request['order'],
			'spam'              => $request['status'] === 'spam' ? 'spam_only' : 'ham_only',
			'user_id'           => $request['author'],
			'scope'				=> $request['scope'],

			// Set optimised defaults.
			'count_total'       => true,
			'fields'            => 'all',
			'show_hidden'       => false,
			'update_meta_cache' => true,
		);
		if ( isset( $request['after'] ) ) {
			$args['since'] = $request['after'];
		}

		if ( isset( $request['component'] ) ) {
			if ( ! isset( $args['filter'] ) ) {
				$args['filter'] = array( 'object' => $request['component'] );
			} else {
				$args['filter']['object'] = $request['component'];
			}
		}

		if ( isset( $request['type'] ) ) {
			if ( ! isset( $args['filter'] ) ) {
				$args['filter'] = array( 'action' => $request['type'] );
			} else {
				$args['filter']['action'] = $request['type'];
			}
		}

		if ( $args['in'] ) {
			$args['count_total'] = false;
		}

		// Override certain options for security.
		// @TODO: Verify and confirm this show_hidden logic, and check core for other edge cases.
		if ( $request['component'] === 'groups' &&
			(
				groups_is_user_member( get_current_user_id(), $request['primary_id'] ) ||
				bp_current_user_can( 'bp_moderate' )
			)
		) {
			$args['show_hidden'] = true;
		}

		if (isset($args['user_id'])) {
			if (!isset($args['filter'])) {
				$args['filter'] = array();
			}
			$args['filter']['user_id'] = $args['user_id'];
		}
		
		if (isset($args['primary_id'])) {
			if (!isset($args['filter'])) {
				$args['filter'] = array();
			}
			$args['filter']['primary_id'] = $args['primary_id'];
		}
		

		$retval     = array();
		$activities = bp_activity_get( $args );

		foreach ( $activities['activities'] as $activity ) {
			$retval[] = $this->prepare_response_for_collection(
				$this->prepare_item_for_response( $activity, $request )
			);
		}

		return rest_ensure_response( $retval );
	}

	/**
	 * Retrieve activity.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Request|WP_Error Plugin object data on success, WP_Error otherwise.
	 */
	public function get_item( $request ) {
		// TODO: query logic. and permissions. and other parameters that might need to be set. etc
		$activity = bp_activity_get( array(
			'in' => (int) $request['id'],
		) );

		$retval = array( $this->prepare_response_for_collection(
			$this->prepare_item_for_response( $activity['activities'][0], $request )
		) );

		return rest_ensure_response( $retval );

	}

	/**
	 * Check if a given request has access to get information about a specific activity.
	 *
	 * @since 0.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return bool
	 */
	public function get_item_permissions_check( $request ) {
		return $this->get_items_permissions_check( $request );
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
	 * Create a single user
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function create_item( $request ) {
		if ( ! empty( $request['id'] ) ) {
			return new WP_Error( 'rest_user_exists', __( 'Cannot create existing resource.' ), array( 'status' => 400 ) );
		}
		$schema = $this->get_item_schema();

		$prepared_activity = $this->prepare_item_for_database( $request );

		$activityId = bp_activity_add($prepared_activity);

		if (isset( $request['type']) && $request['type'] != "activity_comment") {
			// add activity meta bbwall-activity-privacy
			if ( isset( $request['visibility'] ) && ! empty( $schema['properties']['visibility'] ) ) {
				buddyboss_wall_add_visibility_to_activity('', bp_loggedin_user_id(), $activityId);
			}
			bp_activity_update_meta( $activityId, 'buddyboss_wall_initiator', bp_loggedin_user_id() );			
		}
		return rest_ensure_response( array( 'id' => $activityId) );
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

	/**
	 * Prepare a single activity for create or update
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return object $prepared_activity Activity array.
	 */
	protected function prepare_item_for_database( $request ) {
		$prepared_activity = array();
		$schema = $this->get_item_schema();
		
		if ( isset( $request['component'] ) && ! empty( $schema['properties']['component'] ) ) {
			$prepared_activity['component'] = $request['component'];
		}
		if ( isset( $request['type'] ) && ! empty( $schema['properties']['type'] ) ) {
			$prepared_activity['type'] = $request['type'];
		}
		if ( isset( $request['content'] ) && ! empty( $schema['properties']['content'] ) ) {
			$prepared_activity['content'] = $request['content'];
		}
		if ( isset( $request['prime_association'] ) && ! empty( $schema['properties']['prime_association'] ) ) {
			$prepared_activity['item_id'] = $request['prime_association'];
		}
		if ( isset( $request['secondary_association'] ) && ! empty( $schema['properties']['secondary_association'] ) ) {
			$prepared_activity['secondary_item_id'] = $request['secondary_association'];
		}
		/**
		 * Filter user data before inserting user via the REST API.
		 *
		 * @param object          $prepared_activity User object.
		 * @param WP_REST_Request $request       Request object.
		 */
		return apply_filters( 'rest_pre_insert_activity', $prepared_activity, $request );
	}

	/**
	 * Prepare links for the request.
	 *
	 * @since 0.1.0
	 *
	 * @param array $activity Activity.
	 * @return array Links for the given plugin.
	 */
	protected function prepare_links( $activity ) {
		$base = sprintf( '/%s/%s/', $this->namespace, $this->rest_base );

		// Entity meta.
		$links = array(
			'self' => array(
				'href' => rest_url( $base . $activity->id ),
			),
			'collection' => array(
				'href' => rest_url( $base ),
			),
			'author' => array(
				'href' => rest_url( '/wp/v2/users/' . $activity->user_id ),
			)
		);

		if ( $activity->type === 'activity_comment' ) {
			$links['up'] = array(
				'href' => rest_url( $base . $activity->item_id ),
			);
		}

		return $links;
	}

	/**
	 * Convert the input date to RFC3339 format.
	 *
	 * @param string $date_gmt
	 * @param string|null $date Optional. Date object.
	 * @return string|null ISO8601/RFC3339 formatted datetime.
	 */
	protected function prepare_date_response( $date_gmt, $date = null ) {
		if ( isset( $date ) ) {
			return mysql_to_rfc3339( $date );
		}

		if ( $date_gmt === '0000-00-00 00:00:00' ) {
			return null;
		}

		return mysql_to_rfc3339( $date_gmt );
	}
}
