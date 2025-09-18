<?php
/**
 * Asset management.
 *
 * @package PhantomViews
 */

namespace PhantomViews\Assets;

use PhantomViews\Traits\Singleton;
use function add_action;
use function admin_url;
use function esc_url_raw;
use function get_option;
use function rest_url;
use function sanitize_text_field;
use function wp_create_nonce;
use function wp_enqueue_script;
use function wp_enqueue_style;
use function wp_get_current_user;
use function wp_localize_script;
use function wp_register_script;
use function wp_register_style;
use function __;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Handles registering and enqueueing assets.
 */
class Assets {
use Singleton;

/**
 * Constructor.
 */
public function __construct() {
add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend' ] );
add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ] );
}

/**
 * Enqueue frontend assets.
 */
public function enqueue_frontend() {
wp_register_script(
'phantomviews-three',
'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.min.js',
[],
'0.160.0',
true
);

wp_register_script(
'phantomviews-panellum',
'https://cdn.jsdelivr.net/npm/panolens@0.12.0/build/panolens.min.js',
[ 'phantomviews-three' ],
'0.12.0',
true
);

wp_register_style(
'phantomviews-frontend',
PHANTOMVIEWS_PLUGIN_URL . 'assets/css/frontend.css',
[],
PHANTOMVIEWS_VERSION
);

wp_register_script(
'phantomviews-frontend',
PHANTOMVIEWS_PLUGIN_URL . 'assets/js/frontend.js',
[ 'wp-element', 'phantomviews-panellum' ],
PHANTOMVIEWS_VERSION,
true
);

wp_enqueue_style( 'phantomviews-frontend' );
wp_enqueue_script( 'phantomviews-frontend' );
}

/**
 * Enqueue admin assets.
 */
public function enqueue_admin( $hook ) {
if ( false === strpos( $hook, 'phantomviews' ) && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
return;
}

wp_register_style(
'phantomviews-admin',
PHANTOMVIEWS_PLUGIN_URL . 'assets/css/admin.css',
[],
PHANTOMVIEWS_VERSION
);

wp_register_script(
'phantomviews-admin',
PHANTOMVIEWS_PLUGIN_URL . 'assets/js/admin.js',
[ 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
PHANTOMVIEWS_VERSION,
true
);

wp_enqueue_style( 'phantomviews-admin' );
wp_enqueue_script( 'phantomviews-admin' );

wp_localize_script(
'phantomviews-admin',
'PhantomViewsAdmin',
[
'root'              => esc_url_raw( rest_url( 'phantomviews/v1' ) ),
'nonce'             => wp_create_nonce( 'wp_rest' ),
'ajax_url'          => admin_url( 'admin-ajax.php' ),
'ajax_nonce'        => wp_create_nonce( 'phantomviews_checkout' ),
'license_state'     => get_option( 'phantomviews_license_state', 'inactive' ),
'pro_enabled'       => (bool) get_option( 'phantomviews_license_valid', false ),
            'license_expiry'    => get_option( 'phantomviews_license_expires', '' ),
            'license_plan'      => sanitize_text_field( get_option( 'phantomviews_license_plan', 'monthly' ) ),
'current_user_email'=> ( $user = wp_get_current_user() ) ? $user->user_email : '',
'currency'          => get_option( 'phantomviews_currency', 'NGN' ),
'pricing'           => [
'monthly' => get_option( 'phantomviews_pro_price_monthly', '' ),
'yearly'  => get_option( 'phantomviews_pro_price_yearly', '' ),
],
'i18n'              => [
'sceneLimitReached' => __( 'You have reached the maximum number of scenes available on the free plan.', 'phantomviews' ),
'save'              => __( 'Save Tour', 'phantomviews' ),
'preview'           => __( 'Preview Tour', 'phantomviews' ),
'checkoutRedirect'  => __( 'Opening secure checkout in a new tab…', 'phantomviews' ),
'checkoutFailed'    => __( 'Unable to start checkout. Please verify your billing configuration.', 'phantomviews' ),
'checkoutEmailRequired' => __( 'Enter an email address to receive your license key.', 'phantomviews' ),
],
]
);
}
}
