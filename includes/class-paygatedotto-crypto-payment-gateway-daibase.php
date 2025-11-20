<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_paygatedottocryptogateway_daibase_gateway');

function init_paygatedottocryptogateway_daibase_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

class PayGateDotTo_Crypto_Payment_Gateway_Daibase extends WC_Payment_Gateway {

protected $daibase_wallet_address;
protected $daibase_blockchain_fees;
protected $daibase_tolerance_percentage;
protected $icon_url;

    public function __construct() {
        $this->id                 = 'paygatedotto-crypto-payment-gateway-daibase';
        $this->icon = esc_url(plugin_dir_url(__DIR__) . 'static/daibase.png');
        $this->method_title       = esc_html__('Dai Token base Crypto Payment Gateway With Instant Payouts', 'crypto-payment-gateway'); // Escaping title
        $this->method_description = esc_html__('Dai Token base Crypto Payment Gateway With Instant Payouts to your base_dai wallet. Allows you to accept crypto base/dai payments without sign up and without KYC.', 'crypto-payment-gateway'); // Escaping description
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = sanitize_text_field($this->get_option('title'));
        $this->description = sanitize_text_field($this->get_option('description'));

        // Use the configured settings for redirect and icon URLs
        $this->daibase_wallet_address = sanitize_text_field($this->get_option('daibase_wallet_address'));
		$this->daibase_tolerance_percentage = sanitize_text_field($this->get_option('daibase_tolerance_percentage'));
		$this->daibase_blockchain_fees = $this->get_option('daibase_blockchain_fees');
        $this->icon_url     = sanitize_url($this->get_option('icon_url'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		add_action('woocommerce_before_thankyou', array($this, 'before_thankyou_page'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => esc_html__('Enable/Disable', 'crypto-payment-gateway'), // Escaping title
                'type'    => 'checkbox',
                'label'   => esc_html__('Enable base_dai payment gateway', 'crypto-payment-gateway'), // Escaping label
                'default' => 'no',
            ),
            'title' => array(
                'title'       => esc_html__('Title', 'crypto-payment-gateway'), // Escaping title
                'type'        => 'text',
                'description' => esc_html__('Payment method title that users will see during checkout.', 'crypto-payment-gateway'), // Escaping description
                'default'     => esc_html__('Dai Token base', 'crypto-payment-gateway'), // Escaping default value
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => esc_html__('Description', 'crypto-payment-gateway'), // Escaping title
                'type'        => 'textarea',
                'description' => esc_html__('Payment method description that users will see during checkout.', 'crypto-payment-gateway'), // Escaping description
                'default'     => esc_html__('Pay via crypto Dai Token base base_dai', 'crypto-payment-gateway'), // Escaping default value
                'desc_tip'    => true,
            ),
            'daibase_wallet_address' => array(
                'title'       => esc_html__('Wallet Address', 'crypto-payment-gateway'), // Escaping title
                'type'        => 'text',
                'description' => esc_html__('Insert your base/dai wallet address to receive instant payouts.', 'crypto-payment-gateway'), // Escaping description
                'desc_tip'    => true,
            ),
            'daibase_tolerance_percentage' => array(
                'title'       => esc_html__('Underpaid Tolerance', 'crypto-payment-gateway'),
                'type'        => 'select',
                'description' => esc_html__('Select percentage to tolerate underpayment when a customer sends less crypto than the required amount.', 'crypto-payment-gateway'),
                'desc_tip'    => true,
                'default'     => '1',
                'options'     => array(
                    '1'    => '0%',
                    '0.99' => '1%',
                    '0.98' => '2%',
                    '0.97' => '3%',
                    '0.96' => '4%',
                    '0.95' => '5%',
                    '0.94' => '6%',
                    '0.93' => '7%',
                    '0.92' => '8%',
                    '0.91' => '9%',
                    '0.90' => '10%'
                ),
            ),
			'daibase_blockchain_fees' => array(
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
        $paygatedottocryptogateway_daibase_admin_wallet_address = isset($_POST[$this->plugin_id . $this->id . '_daibase_wallet_address']) ? sanitize_text_field( wp_unslash( $_POST[$this->plugin_id . $this->id . '_daibase_wallet_address'])) : '';

        // Check if wallet address is empty
        if (empty($paygatedottocryptogateway_daibase_admin_wallet_address)) {
		WC_Admin_Settings::add_error(__('Invalid Wallet Address: Please insert a valid Dai Token base wallet address.', 'crypto-payment-gateway'));
            return false;
		}

        // Proceed with the default processing if validations pass
        return parent::process_admin_options();
    }
	
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $paygatedottocryptogateway_daibase_currency = get_woocommerce_currency();
		$paygatedottocryptogateway_daibase_total = $order->get_total();
		$paygatedottocryptogateway_daibase_nonce = wp_create_nonce( 'paygatedottocryptogateway_daibase_nonce_' . $order_id );
		$paygatedottocryptogateway_daibase_tolerance_percentage = $this->daibase_tolerance_percentage;
		$paygatedottocryptogateway_daibase_callback = add_query_arg(array('order_id' => $order_id, 'nonce' => $paygatedottocryptogateway_daibase_nonce,), rest_url('paygatedottocryptogateway/v1/paygatedottocryptogateway-daibase/'));
		$paygatedottocryptogateway_daibase_email = urlencode(sanitize_email($order->get_billing_email()));
		$paygatedottocryptogateway_daibase_status_nonce = wp_create_nonce( 'paygatedottocryptogateway_daibase_status_nonce_' . $paygatedottocryptogateway_daibase_email );

		
$paygatedottocryptogateway_daibase_response = wp_remote_get('https://api.paygate.to/crypto/base/dai/convert.php?value=' . $paygatedottocryptogateway_daibase_total . '&from=' . strtolower($paygatedottocryptogateway_daibase_currency), array('timeout' => 30));

if (is_wp_error($paygatedottocryptogateway_daibase_response)) {
    // Handle error
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed due to failed currency conversion process, please try again', 'crypto-payment-gateway'), 'error');
    return null;
} else {

$paygatedottocryptogateway_daibase_body = wp_remote_retrieve_body($paygatedottocryptogateway_daibase_response);
$paygatedottocryptogateway_daibase_conversion_resp = json_decode($paygatedottocryptogateway_daibase_body, true);

if ($paygatedottocryptogateway_daibase_conversion_resp && isset($paygatedottocryptogateway_daibase_conversion_resp['value_coin'])) {
    // Escape output
    $paygatedottocryptogateway_daibase_final_total	= sanitize_text_field($paygatedottocryptogateway_daibase_conversion_resp['value_coin']);
    $paygatedottocryptogateway_daibase_reference_total = (float)$paygatedottocryptogateway_daibase_final_total;	
} else {
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed, please try again (unsupported store currency)', 'crypto-payment-gateway'), 'error');
    return null;
}	
		}
		
		if ($this->daibase_blockchain_fees === 'yes') {
			
			// Get the estimated feed for our crypto coin in USD fiat currency
			
		$paygatedottocryptogateway_daibase_feesest_response = wp_remote_get('https://api.paygate.to/crypto/base/dai/fees.php', array('timeout' => 30));

if (is_wp_error($paygatedottocryptogateway_daibase_feesest_response)) {
    // Handle error
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Failed to get estimated fees, please try again', 'crypto-payment-gateway'), 'error');
    return null;
} else {

$paygatedottocryptogateway_daibase_feesest_body = wp_remote_retrieve_body($paygatedottocryptogateway_daibase_feesest_response);
$paygatedottocryptogateway_daibase_feesest_conversion_resp = json_decode($paygatedottocryptogateway_daibase_feesest_body, true);

if ($paygatedottocryptogateway_daibase_feesest_conversion_resp && isset($paygatedottocryptogateway_daibase_feesest_conversion_resp['estimated_cost_currency']['USD'])) {
    // Escape output
    $paygatedottocryptogateway_daibase_feesest_final_total = sanitize_text_field($paygatedottocryptogateway_daibase_feesest_conversion_resp['estimated_cost_currency']['USD']);
    $paygatedottocryptogateway_daibase_feesest_reference_total = (float)$paygatedottocryptogateway_daibase_feesest_final_total;	
} else {
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Failed to get estimated fees, please try again', 'crypto-payment-gateway'), 'error');
    return null;
}	
		}

// Convert the estimated fee back to our crypto

$paygatedottocryptogateway_daibase_revfeesest_response = wp_remote_get('https://api.paygate.to/crypto/base/dai/convert.php?value=' . $paygatedottocryptogateway_daibase_feesest_reference_total . '&from=usd', array('timeout' => 30));

if (is_wp_error($paygatedottocryptogateway_daibase_revfeesest_response)) {
    // Handle error
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed due to failed currency conversion process, please try again', 'crypto-payment-gateway'), 'error');
    return null;
} else {

$paygatedottocryptogateway_daibase_revfeesest_body = wp_remote_retrieve_body($paygatedottocryptogateway_daibase_revfeesest_response);
$paygatedottocryptogateway_daibase_revfeesest_conversion_resp = json_decode($paygatedottocryptogateway_daibase_revfeesest_body, true);

if ($paygatedottocryptogateway_daibase_revfeesest_conversion_resp && isset($paygatedottocryptogateway_daibase_revfeesest_conversion_resp['value_coin'])) {
    // Escape output
    $paygatedottocryptogateway_daibase_revfeesest_final_total = sanitize_text_field($paygatedottocryptogateway_daibase_revfeesest_conversion_resp['value_coin']);
    $paygatedottocryptogateway_daibase_revfeesest_reference_total = (float)$paygatedottocryptogateway_daibase_revfeesest_final_total;
	// Calculating order total after adding the blockchain fees
	$paygatedottocryptogateway_daibase_payin_total = $paygatedottocryptogateway_daibase_reference_total + $paygatedottocryptogateway_daibase_revfeesest_reference_total;
} else {
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed, please try again (unsupported store currency)', 'crypto-payment-gateway'), 'error');
    return null;
}	
		}
		
		} else {
			
		$paygatedottocryptogateway_daibase_payin_total = $paygatedottocryptogateway_daibase_reference_total;	

		}
		
$paygatedottocryptogateway_daibase_response_minimum = wp_remote_get('https://api.paygate.to/crypto/base/dai/info.php', array('timeout' => 30));
if (is_wp_error($paygatedottocryptogateway_daibase_response_minimum)) {
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed due to failed minimum retrieval process, please try again', 'crypto-payment-gateway'), 'error');
    return null;
} else {
    $paygatedottocryptogateway_daibase_body_minimum = wp_remote_retrieve_body($paygatedottocryptogateway_daibase_response_minimum);
    $paygatedottocryptogateway_daibase_conversion_resp_minimum = json_decode($paygatedottocryptogateway_daibase_body_minimum, true);
    if ($paygatedottocryptogateway_daibase_conversion_resp_minimum && isset($paygatedottocryptogateway_daibase_conversion_resp_minimum['minimum'])) {
        $paygatedottocryptogateway_daibase_final_minimum = sanitize_text_field($paygatedottocryptogateway_daibase_conversion_resp_minimum['minimum']);
        if ($paygatedottocryptogateway_daibase_payin_total < $paygatedottocryptogateway_daibase_final_minimum) {
            paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed because the coin amount is below the minimum required', 'crypto-payment-gateway'), 'error');
            return null;
        }
    } else {
        paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed, please try again (failed to fetch minimum coin amount)', 'crypto-payment-gateway'), 'error');
        return null;
    }
}
$paygatedottocryptogateway_daibase_gen_wallet = wp_remote_get('https://api.paygate.to/crypto/base/dai/wallet.php?address=' . $this->daibase_wallet_address .'&callback=' . urlencode($paygatedottocryptogateway_daibase_callback), array('timeout' => 30));

if (is_wp_error($paygatedottocryptogateway_daibase_gen_wallet)) {
    // Handle error
    paygatedottocryptogateway_add_notice(__('Wallet error:', 'crypto-payment-gateway') . __('Payment could not be processed due to incorrect payout wallet settings, please contact website admin', 'crypto-payment-gateway'), 'error');
    return null;
} else {
	$paygatedottocryptogateway_daibase_wallet_body = wp_remote_retrieve_body($paygatedottocryptogateway_daibase_gen_wallet);
	$paygatedottocryptogateway_daibase_wallet_decbody = json_decode($paygatedottocryptogateway_daibase_wallet_body, true);

 // Check if decoding was successful
    if ($paygatedottocryptogateway_daibase_wallet_decbody && isset($paygatedottocryptogateway_daibase_wallet_decbody['address_in'])) {
		// Store and sanitize variables
        $paygatedottocryptogateway_daibase_gen_addressIn = wp_kses_post($paygatedottocryptogateway_daibase_wallet_decbody['address_in']);
        $paygatedottocryptogateway_daibase_gen_ipntoken = wp_kses_post($paygatedottocryptogateway_daibase_wallet_decbody['ipn_token']);
		$paygatedottocryptogateway_daibase_gen_callback = sanitize_url($paygatedottocryptogateway_daibase_wallet_decbody['callback_url']);
        
		// Generate QR code Image
		$paygatedottocryptogateway_daibase_genqrcode_response = wp_remote_get('https://api.paygate.to/crypto/base/dai/qrcode.php?address=' . $paygatedottocryptogateway_daibase_gen_addressIn, array('timeout' => 30));

if (is_wp_error($paygatedottocryptogateway_daibase_genqrcode_response)) {
    // Handle error
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Unable to generate QR code', 'crypto-payment-gateway'), 'error');
    return null;
} else {

$paygatedottocryptogateway_daibase_genqrcode_body = wp_remote_retrieve_body($paygatedottocryptogateway_daibase_genqrcode_response);
$paygatedottocryptogateway_daibase_genqrcode_conversion_resp = json_decode($paygatedottocryptogateway_daibase_genqrcode_body, true);

if ($paygatedottocryptogateway_daibase_genqrcode_conversion_resp && isset($paygatedottocryptogateway_daibase_genqrcode_conversion_resp['qr_code'])) {
    
    $paygatedottocryptogateway_daibase_genqrcode_pngimg = wp_kses_post($paygatedottocryptogateway_daibase_genqrcode_conversion_resp['qr_code']);	
	
} else {
    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Unable to generate QR code', 'crypto-payment-gateway'), 'error');
    return null;
}	
		}
		
		
		// Save $daibaseresponse in order meta data
    $order->add_meta_data('paygatedotto_daibase_payin_address', $paygatedottocryptogateway_daibase_gen_addressIn, true);
    $order->add_meta_data('paygatedotto_daibase_ipntoken', $paygatedottocryptogateway_daibase_gen_ipntoken, true);
    $order->add_meta_data('paygatedotto_daibase_callback', $paygatedottocryptogateway_daibase_gen_callback, true);
	$order->add_meta_data('paygatedotto_daibase_payin_amount', $paygatedottocryptogateway_daibase_payin_total, true);
	$order->add_meta_data('paygatedotto_daibase_tolerance_percentage', $paygatedottocryptogateway_daibase_tolerance_percentage, true);
	$order->add_meta_data('paygatedotto_daibase_qrcode', $paygatedottocryptogateway_daibase_genqrcode_pngimg, true);
	$order->add_meta_data('paygatedotto_daibase_nonce', $paygatedottocryptogateway_daibase_nonce, true);
	$order->add_meta_data('paygatedotto_daibase_status_nonce', $paygatedottocryptogateway_daibase_status_nonce, true);
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
    $paygatedottogateway_crypto_total = $order->get_meta('paygatedotto_daibase_payin_amount', true);
    $paygatedottogateway__crypto_wallet_address = $order->get_meta('paygatedotto_daibase_payin_address', true);
    $paygatedottogateway_crypto_qrcode = $order->get_meta('paygatedotto_daibase_qrcode', true);
	$paygatedottogateway_crypto_qrcode_status_nonce = $order->get_meta('paygatedotto_daibase_status_nonce', true);

    // CSS
	wp_enqueue_style('paygatedottocryptogateway-daibase-loader-css', plugin_dir_url( __DIR__ ) . 'static/payment-status.css', array(), '1.0.0');

    // Title
    echo '<div id="paygatedottocryptogateway-wrapper"><h1 style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '">'
        . esc_html__('Please Complete Your Payment', 'crypto-payment-gateway') 
        . '</h1>';

    // QR Code Image
    echo '<div style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '"><img class="' . esc_attr('paygatedottoqrcodeimg') . '" style="' . esc_attr('text-align:center;max-width:80%;margin:0 auto;') . '" src="data:image/png;base64,' 
        . esc_attr($paygatedottogateway_crypto_qrcode) . '" alt="' . esc_attr('base/dai Payment Address') . '"/></div>';

    // Payment Instructions
	/* translators: 1: Amount of cryptocurrency to be sent, 2: Name of the cryptocurrency */
    echo '<p style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '">' . sprintf( esc_html__('Please send %1$s %2$s to the following address:', 'crypto-payment-gateway'), '<br><strong>' . esc_html($paygatedottogateway_crypto_total) . '</strong>', esc_html__('base/dai', 'crypto-payment-gateway') ) . '</p>';


    // Wallet Address
    echo '<p style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '">'
        . '<strong>' . esc_html($paygatedottogateway__crypto_wallet_address) . '</strong>'
        . '</p><br><hr></div>';
		
	echo '<div class="' . esc_attr('paygatedottocryptogateway-unpaid') . '" id="' . esc_attr('paygatedotto-payment-status-message') . '" style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '">'
                . esc_html__('Waiting for payment', 'crypto-payment-gateway')
                . '</div><br><hr><br>';	

  // Enqueue jQuery and the external script
    wp_enqueue_script('jquery');
    wp_enqueue_script('paygatedottocryptogateway-check-status', plugin_dir_url(__DIR__) . 'assets/js/paygatedottocryptogateway-payment-status-check.js?order_id=' . esc_attr($order_id) . '&nonce=' . esc_attr($paygatedottogateway_crypto_qrcode_status_nonce) . '&tickerstring=daibase', array('jquery'), '1.0.0', true);

}



public function paygatedotto_crypto_payment_gateway_get_icon_url() {
        return !empty($this->icon) ? esc_url($this->icon) : '';
    }
}

function paygatedottocryptogateway_add_instant_payment_gateway_daibase($gateways) {
    $gateways[] = 'PayGateDotTo_Crypto_Payment_Gateway_Daibase';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'paygatedottocryptogateway_add_instant_payment_gateway_daibase');
}

// Add custom endpoint for reading crypto payment status

   function paygatedottocryptogateway_daibase_check_order_status_rest_endpoint() {
        register_rest_route('paygatedottocryptogateway/v1', '/paygatedottocryptogateway-check-order-status-daibase/', array(
            'methods'  => 'GET',
            'callback' => 'paygatedottocryptogateway_daibase_check_order_status_callback',
            'permission_callback' => '__return_true',
        ));
    }

    add_action('rest_api_init', 'paygatedottocryptogateway_daibase_check_order_status_rest_endpoint');

    function paygatedottocryptogateway_daibase_check_order_status_callback($request) {
        $order_id = absint($request->get_param('order_id'));
		$paygatedottocryptogateway_daibase_live_status_nonce = sanitize_text_field($request->get_param('nonce'));

        if (empty($order_id)) {
            return new WP_Error('missing_order_id', __('Order ID parameter is missing.', 'crypto-payment-gateway'), array('status' => 400));
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('invalid_order', __('Invalid order ID.', 'crypto-payment-gateway'), array('status' => 404));
        }
		
		// Verify stored status nonce

        if ( empty( $paygatedottocryptogateway_daibase_live_status_nonce ) || $order->get_meta('paygatedotto_daibase_status_nonce', true) !== $paygatedottocryptogateway_daibase_live_status_nonce ) {
        return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'crypto-payment-gateway' ), array( 'status' => 403 ) );
    }
        return array('status' => $order->get_status());
    }

// Add custom endpoint for changing order status
function paygatedottocryptogateway_daibase_change_order_status_rest_endpoint() {
    // Register custom route
    register_rest_route( 'paygatedottocryptogateway/v1', '/paygatedottocryptogateway-daibase/', array(
        'methods'  => 'GET',
        'callback' => 'paygatedottocryptogateway_daibase_change_order_status_callback',
        'permission_callback' => '__return_true',
    ));
}
add_action( 'rest_api_init', 'paygatedottocryptogateway_daibase_change_order_status_rest_endpoint' );

// Callback function to change order status
function paygatedottocryptogateway_daibase_change_order_status_callback( $request ) {
    $order_id = absint($request->get_param( 'order_id' ));
	$paygatedottocryptogateway_daibasegetnonce = sanitize_text_field($request->get_param( 'nonce' ));
	$paygatedottocryptogateway_daibasepaid_value_coin = sanitize_text_field($request->get_param('value_coin'));
	$paygatedottocryptogateway_daibase_paid_coin_name = sanitize_text_field($request->get_param('coin'));
	$paygatedottocryptogateway_daibase_paid_txid_in = sanitize_text_field($request->get_param('txid_in'));

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
    if ( empty( $paygatedottocryptogateway_daibasegetnonce ) || $order->get_meta('paygatedotto_daibase_nonce', true) !== $paygatedottocryptogateway_daibasegetnonce ) {
        return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'crypto-payment-gateway' ), array( 'status' => 403 ) );
    }

