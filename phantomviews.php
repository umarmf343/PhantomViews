<?php
/**
 * Plugin Name:       PhantomViews
 * Plugin URI:        https://phantomviews.example.com
 * Description:       Create immersive, interactive 360° virtual tours with manual hotspots, licensing, and payment integration.
 * Version:           0.1.0
 * Author:            PhantomViews
 * Author URI:        https://phantomviews.example.com
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       phantomviews
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 *
 * @package PhantomViews
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

define( 'PHANTOMVIEWS_VERSION', '0.1.0' );
define( 'PHANTOMVIEWS_PLUGIN_FILE', __FILE__ );
define( 'PHANTOMVIEWS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PHANTOMVIEWS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PHANTOMVIEWS_PLUGIN_DIR . 'includes/class-phantomviews.php';

/**
 * Kick off the plugin.
 */
function phantomviews() {
return PhantomViews\Plugin::instance();
}

phantomviews();
