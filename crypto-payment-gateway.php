<?php
/**
 * Plugin Name: Crypto Payment Gateway with Instant Payouts
 * Plugin URI: https://paygate.to/crypto-payment-gateway-no-kyc-instant-payouts/
 * Description: Cryptocurrency Payment Gateway with instant payouts to your wallet and without KYC hosted directly on your website.
 * Version: 1.1.4
 * Requires Plugins: woocommerce
 * Requires at least: 5.8
 * Tested up to: 7.0
 * WC requires at least: 5.8
 * WC tested up to: 10.8.1
 * Requires PHP: 7.2
 * Author: PayGate.to
 * Author URI: https://paygate.to/
 * License: GPLv3
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

/**
 * Register (once) the shared block-checkout client script and localise the list
 * of enabled PayGate gateways for it.
 *
 * Called from the block payment integration so WooCommerce loads it within the
 * Cart/Checkout block context only — never on the front end at large and never
 * in the block editor. Dependencies are intentionally minimal: pulling in the
 * editor runtime (wp-editor/wp-blocks) on the storefront makes WooCommerce load
 * its editor "preview cart" (sample products Beanie/Cap) and emit phantom
 * "removed from your cart" notices.
 */
function paygatedottocryptogateway_register_block_checkout_script()
{
    $handle = 'paygatedottocryptogateway-block-support';
    $path   = 'assets/js/paygatedottocryptogateway-block-checkout-support.js';

    if (wp_script_is($handle, 'registered')) {
        return;
    }

    wp_register_script(
        $handle,
        plugin_dir_url(__FILE__) . $path,
        array('wc-blocks-registry', 'wp-element'),
        filemtime(plugin_dir_path(__FILE__) . $path),
        true
    );

    $paygatedottocryptogateway_gateways_data = array();
    foreach (WC()->payment_gateways()->payment_gateways() as $gateway_id => $gateway) {
        // The dynamic individual-coin gateway has its own dedicated block script
        // (it renders a coin selector), so skip it here to avoid double-registration.
        if ('paygatedotto-crypto-payment-gateway-dynamic' === $gateway_id) {
            continue;
        }
        if (strpos($gateway_id, 'paygatedotto-crypto-payment-gateway') === 0 && 'yes' === $gateway->enabled) {
            $icon_url = method_exists($gateway, 'paygatedotto_crypto_payment_gateway_get_icon_url') ? $gateway->paygatedotto_crypto_payment_gateway_get_icon_url() : '';
            $paygatedottocryptogateway_gateways_data[] = array(
                'id'          => $gateway_id,
                'label'       => sanitize_text_field($gateway->get_title()),
                'description' => wp_kses_post($gateway->get_description()),
                'icon_url'    => sanitize_url($icon_url),
            );
        }
    }

    wp_localize_script($handle, 'paygatedottocryptogatewayData', $paygatedottocryptogateway_gateways_data);
}

/**
 * Register each PayGate gateway with the WooCommerce Cart & Checkout blocks.
 *
 * Server-side registration is required (in addition to the client-side
 * registerPaymentMethod call) so the Store API recognises the gateway as a
 * submittable payment method. Without it the block checkout sends an empty
 * payment_method and fails with "No payment method provided".
 */
add_action('woocommerce_blocks_payment_method_type_registration', function ($paygatedottocryptogateway_payment_method_registry) {
    require_once plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-blocks-integration.php';

    foreach (WC()->payment_gateways()->payment_gateways() as $gateway_id => $gateway) {
        if ('paygatedotto-crypto-payment-gateway-dynamic' === $gateway_id) {
            // The dynamic individual-coin gateway uses its own block integration
            // (defined alongside the gateway) so it can render a coin selector.
            if (class_exists('PayGateDotTo_Crypto_Payment_Gateway_Dynamic_Blocks_Integration')) {
                $paygatedottocryptogateway_payment_method_registry->register(
                    new PayGateDotTo_Crypto_Payment_Gateway_Dynamic_Blocks_Integration($gateway)
                );
            }
            continue;
        }
        if (strpos($gateway_id, 'paygatedotto-crypto-payment-gateway') === 0) {
            $paygatedottocryptogateway_payment_method_registry->register(
                new PayGateDotTo_Crypto_Payment_Gateway_Blocks_Integration($gateway_id, $gateway)
            );
        }
    }
});

/**
 * Enqueue styles for the gateway on checkout page.
 */
