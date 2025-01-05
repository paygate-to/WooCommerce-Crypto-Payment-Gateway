<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_paygatedottocryptogateway_usdtsol_gateway');

function init_paygatedottocryptogateway_usdtsol_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

class PayGateDotTo_Crypto_Payment_Gateway_Usdtsol extends WC_Payment_Gateway {

protected $usdtsol_wallet_address;
protected $usdtsol_blockchain_fees;
protected $icon_url;

    public function __construct() {
        $this->id                 = 'paygatedotto-crypto-payment-gateway-usdtsol';
        $this->icon = esc_url(plugin_dir_url(__DIR__) . 'static/usdtsol.png');
        $this->method_title       = esc_html__('USDT Solana Crypto Payment Gateway With Instant Payouts', 'crypto-payment-gateway'); // Escaping title
        $this->method_description = esc_html__('USDT Solana Crypto Payment Gateway With Instant Payouts to your sol_usdt wallet. Allows you to accept crypto sol/usdt payments without sign up and without KYC.', 'crypto-payment-gateway'); // Escaping description
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = sanitize_text_field($this->get_option('title'));
        $this->description = sanitize_text_field($this->get_option('description'));

        // Use the configured settings for redirect and icon URLs
        $this->usdtsol_wallet_address = sanitize_text_field($this->get_option('usdtsol_wallet_address'));
		$this->usdtsol_blockchain_fees = $this->get_option('usdtsol_blockchain_fees');
        $this->icon_url     = sanitize_url($this->get_option('icon_url'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_before_thankyou', array($this, 'before_thankyou_page'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => esc_html__('Enable/Disable', 'crypto-payment-gateway'), // Escaping title
                'type'    => 'checkbox',
                'label'   => esc_html__('Enable sol_usdt payment gateway', 'crypto-payment-gateway'), // Escaping label
                'default' => 'no',
            ),
            'title' => array(
                'title'       => esc_html__('Title', 'crypto-payment-gateway'), // Escaping title
                'type'        => 'text',
                'description' => esc_html__('Payment method title that users will see during checkout.', 'crypto-payment-gateway'), // Escaping description
                'default'     => esc_html__('USDT Solana', 'crypto-payment-gateway'), // Escaping default value
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => esc_html__('Description', 'crypto-payment-gateway'), // Escaping title
                'type'        => 'textarea',
                'description' => esc_html__('Payment method description that users will see during checkout.', 'crypto-payment-gateway'), // Escaping description
                'default'     => esc_html__('Pay via crypto USDT Solana sol_usdt', 'crypto-payment-gateway'), // Escaping default value
                'desc_tip'    => true,
            ),
            'usdtsol_wallet_address' => array(
                'title'       => esc_html__('Wallet Address', 'crypto-payment-gateway'), // Escaping title
                'type'        => 'text',
                'description' => esc_html__('Insert your sol/usdt wallet address to receive instant payouts.', 'crypto-payment-gateway'), // Escaping description
                'desc_tip'    => true,
            ),
			'usdtsol_blockchain_fees' => array(
                'title'       => esc_html__('Customer Pays Blockchain Fees', 'crypto-payment-gateway'), // Escaping title
                'type'        => 'checkbox',
                'description' => esc_html__('Add estimated blockchian fees to the order total.', 'crypto-payment-gateway'), // Escaping description
                'desc_tip'    => true,
				'default' => 'no',
            ),
        );
    }
	
	 // Add this method to validate the wallet address in wp-admin
    public function process_admin_options() {
		if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')) {
    WC_Admin_Settings::add_error(__('Nonce verification failed. Please try again.', 'crypto-payment-gateway'));
    return false;
}
        $paygatedottocryptogateway_usdtsol_admin_wallet_address = isset($_POST[$this->plugin_id . $this->id . '_usdtsol_wallet_address']) ? sanitize_text_field( wp_unslash( $_POST[$this->plugin_id . $this->id . '_usdtsol_wallet_address'])) : '';

        // Check if wallet address is empty
        if (empty($paygatedottocryptogateway_usdtsol_admin_wallet_address)) {
		WC_Admin_Settings::add_error(__('Invalid Wallet Address: Please insert a valid USDT Solana wallet address.', 'crypto-payment-gateway'));
            return false;
		}

