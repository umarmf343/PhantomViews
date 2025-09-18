<?php
/**
 * Licensing logic.
 *
 * @package PhantomViews
 */

namespace PhantomViews\Licensing;

use PhantomViews\Traits\Singleton;
use function __;
use function add_action;
use function delete_option;
use function do_action;
use function get_option;
use function sanitize_text_field;
use function update_option;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Handles license activation, validation and state.
 */
class License_Manager {
use Singleton;

/**
 * Constructor.
 */
public function __construct() {
add_action( 'wp_login', [ $this, 'validate_license_on_login' ], 10, 2 );
add_action( 'init', [ $this, 'maybe_expire_license' ] );
}

/**
 * Activate license.
 *
 * @param string $license_key License key.
 * @param string $plan        Plan slug.
 *
 * @return array
 */
public function activate_license( $license_key, $plan = 'yearly' ) {
$license_key = sanitize_text_field( $license_key );

$plan = in_array( $plan, [ 'monthly', 'yearly' ], true ) ? $plan : 'yearly';

if ( empty( $license_key ) ) {
return [
'success' => false,
'message' => __( 'License key cannot be empty.', 'phantomviews' ),
];
}

update_option( 'phantomviews_license_key', $license_key );
update_option( 'phantomviews_license_state', 'active' );
update_option( 'phantomviews_license_valid', true );
update_option( 'phantomviews_license_plan', $plan );
update_option( 'phantomviews_license_expires', $this->calculate_expiration_date( $plan ) );

do_action( 'phantomviews_license_activated', $license_key, $plan );

return [
'success' => true,
'message' => __( 'License activated successfully.', 'phantomviews' ),
];
}

/**
 * Deactivate license.
 *
 * @return array
 */
public function deactivate_license() {
delete_option( 'phantomviews_license_valid' );
update_option( 'phantomviews_license_state', 'inactive' );
delete_option( 'phantomviews_license_expires' );
delete_option( 'phantomviews_license_plan' );
delete_option( 'phantomviews_license_key' );

do_action( 'phantomviews_license_deactivated' );

return [
'success' => true,
'message' => __( 'License deactivated.', 'phantomviews' ),
];
}

    /**
     * Validate license on login.
     *
     * @param string  $user_login Username.
     * @param \WP_User $user       User object.
     */
    public function validate_license_on_login( $user_login, $user ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
        $license_key = get_option( 'phantomviews_license_key', '' );
        if ( empty( $license_key ) ) {
            return;
        }

// Placeholder for remote validation.
$valid = true;

if ( ! $valid ) {
$this->deactivate_license();
}
}

/**
 * Maybe mark the license as expired.
 */
public function maybe_expire_license() {
$expires = get_option( 'phantomviews_license_expires', '' );
if ( empty( $expires ) ) {
return;
}

if ( gmdate( 'Y-m-d' ) > $expires ) {
update_option( 'phantomviews_license_state', 'expired' );
update_option( 'phantomviews_license_valid', false );

do_action( 'phantomviews_license_expired', get_option( 'phantomviews_license_key', '' ) );
}
}

/**
 * Get license state.
 *
 * @return string
 */
public function get_license_state() {
$this->maybe_expire_license();
return get_option( 'phantomviews_license_state', 'inactive' );
}

/**
 * Check if Pro features enabled.
 *
 * @return bool
 */
public function has_pro_access() {
if ( ! get_option( 'phantomviews_license_valid', false ) ) {
return false;
}

$expires = get_option( 'phantomviews_license_expires', '' );
if ( $expires && gmdate( 'Y-m-d' ) > $expires ) {
return false;
}

return 'active' === get_option( 'phantomviews_license_state', 'inactive' );
}

/**
 * Calculate expiration date for a plan.
 *
 * @param string $plan Plan slug.
 *
 * @return string
 */
private function calculate_expiration_date( $plan ) {
$interval = 'yearly' === $plan ? '+1 year' : '+1 month';

return gmdate( 'Y-m-d', strtotime( $interval ) );
}
}