function paygatedottocryptogateway_enqueue_styles() {
    if (is_checkout()) {
        wp_enqueue_style(
            'paygatedottocryptogateway-styles',
            plugin_dir_url(__FILE__) . 'assets/css/paygatedottocryptogateway-payment-gateway-styles.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'assets/css/paygatedottocryptogateway-payment-gateway-styles.css')
        );
    }
}
add_action('wp_enqueue_scripts', 'paygatedottocryptogateway_enqueue_styles');

		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-multicoin.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-dynamic.php'); // Dynamic individual-coin gateway (replaces the per-coin files)

	// Conditional function that check if Checkout page use Checkout Blocks
function paygatedottocryptogateway_is_checkout_block() {
    return WC_Blocks_Utils::has_block_in_page( wc_get_page_id('checkout'), 'woocommerce/checkout' );
}

function paygatedottocryptogateway_add_notice($paygatedottocryptogateway_message, $paygatedottocryptogateway_notice_type = 'error') {
    // Check if the Checkout page is using Checkout Blocks
    if (paygatedottocryptogateway_is_checkout_block()) {
        // For blocks, throw a WooCommerce exception
        if ($paygatedottocryptogateway_notice_type === 'error') {
            throw new \WC_Data_Exception('checkout_error', esc_html($paygatedottocryptogateway_message)); 
        }
        // Handle other notice types if needed
    } else {
        // Default WooCommerce behavior
        wc_add_notice(esc_html($paygatedottocryptogateway_message), $paygatedottocryptogateway_notice_type); 
    }
}		

/**
 * Whether the given order belongs to one of this plugin's payment methods.
 */
function paygatedottocryptogateway_is_plugin_order($order)
{
    return $order instanceof WC_Order
        && strpos((string) $order->get_payment_method(), 'paygatedotto-crypto-payment-gateway') === 0;
}

/**
 * Remember which customer session placed a PayGate order.
 *
 * The payment provider confirms payment via a server-to-server callback and the
 * customer is NOT redirected back to the store (no return URL / no thank-you
 * page). So when payment completes we have no access to the buyer's session and
 * cannot clear their cart unless we recorded the session key at checkout time.
 * Runs in the customer's session for both classic and block checkout.
 */
function paygatedottocryptogateway_record_order_session($order)
{
    if (is_numeric($order)) {
        $order = wc_get_order($order);
    }
    if (! paygatedottocryptogateway_is_plugin_order($order)) {
        return;
    }
    if (function_exists('WC') && WC()->session) {
        $paygatedottocryptogateway_session_key = WC()->session->get_customer_id();
        if ($paygatedottocryptogateway_session_key) {
            $order->update_meta_data('_paygatedotto_session_key', $paygatedottocryptogateway_session_key);
            $order->save();
        }
    }
}
add_action('woocommerce_checkout_order_processed', 'paygatedottocryptogateway_record_order_session', 20, 1);
add_action('woocommerce_store_api_checkout_order_processed', 'paygatedottocryptogateway_record_order_session', 20, 1);
add_action('woocommerce_blocks_checkout_order_processed', 'paygatedottocryptogateway_record_order_session', 20, 1);

/**
 * Empty the cart belonging to a specific WooCommerce session key.
 *
 * Used from the payment-confirmation callback, which runs outside the buyer's
 * session, so WC()->cart cannot be used directly. Clears the cart-related keys
 * in the stored session row and busts the session cache.
 */