        // Proceed with the default processing if validations pass
        return parent::process_admin_options();
    }
	
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $paygatedottocryptogateway_usdtsol_currency = get_woocommerce_currency();
		$paygatedottocryptogateway_usdtsol_total = $order->get_total();
		$paygatedottocryptogateway_usdtsol_nonce = wp_create_nonce( 'paygatedottocryptogateway_usdtsol_nonce_' . $order_id );
		$paygatedottocryptogateway_usdtsol_callback = add_query_arg(array('order_id' => $order_id, 'nonce' => $paygatedottocryptogateway_usdtsol_nonce,), rest_url('paygatedottocryptogateway/v1/paygatedottocryptogateway-usdtsol/'));
		$paygatedottocryptogateway_usdtsol_email = urlencode(sanitize_email($order->get_billing_email()));
		$paygatedottocryptogateway_usdtsol_status_nonce = wp_create_nonce( 'paygatedottocryptogateway_usdtsol_status_nonce_' . $paygatedottocryptogateway_usdtsol_email );

		
$paygatedottocryptogateway_usdtsol_response = wp_remote_get('https://api.paygate.to/crypto/sol/usdt/convert.php?value=' . $paygatedottocryptogateway_usdtsol_total . '&from=' . strtolower($paygatedottocryptogateway_usdtsol_currency), array('timeout' => 30));

if (is_wp_error($paygatedottocryptogateway_usdtsol_response)) {
    // Handle error
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed due to failed currency conversion process, please try again', 'crypto-payment-gateway'), 'error');
    return null;
} else {

$paygatedottocryptogateway_usdtsol_body = wp_remote_retrieve_body($paygatedottocryptogateway_usdtsol_response);
$paygatedottocryptogateway_usdtsol_conversion_resp = json_decode($paygatedottocryptogateway_usdtsol_body, true);

if ($paygatedottocryptogateway_usdtsol_conversion_resp && isset($paygatedottocryptogateway_usdtsol_conversion_resp['value_coin'])) {
    // Escape output
    $paygatedottocryptogateway_usdtsol_final_total	= sanitize_text_field($paygatedottocryptogateway_usdtsol_conversion_resp['value_coin']);
    $paygatedottocryptogateway_usdtsol_reference_total = (float)$paygatedottocryptogateway_usdtsol_final_total;	
} else {
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed, please try again (unsupported store currency)', 'crypto-payment-gateway'), 'error');
    return null;
}	
		}
		
		if ($this->usdtsol_blockchain_fees === 'yes') {
			
			// Get the estimated feed for our crypto coin in USD fiat currency
			
		$paygatedottocryptogateway_usdtsol_feesest_response = wp_remote_get('https://api.paygate.to/crypto/sol/usdt/fees.php', array('timeout' => 30));

if (is_wp_error($paygatedottocryptogateway_usdtsol_feesest_response)) {
    // Handle error
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Failed to get estimated fees, please try again', 'crypto-payment-gateway'), 'error');
    return null;
} else {

$paygatedottocryptogateway_usdtsol_feesest_body = wp_remote_retrieve_body($paygatedottocryptogateway_usdtsol_feesest_response);
$paygatedottocryptogateway_usdtsol_feesest_conversion_resp = json_decode($paygatedottocryptogateway_usdtsol_feesest_body, true);

if ($paygatedottocryptogateway_usdtsol_feesest_conversion_resp && isset($paygatedottocryptogateway_usdtsol_feesest_conversion_resp['estimated_cost_currency']['USD'])) {
    // Escape output
    $paygatedottocryptogateway_usdtsol_feesest_final_total = sanitize_text_field($paygatedottocryptogateway_usdtsol_feesest_conversion_resp['estimated_cost_currency']['USD']);
    $paygatedottocryptogateway_usdtsol_feesest_reference_total = (float)$paygatedottocryptogateway_usdtsol_feesest_final_total;	
} else {
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Failed to get estimated fees, please try again', 'crypto-payment-gateway'), 'error');
    return null;
}	
		}

// Convert the estimated fee back to our crypto

$paygatedottocryptogateway_usdtsol_revfeesest_response = wp_remote_get('https://api.paygate.to/crypto/sol/usdt/convert.php?value=' . $paygatedottocryptogateway_usdtsol_feesest_reference_total . '&from=usd', array('timeout' => 30));

if (is_wp_error($paygatedottocryptogateway_usdtsol_revfeesest_response)) {
    // Handle error
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed due to failed currency conversion process, please try again', 'crypto-payment-gateway'), 'error');
    return null;
} else {

$paygatedottocryptogateway_usdtsol_revfeesest_body = wp_remote_retrieve_body($paygatedottocryptogateway_usdtsol_revfeesest_response);
$paygatedottocryptogateway_usdtsol_revfeesest_conversion_resp = json_decode($paygatedottocryptogateway_usdtsol_revfeesest_body, true);

if ($paygatedottocryptogateway_usdtsol_revfeesest_conversion_resp && isset($paygatedottocryptogateway_usdtsol_revfeesest_conversion_resp['value_coin'])) {
    // Escape output
    $paygatedottocryptogateway_usdtsol_revfeesest_final_total = sanitize_text_field($paygatedottocryptogateway_usdtsol_revfeesest_conversion_resp['value_coin']);
    $paygatedottocryptogateway_usdtsol_revfeesest_reference_total = (float)$paygatedottocryptogateway_usdtsol_revfeesest_final_total;
	// Calculating order total after adding the blockchain fees
	$paygatedottocryptogateway_usdtsol_payin_total = $paygatedottocryptogateway_usdtsol_reference_total + $paygatedottocryptogateway_usdtsol_revfeesest_reference_total;
} else {
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed, please try again (unsupported store currency)', 'crypto-payment-gateway'), 'error');
    return null;
}	
		}
		
		} else {
			
		$paygatedottocryptogateway_usdtsol_payin_total = $paygatedottocryptogateway_usdtsol_reference_total;	

		}
		
