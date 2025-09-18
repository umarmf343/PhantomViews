<?php
/**
 * Helper functions.
 *
 * @package PhantomViews
 */

namespace PhantomViews;

use function apply_filters;
use function get_option;
use function sanitize_text_field;
use function wp_unslash;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Recursively sanitize an array.
 *
 * @param mixed $value Value to sanitize.
 *
 * @return mixed
 */
function recursive_sanitize( $value ) {
if ( is_array( $value ) ) {
return array_map( __NAMESPACE__ . '\\recursive_sanitize', $value );
}

if ( is_scalar( $value ) ) {
return sanitize_text_field( wp_unslash( $value ) );
}

return $value;
}

/**
 * Check if current site has Pro license.
 *
 * @return bool
 */
function has_pro_license() {
return apply_filters( 'phantomviews_has_pro_license', (bool) get_option( 'phantomviews_license_valid', false ) );
}
