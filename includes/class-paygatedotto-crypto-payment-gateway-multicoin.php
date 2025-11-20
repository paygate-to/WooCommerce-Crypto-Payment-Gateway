<?php
if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_paygatedottocryptogateway_multicoin_gateway');

function init_paygatedottocryptogateway_multicoin_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

class PayGateDotTo_Crypto_Payment_Gateway_Multicoin extends WC_Payment_Gateway {

protected $multicoin_wallet_address;
protected $multicoin_blockchain_fees;
protected $multicoin_tolerance_percentage;
protected $multicoin_custom_domain;
protected $icon_url;

    public function __construct() {
        $this->id                 = 'paygatedotto-crypto-payment-gateway-multicoin';
        $this->icon = esc_url(plugin_dir_url(__DIR__) . 'static/multicoin.png');
        $this->method_title       = esc_html__('Multicoin Crypto Payment Gateway With Instant Payouts', 'crypto-payment-gateway'); // Escaping title
        $this->method_description = esc_html__('Multicoin Crypto Payment Gateway With Instant Payouts to your cryptocurrency wallet. Allows you to accept crypto bep20/cake payments without sign up and without KYC.', 'crypto-payment-gateway'); // Escaping description
        $this->has_fields         = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = sanitize_text_field($this->get_option('title'));
        $this->description = sanitize_text_field($this->get_option('description'));

        // Use the configured settings for redirect and icon URLs
        $this->multicoin_wallet_address = array(
    'evm'          => sanitize_text_field($this->get_option('multicoin_wallet_evm')),
    'btc'          => sanitize_text_field($this->get_option('multicoin_wallet_btc')),
    'bitcoincash'  => sanitize_text_field($this->get_option('multicoin_wallet_bitcoincash')),
    'ltc'          => sanitize_text_field($this->get_option('multicoin_wallet_ltc')),
    'doge'         => sanitize_text_field($this->get_option('multicoin_wallet_doge')),
    'solana'       => sanitize_text_field($this->get_option('multicoin_wallet_solana')),
    'trc20'        => sanitize_text_field($this->get_option('multicoin_wallet_trc20')),
);
		$this->multicoin_tolerance_percentage = sanitize_text_field($this->get_option('multicoin_tolerance_percentage'));
		$this->multicoin_custom_domain = rtrim(str_replace(['https://','http://'], '', sanitize_text_field($this->get_option('multicoin_custom_domain'))), '/');
		$this->multicoin_blockchain_fees = $this->get_option('multicoin_blockchain_fees');
        $this->icon_url     = sanitize_url($this->get_option('icon_url'));

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => esc_html__('Enable/Disable', 'crypto-payment-gateway'), // Escaping title
                'type'    => 'checkbox',
                'label'   => esc_html__('Enable cryptocurrency payment gateway', 'crypto-payment-gateway'), // Escaping label
                'default' => 'no',
            ),
            'title' => array(
                'title'       => esc_html__('Title', 'crypto-payment-gateway'), // Escaping title
                'type'        => 'text',
                'description' => esc_html__('Payment method title that users will see during checkout.', 'crypto-payment-gateway'), // Escaping description
                'default'     => esc_html__('Multicoin', 'crypto-payment-gateway'), // Escaping default value
                'desc_tip'    => true,
            ),
			'multicoin_custom_domain' => array(
                'title'       => esc_html__('Custom Domain', 'crypto-payment-gateway'), // Escaping title
                'type'        => 'text',
                'description' => esc_html__('Follow the custom domain guide to use your own domain name for the checkout pages and links.', 'crypto-payment-gateway'), // Escaping description
                'default'     => esc_html__('checkout.paygate.to', 'crypto-payment-gateway'), // Escaping default value
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => esc_html__('Description', 'crypto-payment-gateway'), // Escaping title
                'type'        => 'textarea',
                'description' => esc_html__('Payment method description that users will see during checkout.', 'crypto-payment-gateway'), // Escaping description
                'default'     => esc_html__('Pay via crypto Multicoin cryptocurrency', 'crypto-payment-gateway'), // Escaping default value
                'desc_tip'    => true,
            ),
'multicoin_wallet_evm' => array(
    'title'       => esc_html__('EVM Wallet Address', 'crypto-payment-gateway'),
    'type'        => 'text',
    'description' => esc_html__('Insert your EVM-compatible wallet address (ERC20/ETH/BEP20/Polygon/Optimism/Arbitrum/Base/Avax-C).', 'crypto-payment-gateway'),
    'desc_tip'    => true,
),
'multicoin_wallet_btc' => array(
    'title'       => esc_html__('Bitcoin Wallet Address (BTC)', 'crypto-payment-gateway'),
    'type'        => 'text',
    'description' => esc_html__('Insert your Bitcoin wallet address.', 'crypto-payment-gateway'),
    'desc_tip'    => true,
),
'multicoin_wallet_bitcoincash' => array(
    'title'       => esc_html__('Bitcoin Cash Wallet Address (BCH)', 'crypto-payment-gateway'),
    'type'        => 'text',
    'description' => esc_html__('Insert your Bitcoin Cash wallet address.', 'crypto-payment-gateway'),
    'desc_tip'    => true,
),
'multicoin_wallet_ltc' => array(
    'title'       => esc_html__('Litecoin Wallet Address (LTC)', 'crypto-payment-gateway'),
    'type'        => 'text',
    'description' => esc_html__('Insert your Litecoin wallet address.', 'crypto-payment-gateway'),
    'desc_tip'    => true,
),
'multicoin_wallet_doge' => array(
    'title'       => esc_html__('Dogecoin Wallet Address (DOGE)', 'crypto-payment-gateway'),
    'type'        => 'text',
    'description' => esc_html__('Insert your Dogecoin wallet address.', 'crypto-payment-gateway'),
    'desc_tip'    => true,
),
'multicoin_wallet_solana' => array(
    'title'       => esc_html__('Solana Wallet Address (SOL)', 'crypto-payment-gateway'),
    'type'        => 'text',
    'description' => esc_html__('Insert your Solana wallet address.', 'crypto-payment-gateway'),
    'desc_tip'    => true,
),
'multicoin_wallet_trc20' => array(
    'title'       => esc_html__('TRC20 Wallet Address (USDT-TRON)', 'crypto-payment-gateway'),
    'type'        => 'text',
    'description' => esc_html__('Insert your TRC20 (USDT on Tron) wallet address.', 'crypto-payment-gateway'),
    'desc_tip'    => true,
),
            'multicoin_tolerance_percentage' => array(
                'title'       => esc_html__('Underpaid Tolerance', 'crypto-payment-gateway'),
                'type'        => 'select',
                'description' => esc_html__('Select percentage to tolerate underpayment when a customer sends less crypto than the required amount. Recommended is 1% or more due to volatile crypto rates.', 'crypto-payment-gateway'),
                'desc_tip'    => true,
                'default'     => '0.99',
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
			'multicoin_blockchain_fees' => array(
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
    if (
        !isset($_POST['_wpnonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')
    ) {
        WC_Admin_Settings::add_error(__('Nonce verification failed. Please try again.', 'crypto-payment-gateway'));
        return false;
    }

    $required_wallet_keys = [
        'multicoin_wallet_evm',
        'multicoin_wallet_btc',
        'multicoin_wallet_bitcoincash',
        'multicoin_wallet_ltc',
        'multicoin_wallet_doge',
        'multicoin_wallet_solana',
        'multicoin_wallet_trc20',
    ];

    foreach ($required_wallet_keys as $key) {
        $field_name = $this->plugin_id . $this->id . '_' . $key;
        $value = isset($_POST[$field_name]) ? trim(sanitize_text_field(wp_unslash($_POST[$field_name]))) : '';

        if (empty($value)) {
    /* translators: 1: coin label generated from the field name, e.g. "EVM", "BTC", "LTC" */			
            WC_Admin_Settings::add_error(sprintf(__('Missing wallet address for %s. Please fill in all wallet fields.', 'crypto-payment-gateway'), esc_html(strtoupper(str_replace('multicoin_wallet_', '', $key)))));
            return false;
        }
    }

    return parent::process_admin_options();
}

	
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $paygatedottocryptogateway_multicoinmulticoin_currency = get_woocommerce_currency();
		$paygatedottocryptogateway_multicoinmulticoin_total = $order->get_total();
		$paygatedottocryptogateway_multicoinmulticoin_nonce = wp_create_nonce( 'paygatedottocryptogateway_multicoin_nonce_' . $order_id );
		$paygatedottocryptogateway_multicoinmulticoin_tolerance_percentage = $this->multicoin_tolerance_percentage;
		$paygatedottocryptogateway_multicoinmulticoin_callback = add_query_arg(array('order_id' => $order_id, 'nonce' => $paygatedottocryptogateway_multicoinmulticoin_nonce,), rest_url('paygatedottocryptogateway/v1/paygatedottocryptogateway-multicoin/'));
		$paygatedottocryptogateway_multicoinmulticoin_email = urlencode(sanitize_email($order->get_billing_email()));
$paygatedottocryptogateway_multicoinmulticoin_evm_wallet         = $this->multicoin_wallet_address['evm'];
$paygatedottocryptogateway_multicoinmulticoin_btc_wallet         = $this->multicoin_wallet_address['btc'];
$paygatedottocryptogateway_multicoinmulticoin_bitcoincash_wallet = $this->multicoin_wallet_address['bitcoincash'];
$paygatedottocryptogateway_multicoinmulticoin_ltc_wallet         = $this->multicoin_wallet_address['ltc'];
$paygatedottocryptogateway_multicoinmulticoin_doge_wallet        = $this->multicoin_wallet_address['doge'];
$paygatedottocryptogateway_multicoinmulticoin_solana_wallet      = $this->multicoin_wallet_address['solana'];
$paygatedottocryptogateway_multicoinmulticoin_trc20_wallet       = $this->multicoin_wallet_address['trc20'];
		
   $paygatedottocryptogateway_multicoindecoded_payload = array(
		'evm' => $paygatedottocryptogateway_multicoinmulticoin_evm_wallet,
        'btc' => $paygatedottocryptogateway_multicoinmulticoin_btc_wallet,
		'bitcoincash' => $paygatedottocryptogateway_multicoinmulticoin_bitcoincash_wallet,
        'ltc' => $paygatedottocryptogateway_multicoinmulticoin_ltc_wallet,
		'doge' => $paygatedottocryptogateway_multicoinmulticoin_doge_wallet,
		'solana' => $paygatedottocryptogateway_multicoinmulticoin_solana_wallet,
		'trc20' => $paygatedottocryptogateway_multicoinmulticoin_trc20_wallet,
		'fiat_amount' => $paygatedottocryptogateway_multicoinmulticoin_total,
		'fiat_currency' => $paygatedottocryptogateway_multicoinmulticoin_currency,
        'callback' => $paygatedottocryptogateway_multicoinmulticoin_callback,
            );
			$paygatedottocryptogateway_multicoinjson_payload = json_encode($paygatedottocryptogateway_multicoindecoded_payload);

		
		if ($this->multicoin_blockchain_fees === 'yes') {
			
		$paygatedottocryptogateway_multicoinmulticoin_fees_value = '1';
		
		} else {
			
		$paygatedottocryptogateway_multicoinmulticoin_fees_value = '0';	

		}
		
$paygatedottocryptogateway_multicoinmulticoin_gen_wallet = wp_remote_get('https://api.paygate.to/crypto/multi-hosted-wallet.php?payload=' . urlencode($paygatedottocryptogateway_multicoinjson_payload) , array('timeout' => 30));

if (is_wp_error($paygatedottocryptogateway_multicoinmulticoin_gen_wallet)) {
    // Handle error
    paygatedottocryptogateway_add_notice(__('Wallet error:', 'crypto-payment-gateway') . __('Payment could not be processed due to incorrect payout wallet settings, please contact website admin', 'crypto-payment-gateway'), 'error');
    return null;
} else {
	$paygatedottocryptogateway_multicoinmulticoin_wallet_body = wp_remote_retrieve_body($paygatedottocryptogateway_multicoinmulticoin_gen_wallet);
	$paygatedottocryptogateway_multicoinmulticoin_wallet_decbody = json_decode($paygatedottocryptogateway_multicoinmulticoin_wallet_body, true);

 // Check if decoding was successful
    if ($paygatedottocryptogateway_multicoinmulticoin_wallet_decbody && isset($paygatedottocryptogateway_multicoinmulticoin_wallet_decbody['payment_token'])) {
		// Store and sanitize variables
        $paygatedottocryptogateway_multicoinmulticoin_gen_addressIn = wp_kses_post($paygatedottocryptogateway_multicoinmulticoin_wallet_decbody['payment_token']);
        $paygatedottocryptogateway_multicoinmulticoin_gen_ipntoken = wp_kses_post($paygatedottocryptogateway_multicoinmulticoin_wallet_decbody['ipn_token']);
		$paygatedottocryptogateway_multicoinmulticoin_gen_callback = sanitize_url($paygatedottocryptogateway_multicoinmulticoin_wallet_decbody['callback_url']);
        
		
		
		// Save $multicoinresponse in order meta data
    $order->add_meta_data('paygatedotto_multicoin_payin_address', $paygatedottocryptogateway_multicoinmulticoin_gen_addressIn, true);
    $order->add_meta_data('paygatedotto_multicoin_ipntoken', $paygatedottocryptogateway_multicoinmulticoin_gen_ipntoken, true);
    $order->add_meta_data('paygatedotto_multicoin_callback', $paygatedottocryptogateway_multicoinmulticoin_gen_callback, true);
	$order->add_meta_data('paygatedotto_multicoin_payin_amount', $paygatedottocryptogateway_multicoinmulticoin_total, true);
	$order->add_meta_data('paygatedotto_multicoin_tolerance_percentage', $paygatedottocryptogateway_multicoinmulticoin_tolerance_percentage, true);
	$order->add_meta_data('paygatedotto_multicoin_currency', $paygatedottocryptogateway_multicoinmulticoin_currency, true);
	$order->add_meta_data('paygatedotto_multicoin_nonce', $paygatedottocryptogateway_multicoinmulticoin_nonce, true);
	$order->add_meta_data('paygatedotto_multicoin_fees_value_settings', $paygatedottocryptogateway_multicoinmulticoin_fees_value, true);
    $order->save();
    } else {
        paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed, please try again (wallet address error)', 'crypto-payment-gateway'), 'error');

        return null;
    }
}

        // Redirect to payment page
        return array(
            'result'   => 'success',
            'redirect' => 'https://' . $this->multicoin_custom_domain . '/crypto/hosted.php?payment_token=' . $paygatedottocryptogateway_multicoinmulticoin_gen_addressIn . '&add_fees=' . $paygatedottocryptogateway_multicoinmulticoin_fees_value,
        );
    }


public function paygatedotto_crypto_payment_gateway_get_icon_url() {
        return !empty($this->icon) ? esc_url($this->icon) : '';
    }
}

function paygatedottocryptogateway_add_instant_payment_gateway_multicoin($gateways) {
    $gateways[] = 'PayGateDotTo_Crypto_Payment_Gateway_Multicoin';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'paygatedottocryptogateway_add_instant_payment_gateway_multicoin');
}