$paygatedottocryptogateway_usdtsol_response_minimum = wp_remote_get('https://api.paygate.to/crypto/sol/usdt/info.php', array('timeout' => 30));
if (is_wp_error($paygatedottocryptogateway_usdtsol_response_minimum)) {
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed due to failed minimum retrieval process, please try again', 'crypto-payment-gateway'), 'error');
    return null;
} else {
    $paygatedottocryptogateway_usdtsol_body_minimum = wp_remote_retrieve_body($paygatedottocryptogateway_usdtsol_response_minimum);
    $paygatedottocryptogateway_usdtsol_conversion_resp_minimum = json_decode($paygatedottocryptogateway_usdtsol_body_minimum, true);
    if ($paygatedottocryptogateway_usdtsol_conversion_resp_minimum && isset($paygatedottocryptogateway_usdtsol_conversion_resp_minimum['minimum'])) {
        $paygatedottocryptogateway_usdtsol_final_minimum = sanitize_text_field($paygatedottocryptogateway_usdtsol_conversion_resp_minimum['minimum']);
        if ($paygatedottocryptogateway_usdtsol_payin_total < $paygatedottocryptogateway_usdtsol_final_minimum) {
            paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed because the coin amount is below the minimum required', 'crypto-payment-gateway'), 'error');
            return null;
        }
    } else {
        paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed, please try again (failed to fetch minimum coin amount)', 'crypto-payment-gateway'), 'error');
        return null;
    }
}
$paygatedottocryptogateway_usdtsol_gen_wallet = wp_remote_get('https://api.paygate.to/crypto/sol/usdt/wallet.php?address=' . $this->usdtsol_wallet_address .'&callback=' . urlencode($paygatedottocryptogateway_usdtsol_callback), array('timeout' => 30));