function paygatedottocryptogateway_clear_session_cart($paygatedottocryptogateway_session_key)
{
    global $wpdb;

    if (empty($paygatedottocryptogateway_session_key)) {
        return;
    }

    /*
     * The table name is composed solely from $wpdb->prefix (a trusted,
     * server-controlled value) and a hard-coded string literal — no
     * user-supplied input is involved. esc_sql() is applied as an extra
     * safety layer to satisfy static-analysis tools.
     */
    $paygatedottocryptogateway_sessions_table = esc_sql( $wpdb->prefix . 'woocommerce_sessions' );

    $paygatedottocryptogateway_cache_group = defined('WC_SESSION_CACHE_GROUP') ? WC_SESSION_CACHE_GROUP : 'wc_session_id';
    $paygatedottocryptogateway_cache_key   = 'paygatedotto_sess_' . md5($paygatedottocryptogateway_session_key);

    $paygatedottocryptogateway_session_value = wp_cache_get($paygatedottocryptogateway_cache_key, $paygatedottocryptogateway_cache_group);

    if (false === $paygatedottocryptogateway_session_value) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $paygatedottocryptogateway_session_value = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                "SELECT session_value FROM {$paygatedottocryptogateway_sessions_table} WHERE session_key = %s",
                $paygatedottocryptogateway_session_key
            )
        );
        wp_cache_set($paygatedottocryptogateway_cache_key, $paygatedottocryptogateway_session_value, $paygatedottocryptogateway_cache_group, 300);
    }

    if (null === $paygatedottocryptogateway_session_value || false === $paygatedottocryptogateway_session_value) {
        return;
    }

    $paygatedottocryptogateway_session_data = maybe_unserialize($paygatedottocryptogateway_session_value);
    if (! is_array($paygatedottocryptogateway_session_data)) {
        return;
    }

    // Each session value is itself serialised (see WC_Session::set()).
    $paygatedottocryptogateway_empty = maybe_serialize(array());
    foreach (array('cart', 'cart_totals', 'applied_coupons', 'coupon_discount_totals', 'coupon_discount_tax_totals', 'removed_cart_contents') as $paygatedottocryptogateway_key) {
        if (isset($paygatedottocryptogateway_session_data[$paygatedottocryptogateway_key])) {
            $paygatedottocryptogateway_session_data[$paygatedottocryptogateway_key] = $paygatedottocryptogateway_empty;
        }
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $wpdb->update(
        $paygatedottocryptogateway_sessions_table,
        array('session_value' => maybe_serialize($paygatedottocryptogateway_session_data)),
        array('session_key' => $paygatedottocryptogateway_session_key)
    );

    // Invalidate our cache entry and bust WooCommerce's cached copy of the session.
    wp_cache_delete($paygatedottocryptogateway_cache_key, $paygatedottocryptogateway_cache_group);
    wp_cache_delete($paygatedottocryptogateway_session_key, $paygatedottocryptogateway_cache_group);
}

/**
 * Clear the buyer's cart once a PayGate order is actually paid.
 *
 * Fires on payment_complete(), which this plugin's providers call from their
 * payment-confirmation callbacks. Works for every checkout type (classic,
 * shortcode, page builder, blocks). Unpaid/abandoned orders are never touched,
 * so an abandoned checkout keeps its cart for an easy retry.
 */
function paygatedottocryptogateway_empty_cart_on_payment_complete($order_id)
{
    $order = wc_get_order($order_id);
    if (! paygatedottocryptogateway_is_plugin_order($order)) {
        return;
    }

    // Logged-in buyers: remove the persistent cart so it is not restored later.
    $paygatedottocryptogateway_user_id = $order->get_customer_id();
    if ($paygatedottocryptogateway_user_id) {
        delete_user_meta($paygatedottocryptogateway_user_id, '_woocommerce_persistent_cart_' . get_current_blog_id());
    }

    // Clear the originating session's cart (covers guests and logged-in buyers).
    paygatedottocryptogateway_clear_session_cart($order->get_meta('_paygatedotto_session_key'));

    // If this ever runs inside the buyer's own session, clear the live cart too.
    if (function_exists('WC') && WC()->cart) {
        WC()->cart->empty_cart();
    }
}
add_action('woocommerce_payment_complete', 'paygatedottocryptogateway_empty_cart_on_payment_complete', 20, 1);

/**
 * Safety net: if a store IS configured so the buyer lands on the order-received
 * page, clear the cart there too — but only once the order is actually paid.
 *
 * This plugin keeps the buyer on the order-received page to display the payment
 * address / QR code while the order is still pending, so the cart must NOT be
 * emptied on first landing (an abandoned, unpaid checkout keeps its cart for an
 * easy retry). Once payment is confirmed the cart is cleared by
 * paygatedottocryptogateway_empty_cart_on_payment_complete(); this hook only
 * covers the case where the buyer revisits the order-received page afterwards.
 */
add_action('woocommerce_thankyou', function ($order_id) {
    if (! $order_id) {
        return;
    }
    $order = wc_get_order($order_id);
    if (! paygatedottocryptogateway_is_plugin_order($order)) {
        return;
    }
    if (! $order->is_paid()) {
        return;
    }
    if (WC()->cart && ! WC()->cart->is_empty()) {
        WC()->cart->empty_cart();
    }
});
?>