// Add custom endpoint for reading crypto payment status

   function paygatedottocryptogateway_multicoin_check_order_status_rest_endpoint() {
        register_rest_route('paygatedottocryptogateway/v1', '/paygatedottocryptogateway-check-order-status-multicoin/', array(
            'methods'  => 'GET',
            'callback' => 'paygatedottocryptogateway_multicoin_check_order_status_callback',
            'permission_callback' => '__return_true',
        ));
    }

    add_action('rest_api_init', 'paygatedottocryptogateway_multicoin_check_order_status_rest_endpoint');

    function paygatedottocryptogateway_multicoin_check_order_status_callback($request) {
        $order_id = absint($request->get_param('order_id'));
		$paygatedottocryptogateway_multicoinmulticoin_live_status_nonce = sanitize_text_field($request->get_param('nonce'));

        if (empty($order_id)) {
            return new WP_Error('missing_order_id', __('Order ID parameter is missing.', 'crypto-payment-gateway'), array('status' => 400));
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return new WP_Error('invalid_order', __('Invalid order ID.', 'crypto-payment-gateway'), array('status' => 404));
        }
		
		// Verify stored status nonce

        if ( empty( $paygatedottocryptogateway_multicoinmulticoin_live_status_nonce ) || $order->get_meta('paygatedotto_multicoin_status_nonce', true) !== $paygatedottocryptogateway_multicoinmulticoin_live_status_nonce ) {
        return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'crypto-payment-gateway' ), array( 'status' => 403 ) );
    }
        return array('status' => $order->get_status());
    }