if (is_wp_error($paygatedottocryptogateway_usdtsol_gen_wallet)) {
    // Handle error
    paygatedottocryptogateway_add_notice(__('Wallet error:', 'crypto-payment-gateway') . __('Payment could not be processed due to incorrect payout wallet settings, please contact website admin', 'crypto-payment-gateway'), 'error');
    return null;
} else {
	$paygatedottocryptogateway_usdtsol_wallet_body = wp_remote_retrieve_body($paygatedottocryptogateway_usdtsol_gen_wallet);
	$paygatedottocryptogateway_usdtsol_wallet_decbody = json_decode($paygatedottocryptogateway_usdtsol_wallet_body, true);

 // Check if decoding was successful
    if ($paygatedottocryptogateway_usdtsol_wallet_decbody && isset($paygatedottocryptogateway_usdtsol_wallet_decbody['address_in'])) {
		// Store and sanitize variables
        $paygatedottocryptogateway_usdtsol_gen_addressIn = wp_kses_post($paygatedottocryptogateway_usdtsol_wallet_decbody['address_in']);
        $paygatedottocryptogateway_usdtsol_gen_ipntoken = wp_kses_post($paygatedottocryptogateway_usdtsol_wallet_decbody['ipn_token']);
		$paygatedottocryptogateway_usdtsol_gen_callback = sanitize_url($paygatedottocryptogateway_usdtsol_wallet_decbody['callback_url']);
        
		// Generate QR code Image
		$paygatedottocryptogateway_usdtsol_genqrcode_response = wp_remote_get('https://api.paygate.to/crypto/sol/usdt/qrcode.php?address=' . $paygatedottocryptogateway_usdtsol_gen_addressIn, array('timeout' => 30));

if (is_wp_error($paygatedottocryptogateway_usdtsol_genqrcode_response)) {
    // Handle error
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Unable to generate QR code', 'crypto-payment-gateway'), 'error');
    return null;
} else {

$paygatedottocryptogateway_usdtsol_genqrcode_body = wp_remote_retrieve_body($paygatedottocryptogateway_usdtsol_genqrcode_response);
$paygatedottocryptogateway_usdtsol_genqrcode_conversion_resp = json_decode($paygatedottocryptogateway_usdtsol_genqrcode_body, true);

if ($paygatedottocryptogateway_usdtsol_genqrcode_conversion_resp && isset($paygatedottocryptogateway_usdtsol_genqrcode_conversion_resp['qr_code'])) {
    
    $paygatedottocryptogateway_usdtsol_genqrcode_pngimg = wp_kses_post($paygatedottocryptogateway_usdtsol_genqrcode_conversion_resp['qr_code']);	
	
} else {
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Unable to generate QR code', 'crypto-payment-gateway'), 'error');
    return null;
}	
		}
		
		
		// Save $usdtsolresponse in order meta data
    $order->add_meta_data('paygatedotto_usdtsol_payin_address', $paygatedottocryptogateway_usdtsol_gen_addressIn, true);
    $order->add_meta_data('paygatedotto_usdtsol_ipntoken', $paygatedottocryptogateway_usdtsol_gen_ipntoken, true);
    $order->add_meta_data('paygatedotto_usdtsol_callback', $paygatedottocryptogateway_usdtsol_gen_callback, true);
	$order->add_meta_data('paygatedotto_usdtsol_payin_amount', $paygatedottocryptogateway_usdtsol_payin_total, true);
	$order->add_meta_data('paygatedotto_usdtsol_qrcode', $paygatedottocryptogateway_usdtsol_genqrcode_pngimg, true);
	$order->add_meta_data('paygatedotto_usdtsol_nonce', $paygatedottocryptogateway_usdtsol_nonce, true);
	$order->add_meta_data('paygatedotto_usdtsol_status_nonce', $paygatedottocryptogateway_usdtsol_status_nonce, true);
    $order->save();
    } else {
        paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed, please try again (wallet address error)', 'crypto-payment-gateway'), 'error');

        return null;
    }
}

        // Redirect to payment page
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

// Show payment instructions on thankyou page
public function before_thankyou_page($order_id) {
    $order = wc_get_order($order_id);
	// Check if this is the correct payment method
    if ($order->get_payment_method() !== $this->id) {
        return;
    }
    $paygatedottogateway_crypto_total = $order->get_meta('paygatedotto_usdtsol_payin_amount', true);
    $paygatedottogateway__crypto_wallet_address = $order->get_meta('paygatedotto_usdtsol_payin_address', true);
    $paygatedottogateway_crypto_qrcode = $order->get_meta('paygatedotto_usdtsol_qrcode', true);
	$paygatedottogateway_crypto_qrcode_status_nonce = $order->get_meta('paygatedotto_usdtsol_status_nonce', true);

    // CSS
	wp_enqueue_style('paygatedottocryptogateway-usdtsol-loader-css', plugin_dir_url( __DIR__ ) . 'static/payment-status.css', array(), '1.0.0');

    // Title
    echo '<div id="paygatedottocryptogateway-wrapper"><h1 style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '">'
        . esc_html__('Please Complete Your Payment', 'crypto-payment-gateway') 
        . '</h1>';

    // QR Code Image
    echo '<div style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '"><img class="' . esc_attr('paygatedottoqrcodeimg') . '" style="' . esc_attr('text-align:center;max-width:80%;margin:0 auto;') . '" src="data:image/png;base64,' 
        . esc_attr($paygatedottogateway_crypto_qrcode) . '" alt="' . esc_attr('sol/usdt Payment Address') . '"/></div>';

    // Payment Instructions
	/* translators: 1: Amount of cryptocurrency to be sent, 2: Name of the cryptocurrency */
    echo '<p style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '">' . sprintf( esc_html__('Please send %1$s %2$s to the following address:', 'crypto-payment-gateway'), '<br><strong>' . esc_html($paygatedottogateway_crypto_total) . '</strong>', esc_html__('sol/usdt', 'crypto-payment-gateway') ) . '</p>';


    // Wallet Address
    echo '<p style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '">'
        . '<strong>' . esc_html($paygatedottogateway__crypto_wallet_address) . '</strong>'
        . '</p><br><hr></div>';
		
	echo '<div class="' . esc_attr('paygatedottocryptogateway-unpaid') . '" id="' . esc_attr('paygatedotto-payment-status-message') . '" style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '">'
                . esc_html__('Waiting for payment', 'crypto-payment-gateway')
                . '</div><br><hr><br>';	

  // Enqueue jQuery and the external script
    wp_enqueue_script('jquery');
    wp_enqueue_script('paygatedottocryptogateway-check-status', plugin_dir_url(__DIR__) . 'assets/js/paygatedottocryptogateway-payment-status-check.js?order_id=' . esc_attr($order_id) . '&nonce=' . esc_attr($paygatedottogateway_crypto_qrcode_status_nonce) . '&tickerstring=usdtsol', array('jquery'), '1.0.0', true);

}



