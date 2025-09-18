<?php
/**
 * Hotspot management.
 *
 * @package PhantomViews
 */

namespace PhantomViews\Hotspots;

use PhantomViews\Traits\Singleton;
use function PhantomViews\recursive_sanitize;
use function __;
use function add_action;
use function esc_url_raw;
use function floatval;
use function get_post_meta;
use function register_rest_field;
use function update_post_meta;
use function wp_parse_args;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Handles storing and retrieving hotspots.
 */
class Hotspots {
use Singleton;

/**
 * Constructor.
 */
public function __construct() {
add_action( 'rest_api_init', [ $this, 'register_rest_fields' ] );
}

/**
 * Get scenes for a tour.
 *
 * @param int $tour_id Tour ID.
 *
 * @return array
 */
public function get_tour_scenes( $tour_id ) {
$scenes = get_post_meta( $tour_id, '_phantomviews_scenes', true );
if ( ! is_array( $scenes ) ) {
$scenes = [];
}

return array_map( [ $this, 'prepare_scene_for_display' ], $scenes );
}

/**
 * Prepare scene for display.
 *
 * @param array $scene Scene data.
 *
 * @return array
 */
private function prepare_scene_for_display( $scene ) {
$defaults = [
'id'       => '',
'title'    => '',
'image_url'=> '',
'hotspots' => [],
];

$scene = wp_parse_args( $scene, $defaults );

if ( is_array( $scene['hotspots'] ) ) {
$scene['hotspots'] = array_map( [ $this, 'prepare_hotspot_for_display' ], $scene['hotspots'] );
}

return $scene;
}

/**
 * Prepare a hotspot for display.
 *
 * @param array $hotspot Hotspot data.
 *
 * @return array
 */
private function prepare_hotspot_for_display( $hotspot ) {
$defaults = [
'id'           => '',
'title'        => '',
'description'  => '',
'type'         => 'info',
'icon_url'     => '',
'position'     => [ 'x' => 0, 'y' => 0, 'z' => 0 ],
'target_scene' => '',
'url'          => '',
'media_url'    => '',
];

$hotspot = wp_parse_args( $hotspot, $defaults );

if ( ! is_array( $hotspot['position'] ) ) {
$hotspot['position'] = [ 'x' => 0, 'y' => 0, 'z' => 0 ];
}

$hotspot['position'] = [
'x' => floatval( $hotspot['position']['x'] ?? 0 ),
'y' => floatval( $hotspot['position']['y'] ?? 0 ),
'z' => floatval( $hotspot['position']['z'] ?? 0 ),
];

if ( 'url' === $hotspot['type'] && ! empty( $hotspot['url'] ) ) {
$hotspot['url'] = esc_url_raw( $hotspot['url'] );
}

if ( 'media' === $hotspot['type'] && ! empty( $hotspot['media_url'] ) ) {
$hotspot['media_url'] = esc_url_raw( $hotspot['media_url'] );
}

return $hotspot;
}

/**
 * Register REST fields for scenes.
 */
public function register_rest_fields() {
register_rest_field(
'pv_tour',
'scenes',
[
'get_callback'    => [ $this, 'rest_get_scenes' ],
'update_callback' => [ $this, 'rest_update_scenes' ],
'schema'          => [
'description' => __( 'Scenes data for PhantomViews tours.', 'phantomviews' ),
'type'        => 'array',
],
]
);
}

/**
 * REST: get scenes.
 */
public function rest_get_scenes( $object ) {
return $this->get_tour_scenes( $object['id'] );
}

/**
 * REST: update scenes.
 */
public function rest_update_scenes( $value, $object ) {
$scenes = recursive_sanitize( $value );
update_post_meta( $object->ID, '_phantomviews_scenes', $scenes );
return true;
}
}
