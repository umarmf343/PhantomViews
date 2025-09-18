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
}

/**
 * Activate license.
 *
 * @param string $license_key License key.
 *
 * @return array
 */
public function activate_license( $license_key ) {
$license_key = sanitize_text_field( $license_key );

if ( empty( $license_key ) ) {
return [
'success' => false,
'message' => __( 'License key cannot be empty.', 'phantomviews' ),
];
}

update_option( 'phantomviews_license_key', $license_key );
update_option( 'phantomviews_license_state', 'active' );
update_option( 'phantomviews_license_valid', true );
update_option( 'phantomviews_license_expires', gmdate( 'Y-m-d', strtotime( '+1 year' ) ) );

do_action( 'phantomviews_license_activated', $license_key );

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

do_action( 'phantomviews_license_deactivated' );

return [
'success' => true,
'message' => __( 'License deactivated.', 'phantomviews' ),
];
}

/**
 * Validate license on login.
 */
public function validate_license_on_login() {
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
 * Get license state.
 *
 * @return string
 */
public function get_license_state() {
return get_option( 'phantomviews_license_state', 'inactive' );
}

/**
 * Check if Pro features enabled.
 *
 * @return bool
 */
public function has_pro_access() {
return (bool) get_option( 'phantomviews_license_valid', false );
}
}