public function paygatedotto_crypto_payment_gateway_get_icon_url() {
        return !empty($this->icon) ? esc_url($this->icon) : '';
    }
}

function paygatedotto_add_instant_payment_gateway_usdtsol($gateways) {
    $gateways[] = 'PayGateDotTo_Crypto_Payment_Gateway_Usdtsol';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'paygatedotto_add_instant_payment_gateway_usdtsol');
}

// Add custom endpoint for reading crypto payment status

   function paygatedottocryptogateway_usdtsol_check_order_status_rest_endpoint() {
        register_rest_route('paygatedottocryptogateway/v1', '/paygatedottocryptogateway-check-order-status-usdtsol/', array(
            'methods'  => 'GET',
            'callback' => 'paygatedottocryptogateway_usdtsol_check_order_status_callback',
            'permission_callback' => '__return_true',
        ));
    }

    add_action('rest_api_init', 'paygatedottocryptogateway_usdtsol_check_order_status_rest_endpoint');

    function paygatedottocryptogateway_usdtsol_check_order_status_callback($request) {
        $order_id = absint($request->get_param('order_id'));
		$paygatedottocryptogateway_usdtsol_live_status_nonce = sanitize_text_field($request->get_param('nonce'));

        if (empty($order_id)) {
            return new WP_Error('missing_order_id', __('Order ID parameter is missing.', 'crypto-payment-gateway'), array('status' => 400));
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('invalid_order', __('Invalid order ID.', 'crypto-payment-gateway'), array('status' => 404));
        }
		
		// Verify stored status nonce

        if ( empty( $paygatedottocryptogateway_usdtsol_live_status_nonce ) || $order->get_meta('paygatedotto_usdtsol_status_nonce', true) !== $paygatedottocryptogateway_usdtsol_live_status_nonce ) {
        return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'crypto-payment-gateway' ), array( 'status' => 403 ) );
    }
        return array('status' => $order->get_status());
    }

// Add custom endpoint for changing order status
function paygatedottocryptogateway_usdtsol_change_order_status_rest_endpoint() {
    // Register custom route
    register_rest_route( 'paygatedottocryptogateway/v1', '/paygatedottocryptogateway-usdtsol/', array(
        'methods'  => 'GET',
        'callback' => 'paygatedottocryptogateway_usdtsol_change_order_status_callback',
        'permission_callback' => '__return_true',
    ));
}
add_action( 'rest_api_init', 'paygatedottocryptogateway_usdtsol_change_order_status_rest_endpoint' );

