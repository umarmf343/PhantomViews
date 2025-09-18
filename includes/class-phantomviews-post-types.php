<?php
/**
 * Register custom post types and taxonomies.
 *
 * @package PhantomViews
 */

namespace PhantomViews\Post_Types;

use PhantomViews\Traits\Singleton;
use function _x;
use function __;
use function absint;
use function add_action;
use function add_meta_box;
use function apply_filters;
use function esc_attr;
use function register_post_type;
use function sanitize_key;
use function update_post_meta;
use function wp_json_encode;
use function wp_nonce_field;
use function wp_unslash;
use function wp_verify_nonce;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Handles registration of tours and scenes.
 */
class Post_Types {
use Singleton;

/**
 * Constructor.
 */
public function __construct() {
add_action( 'init', [ $this, 'register_post_types' ] );
add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
add_action( 'save_post', [ $this, 'save_tour_meta' ] );
}

/**
 * Register custom post types.
 */
public function register_post_types() {
$labels = [
'name'               => _x( 'Virtual Tours', 'post type general name', 'phantomviews' ),
'singular_name'      => _x( 'Virtual Tour', 'post type singular name', 'phantomviews' ),
'menu_name'          => _x( 'PhantomViews', 'admin menu', 'phantomviews' ),
'name_admin_bar'     => _x( 'Virtual Tour', 'add new on admin bar', 'phantomviews' ),
'add_new'            => _x( 'Add New', 'tour', 'phantomviews' ),
'add_new_item'       => __( 'Add New Virtual Tour', 'phantomviews' ),
'new_item'           => __( 'New Virtual Tour', 'phantomviews' ),
'edit_item'          => __( 'Edit Virtual Tour', 'phantomviews' ),
'view_item'          => __( 'View Virtual Tour', 'phantomviews' ),
'all_items'          => __( 'All Virtual Tours', 'phantomviews' ),
'search_items'       => __( 'Search Virtual Tours', 'phantomviews' ),
'parent_item_colon'  => __( 'Parent Tours:', 'phantomviews' ),
'not_found'          => __( 'No virtual tours found.', 'phantomviews' ),
'not_found_in_trash' => __( 'No virtual tours found in Trash.', 'phantomviews' ),
];

$args = [
'labels'             => $labels,
'public'             => true,
'exclude_from_search'=> false,
'show_ui'            => true,
'show_in_menu'       => true,
'menu_icon'          => 'dashicons-location',
'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
'has_archive'        => true,
'show_in_rest'       => true,
'rewrite'            => [ 'slug' => 'virtual-tours' ],
];

register_post_type( 'pv_tour', $args );
}

/**
 * Register meta boxes.
 */
public function register_meta_boxes() {
add_meta_box(
'phantomviews-tour-builder',
__( 'PhantomViews Tour Builder', 'phantomviews' ),
[ $this, 'render_tour_meta_box' ],
'pv_tour',
'normal',
'high'
);
}

/**
 * Render tour builder meta box.
 */
public function render_tour_meta_box( $post ) {
wp_nonce_field( 'phantomviews_save_tour', 'phantomviews_nonce' );
        $scenes = get_post_meta( $post->ID, '_phantomviews_scenes', true );
        if ( ! is_array( $scenes ) ) {
            $scenes = [];
        }

        $branding = get_post_meta( $post->ID, '_phantomviews_branding', true );
        if ( ! is_array( $branding ) ) {
            $branding = [];
        }

        $theme = get_post_meta( $post->ID, '_phantomviews_theme', true );
        if ( ! is_array( $theme ) ) {
            $theme = [];
        }

        $expiration = get_post_meta( $post->ID, '_phantomviews_expiration', true );
        if ( ! is_array( $expiration ) ) {
            $expiration = [];
        }

        $floor_plans = get_post_meta( $post->ID, '_phantomviews_floor_plans', true );
        if ( ! is_array( $floor_plans ) ) {
            $floor_plans = [];
        }

        $audio_tracks = get_post_meta( $post->ID, '_phantomviews_audio_tracks', true );
        if ( ! is_array( $audio_tracks ) ) {
            $audio_tracks = [];
        }

        $scene_limit = apply_filters( 'phantomviews_free_scene_limit', 3 );
        ?>
        <div
            id="phantomviews-tour-root"
            data-post-id="<?php echo esc_attr( $post->ID ); ?>"
            data-scenes='<?php echo esc_attr( wp_json_encode( $scenes ) ); ?>'
            data-branding='<?php echo esc_attr( wp_json_encode( $branding ) ); ?>'
            data-theme='<?php echo esc_attr( wp_json_encode( $theme ) ); ?>'
            data-expiration='<?php echo esc_attr( wp_json_encode( $expiration ) ); ?>'
            data-floor-plans='<?php echo esc_attr( wp_json_encode( $floor_plans ) ); ?>'
            data-audio-tracks='<?php echo esc_attr( wp_json_encode( $audio_tracks ) ); ?>'
            data-scene-limit="<?php echo esc_attr( $scene_limit ); ?>"
        ></div>
        <?php
}

/**
 * Save tour metadata.
 */
public function save_tour_meta( $post_id ) {
if ( ! isset( $_POST['phantomviews_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['phantomviews_nonce'] ), 'phantomviews_save_tour' ) ) {
return;
}

if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
return;
}

if ( isset( $_POST['_phantomviews_scenes'] ) && is_array( $_POST['_phantomviews_scenes'] ) ) {
$scenes = array_map( 'wp_unslash', $_POST['_phantomviews_scenes'] );
update_post_meta( $post_id, '_phantomviews_scenes', $scenes );
}
}
}
