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
use function admin_url;
use function apply_filters;
use function check_ajax_referer;
use function current_user_can;
use function get_bloginfo;
use function get_option;
use function get_users;
use function home_url;
use function is_wp_error;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_email;
use function sanitize_text_field;
use function update_user_meta;
use function wp_generate_password;
use function wp_get_current_user;
use function wp_json_encode;
use function wp_mail;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;
use function wp_send_json_error;
use function wp_send_json_success;
use function wp_unslash;
use WP_REST_Response;

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

$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
check_ajax_referer( 'phantomviews_checkout', 'nonce', true );

$gateway = sanitize_text_field( wp_unslash( $_POST['gateway'] ?? '' ) );
$plan    = sanitize_text_field( wp_unslash( $_POST['plan'] ?? 'monthly' ) );
$email   = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

if ( empty( $email ) ) {
$current_user = wp_get_current_user();
if ( $current_user && $current_user->user_email ) {
$email = $current_user->user_email;
}
}

if ( empty( $gateway ) ) {
wp_send_json_error( [ 'message' => __( 'Gateway is required.', 'phantomviews' ) ], 400 );
}

$url = $this->generate_payment_url( $gateway, $plan, $email );

if ( is_wp_error( $url ) ) {
wp_send_json_error( [ 'message' => $url->get_error_message() ], 400 );
}

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
 * @param string $email   Customer email address.
 *
 * @return string|false|\WP_Error
 */
private function generate_payment_url( $gateway, $plan, $email ) {
$amount = $this->get_plan_amount( $plan );

if ( $amount <= 0 ) {
return new \WP_Error( 'phantomviews-invalid-amount', __( 'Set a price for the selected plan before generating a checkout.', 'phantomviews' ) );
}

switch ( $gateway ) {
case 'paystack':
return $this->generate_paystack_link( $amount, $plan, $email );
 case 'flutterwave':
 return $this->generate_flutterwave_link( $amount, $plan, $email );
 default:
 return false;
 }
}

/**
 * Initialize a Paystack transaction and return the authorization link.
 *
 * @param float  $amount Amount to bill.
 * @param string $plan   Plan slug.
 * @param string $email  Customer email.
 *
 * @return string|false|\WP_Error
 */
private function generate_paystack_link( $amount, $plan, $email ) {
 $secret_key = get_option( 'phantomviews_paystack_secret_key' );
 $public_key = get_option( 'phantomviews_paystack_public_key' );

 if ( empty( $secret_key ) || empty( $public_key ) ) {
 return new \WP_Error( 'phantomviews-paystack-missing', __( 'Paystack API keys are not configured.', 'phantomviews' ) );
 }

 $body = [
 'email'        => $email,
 'amount'       => $this->format_amount_for_gateway( $amount ),
 'callback_url' => add_query_arg( [ 'plan' => $plan ], admin_url( 'admin.php?page=phantomviews-licensing' ) ),
 'metadata'     => [
 'plan'     => $plan,
 'site_url' => home_url(),
 ],
 'currency'     => $this->get_currency(),
 'channels'     => apply_filters( 'phantomviews_paystack_channels', [ 'card', 'bank' ] ),
 ];

 $response = wp_remote_post(
 'https://api.paystack.co/transaction/initialize',
 [
 'headers' => [
 'Authorization' => 'Bearer ' . $secret_key,
 'Content-Type'  => 'application/json',
 ],
 'timeout' => 45,
 'body'    => wp_json_encode( $body ),
 ]
 );

 if ( is_wp_error( $response ) ) {
 return $response;
 }

 $code = wp_remote_retrieve_response_code( $response );
 $data = json_decode( wp_remote_retrieve_body( $response ), true );

 if ( 200 !== $code || empty( $data['status'] ) ) {
 $message = $data['message'] ?? __( 'Unable to initialize Paystack transaction.', 'phantomviews' );

 return new \WP_Error( 'phantomviews-paystack-error', $message );
 }

 return $data['data']['authorization_url'] ?? false;
}

/**
 * Initialize a Flutterwave payment and return the checkout link.
 *
 * @param float  $amount Amount to bill.
 * @param string $plan   Plan slug.
 * @param string $email  Customer email.
 *
 * @return string|false|\WP_Error
 */
