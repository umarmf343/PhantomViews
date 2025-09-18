<?php
/**
 * REST API endpoints.
 *
 * @package PhantomViews
 */

namespace PhantomViews\Rest;

use PhantomViews\Licensing\License_Manager;
use PhantomViews\Traits\Singleton;
use function __;
use function add_action;
use function current_user_can;
use function get_post_type;
use function register_rest_route;
use function sanitize_text_field;
use function update_post_meta;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function PhantomViews\recursive_sanitize;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Register custom REST endpoints.
 */
class Rest_Controller {
use Singleton;

/**
 * Constructor.
 */
public function __construct() {
add_action( 'rest_api_init', [ $this, 'register_routes' ] );
}

/**
 * Register REST routes.
 */
public function register_routes() {
register_rest_route(
'phantomviews/v1',
'/tours/(?P<id>\d+)',
[
'methods'             => WP_REST_Server::EDITABLE,
'callback'            => [ $this, 'update_tour_scenes' ],
'permission_callback' => function () {
return current_user_can( 'edit_posts' );
},
]
);

register_rest_route(
'phantomviews/v1',
'/license/activate',
[
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => [ $this, 'activate_license' ],
'permission_callback' => function () {
return current_user_can( 'manage_options' );
},
]
);

register_rest_route(
'phantomviews/v1',
'/license/deactivate',
[
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => [ $this, 'deactivate_license' ],
'permission_callback' => function () {
return current_user_can( 'manage_options' );
},
]
);
}

/**
 * Update tour scenes via REST.
 */
public function update_tour_scenes( WP_REST_Request $request ) {
$tour_id = (int) $request['id'];
if ( ! $tour_id || 'pv_tour' !== get_post_type( $tour_id ) ) {
return new WP_REST_Response( [ 'message' => __( 'Invalid tour ID.', 'phantomviews' ) ], 400 );
}

$scenes = $request->get_param( 'scenes' );
$scenes = is_array( $scenes ) ? recursive_sanitize( $scenes ) : [];
update_post_meta( $tour_id, '_phantomviews_scenes', $scenes );

return new WP_REST_Response( [ 'message' => __( 'Tour scenes saved successfully.', 'phantomviews' ) ], 200 );
}

/**
 * Activate license via REST.
 */
public function activate_license( WP_REST_Request $request ) {
$license_key = sanitize_text_field( $request->get_param( 'license_key' ) );
if ( empty( $license_key ) ) {
return new WP_REST_Response( [ 'message' => __( 'License key is required.', 'phantomviews' ) ], 400 );
}

$manager = License_Manager::instance();
$result  = $manager->activate_license( $license_key );

return new WP_REST_Response( $result, $result['success'] ? 200 : 400 );
}

/**
 * Deactivate license via REST.
 */
public function deactivate_license() {
$manager = License_Manager::instance();
$result  = $manager->deactivate_license();

return new WP_REST_Response( $result, $result['success'] ? 200 : 400 );
}
}
