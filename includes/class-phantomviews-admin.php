<?php
/**
 * Admin functionality.
 *
 * @package PhantomViews
 */

namespace PhantomViews\Admin;

use PhantomViews\Licensing\License_Manager;
use PhantomViews\Payments\Payment_Gateway_Manager;
use function __;
use function add_menu_page;
use function add_submenu_page;
use function esc_attr;
use function esc_html_e;
use function get_option;
use function register_setting;
use function sanitize_text_field;
use function settings_fields;
use function selected;
use function submit_button;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Admin UI and hooks.
 */
class Admin {

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
 * Constructor.
 *
 * @param License_Manager          $license_manager License manager.
 * @param Payment_Gateway_Manager  $payment_manager Payment manager.
 */
public function __construct( ?License_Manager $license_manager = null, ?Payment_Gateway_Manager $payment_manager = null ) {
 $this->license_manager = $license_manager ?: License_Manager::instance();
 $this->payment_manager = $payment_manager ?: new Payment_Gateway_Manager( $this->license_manager );

add_action( 'admin_menu', [ $this, 'register_menu' ] );
add_action( 'admin_init', [ $this, 'register_settings' ] );
}

/**
 * Register admin menu.
 */
public function register_menu() {
add_menu_page(
__( 'PhantomViews', 'phantomviews' ),
__( 'PhantomViews', 'phantomviews' ),
'manage_options',
'phantomviews',
[ $this, 'render_settings_page' ],
'dashicons-image-filter',
59
);

add_submenu_page(
'phantomviews',
__( 'Settings', 'phantomviews' ),
__( 'Settings', 'phantomviews' ),
'manage_options',
'phantomviews',
[ $this, 'render_settings_page' ]
);

add_submenu_page(
'phantomviews',
__( 'Licensing', 'phantomviews' ),
__( 'Licensing', 'phantomviews' ),
'manage_options',
'phantomviews-licensing',
[ $this, 'render_license_page' ]
);
}

/**
 * Register settings.
 */
public function register_settings() {
register_setting( 'phantomviews_settings', 'phantomviews_paystack_public_key', [ $this, 'sanitize_text' ] );
register_setting( 'phantomviews_settings', 'phantomviews_paystack_secret_key', [ $this, 'sanitize_text' ] );
register_setting( 'phantomviews_settings', 'phantomviews_flutterwave_public_key', [ $this, 'sanitize_text' ] );
register_setting( 'phantomviews_settings', 'phantomviews_flutterwave_secret_key', [ $this, 'sanitize_text' ] );
register_setting( 'phantomviews_settings', 'phantomviews_pro_price_monthly', [ $this, 'sanitize_text' ] );
register_setting( 'phantomviews_settings', 'phantomviews_pro_price_yearly', [ $this, 'sanitize_text' ] );
register_setting( 'phantomviews_settings', 'phantomviews_currency', [ $this, 'sanitize_text' ] );
}

/**
 * Sanitize text fields.
 */
public function sanitize_text( $value ) {
return sanitize_text_field( $value );
}

/**
 * Render settings page.
 */
public function render_settings_page() {
?>
<div class="wrap phantomviews-settings">
<h1><?php esc_html_e( 'PhantomViews Settings', 'phantomviews' ); ?></h1>
<form action="options.php" method="post">
<?php settings_fields( 'phantomviews_settings' ); ?>
<table class="form-table" role="presentation">
<tr>
<th scope="row"><label for="phantomviews_paystack_public_key"><?php esc_html_e( 'Paystack Public Key', 'phantomviews' ); ?></label></th>
<td><input type="text" id="phantomviews_paystack_public_key" name="phantomviews_paystack_public_key" value="<?php echo esc_attr( get_option( 'phantomviews_paystack_public_key', '' ) ); ?>" class="regular-text" /></td>
</tr>
<tr>
<th scope="row"><label for="phantomviews_paystack_secret_key"><?php esc_html_e( 'Paystack Secret Key', 'phantomviews' ); ?></label></th>
<td><input type="text" id="phantomviews_paystack_secret_key" name="phantomviews_paystack_secret_key" value="<?php echo esc_attr( get_option( 'phantomviews_paystack_secret_key', '' ) ); ?>" class="regular-text" /></td>
</tr>
<tr>
<th scope="row"><label for="phantomviews_flutterwave_public_key"><?php esc_html_e( 'Flutterwave Public Key', 'phantomviews' ); ?></label></th>
<td><input type="text" id="phantomviews_flutterwave_public_key" name="phantomviews_flutterwave_public_key" value="<?php echo esc_attr( get_option( 'phantomviews_flutterwave_public_key', '' ) ); ?>" class="regular-text" /></td>
</tr>
<tr>
<th scope="row"><label for="phantomviews_flutterwave_secret_key"><?php esc_html_e( 'Flutterwave Secret Key', 'phantomviews' ); ?></label></th>
<td><input type="text" id="phantomviews_flutterwave_secret_key" name="phantomviews_flutterwave_secret_key" value="<?php echo esc_attr( get_option( 'phantomviews_flutterwave_secret_key', '' ) ); ?>" class="regular-text" /></td>
</tr>
<tr>
<th scope="row"><label for="phantomviews_pro_price_monthly"><?php esc_html_e( 'Pro Monthly Price', 'phantomviews' ); ?></label></th>
<td><input type="text" id="phantomviews_pro_price_monthly" name="phantomviews_pro_price_monthly" value="<?php echo esc_attr( get_option( 'phantomviews_pro_price_monthly', '' ) ); ?>" class="regular-text" /></td>
</tr>
<tr>
<th scope="row"><label for="phantomviews_pro_price_yearly"><?php esc_html_e( 'Pro Yearly Price', 'phantomviews' ); ?></label></th>
<td><input type="text" id="phantomviews_pro_price_yearly" name="phantomviews_pro_price_yearly" value="<?php echo esc_attr( get_option( 'phantomviews_pro_price_yearly', '' ) ); ?>" class="regular-text" /></td>
</tr>
<tr>
<th scope="row"><label for="phantomviews_currency"><?php esc_html_e( 'Billing Currency', 'phantomviews' ); ?></label></th>
<td>
<?php $currency = get_option( 'phantomviews_currency', 'NGN' ); ?>
<select id="phantomviews_currency" name="phantomviews_currency">
<?php
$currencies = [
'NGN' => __( 'Nigerian Naira (₦)', 'phantomviews' ),
'USD' => __( 'US Dollars ($)', 'phantomviews' ),
'GHS' => __( 'Ghanaian Cedi (₵)', 'phantomviews' ),
'KES' => __( 'Kenyan Shilling (KSh)', 'phantomviews' ),
'ZAR' => __( 'South African Rand (R)', 'phantomviews' ),
];
foreach ( $currencies as $code => $label ) :
?>
<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $currency, $code ); ?>><?php echo esc_html( $label ); ?></option>
<?php endforeach; ?>
</select>
<p class="description"><?php esc_html_e( 'The selected currency is used when generating Paystack or Flutterwave checkouts.', 'phantomviews' ); ?></p>
</td>
</tr>
</table>
<?php submit_button(); ?>
</form>
</div>
<?php
}

/**
 * Render license page.
 */
public function render_license_page() {
?>
<div class="wrap phantomviews-settings">
<h1><?php esc_html_e( 'PhantomViews License', 'phantomviews' ); ?></h1>
<div id="phantomviews-license-root"></div>
</div>
<?php
}
}
