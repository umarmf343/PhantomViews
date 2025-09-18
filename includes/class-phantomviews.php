<?php
/**
 * Core plugin bootstrap file.
 *
 * @package PhantomViews
 */

namespace PhantomViews;

use PhantomViews\Admin\Admin;
use PhantomViews\Assets\Assets;
use PhantomViews\Hotspots\Hotspots;
use PhantomViews\Licensing\License_Manager;
use PhantomViews\Payments\Payment_Gateway_Manager;
use PhantomViews\Post_Types\Post_Types;
use PhantomViews\Rest\Rest_Controller;
use function absint;
use function add_action;
use function add_shortcode;
use function do_action;
use function esc_attr;
use function esc_html_e;
use function flush_rewrite_rules;
use function get_post_meta;
use function load_plugin_textdomain;
use function plugin_basename;
use function register_activation_hook;
use function register_deactivation_hook;
use function shortcode_atts;
use function wp_json_encode;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Main plugin class.
 */
final class Plugin {
/**
 * Single instance of the class.
 *
 * @var Plugin
 */
private static $instance;

/**
 * Assets handler.
 *
 * @var Assets
 */
private $assets;

/**
 * Admin handler.
 *
 * @var Admin
 */
private $admin;

/**
 * Post type handler.
 *
 * @var Post_Types
 */
private $post_types;

/**
 * Hotspots handler.
 *
 * @var Hotspots
 */
private $hotspots;

/**
 * REST controller.
 *
 * @var Rest_Controller
 */
private $rest_controller;

/**
 * License manager.
 *
 * @var License_Manager
 */
private $license_manager;

/**
 * Payment manager.
 *
 * @var Payment_Gateway_Manager
 */
private $payment_manager;

/**
 * Plugin constructor.
 */
private function __construct() {
$this->includes();
$this->init_hooks();
}

/**
 * Get singleton instance.
 *
 * @return Plugin
 */
public static function instance() {
if ( null === self::$instance ) {
self::$instance = new self();
}

return self::$instance;
}

/**
 * Load dependencies.
 */
private function includes() {
require_once PHANTOMVIEWS_PLUGIN_DIR . 'includes/traits/trait-singleton.php';
require_once PHANTOMVIEWS_PLUGIN_DIR . 'includes/class-phantomviews-helpers.php';
require_once PHANTOMVIEWS_PLUGIN_DIR . 'includes/class-phantomviews-assets.php';
require_once PHANTOMVIEWS_PLUGIN_DIR . 'includes/class-phantomviews-post-types.php';
require_once PHANTOMVIEWS_PLUGIN_DIR . 'includes/class-phantomviews-hotspots.php';
require_once PHANTOMVIEWS_PLUGIN_DIR . 'includes/class-phantomviews-rest.php';
require_once PHANTOMVIEWS_PLUGIN_DIR . 'includes/class-phantomviews-admin.php';
require_once PHANTOMVIEWS_PLUGIN_DIR . 'includes/class-phantomviews-license.php';
require_once PHANTOMVIEWS_PLUGIN_DIR . 'includes/class-phantomviews-payments.php';
}

/**
 * Hook into WordPress lifecycle.
 */
private function init_hooks() {
register_activation_hook( PHANTOMVIEWS_PLUGIN_FILE, [ $this, 'activate' ] );
register_deactivation_hook( PHANTOMVIEWS_PLUGIN_FILE, [ $this, 'deactivate' ] );

add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
add_action( 'init', [ $this, 'init_components' ], 0 );
add_action( 'init', [ $this, 'register_shortcodes' ] );
}

/**
 * Load plugin textdomain.
 */
public function load_textdomain() {
load_plugin_textdomain( 'phantomviews', false, dirname( plugin_basename( PHANTOMVIEWS_PLUGIN_FILE ) ) . '/languages' );
}

/**
 * Initialize plugin components.
 */
public function init_components() {
$this->assets           = Assets::instance();
$this->post_types       = Post_Types::instance();
$this->hotspots         = Hotspots::instance();
$this->rest_controller  = Rest_Controller::instance();
$this->license_manager  = License_Manager::instance();
$this->payment_manager  = new Payment_Gateway_Manager( $this->license_manager );
$this->admin            = new Admin( $this->license_manager, $this->payment_manager );
}

/**
 * Register plugin shortcodes.
 */
public function register_shortcodes() {
add_shortcode( 'phantomviews_tour', [ $this, 'render_tour_shortcode' ] );
}

/**
 * Render tour shortcode.
 *
 * @param array  $atts Shortcode attributes.
 * @param string $content Content.
 *
 * @return string
 */
public function render_tour_shortcode( $atts, $content = '' ) {
$atts = shortcode_atts(
[
'id'       => 0,
'width'    => '100%',
'height'   => '600px',
'autoplay' => 'false',
],
$atts,
'phantomviews_tour'
);

$tour_id = absint( $atts['id'] );
if ( ! $tour_id ) {
return '';
}

        $license_state = $this->license_manager ? $this->license_manager->get_license_state() : 'free';
        $scenes        = $this->hotspots ? $this->hotspots->get_tour_scenes( $tour_id ) : [];

        $branding    = get_post_meta( $tour_id, '_phantomviews_branding', true );
        $theme       = get_post_meta( $tour_id, '_phantomviews_theme', true );
        $expiration  = get_post_meta( $tour_id, '_phantomviews_expiration', true );
        $floor_plans = get_post_meta( $tour_id, '_phantomviews_floor_plans', true );
        $audio       = get_post_meta( $tour_id, '_phantomviews_audio_tracks', true );

        if ( ! is_array( $branding ) ) {
            $branding = [];
        }

        if ( ! is_array( $theme ) ) {
            $theme = [];
        }

        if ( ! is_array( $expiration ) ) {
            $expiration = [];
        }

        if ( ! is_array( $floor_plans ) ) {
            $floor_plans = [];
        }

        if ( ! is_array( $audio ) ) {
            $audio = [];
        }

        $expired_message = '';
        if ( isset( $expiration['enabled'] ) && filter_var( $expiration['enabled'], FILTER_VALIDATE_BOOLEAN ) ) {
            $expires_at = isset( $expiration['expires_at'] ) ? strtotime( $expiration['expires_at'] ) : 0;
            if ( $expires_at && time() > $expires_at ) {
                $expired_message = __( 'This immersive experience is no longer available because the sharing link has expired.', 'phantomviews' );
            }
        }

        if ( $expired_message ) {
            return '<div class="phantomviews-tour-expired">' . esc_html( $expired_message ) . '</div>';
        }

        $tour_payload = [
            'scenes'      => $scenes,
            'branding'    => $branding,
            'theme'       => $theme,
            'expiration'  => $expiration,
            'floorPlans'  => $floor_plans,
            'audioTracks' => $audio,
        ];

        ob_start();
        ?>
        <div class="phantomviews-tour" data-tour-id="<?php echo esc_attr( $tour_id ); ?>" data-license-state="<?php echo esc_attr( $license_state ); ?>" style="width: <?php echo esc_attr( $atts['width'] ); ?>; height: <?php echo esc_attr( $atts['height'] ); ?>;">
            <div class="phantomviews-viewer" aria-live="polite"></div>
            <noscript><?php esc_html_e( 'PhantomViews requires JavaScript to display the tour.', 'phantomviews' ); ?></noscript>
        </div>
        <script type="application/json" class="phantomviews-data" data-tour-id="<?php echo esc_attr( $tour_id ); ?>"><?php echo wp_json_encode( $tour_payload ); ?></script>
        <?php

        return ob_get_clean();
}

/**
 * Plugin activation callback.
 */
public function activate() {
do_action( 'phantomviews_before_activate' );
if ( class_exists( '\PhantomViews\\Post_Types\\Post_Types' ) ) {
$post_types = new Post_Types();
$post_types->register_post_types();
}
flush_rewrite_rules();
do_action( 'phantomviews_after_activate' );
}

/**
 * Plugin deactivation callback.
 */
public function deactivate() {
do_action( 'phantomviews_before_deactivate' );
flush_rewrite_rules();
do_action( 'phantomviews_after_deactivate' );
}
}
