<?php
/**
 * Plugin Name: Crypto Payment Gateway with Instant Payouts
 * Plugin URI: https://paygate.to/crypto-payment-gateway-no-kyc-instant-payouts/
 * Description: Cryptocurrency Payment Gateway with instant payouts to your wallet and without KYC hosted directly on your website.
 * Version: 1.1.2
 * Requires Plugins: woocommerce
 * Requires at least: 5.8
 * Tested up to: 6.8
 * WC requires at least: 5.8
 * WC tested up to: 10.3.6
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
 * Enqueue block assets for the gateway.
 */
function paygatedottocryptogateway_enqueue_block_assets() {
    // Fetch all enabled WooCommerce payment gateways
    $paygatedottocryptogateway_available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
    $paygatedottocryptogateway_gateways_data = array();

    foreach ($paygatedottocryptogateway_available_gateways as $gateway_id => $gateway) {
		if (strpos($gateway_id, 'paygatedotto-crypto-payment-gateway') === 0) {
        $icon_url = method_exists($gateway, 'paygatedotto_crypto_payment_gateway_get_icon_url') ? $gateway->paygatedotto_crypto_payment_gateway_get_icon_url() : '';
        $paygatedottocryptogateway_gateways_data[] = array(
            'id' => sanitize_key($gateway_id),
            'label' => sanitize_text_field($gateway->get_title()),
            'description' => wp_kses_post($gateway->get_description()),
            'icon_url' => sanitize_url($icon_url),
        );
		}
    }

    wp_enqueue_script(
        'paygatedottocryptogateway-block-support',
        plugin_dir_url(__FILE__) . 'assets/js/paygatedottocryptogateway-block-checkout-support.js',
        array('wc-blocks-registry', 'wp-element', 'wp-i18n', 'wp-components', 'wp-blocks', 'wp-editor'),
        filemtime(plugin_dir_path(__FILE__) . 'assets/js/paygatedottocryptogateway-block-checkout-support.js'),
        true
    );

    // Localize script with gateway data
    wp_localize_script(
        'paygatedottocryptogateway-block-support',
        'paygatedottocryptogatewayData',
        $paygatedottocryptogateway_gateways_data
    );
}
add_action('enqueue_block_assets', 'paygatedottocryptogateway_enqueue_block_assets');

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
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-btc.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-bch.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-ltc.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-doge.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-oneinchbep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-adabep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-bnbbep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-btcbbep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-cakebep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-daibep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-dogebep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-ethbep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-ltcbep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-phptbep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-shibbep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdcbep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usd1bep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdtbep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-xrpbep20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-oneincherc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-arberc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-bnberc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-daierc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-linkerc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-pepeerc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-shiberc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-tusdtrc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdcerc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdterc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-cbbtcerc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-ondoerc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-polerc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usd1erc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-wxrperc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-arbarbitrum.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-daiarbitrum.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-etharbitrum.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-linkarbitrum.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-pepearbitrum.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdcarbitrum.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdcearbitrum.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-pyusdarbitrum.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdt0arbitrum.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-avaxpolygon.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-polpolygon.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdcpolygon.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdcepolygon.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdtpolygon.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-wethpolygon.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-avaxavaxc.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdcavaxc.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdceavaxc.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdtavaxc.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-wavaxavaxc.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-wetheavaxc.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-daibase.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-ethbase.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdcbase.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-cbbtcbase.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdtbase.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-daioptimism.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-ethoptimism.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-linkoptimism.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-opoptimism.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdcoptimism.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdceoptimism.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdtoptimism.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdt0optimism.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-eth.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-btctrc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-inrttrc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdttrc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usddtrc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-trx.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-pyusderc20.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-solsol.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdcsol.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-usdtsol.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-eurcsol.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-wbtcsol.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-wethsol.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-cbbtcsol.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-pyusdsol.php'); // Include the payment gateway class
		include_once(plugin_dir_path(__FILE__) . 'includes/class-paygatedotto-crypto-payment-gateway-trumpsol.php'); // Include the payment gateway class

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
?>