// Callback function to change order status
function paygatedottocryptogateway_usdtsol_change_order_status_callback( $request ) {
    $order_id = absint($request->get_param( 'order_id' ));
	$paygatedottocryptogateway_usdtsolgetnonce = sanitize_text_field($request->get_param( 'nonce' ));
	$paygatedottocryptogateway_usdtsolpaid_value_coin = sanitize_text_field($request->get_param('value_coin'));
	$paygatedottocryptogateway_usdtsol_paid_coin_name = sanitize_text_field($request->get_param('coin'));
	$paygatedottocryptogateway_usdtsol_paid_txid_in = sanitize_text_field($request->get_param('txid_in'));

    // Check if order ID parameter exists
    if ( empty( $order_id ) ) {
        return new WP_Error( 'missing_order_id', __( 'Order ID parameter is missing.', 'crypto-payment-gateway' ), array( 'status' => 400 ) );
    }

    // Get order object
    $order = wc_get_order( $order_id );

    // Check if order exists
    if ( ! $order ) {
        return new WP_Error( 'invalid_order', __( 'Invalid order ID.', 'crypto-payment-gateway' ), array( 'status' => 404 ) );
    }
	
	// Verify nonce
    if ( empty( $paygatedottocryptogateway_usdtsolgetnonce ) || $order->get_meta('paygatedotto_usdtsol_nonce', true) !== $paygatedottocryptogateway_usdtsolgetnonce ) {
        return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'crypto-payment-gateway' ), array( 'status' => 403 ) );
    }

    // Check if the order is pending and payment method is 'paygatedotto-crypto-payment-gateway-usdtsol'
    if ( $order && !in_array($order->get_status(), ['processing', 'completed'], true) && 'paygatedotto-crypto-payment-gateway-usdtsol' === $order->get_payment_method() ) {
		
		// Get the expected amount and coin
	$paygatedottocryptogateway_usdtsolexpected_amount = $order->get_meta('paygatedotto_usdtsol_payin_amount', true);

	
		if ( $paygatedottocryptogateway_usdtsolpaid_value_coin < $paygatedottocryptogateway_usdtsolexpected_amount || $paygatedottocryptogateway_usdtsol_paid_coin_name !== 'sol_usdt') {
			// Mark the order as failed and add an order note
/* translators: 1: Paid value in coin, 2: Paid coin name, 3: Expected amount, 4: Transaction ID */			
$order->update_status('failed', sprintf(__( '[Order Failed] Customer sent %1$s %2$s instead of %3$s sol_usdt. TXID: %4$s', 'crypto-payment-gateway' ), $paygatedottocryptogateway_usdtsolpaid_value_coin, $paygatedottocryptogateway_usdtsol_paid_coin_name, $paygatedottocryptogateway_usdtsolexpected_amount, $paygatedottocryptogateway_usdtsol_paid_txid_in));
/* translators: 1: Paid value in coin, 2: Paid coin name, 3: Expected amount, 4: Transaction ID */
$order->add_order_note(sprintf( __( '[Order Failed] Customer sent %1$s %2$s instead of %3$s sol_usdt. TXID: %4$s', 'crypto-payment-gateway' ), $paygatedottocryptogateway_usdtsolpaid_value_coin, $paygatedottocryptogateway_usdtsol_paid_coin_name, $paygatedottocryptogateway_usdtsolexpected_amount, $paygatedottocryptogateway_usdtsol_paid_txid_in));
            return array( 'message' => 'Order status changed to failed due to partial payment or incorrect coin. Please check order notes' );
			
		} else {
        // Change order status to processing
		$order->payment_complete();
		/* translators: 1: Paid value in coin, 2: Paid coin name, 3: Transaction ID */
		$order->update_status('processing', sprintf( __( '[Payment completed] Customer sent %1$s %2$s TXID:%3$s', 'crypto-payment-gateway' ), $paygatedottocryptogateway_usdtsolpaid_value_coin, $paygatedottocryptogateway_usdtsol_paid_coin_name, $paygatedottocryptogateway_usdtsol_paid_txid_in));

// Return success response
/* translators: 1: Paid value in coin, 2: Paid coin name, 3: Transaction ID */
$order->add_order_note(sprintf( __( '[Payment completed] Customer sent %1$s %2$s TXID:%3$s', 'crypto-payment-gateway' ), $paygatedottocryptogateway_usdtsolpaid_value_coin, $paygatedottocryptogateway_usdtsol_paid_coin_name, $paygatedottocryptogateway_usdtsol_paid_txid_in));
        return array( 'message' => 'Order status changed to processing.' );
		}
    } else {
        // Return error response if conditions are not met
        return new WP_Error( 'order_not_eligible', __( 'Order is not eligible for status change.', 'crypto-payment-gateway' ), array( 'status' => 400 ) );
    }
}
?>