    // Check if the order is pending and payment method is 'paygatedotto-crypto-payment-gateway-daibase'
    if ( $order && !in_array($order->get_status(), ['processing', 'completed'], true) && 'paygatedotto-crypto-payment-gateway-daibase' === $order->get_payment_method() ) {
		
		// Get the expected amount and coin
	$paygatedottocryptogateway_daibaseexpected_amount = $order->get_meta('paygatedotto_daibase_payin_amount', true) * $order->get_meta('paygatedotto_daibase_tolerance_percentage', true);

	
		if ( $paygatedottocryptogateway_daibasepaid_value_coin < $paygatedottocryptogateway_daibaseexpected_amount || $paygatedottocryptogateway_daibase_paid_coin_name !== 'base_dai') {
			// Mark the order as failed and add an order note
/* translators: 1: Paid value in coin, 2: Paid coin name, 3: Expected amount, 4: Transaction ID */			
$order->update_status('failed', sprintf(__( '[Order Failed] Customer sent %1$s %2$s instead of %3$s base_dai. TXID: %4$s', 'crypto-payment-gateway' ), $paygatedottocryptogateway_daibasepaid_value_coin, $paygatedottocryptogateway_daibase_paid_coin_name, $paygatedottocryptogateway_daibaseexpected_amount, $paygatedottocryptogateway_daibase_paid_txid_in));
/* translators: 1: Paid value in coin, 2: Paid coin name, 3: Expected amount, 4: Transaction ID */
$order->add_order_note(sprintf( __( '[Order Failed] Customer sent %1$s %2$s instead of %3$s base_dai. TXID: %4$s', 'crypto-payment-gateway' ), $paygatedottocryptogateway_daibasepaid_value_coin, $paygatedottocryptogateway_daibase_paid_coin_name, $paygatedottocryptogateway_daibaseexpected_amount, $paygatedottocryptogateway_daibase_paid_txid_in));
            return array( 'message' => 'Order status changed to failed due to partial payment or incorrect coin. Please check order notes' );
			
		} else {
        // Change order status to processing
		$order->payment_complete();


// Return success response
/* translators: 1: Paid value in coin, 2: Paid coin name, 3: Transaction ID */
$order->add_order_note(sprintf( __( '[Payment completed] Customer sent %1$s %2$s TXID:%3$s', 'crypto-payment-gateway' ), $paygatedottocryptogateway_daibasepaid_value_coin, $paygatedottocryptogateway_daibase_paid_coin_name, $paygatedottocryptogateway_daibase_paid_txid_in));
        return array( 'message' => 'Payment confirmed and order status changed.' );
		}
    } else {
        // Return error response if conditions are not met
        return new WP_Error( 'order_not_eligible', __( 'Order is not eligible for status change.', 'crypto-payment-gateway' ), array( 'status' => 400 ) );
    }
}
?>