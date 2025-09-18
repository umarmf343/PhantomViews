<?php
/**
 * Payment gateway integration layer.
 *
 * @package PhantomViews
 */

namespace PhantomViews\Payments;

use PhantomViews\Licensing\License_Manager;
use function __;
use function add_action;
use function add_query_arg;
use function current_user_can;
use function get_option;
use function get_users;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_email;
use function sanitize_text_field;
use function update_user_meta;
use function wp_generate_password;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Handles orchestration between payment gateways and licensing.
 */
class Payment_Gateway_Manager {

/**
 * License manager.
 *
 * @var License_Manager
 */
private $license_manager;

/**
 * Constructor.
 */
public function __construct( ?License_Manager $license_manager = null ) {
$this->license_manager = $license_manager ?: License_Manager::instance();

add_action( 'wp_ajax_phantomviews_create_checkout', [ $this, 'handle_checkout_request' ] );
add_action( 'rest_api_init', [ $this, 'register_webhook_routes' ] );
}

/**
 * Handle checkout request and return payment URL.
 */
public function handle_checkout_request() {
if ( ! current_user_can( 'manage_options' ) ) {
wp_send_json_error( [ 'message' => __( 'Unauthorized.', 'phantomviews' ) ], 403 );
}

$gateway = sanitize_text_field( wp_unslash( $_POST['gateway'] ?? '' ) );
$plan    = sanitize_text_field( wp_unslash( $_POST['plan'] ?? 'monthly' ) );

if ( empty( $gateway ) ) {
wp_send_json_error( [ 'message' => __( 'Gateway is required.', 'phantomviews' ) ], 400 );
}

$url = $this->generate_payment_url( $gateway, $plan );

if ( ! $url ) {
wp_send_json_error( [ 'message' => __( 'Unable to generate payment URL.', 'phantomviews' ) ], 500 );
}

wp_send_json_success(
[
'checkout_url' => $url,
]
);
}

/**
 * Register webhook routes for gateways.
 */
public function register_webhook_routes() {
register_rest_route(
'phantomviews/v1',
'/webhook/paystack',
[
'methods'             => 'POST',
'callback'            => [ $this, 'handle_paystack_webhook' ],
'permission_callback' => '__return_true',
]
);

register_rest_route(
'phantomviews/v1',
'/webhook/flutterwave',
[
'methods'             => 'POST',
'callback'            => [ $this, 'handle_flutterwave_webhook' ],
'permission_callback' => '__return_true',
]
);
}

/**
 * Generate payment URL for the given gateway.
 *
 * @param string $gateway Gateway slug.
 * @param string $plan    Plan slug.
 *
 * @return string|false
 */
private function generate_payment_url( $gateway, $plan ) {
$amounts = [
'monthly' => get_option( 'phantomviews_pro_price_monthly', '0' ),
'yearly'  => get_option( 'phantomviews_pro_price_yearly', '0' ),
];

$amount = $amounts[ $plan ] ?? $amounts['monthly'];

switch ( $gateway ) {
case 'paystack':
return $this->generate_paystack_link( $amount, $plan );
case 'flutterwave':
return $this->generate_flutterwave_link( $amount, $plan );
default:
return false;
}
}

/**
 * Generate Paystack link (placeholder).
 */
private function generate_paystack_link( $amount, $plan ) {
$public_key = get_option( 'phantomviews_paystack_public_key' );
if ( empty( $public_key ) ) {
return false;
}

// Placeholder link generation. In production integrate Paystack initialization.
return add_query_arg(
[
'amount' => $amount,
'plan'   => $plan,
],
'https://paystack.com/pay/phantomviews'
);
}

/**
 * Generate Flutterwave link (placeholder).
 */
private function generate_flutterwave_link( $amount, $plan ) {
$public_key = get_option( 'phantomviews_flutterwave_public_key' );
if ( empty( $public_key ) ) {
return false;
}

return add_query_arg(
[
'amount' => $amount,
'plan'   => $plan,
],
'https://flutterwave.com/pay/phantomviews'
);
}

/**
 * Handle Paystack webhook callback.
 */
public function handle_paystack_webhook( $request ) {
return $this->process_webhook_payload( $request, 'paystack' );
}

/**
 * Handle Flutterwave webhook callback.
 */
public function handle_flutterwave_webhook( $request ) {
return $this->process_webhook_payload( $request, 'flutterwave' );
}

/**
 * Process webhook payload for both gateways.
 *
 * @param \WP_REST_Request $request Request.
 * @param string           $gateway Gateway slug.
 */
private function process_webhook_payload( $request, $gateway ) {
$body   = $request->get_json_params();
$status = $body['status'] ?? '';
$email  = sanitize_email( $body['customer']['email'] ?? '' );

if ( 'success' !== $status || empty( $email ) ) {
return rest_ensure_response( [ 'received' => true ] );
}

$license_key = $this->issue_license_for_email( $email );

return rest_ensure_response(
[
'status'      => 'license-issued',
'license_key' => $license_key,
'gateway'     => $gateway,
]
);
}

/**
 * Issue license key for a customer email.
 *
 * @param string $email Customer email.
 *
 * @return string
 */
private function issue_license_for_email( $email ) {
$license_key = strtoupper( wp_generate_password( 16, false, false ) );

$users = get_users( [ 'search' => $email, 'search_columns' => [ 'user_email' ] ] );
foreach ( $users as $user ) {
update_user_meta( $user->ID, 'phantomviews_license_key', $license_key );
}

$this->license_manager->activate_license( $license_key );

do_action( 'phantomviews_license_issued', $email, $license_key );

return $license_key;
}
}