private function generate_flutterwave_link( $amount, $plan, $email ) {
$secret_key = get_option( 'phantomviews_flutterwave_secret_key' );
$public_key = get_option( 'phantomviews_flutterwave_public_key' );

if ( empty( $secret_key ) || empty( $public_key ) ) {
return new \WP_Error( 'phantomviews-flutterwave-missing', __( 'Flutterwave API keys are not configured.', 'phantomviews' ) );
}

$tx_ref = 'PV-' . wp_generate_password( 10, false, false );

$body = [
'tx_ref'          => $tx_ref,
'amount'          => (float) $amount,
'currency'        => $this->get_currency(),
'redirect_url'    => add_query_arg( [ 'plan' => $plan, 'ref' => $tx_ref ], admin_url( 'admin.php?page=phantomviews-licensing' ) ),
'payment_options' => implode( ',', apply_filters( 'phantomviews_flutterwave_payment_options', [ 'card', 'banktransfer' ] ) ),
'meta'            => [
'plan'     => $plan,
'site_url' => home_url(),
],
'customer'        => [
'email' => $email,
'name'  => get_bloginfo( 'name' ),
],
'customizations'  => [
'title'       => __( 'PhantomViews Pro Subscription', 'phantomviews' ),
'description' => __( 'Unlock premium tour features.', 'phantomviews' ),
],
];

$response = wp_remote_post(
'https://api.flutterwave.com/v3/payments',
[
'headers' => [
'Authorization' => 'Bearer ' . $secret_key,
'Content-Type'  => 'application/json',
],
'timeout' => 45,
'body'    => wp_json_encode( $body ),
]
);

if ( is_wp_error( $response ) ) {
return $response;
}

$code = wp_remote_retrieve_response_code( $response );
$data = json_decode( wp_remote_retrieve_body( $response ), true );

if ( 200 !== $code || empty( $data['status'] ) || 'success' !== $data['status'] ) {
$message = $data['message'] ?? __( 'Unable to initialize Flutterwave payment.', 'phantomviews' );

return new \WP_Error( 'phantomviews-flutterwave-error', $message );
}

return $data['data']['link'] ?? false;
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
$raw_body = $request->get_body();
$payload  = json_decode( $raw_body, true );

if ( ! is_array( $payload ) ) {
return rest_ensure_response( [ 'status' => 'ignored' ] );
}

$email = '';
$plan  = 'monthly';

if ( 'paystack' === $gateway ) {
$secret    = get_option( 'phantomviews_paystack_secret_key' );
$signature = $request->get_header( 'x-paystack-signature' );

if ( empty( $secret ) || empty( $signature ) ) {
return new WP_REST_Response( [ 'status' => 'missing-signature' ], 401 );
}

$expected_signature = hash_hmac( 'sha512', $raw_body, $secret );

if ( ! hash_equals( $expected_signature, $signature ) ) {
return new WP_REST_Response( [ 'status' => 'invalid-signature' ], 401 );
}

$data   = $payload['data'] ?? [];
$status = $data['status'] ?? '';

if ( 'success' !== $status ) {
return rest_ensure_response( [ 'status' => 'ignored' ] );
}

$email = sanitize_email( $data['customer']['email'] ?? '' );
$plan  = sanitize_text_field( $data['metadata']['plan'] ?? 'monthly' );
} elseif ( 'flutterwave' === $gateway ) {
$secret    = get_option( 'phantomviews_flutterwave_secret_key' );
$signature = $request->get_header( 'verif-hash' );

if ( empty( $secret ) || empty( $signature ) || ! hash_equals( $secret, $signature ) ) {
return new WP_REST_Response( [ 'status' => 'invalid-signature' ], 401 );
}

$data   = $payload['data'] ?? [];
$status = $data['status'] ?? $payload['status'] ?? '';

if ( 'successful' !== strtolower( $status ) && 'success' !== strtolower( $status ) ) {
return rest_ensure_response( [ 'status' => 'ignored' ] );
}

$customer = $data['customer'] ?? [];
$email    = sanitize_email( $customer['email'] ?? $data['customer_email'] ?? '' );
$plan     = sanitize_text_field( $data['meta']['plan'] ?? 'monthly' );
}

if ( empty( $email ) ) {
return new WP_REST_Response( [ 'status' => 'missing-email' ], 400 );
}

$license_key = $this->issue_license_for_email( $email, $plan );

return rest_ensure_response(
[
'status'      => 'license-issued',
'license_key' => $license_key,
'gateway'     => $gateway,
'plan'        => $plan,
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
private function issue_license_for_email( $email, $plan = 'monthly' ) {
$license_key = strtoupper( wp_generate_password( 16, false, false ) );

$users = get_users( [ 'search' => $email, 'search_columns' => [ 'user_email' ] ] );
foreach ( $users as $user ) {
update_user_meta( $user->ID, 'phantomviews_license_key', $license_key );
}

$this->license_manager->activate_license( $license_key, $plan );

do_action( 'phantomviews_license_issued', $email, $license_key, $plan );

$subject = sprintf( __( 'Your PhantomViews %s License', 'phantomviews' ), ucfirst( $plan ) );
$message = sprintf(
/* translators: 1: License key, 2: plan name. */
__( 'Thank you for subscribing to PhantomViews Pro (%2$s). Your license key is: %1$s', 'phantomviews' ),
$license_key,
ucfirst( $plan )
);
$message .= PHP_EOL . PHP_EOL . __( 'Add this key in WordPress under PhantomViews → Licensing to unlock premium features.', 'phantomviews' );

wp_mail( $email, $subject, $message );

return $license_key;
}

/**
 * Get the configured subscription amount for a plan.
 *
 * @param string $plan Plan slug.
 *
 * @return float
 */
private function get_plan_amount( $plan ) {
$monthly = (float) get_option( 'phantomviews_pro_price_monthly', 0 );
$yearly  = (float) get_option( 'phantomviews_pro_price_yearly', 0 );

if ( 'yearly' === $plan ) {
return $yearly > 0 ? $yearly : $monthly;
}

return $monthly > 0 ? $monthly : $yearly;
}

/**
 * Convert amount into lowest currency denomination for gateways.
 *
 * @param float $amount Amount.
 *
 * @return int
 */
private function format_amount_for_gateway( $amount ) {
return (int) round( (float) $amount * 100 );
}

/**
 * Retrieve active billing currency.
 *
 * @return string
 */
private function get_currency() {
$currency = get_option( 'phantomviews_currency', 'NGN' );

return strtoupper( sanitize_text_field( $currency ) );
}
}