// Add custom endpoint for changing order status
function paygatedottocryptogateway_multicoin_change_order_status_rest_endpoint() {
    // Register custom route
    register_rest_route( 'paygatedottocryptogateway/v1', '/paygatedottocryptogateway-multicoin/', array(
        'methods'  => 'GET',
        'callback' => 'paygatedottocryptogateway_multicoin_change_order_status_callback',
        'permission_callback' => '__return_true',
    ));
}
add_action( 'rest_api_init', 'paygatedottocryptogateway_multicoin_change_order_status_rest_endpoint' );

// Callback function to change order status
function paygatedottocryptogateway_multicoin_change_order_status_callback( $request ) {
    $order_id = absint($request->get_param( 'order_id' ));
	$paygatedottocryptogateway_multicoingetnonce = sanitize_text_field($request->get_param( 'nonce' ));
	$paygatedottocryptogateway_multicoinpaid_value_coin = sanitize_text_field($request->get_param('value_coin'));
	$paygatedottocryptogateway_multicoin_paid_coin_name = sanitize_text_field($request->get_param('coin'));
	$paygatedottocryptogateway_multicoin_paid_txid_in = sanitize_text_field($request->get_param('txid_in'));

$paygatedottocryptogateway_multicoincoin_label = str_replace( '_', '/', strtoupper( $paygatedottocryptogateway_multicoin_paid_coin_name ) );

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
    if ( empty( $paygatedottocryptogateway_multicoingetnonce ) || $order->get_meta('paygatedotto_multicoin_nonce', true) !== $paygatedottocryptogateway_multicoingetnonce ) {
        return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'crypto-payment-gateway' ), array( 'status' => 403 ) );
    }

    // Check if the order is pending and payment method is 'paygatedotto-crypto-payment-gateway-bch'
    if ( $order && !in_array($order->get_status(), ['processing', 'completed'], true) && 'paygatedotto-crypto-payment-gateway-multicoin' === $order->get_payment_method() ) {
	$paygatedottocryptogateway_multicoincurrency      = $order->get_meta( 'paygatedotto_multicoin_currency', true );	
 // Fetch coin pricing from PayGate
    $paygatedottocryptogateway_multicoininfo_url = 'https://api.paygate.to/crypto/' . strtolower($paygatedottocryptogateway_multicoincoin_label) . '/info.php';
    $paygatedottocryptogateway_multicoinresponse = wp_remote_get( $paygatedottocryptogateway_multicoininfo_url, array( 'timeout' => 30 ) );

    if ( is_wp_error( $paygatedottocryptogateway_multicoinresponse ) ) {
        return new WP_Error(
            'paygatedottocryptogateway_api_error',
            __( 'Failed to fetch coin data.', 'crypto-payment-gateway' ),
            array( 'status' => 500 )
        );
    }

    $paygatedottocryptogateway_multicoinbody      = wp_remote_retrieve_body( $paygatedottocryptogateway_multicoinresponse );
    $paygatedottocryptogateway_multicoincoin_data = json_decode( $paygatedottocryptogateway_multicoinbody, true );

    if ( ! is_array( $paygatedottocryptogateway_multicoincoin_data ) || ! isset( $paygatedottocryptogateway_multicoincoin_data['prices'][ $paygatedottocryptogateway_multicoincurrency ] ) ) {
        return new WP_Error(
            'paygatedottocryptogateway_invalid_coin_data',
            __( 'Invalid coin data received from PayGate.', 'crypto-payment-gateway' ),
            array( 'status' => 500 )
        );
    }

    // Get fiat price for order currency
    $paygatedottocryptogateway_multicoincoin_price    = floatval( $paygatedottocryptogateway_multicoincoin_data['prices'][ $paygatedottocryptogateway_multicoincurrency ] );

    // Convert crypto amount to fiat
    $paygatedottocryptogateway_multicoinreceived_coin = $paygatedottocryptogateway_multicoinpaid_value_coin;
    $paygatedottocryptogateway_multicoinreceived_fiat = $paygatedottocryptogateway_multicoinreceived_coin * $paygatedottocryptogateway_multicoincoin_price;

    // Get expected fiat and tolerance
    $paygatedottocryptogateway_multicoinexpected_fiat     = floatval( $order->get_meta( 'paygatedotto_multicoin_payin_amount', true ) );
    $paygatedottocryptogateway_multicointolerance_percent = floatval( $order->get_meta( 'paygatedotto_multicoin_tolerance_percentage', true ) );
	$paygatedottocryptogateway_multicoin_fee_read_settings = $order->get_meta( 'paygatedotto_multicoin_fees_value_settings', true );
    $paygatedottocryptogateway_multicoinminimum_initial_required  = $paygatedottocryptogateway_multicoinexpected_fiat * $paygatedottocryptogateway_multicointolerance_percent;


if ($paygatedottocryptogateway_multicoin_fee_read_settings === '1') {
			
		 // Fetch coin fees from PayGate
    $paygatedottocryptogateway_multicoinfeesinfo_url = 'https://api.paygate.to/crypto/' . strtolower($paygatedottocryptogateway_multicoincoin_label) . '/fees.php';
    $paygatedottocryptogateway_multicoinfeesresponse = wp_remote_get( $paygatedottocryptogateway_multicoinfeesinfo_url, array( 'timeout' => 30 ) );
	
	
	$paygatedottocryptogateway_multicoinfeesbody      = wp_remote_retrieve_body( $paygatedottocryptogateway_multicoinfeesresponse );
    $paygatedottocryptogateway_multicoinfeescoin_data = json_decode( $paygatedottocryptogateway_multicoinfeesbody, true );

    if ( ! is_array( $paygatedottocryptogateway_multicoinfeescoin_data ) || ! isset( $paygatedottocryptogateway_multicoinfeescoin_data['estimated_cost_currency'][$paygatedottocryptogateway_multicoincurrency] ) ) {
        return new WP_Error(
            'paygatedottocryptogateway_invalid_coin_data',
            __( 'Invalid coin fee data received from PayGate.', 'crypto-payment-gateway' ),
            array( 'status' => 500 )
        );
    }
	
	$paygatedottocryptogateway_multicoinfeescoin_price    = floatval( $paygatedottocryptogateway_multicoinfeescoin_data['estimated_cost_currency'][ $paygatedottocryptogateway_multicoincurrency ] );
	
	$paygatedottocryptogateway_multicoinminimum_required = $paygatedottocryptogateway_multicoinminimum_initial_required + $paygatedottocryptogateway_multicoinfeescoin_price;
		
		} else {
			
		$paygatedottocryptogateway_multicoinminimum_required = $paygatedottocryptogateway_multicoinminimum_initial_required;

		}
	
		if ( $paygatedottocryptogateway_multicoinreceived_fiat < $paygatedottocryptogateway_multicoinminimum_required) {
	
		// Mark the order as failed and add an order note
/* translators: 1: amount received, 2: coin ticker, 3: fiat amount received, 4: fiat currency, 5: minimum required fiat, 6: transaction ID */
$order->update_status('failed', sprintf(__( '[Order Failed] Received %1$s %2$s (~%3$.2f %4$s), required minimum: %5$.2f %4$s. TXID: %6$s', 'crypto-payment-gateway' ),
        $paygatedottocryptogateway_multicoinreceived_coin,
        esc_html( strtoupper( $paygatedottocryptogateway_multicoin_paid_coin_name ) ),
        $paygatedottocryptogateway_multicoinreceived_fiat,
        esc_html( $paygatedottocryptogateway_multicoincurrency ),
        $paygatedottocryptogateway_multicoinminimum_required,
        esc_html( $paygatedottocryptogateway_multicoin_paid_txid_in )
    )
);

/* translators: 1: amount received, 2: coin ticker, 3: fiat amount received, 4: fiat currency, 5: minimum required fiat, 6: transaction ID */
$order->add_order_note( sprintf(__( '[Order Failed] Received %1$s %2$s (~%3$.2f %4$s), required minimum: %5$.2f %4$s. TXID: %6$s', 'crypto-payment-gateway' ),
        $paygatedottocryptogateway_multicoinreceived_coin,
        esc_html( strtoupper( $paygatedottocryptogateway_multicoin_paid_coin_name ) ),
        $paygatedottocryptogateway_multicoinreceived_fiat,
        esc_html( $paygatedottocryptogateway_multicoincurrency ),
        $paygatedottocryptogateway_multicoinminimum_required,
        esc_html( $paygatedottocryptogateway_multicoin_paid_txid_in )
    )
);
return array( 'message' => 'Order status changed to failed due to partial payment. Please check order notes' );			
	
		} else {
        // Change order status to processing
		$order->payment_complete();


// Return success response
/* translators: 1: Paid value in coin, 2: Paid coin name, 3: Transaction ID */
$order->add_order_note(sprintf( __( '[Payment completed] Customer sent %1$s %2$s TXID:%3$s', 'crypto-payment-gateway' ), $paygatedottocryptogateway_multicoinpaid_value_coin, $paygatedottocryptogateway_multicoin_paid_coin_name, $paygatedottocryptogateway_multicoin_paid_txid_in));
        return array( 'message' => 'Payment confirmed and order status changed.' );
		}
    } else {
        // Return error response if conditions are not met
        return new WP_Error( 'order_not_eligible', __( 'Order is not eligible for status change.', 'crypto-payment-gateway' ), array( 'status' => 400 ) );
    }
}

?>