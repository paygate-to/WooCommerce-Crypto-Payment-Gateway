<?php
/**
 * Dynamic Individual-Coin Crypto Payment Gateway.
 *
 * Replaces the (previously ~90) per-coin gateway files with a single gateway
 * whose accepted coins are fetched live from the PayGate coin list API
 * (https://api.paygate.to/crypto/info.php). The admin enables individual coins
 * (each with its own payout wallet) from one settings screen; the storefront
 * shows the enabled coins as selectable options (classic + block checkout) and
 * the QR code / payment address is displayed on the merchant's own site exactly
 * like the original individual gateways.
 *
 * The per-coin payment flow (convert -> optional fees -> minimum -> wallet ->
 * QR code) mirrors the original individual gateways. The payment-confirmation
 * CALLBACK borrows the Multicoin gateway's fiat-value verification (it values
 * the actually-received coin in the order currency via the coin info endpoint
 * and compares it against the expected fiat amount with tolerance + optional
 * blockchain fees), and additionally guards that the paid coin matches the coin
 * the buyer selected.
 */

if (!defined('ABSPATH')) {
    exit;
}

/* -------------------------------------------------------------------------
 * Coin list helper
 * ---------------------------------------------------------------------- */

/**
 * Fetch and flatten the PayGate coin list into a simple map.
 *
 * Returns an associative array keyed by the canonical coin id used throughout
 * this gateway (and reported back in the payment callback):
 *
 *   - Standalone coins (btc, bch, ltc, doge, eth, trx): id === ticker.
 *   - Chain tokens:                                      id === "{chain}_{ticker}".
 *
 * The API path for any coin is derived simply by replacing "_" with "/" in the
 * id (e.g. "erc20_usdc" -> "erc20/usdc", "btc" -> "btc", "avax-c_usdc.e" ->
 * "avax-c/usdc.e"), which matches how the Multicoin callback builds its paths.
 *
 * Each entry: array( 'id', 'ticker', 'chain', 'label', 'logo', 'path' ).
 *
 * Cached in a transient to avoid hammering the API. Pass $force = true to
 * bypass the cache (used right after the admin saves, so validation/snapshot
 * uses fresh data).
 *
 * @param bool $force Skip the cache and refetch.
 * @return array
 */
function paygatedottocryptogateway_dynamic_get_coin_map($force = false) {
    $paygatedottocryptogateway_dynamic_cache_key = 'paygatedottocryptogateway_dynamic_coin_map';

    if (!$force) {
        $paygatedottocryptogateway_dynamic_cached = get_transient($paygatedottocryptogateway_dynamic_cache_key);
        if (is_array($paygatedottocryptogateway_dynamic_cached) && !empty($paygatedottocryptogateway_dynamic_cached)) {
            return $paygatedottocryptogateway_dynamic_cached;
        }
    }

    $paygatedottocryptogateway_dynamic_response = wp_remote_get(
        'https://api.paygate.to/crypto/info.php',
        array('timeout' => 30)
    );

    if (is_wp_error($paygatedottocryptogateway_dynamic_response)) {
        // On failure fall back to any stale cache so the storefront keeps working.
        $paygatedottocryptogateway_dynamic_stale = get_transient($paygatedottocryptogateway_dynamic_cache_key);
        return is_array($paygatedottocryptogateway_dynamic_stale) ? $paygatedottocryptogateway_dynamic_stale : array();
    }

    $paygatedottocryptogateway_dynamic_body = wp_remote_retrieve_body($paygatedottocryptogateway_dynamic_response);
    $paygatedottocryptogateway_dynamic_data = json_decode($paygatedottocryptogateway_dynamic_body, true);

    if (!is_array($paygatedottocryptogateway_dynamic_data)) {
        $paygatedottocryptogateway_dynamic_stale = get_transient($paygatedottocryptogateway_dynamic_cache_key);
        return is_array($paygatedottocryptogateway_dynamic_stale) ? $paygatedottocryptogateway_dynamic_stale : array();
    }

    $paygatedottocryptogateway_dynamic_map = array();

    foreach ($paygatedottocryptogateway_dynamic_data as $paygatedottocryptogateway_dynamic_top_key => $paygatedottocryptogateway_dynamic_entry) {
        if (!is_array($paygatedottocryptogateway_dynamic_entry)) {
            continue;
        }

        if (isset($paygatedottocryptogateway_dynamic_entry['ticker'])) {
            // Standalone coin (btc, bch, ltc, doge, eth, trx).
            $paygatedottocryptogateway_dynamic_ticker = sanitize_text_field($paygatedottocryptogateway_dynamic_entry['ticker']);
            $paygatedottocryptogateway_dynamic_id     = $paygatedottocryptogateway_dynamic_ticker;
            $paygatedottocryptogateway_dynamic_map[$paygatedottocryptogateway_dynamic_id] = array(
                'id'     => $paygatedottocryptogateway_dynamic_id,
                'ticker' => $paygatedottocryptogateway_dynamic_ticker,
                'chain'  => '',
                'label'  => isset($paygatedottocryptogateway_dynamic_entry['coin']) ? sanitize_text_field($paygatedottocryptogateway_dynamic_entry['coin']) : strtoupper($paygatedottocryptogateway_dynamic_ticker),
                'logo'   => isset($paygatedottocryptogateway_dynamic_entry['logo']) ? esc_url_raw($paygatedottocryptogateway_dynamic_entry['logo']) : '',
                'path'   => $paygatedottocryptogateway_dynamic_id,
            );
            continue;
        }

        // Otherwise it is a chain group whose children are coins.
        $paygatedottocryptogateway_dynamic_chain = sanitize_text_field($paygatedottocryptogateway_dynamic_top_key);
        foreach ($paygatedottocryptogateway_dynamic_entry as $paygatedottocryptogateway_dynamic_child) {
            if (!is_array($paygatedottocryptogateway_dynamic_child) || !isset($paygatedottocryptogateway_dynamic_child['ticker'])) {
                continue;
            }
            $paygatedottocryptogateway_dynamic_ticker = sanitize_text_field($paygatedottocryptogateway_dynamic_child['ticker']);
            $paygatedottocryptogateway_dynamic_id     = $paygatedottocryptogateway_dynamic_chain . '_' . $paygatedottocryptogateway_dynamic_ticker;
            $paygatedottocryptogateway_dynamic_map[$paygatedottocryptogateway_dynamic_id] = array(
                'id'     => $paygatedottocryptogateway_dynamic_id,
                'ticker' => $paygatedottocryptogateway_dynamic_ticker,
                'chain'  => $paygatedottocryptogateway_dynamic_chain,
                'label'  => isset($paygatedottocryptogateway_dynamic_child['coin']) ? sanitize_text_field($paygatedottocryptogateway_dynamic_child['coin']) : strtoupper($paygatedottocryptogateway_dynamic_ticker),
                'logo'   => isset($paygatedottocryptogateway_dynamic_child['logo']) ? esc_url_raw($paygatedottocryptogateway_dynamic_child['logo']) : '',
                'path'   => str_replace('_', '/', $paygatedottocryptogateway_dynamic_id),
            );
        }
    }

    if (!empty($paygatedottocryptogateway_dynamic_map)) {
        set_transient($paygatedottocryptogateway_dynamic_cache_key, $paygatedottocryptogateway_dynamic_map, HOUR_IN_SECONDS);
    }

    return $paygatedottocryptogateway_dynamic_map;
}

/**
 * Human-friendly display label for a coin id, e.g. "USD Coin (ERC20)".
 *
 * @param array $paygatedottocryptogateway_dynamic_coin Coin entry from the map / config snapshot.
 * @return string
 */
function paygatedottocryptogateway_dynamic_coin_display_label($paygatedottocryptogateway_dynamic_coin) {
    $paygatedottocryptogateway_dynamic_label = isset($paygatedottocryptogateway_dynamic_coin['label']) ? $paygatedottocryptogateway_dynamic_coin['label'] : '';
    $paygatedottocryptogateway_dynamic_chain = isset($paygatedottocryptogateway_dynamic_coin['chain']) ? $paygatedottocryptogateway_dynamic_coin['chain'] : '';
    if ('' !== $paygatedottocryptogateway_dynamic_chain) {
        return $paygatedottocryptogateway_dynamic_label . ' (' . strtoupper($paygatedottocryptogateway_dynamic_chain) . ')';
    }
    return $paygatedottocryptogateway_dynamic_label;
}

/* -------------------------------------------------------------------------
 * Gateway class
 * ---------------------------------------------------------------------- */

add_action('plugins_loaded', 'paygatedottocryptogateway_init_dynamic_gateway');

function paygatedottocryptogateway_init_dynamic_gateway() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    class PayGateDotTo_Crypto_Payment_Gateway_Dynamic extends WC_Payment_Gateway {

        protected $dynamic_tolerance_percentage;
        protected $dynamic_blockchain_fees;
        protected $dynamic_coin_config;
        protected $icon_url;

        public function __construct() {
            $this->id                 = 'paygatedotto-crypto-payment-gateway-dynamic';
            $this->icon               = esc_url(plugin_dir_url(__DIR__) . 'static/multicoin.png');
            $this->method_title       = esc_html__('Individual Coin Crypto Payment Gateway With Instant Payouts', 'crypto-payment-gateway');
            $this->method_description = esc_html__('Accept individual cryptocurrencies with instant payouts to your own wallet and without KYC. The QR code and payment address are shown directly on your website. Enable the coins you want to accept below; the list is fetched live from PayGate.', 'crypto-payment-gateway');
            $this->has_fields         = true;

            $this->init_form_fields();
            $this->init_settings();

            $this->title       = sanitize_text_field($this->get_option('title'));
            $this->description = sanitize_text_field($this->get_option('description'));

            $this->dynamic_tolerance_percentage = sanitize_text_field($this->get_option('dynamic_tolerance_percentage'));
            $this->dynamic_blockchain_fees      = $this->get_option('dynamic_blockchain_fees');
            $this->icon_url                     = sanitize_url($this->get_option('icon_url'));

            $paygatedottocryptogateway_dynamic_stored_config = $this->get_option('coin_config');
            $this->dynamic_coin_config = is_array($paygatedottocryptogateway_dynamic_stored_config) ? $paygatedottocryptogateway_dynamic_stored_config : array();

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_before_thankyou', array($this, 'before_thankyou_page'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => esc_html__('Enable/Disable', 'crypto-payment-gateway'),
                    'type'    => 'checkbox',
                    'label'   => esc_html__('Enable individual coin cryptocurrency payment gateway', 'crypto-payment-gateway'),
                    'default' => 'no',
                ),
                'title' => array(
                    'title'       => esc_html__('Title', 'crypto-payment-gateway'),
                    'type'        => 'text',
                    'description' => esc_html__('Payment method title that users will see during checkout.', 'crypto-payment-gateway'),
                    'default'     => esc_html__('Cryptocurrency', 'crypto-payment-gateway'),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => esc_html__('Description', 'crypto-payment-gateway'),
                    'type'        => 'textarea',
                    'description' => esc_html__('Payment method description that users will see during checkout.', 'crypto-payment-gateway'),
                    'default'     => esc_html__('Pay with your preferred cryptocurrency', 'crypto-payment-gateway'),
                    'desc_tip'    => true,
                ),
                'dynamic_tolerance_percentage' => array(
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
                        '0.90' => '10%',
                    ),
                ),
                'dynamic_blockchain_fees' => array(
                    'title'       => esc_html__('Customer Pays Blockchain Fees', 'crypto-payment-gateway'),
                    'type'        => 'checkbox',
                    'description' => esc_html__('Add estimated blockchain fees to the order total.', 'crypto-payment-gateway'),
                    'desc_tip'    => true,
                    'default'     => 'no',
                ),
                'coin_config' => array(
                    'title' => esc_html__('Accepted Coins', 'crypto-payment-gateway'),
                    'type'  => 'coin_table',
                ),
            );
        }

        /**
         * Render the dynamic coin table (checkbox + wallet field per coin).
         *
         * The coin list is fetched live from the PayGate coin list API and the
         * rows are pre-filled from the saved configuration.
         */
        public function generate_coin_table_html($key, $data) {
            $paygatedottocryptogateway_dynamic_field_key = $this->get_field_key($key);
            $paygatedottocryptogateway_dynamic_map       = paygatedottocryptogateway_dynamic_get_coin_map();
            $paygatedottocryptogateway_dynamic_saved     = is_array($this->dynamic_coin_config) ? $this->dynamic_coin_config : array();

            ob_start();
            ?>
            <tr valign="top">
                <th scope="row" class="titledesc">
                    <label><?php echo esc_html($data['title']); ?></label>
                </th>
                <td class="forminp">
                    <?php if (empty($paygatedottocryptogateway_dynamic_map)) : ?>
                        <p><?php echo esc_html__('Could not fetch the coin list from PayGate right now. Please save and reload the page, or check your server\'s outbound connection.', 'crypto-payment-gateway'); ?></p>
                    <?php else : ?>
                        <p class="description" style="margin-bottom:10px;">
                            <?php echo esc_html__('Tick each coin you want to accept and enter the payout wallet address for it. Coins without a wallet address will not be offered at checkout.', 'crypto-payment-gateway'); ?>
                        </p>
                        <table class="widefat striped" style="max-width:760px;">
                            <thead>
                                <tr>
                                    <th style="width:40px;"><?php echo esc_html__('Enable', 'crypto-payment-gateway'); ?></th>
                                    <th style="width:240px;"><?php echo esc_html__('Coin', 'crypto-payment-gateway'); ?></th>
                                    <th><?php echo esc_html__('Payout Wallet Address', 'crypto-payment-gateway'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($paygatedottocryptogateway_dynamic_map as $paygatedottocryptogateway_dynamic_coin_id => $paygatedottocryptogateway_dynamic_coin) :
                                $paygatedottocryptogateway_dynamic_row     = isset($paygatedottocryptogateway_dynamic_saved[$paygatedottocryptogateway_dynamic_coin_id]) ? $paygatedottocryptogateway_dynamic_saved[$paygatedottocryptogateway_dynamic_coin_id] : array();
                                $paygatedottocryptogateway_dynamic_checked  = (isset($paygatedottocryptogateway_dynamic_row['enabled']) && 'yes' === $paygatedottocryptogateway_dynamic_row['enabled']);
                                $paygatedottocryptogateway_dynamic_wallet   = isset($paygatedottocryptogateway_dynamic_row['wallet']) ? $paygatedottocryptogateway_dynamic_row['wallet'] : '';
                                ?>
                                <tr>
                                    <td style="text-align:center;">
                                        <input type="checkbox"
                                               name="paygatedotto_coin_enabled[]"
                                               value="<?php echo esc_attr($paygatedottocryptogateway_dynamic_coin_id); ?>"
                                               <?php checked($paygatedottocryptogateway_dynamic_checked); ?> />
                                    </td>
                                    <td>
                                        <?php if (!empty($paygatedottocryptogateway_dynamic_coin['logo'])) : ?>
                                            <img src="<?php echo esc_url($paygatedottocryptogateway_dynamic_coin['logo']); ?>"
                                                 alt="" style="width:20px;height:20px;vertical-align:middle;margin-right:6px;" />
                                        <?php endif; ?>
                                        <?php echo esc_html(paygatedottocryptogateway_dynamic_coin_display_label($paygatedottocryptogateway_dynamic_coin)); ?>
                                        <code style="margin-left:6px;"><?php echo esc_html($paygatedottocryptogateway_dynamic_coin_id); ?></code>
                                    </td>
                                    <td>
                                        <input type="text"
                                               class="input-text regular-input"
                                               style="width:100%;"
                                               name="paygatedotto_coin_wallet[<?php echo esc_attr($paygatedottocryptogateway_dynamic_coin_id); ?>]"
                                               value="<?php echo esc_attr($paygatedottocryptogateway_dynamic_wallet); ?>"
                                               placeholder="<?php echo esc_attr__('Wallet address', 'crypto-payment-gateway'); ?>" />
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </td>
            </tr>
            <?php
            return ob_get_clean();
        }

        /**
         * No-op validator: the coin table is persisted manually in
         * process_admin_options(), so the standard settings pipeline must not
         * overwrite it.
         */
        public function validate_coin_table_field($key, $value) {
            return is_array($this->dynamic_coin_config) ? $this->dynamic_coin_config : array();
        }

        /**
         * Save the standard fields, then persist the per-coin enable/wallet
         * configuration and validate at least one coin is usable.
         */
        public function process_admin_options() {
            if (
                !isset($_POST['_wpnonce']) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'woocommerce-settings')
            ) {
                WC_Admin_Settings::add_error(esc_html__('Nonce verification failed. Please try again.', 'crypto-payment-gateway'));
                return false;
            }

            // Persist the standard fields first (enabled, title, tolerance, ...).
            $paygatedottocryptogateway_dynamic_saved = parent::process_admin_options();

            // Refresh the coin map (force) so we snapshot accurate labels/logos.
            $paygatedottocryptogateway_dynamic_map = paygatedottocryptogateway_dynamic_get_coin_map(true);

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
            $paygatedottocryptogateway_dynamic_enabled_post = isset($_POST['paygatedotto_coin_enabled']) ? map_deep(wp_unslash($_POST['paygatedotto_coin_enabled']), 'sanitize_text_field') : array();
            if (!is_array($paygatedottocryptogateway_dynamic_enabled_post)) {
                $paygatedottocryptogateway_dynamic_enabled_post = array();
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above.
            $paygatedottocryptogateway_dynamic_wallet_post = isset($_POST['paygatedotto_coin_wallet']) ? map_deep(wp_unslash($_POST['paygatedotto_coin_wallet']), 'sanitize_text_field') : array();
            if (!is_array($paygatedottocryptogateway_dynamic_wallet_post)) {
                $paygatedottocryptogateway_dynamic_wallet_post = array();
            }

            $paygatedottocryptogateway_dynamic_config       = array();
            $paygatedottocryptogateway_dynamic_has_usable    = false;

            // Iterate the known coin map so we only ever store recognised coins.
            foreach ($paygatedottocryptogateway_dynamic_map as $paygatedottocryptogateway_dynamic_coin_id => $paygatedottocryptogateway_dynamic_coin) {
                $paygatedottocryptogateway_dynamic_wallet = isset($paygatedottocryptogateway_dynamic_wallet_post[$paygatedottocryptogateway_dynamic_coin_id])
                    ? sanitize_text_field($paygatedottocryptogateway_dynamic_wallet_post[$paygatedottocryptogateway_dynamic_coin_id])
                    : '';
                $paygatedottocryptogateway_dynamic_is_on = in_array($paygatedottocryptogateway_dynamic_coin_id, $paygatedottocryptogateway_dynamic_enabled_post, true);

                // Skip coins that are neither enabled nor have a wallet typed in.
                if (!$paygatedottocryptogateway_dynamic_is_on && '' === $paygatedottocryptogateway_dynamic_wallet) {
                    continue;
                }

                $paygatedottocryptogateway_dynamic_config[$paygatedottocryptogateway_dynamic_coin_id] = array(
                    'enabled' => $paygatedottocryptogateway_dynamic_is_on ? 'yes' : 'no',
                    'wallet'  => $paygatedottocryptogateway_dynamic_wallet,
                    'ticker'  => $paygatedottocryptogateway_dynamic_coin['ticker'],
                    'chain'   => $paygatedottocryptogateway_dynamic_coin['chain'],
                    'label'   => $paygatedottocryptogateway_dynamic_coin['label'],
                    'logo'    => $paygatedottocryptogateway_dynamic_coin['logo'],
                    'path'    => $paygatedottocryptogateway_dynamic_coin['path'],
                );

                if ($paygatedottocryptogateway_dynamic_is_on && '' !== $paygatedottocryptogateway_dynamic_wallet) {
                    $paygatedottocryptogateway_dynamic_has_usable = true;
                }
            }

            // Warn (but still save) when the gateway is enabled with nothing usable.
            $paygatedottocryptogateway_dynamic_gateway_enabled = (isset($_POST[$this->plugin_id . $this->id . '_enabled']));
            if ($paygatedottocryptogateway_dynamic_gateway_enabled && !$paygatedottocryptogateway_dynamic_has_usable) {
                WC_Admin_Settings::add_error(esc_html__('Please enable at least one coin and enter its payout wallet address, otherwise no cryptocurrency can be offered at checkout.', 'crypto-payment-gateway'));
            }

            // Merge the coin config into the saved settings option.
            $paygatedottocryptogateway_dynamic_option_key = $this->get_option_key();
            $paygatedottocryptogateway_dynamic_settings   = get_option($paygatedottocryptogateway_dynamic_option_key, array());
            if (!is_array($paygatedottocryptogateway_dynamic_settings)) {
                $paygatedottocryptogateway_dynamic_settings = array();
            }
            $paygatedottocryptogateway_dynamic_settings['coin_config'] = $paygatedottocryptogateway_dynamic_config;
            update_option($paygatedottocryptogateway_dynamic_option_key, $paygatedottocryptogateway_dynamic_settings);

            // Keep the in-memory copy in sync.
            $this->settings['coin_config'] = $paygatedottocryptogateway_dynamic_config;
            $this->dynamic_coin_config     = $paygatedottocryptogateway_dynamic_config;

            return $paygatedottocryptogateway_dynamic_saved;
        }

        /**
         * Coins that are enabled AND have a payout wallet, ready for checkout.
         *
         * @return array map of coin id => config snapshot.
         */
        public function paygatedotto_dynamic_get_enabled_coins() {
            $paygatedottocryptogateway_dynamic_out = array();
            if (!is_array($this->dynamic_coin_config)) {
                return $paygatedottocryptogateway_dynamic_out;
            }
            foreach ($this->dynamic_coin_config as $paygatedottocryptogateway_dynamic_coin_id => $paygatedottocryptogateway_dynamic_row) {
                if (
                    isset($paygatedottocryptogateway_dynamic_row['enabled'], $paygatedottocryptogateway_dynamic_row['wallet'])
                    && 'yes' === $paygatedottocryptogateway_dynamic_row['enabled']
                    && '' !== $paygatedottocryptogateway_dynamic_row['wallet']
                ) {
                    $paygatedottocryptogateway_dynamic_out[$paygatedottocryptogateway_dynamic_coin_id] = $paygatedottocryptogateway_dynamic_row;
                }
            }
            return $paygatedottocryptogateway_dynamic_out;
        }

        /**
         * Only available when at least one coin is usable.
         */
        public function is_available() {
            if ('yes' !== $this->enabled) {
                return false;
            }
            return !empty($this->paygatedotto_dynamic_get_enabled_coins());
        }

        /**
         * Classic checkout: description + a radio list of the enabled coins.
         */
        public function payment_fields() {
            if ($this->description) {
                echo wp_kses_post(wpautop(wptexturize($this->description)));
            }

            $paygatedottocryptogateway_dynamic_coins = $this->paygatedotto_dynamic_get_enabled_coins();
            if (empty($paygatedottocryptogateway_dynamic_coins)) {
                return;
            }

            echo '<div class="paygatedottocryptogateway-coin-select">';
            echo '<p>' . esc_html__('Select the coin you want to pay with:', 'crypto-payment-gateway') . '</p>';

            $paygatedottocryptogateway_dynamic_first = true;
            foreach ($paygatedottocryptogateway_dynamic_coins as $paygatedottocryptogateway_dynamic_coin_id => $paygatedottocryptogateway_dynamic_coin) {
                echo '<label class="paygatedottocryptogateway-coin-option" style="' . esc_attr('display:flex;align-items:center;gap:8px;margin:4px 0;') . '">';
                echo '<input type="radio" name="paygatedotto_selected_coin" value="' . esc_attr($paygatedottocryptogateway_dynamic_coin_id) . '" ' . checked($paygatedottocryptogateway_dynamic_first, true, false) . ' />';
                if (!empty($paygatedottocryptogateway_dynamic_coin['logo'])) {
                    echo '<img src="' . esc_url($paygatedottocryptogateway_dynamic_coin['logo']) . '" alt="" style="' . esc_attr('width:20px;height:20px;') . '" />';
                }
                echo '<span>' . esc_html(paygatedottocryptogateway_dynamic_coin_display_label($paygatedottocryptogateway_dynamic_coin)) . '</span>';
                echo '</label>';
                $paygatedottocryptogateway_dynamic_first = false;
            }

            echo '</div>';
        }

        /**
         * Validate the selected coin on classic checkout.
         */
        public function validate_fields() {
            $paygatedottocryptogateway_dynamic_coins = $this->paygatedotto_dynamic_get_enabled_coins();
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce.
            $paygatedottocryptogateway_dynamic_selected = isset($_POST['paygatedotto_selected_coin']) ? sanitize_text_field(wp_unslash($_POST['paygatedotto_selected_coin'])) : '';
            if ('' === $paygatedottocryptogateway_dynamic_selected || !isset($paygatedottocryptogateway_dynamic_coins[$paygatedottocryptogateway_dynamic_selected])) {
                paygatedottocryptogateway_add_notice(esc_html__('Please select a cryptocurrency to pay with.', 'crypto-payment-gateway'), 'error');
                return false;
            }
            return true;
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);

            $paygatedottocryptogateway_dynamic_coins = $this->paygatedotto_dynamic_get_enabled_coins();

            // Read the coin selected at checkout (works for classic and, via the
            // Store API, block checkout which copies paymentMethodData into POST).
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before process_payment.
            $paygatedottocryptogateway_dynamic_selected = isset($_POST['paygatedotto_selected_coin']) ? sanitize_text_field(wp_unslash($_POST['paygatedotto_selected_coin'])) : '';

            if ('' === $paygatedottocryptogateway_dynamic_selected || !isset($paygatedottocryptogateway_dynamic_coins[$paygatedottocryptogateway_dynamic_selected])) {
                paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Please select a valid cryptocurrency to pay with.', 'crypto-payment-gateway'), 'error');
                return null;
            }

            $paygatedottocryptogateway_dynamic_coin   = $paygatedottocryptogateway_dynamic_coins[$paygatedottocryptogateway_dynamic_selected];
            $paygatedottocryptogateway_dynamic_path   = isset($paygatedottocryptogateway_dynamic_coin['path']) ? $paygatedottocryptogateway_dynamic_coin['path'] : str_replace('_', '/', $paygatedottocryptogateway_dynamic_selected);
            $paygatedottocryptogateway_dynamic_wallet = $paygatedottocryptogateway_dynamic_coin['wallet'];

            $paygatedottocryptogateway_dynamic_currency = get_woocommerce_currency();
            $paygatedottocryptogateway_dynamic_total    = $order->get_total();
            $paygatedottocryptogateway_dynamic_nonce    = wp_create_nonce('paygatedottocryptogateway_dynamic_nonce_' . $order_id);
            $paygatedottocryptogateway_dynamic_tolerance_percentage = $this->dynamic_tolerance_percentage;
            $paygatedottocryptogateway_dynamic_callback = add_query_arg(
                array('order_id' => $order_id, 'nonce' => $paygatedottocryptogateway_dynamic_nonce),
                rest_url('paygatedottocryptogateway/v1/paygatedottocryptogateway-dynamic/')
            );
            $paygatedottocryptogateway_dynamic_email        = urlencode(sanitize_email($order->get_billing_email()));
            $paygatedottocryptogateway_dynamic_status_nonce = wp_create_nonce('paygatedottocryptogateway_dynamic_status_nonce_' . $paygatedottocryptogateway_dynamic_email);

            // 1) Convert the fiat order total into the selected coin.
            $paygatedottocryptogateway_dynamic_response = wp_remote_get('https://api.paygate.to/crypto/' . $paygatedottocryptogateway_dynamic_path . '/convert.php?value=' . $paygatedottocryptogateway_dynamic_total . '&from=' . strtolower($paygatedottocryptogateway_dynamic_currency), array('timeout' => 30));

            if (is_wp_error($paygatedottocryptogateway_dynamic_response)) {
                paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed due to failed currency conversion process, please try again', 'crypto-payment-gateway'), 'error');
                return null;
            }

            $paygatedottocryptogateway_dynamic_body            = wp_remote_retrieve_body($paygatedottocryptogateway_dynamic_response);
            $paygatedottocryptogateway_dynamic_conversion_resp = json_decode($paygatedottocryptogateway_dynamic_body, true);

            if ($paygatedottocryptogateway_dynamic_conversion_resp && isset($paygatedottocryptogateway_dynamic_conversion_resp['value_coin'])) {
                $paygatedottocryptogateway_dynamic_final_total     = sanitize_text_field($paygatedottocryptogateway_dynamic_conversion_resp['value_coin']);
                $paygatedottocryptogateway_dynamic_reference_total = (float) $paygatedottocryptogateway_dynamic_final_total;
            } else {
                paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed, please try again (unsupported store currency)', 'crypto-payment-gateway'), 'error');
                return null;
            }

            // 2) Optionally add estimated blockchain fees (mirrors individual gateways).
            if ($this->dynamic_blockchain_fees === 'yes') {
                $paygatedottocryptogateway_dynamic_feesest_response = wp_remote_get('https://api.paygate.to/crypto/' . $paygatedottocryptogateway_dynamic_path . '/fees.php', array('timeout' => 30));

                if (is_wp_error($paygatedottocryptogateway_dynamic_feesest_response)) {
                    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Failed to get estimated fees, please try again', 'crypto-payment-gateway'), 'error');
                    return null;
                }

                $paygatedottocryptogateway_dynamic_feesest_body            = wp_remote_retrieve_body($paygatedottocryptogateway_dynamic_feesest_response);
                $paygatedottocryptogateway_dynamic_feesest_conversion_resp = json_decode($paygatedottocryptogateway_dynamic_feesest_body, true);

                if ($paygatedottocryptogateway_dynamic_feesest_conversion_resp && isset($paygatedottocryptogateway_dynamic_feesest_conversion_resp['estimated_cost_currency']['USD'])) {
                    $paygatedottocryptogateway_dynamic_feesest_final_total     = sanitize_text_field($paygatedottocryptogateway_dynamic_feesest_conversion_resp['estimated_cost_currency']['USD']);
                    $paygatedottocryptogateway_dynamic_feesest_reference_total = (float) $paygatedottocryptogateway_dynamic_feesest_final_total;
                } else {
                    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Failed to get estimated fees, please try again', 'crypto-payment-gateway'), 'error');
                    return null;
                }

                $paygatedottocryptogateway_dynamic_revfeesest_response = wp_remote_get('https://api.paygate.to/crypto/' . $paygatedottocryptogateway_dynamic_path . '/convert.php?value=' . $paygatedottocryptogateway_dynamic_feesest_reference_total . '&from=usd', array('timeout' => 30));

                if (is_wp_error($paygatedottocryptogateway_dynamic_revfeesest_response)) {
                    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed due to failed currency conversion process, please try again', 'crypto-payment-gateway'), 'error');
                    return null;
                }

                $paygatedottocryptogateway_dynamic_revfeesest_body            = wp_remote_retrieve_body($paygatedottocryptogateway_dynamic_revfeesest_response);
                $paygatedottocryptogateway_dynamic_revfeesest_conversion_resp = json_decode($paygatedottocryptogateway_dynamic_revfeesest_body, true);

                if ($paygatedottocryptogateway_dynamic_revfeesest_conversion_resp && isset($paygatedottocryptogateway_dynamic_revfeesest_conversion_resp['value_coin'])) {
                    $paygatedottocryptogateway_dynamic_revfeesest_reference_total = (float) sanitize_text_field($paygatedottocryptogateway_dynamic_revfeesest_conversion_resp['value_coin']);
                    $paygatedottocryptogateway_dynamic_payin_total = $paygatedottocryptogateway_dynamic_reference_total + $paygatedottocryptogateway_dynamic_revfeesest_reference_total;
                } else {
                    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed, please try again (unsupported store currency)', 'crypto-payment-gateway'), 'error');
                    return null;
                }
            } else {
                $paygatedottocryptogateway_dynamic_payin_total = $paygatedottocryptogateway_dynamic_reference_total;
            }

            // 3) Enforce the coin minimum.
            $paygatedottocryptogateway_dynamic_response_minimum = wp_remote_get('https://api.paygate.to/crypto/' . $paygatedottocryptogateway_dynamic_path . '/info.php', array('timeout' => 30));
            if (is_wp_error($paygatedottocryptogateway_dynamic_response_minimum)) {
                paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed due to failed minimum retrieval process, please try again', 'crypto-payment-gateway'), 'error');
                return null;
            }
            $paygatedottocryptogateway_dynamic_body_minimum            = wp_remote_retrieve_body($paygatedottocryptogateway_dynamic_response_minimum);
            $paygatedottocryptogateway_dynamic_conversion_resp_minimum = json_decode($paygatedottocryptogateway_dynamic_body_minimum, true);
            if ($paygatedottocryptogateway_dynamic_conversion_resp_minimum && isset($paygatedottocryptogateway_dynamic_conversion_resp_minimum['minimum'])) {
                $paygatedottocryptogateway_dynamic_final_minimum = sanitize_text_field($paygatedottocryptogateway_dynamic_conversion_resp_minimum['minimum']);
                if ($paygatedottocryptogateway_dynamic_payin_total < $paygatedottocryptogateway_dynamic_final_minimum) {
                    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed because the coin amount is below the minimum required', 'crypto-payment-gateway'), 'error');
                    return null;
                }
            } else {
                paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed, please try again (failed to fetch minimum coin amount)', 'crypto-payment-gateway'), 'error');
                return null;
            }

            // 4) Generate the unique pay-in address for this coin + payout wallet.
            $paygatedottocryptogateway_dynamic_gen_wallet = wp_remote_get('https://api.paygate.to/crypto/' . $paygatedottocryptogateway_dynamic_path . '/wallet.php?address=' . rawurlencode($paygatedottocryptogateway_dynamic_wallet) . '&callback=' . urlencode($paygatedottocryptogateway_dynamic_callback), array('timeout' => 30));

            if (is_wp_error($paygatedottocryptogateway_dynamic_gen_wallet)) {
                paygatedottocryptogateway_add_notice(__('Wallet error:', 'crypto-payment-gateway') . __('Payment could not be processed due to incorrect payout wallet settings, please contact website admin', 'crypto-payment-gateway'), 'error');
                return null;
            }

            $paygatedottocryptogateway_dynamic_wallet_body    = wp_remote_retrieve_body($paygatedottocryptogateway_dynamic_gen_wallet);
            $paygatedottocryptogateway_dynamic_wallet_decbody = json_decode($paygatedottocryptogateway_dynamic_wallet_body, true);

            if ($paygatedottocryptogateway_dynamic_wallet_decbody && isset($paygatedottocryptogateway_dynamic_wallet_decbody['address_in'])) {
                $paygatedottocryptogateway_dynamic_gen_addressIn = wp_kses_post($paygatedottocryptogateway_dynamic_wallet_decbody['address_in']);
                $paygatedottocryptogateway_dynamic_gen_ipntoken  = wp_kses_post($paygatedottocryptogateway_dynamic_wallet_decbody['ipn_token']);
                $paygatedottocryptogateway_dynamic_gen_callback  = sanitize_url($paygatedottocryptogateway_dynamic_wallet_decbody['callback_url']);

                // 5) Generate the QR code for the pay-in address.
                $paygatedottocryptogateway_dynamic_genqrcode_response = wp_remote_get('https://api.paygate.to/crypto/' . $paygatedottocryptogateway_dynamic_path . '/qrcode.php?address=' . $paygatedottocryptogateway_dynamic_gen_addressIn, array('timeout' => 30));

                if (is_wp_error($paygatedottocryptogateway_dynamic_genqrcode_response)) {
                    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Unable to generate QR code', 'crypto-payment-gateway'), 'error');
                    return null;
                }

                $paygatedottocryptogateway_dynamic_genqrcode_body            = wp_remote_retrieve_body($paygatedottocryptogateway_dynamic_genqrcode_response);
                $paygatedottocryptogateway_dynamic_genqrcode_conversion_resp = json_decode($paygatedottocryptogateway_dynamic_genqrcode_body, true);

                if ($paygatedottocryptogateway_dynamic_genqrcode_conversion_resp && isset($paygatedottocryptogateway_dynamic_genqrcode_conversion_resp['qr_code'])) {
                    $paygatedottocryptogateway_dynamic_genqrcode_pngimg = wp_kses_post($paygatedottocryptogateway_dynamic_genqrcode_conversion_resp['qr_code']);
                } else {
                    paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Unable to generate QR code', 'crypto-payment-gateway'), 'error');
                    return null;
                }

                // Persist order meta.
                // NOTE: payin_amount stores the FIAT order total (not the coin amount).
                // The callback values the received coin in fiat and compares it to
                // this expected fiat, exactly like the Multicoin callback. The coin
                // amount the buyer must send (incl. fees) is stored separately for
                // display on the QR page.
                $order->add_meta_data('paygatedotto_dynamic_payin_address', $paygatedottocryptogateway_dynamic_gen_addressIn, true);
                $order->add_meta_data('paygatedotto_dynamic_ipntoken', $paygatedottocryptogateway_dynamic_gen_ipntoken, true);
                $order->add_meta_data('paygatedotto_dynamic_callback', $paygatedottocryptogateway_dynamic_gen_callback, true);
                $order->add_meta_data('paygatedotto_dynamic_payin_amount', $paygatedottocryptogateway_dynamic_total, true);
                $order->add_meta_data('paygatedotto_dynamic_display_total', $paygatedottocryptogateway_dynamic_payin_total, true);
                $order->add_meta_data('paygatedotto_dynamic_tolerance_percentage', $paygatedottocryptogateway_dynamic_tolerance_percentage, true);
                $order->add_meta_data('paygatedotto_dynamic_currency', $paygatedottocryptogateway_dynamic_currency, true);
                $order->add_meta_data('paygatedotto_dynamic_fees_value_settings', ($this->dynamic_blockchain_fees === 'yes' ? '1' : '0'), true);
                $order->add_meta_data('paygatedotto_dynamic_coin', $paygatedottocryptogateway_dynamic_selected, true);
                $order->add_meta_data('paygatedotto_dynamic_coin_label', paygatedottocryptogateway_dynamic_coin_display_label($paygatedottocryptogateway_dynamic_coin), true);
                $order->add_meta_data('paygatedotto_dynamic_qrcode', $paygatedottocryptogateway_dynamic_genqrcode_pngimg, true);
                $order->add_meta_data('paygatedotto_dynamic_nonce', $paygatedottocryptogateway_dynamic_nonce, true);
                $order->add_meta_data('paygatedotto_dynamic_status_nonce', $paygatedottocryptogateway_dynamic_status_nonce, true);
                $order->save();
            } else {
                paygatedottocryptogateway_add_notice(__('Payment error:', 'crypto-payment-gateway') . __('Payment could not be processed, please try again (wallet address error)', 'crypto-payment-gateway'), 'error');
                return null;
            }

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }

        /**
         * Show the payment address + QR code on the merchant's own site.
         */
        public function before_thankyou_page($order_id) {
            $order = wc_get_order($order_id);
            if (!$order || $order->get_payment_method() !== $this->id) {
                return;
            }

            $paygatedottogateway_crypto_total              = $order->get_meta('paygatedotto_dynamic_display_total', true);
            $paygatedottogateway__crypto_wallet_address    = $order->get_meta('paygatedotto_dynamic_payin_address', true);
            $paygatedottogateway_crypto_qrcode             = $order->get_meta('paygatedotto_dynamic_qrcode', true);
            $paygatedottogateway_crypto_qrcode_status_nonce = $order->get_meta('paygatedotto_dynamic_status_nonce', true);
            $paygatedottogateway_crypto_coin_label         = $order->get_meta('paygatedotto_dynamic_coin_label', true);

            wp_enqueue_style('paygatedottocryptogateway-dynamic-loader-css', plugin_dir_url(__DIR__) . 'static/payment-status.css', array(), '1.0.0');

            echo '<div id="paygatedottocryptogateway-wrapper"><h1 style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '">'
                . esc_html__('Please Complete Your Payment', 'crypto-payment-gateway')
                . '</h1>';

            echo '<div style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '"><img class="' . esc_attr('paygatedottoqrcodeimg') . '" style="' . esc_attr('text-align:center;max-width:80%;margin:0 auto;') . '" src="data:image/png;base64,'
                . esc_attr($paygatedottogateway_crypto_qrcode) . '" alt="' . esc_attr__('Payment Address', 'crypto-payment-gateway') . '"/></div>';

            /* translators: 1: Amount of cryptocurrency to be sent, 2: Name of the cryptocurrency */
            echo '<p style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '">' . sprintf(esc_html__('Please send %1$s %2$s to the following address:', 'crypto-payment-gateway'), '<br><strong>' . esc_html($paygatedottogateway_crypto_total) . '</strong>', '<strong>' . esc_html($paygatedottogateway_crypto_coin_label) . '</strong>') . '</p>';

            echo '<p style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '">'
                . '<strong>' . esc_html($paygatedottogateway__crypto_wallet_address) . '</strong>'
                . '</p><br><hr></div>';

            echo '<div class="' . esc_attr('paygatedottocryptogateway-unpaid') . '" id="' . esc_attr('paygatedotto-payment-status-message') . '" style="' . esc_attr('text-align:center;max-width:100%;margin:0 auto;') . '">'
                . esc_html__('Waiting for payment', 'crypto-payment-gateway')
                . '</div><br><hr><br>';

            wp_enqueue_script('jquery');
            wp_enqueue_script('paygatedottocryptogateway-check-status', plugin_dir_url(__DIR__) . 'assets/js/paygatedottocryptogateway-payment-status-check.js?order_id=' . esc_attr($order_id) . '&nonce=' . esc_attr($paygatedottogateway_crypto_qrcode_status_nonce) . '&tickerstring=dynamic', array('jquery'), '1.0.0', true);
        }

        public function paygatedotto_crypto_payment_gateway_get_icon_url() {
            return !empty($this->icon) ? esc_url($this->icon) : '';
        }
    }

    function paygatedottocryptogateway_add_instant_payment_gateway_dynamic($gateways) {
        $gateways[] = 'PayGateDotTo_Crypto_Payment_Gateway_Dynamic';
        return $gateways;
    }
    add_filter('woocommerce_payment_gateways', 'paygatedottocryptogateway_add_instant_payment_gateway_dynamic');
}

/* -------------------------------------------------------------------------
 * REST: live status check (polled by the QR page)
 * ---------------------------------------------------------------------- */

function paygatedottocryptogateway_dynamic_check_order_status_rest_endpoint() {
    register_rest_route('paygatedottocryptogateway/v1', '/paygatedottocryptogateway-check-order-status-dynamic/', array(
        'methods'             => 'GET',
        'callback'            => 'paygatedottocryptogateway_dynamic_check_order_status_callback',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'paygatedottocryptogateway_dynamic_check_order_status_rest_endpoint');

function paygatedottocryptogateway_dynamic_check_order_status_callback($request) {
    $order_id = absint($request->get_param('order_id'));
    $paygatedottocryptogateway_dynamic_live_status_nonce = sanitize_text_field($request->get_param('nonce'));

    if (empty($order_id)) {
        return new WP_Error('missing_order_id', __('Order ID parameter is missing.', 'crypto-payment-gateway'), array('status' => 400));
    }

    $order = wc_get_order($order_id);

    if (!$order) {
        return new WP_Error('invalid_order', __('Invalid order ID.', 'crypto-payment-gateway'), array('status' => 404));
    }

    if (empty($paygatedottocryptogateway_dynamic_live_status_nonce) || $order->get_meta('paygatedotto_dynamic_status_nonce', true) !== $paygatedottocryptogateway_dynamic_live_status_nonce) {
        return new WP_Error('invalid_nonce', __('Invalid nonce.', 'crypto-payment-gateway'), array('status' => 403));
    }

    return array('status' => $order->get_status());
}

/* -------------------------------------------------------------------------
 * REST: payment-confirmation callback (Multicoin-style fiat verification)
 * ---------------------------------------------------------------------- */

function paygatedottocryptogateway_dynamic_change_order_status_rest_endpoint() {
    register_rest_route('paygatedottocryptogateway/v1', '/paygatedottocryptogateway-dynamic/', array(
        'methods'             => 'GET',
        'callback'            => 'paygatedottocryptogateway_dynamic_change_order_status_callback',
        'permission_callback' => '__return_true',
    ));
}
add_action('rest_api_init', 'paygatedottocryptogateway_dynamic_change_order_status_rest_endpoint');

/**
 * Confirm payment for the dynamic individual-coin gateway.
 *
 * Verification logic is borrowed from the Multicoin gateway: the actually
 * received coin amount is valued in the order currency (via the coin info
 * endpoint) and compared against the expected fiat amount with tolerance and
 * (optionally) estimated blockchain fees. In addition, this gateway guards that
 * the coin actually paid matches the coin the buyer selected at checkout.
 */
function paygatedottocryptogateway_dynamic_change_order_status_callback($request) {
    $order_id = absint($request->get_param('order_id'));
    $paygatedottocryptogateway_dynamic_getnonce        = sanitize_text_field($request->get_param('nonce'));
    $paygatedottocryptogateway_dynamic_paid_value_coin = sanitize_text_field($request->get_param('value_coin'));
    $paygatedottocryptogateway_dynamic_paid_coin_name  = sanitize_text_field($request->get_param('coin'));
    $paygatedottocryptogateway_dynamic_paid_txid_in    = sanitize_text_field($request->get_param('txid_in'));

    if (empty($order_id)) {
        return new WP_Error('missing_order_id', __('Order ID parameter is missing.', 'crypto-payment-gateway'), array('status' => 400));
    }

    $order = wc_get_order($order_id);

    if (!$order) {
        return new WP_Error('invalid_order', __('Invalid order ID.', 'crypto-payment-gateway'), array('status' => 404));
    }

    // Verify nonce.
    if (empty($paygatedottocryptogateway_dynamic_getnonce) || $order->get_meta('paygatedotto_dynamic_nonce', true) !== $paygatedottocryptogateway_dynamic_getnonce) {
        return new WP_Error('invalid_nonce', __('Invalid nonce.', 'crypto-payment-gateway'), array('status' => 403));
    }

    if ($order && !in_array($order->get_status(), array('processing', 'completed'), true) && 'paygatedotto-crypto-payment-gateway-dynamic' === $order->get_payment_method()) {

        $paygatedottocryptogateway_dynamic_expected_coin = $order->get_meta('paygatedotto_dynamic_coin', true);
        $paygatedottocryptogateway_dynamic_currency      = $order->get_meta('paygatedotto_dynamic_currency', true);

        // Guard: the coin paid must match the coin the buyer selected.
        // Compare case-insensitively because PayGate may echo the coin id in a
        // different case than the lower-case id stored at checkout.
        if (strtolower($paygatedottocryptogateway_dynamic_paid_coin_name) !== strtolower($paygatedottocryptogateway_dynamic_expected_coin)) {
            /* translators: 1: Paid value in coin, 2: Paid coin name, 3: Expected coin name, 4: Transaction ID */
            $order->update_status('failed', sprintf(__('[Order Failed] Customer sent %1$s %2$s instead of %3$s. TXID: %4$s', 'crypto-payment-gateway'), $paygatedottocryptogateway_dynamic_paid_value_coin, $paygatedottocryptogateway_dynamic_paid_coin_name, $paygatedottocryptogateway_dynamic_expected_coin, $paygatedottocryptogateway_dynamic_paid_txid_in));
            /* translators: 1: Paid value in coin, 2: Paid coin name, 3: Expected coin name, 4: Transaction ID */
            $order->add_order_note(sprintf(__('[Order Failed] Customer sent %1$s %2$s instead of %3$s. TXID: %4$s', 'crypto-payment-gateway'), $paygatedottocryptogateway_dynamic_paid_value_coin, $paygatedottocryptogateway_dynamic_paid_coin_name, $paygatedottocryptogateway_dynamic_expected_coin, $paygatedottocryptogateway_dynamic_paid_txid_in));
            return array('message' => 'Order status changed to failed due to incorrect coin. Please check order notes');
        }

        // Build the API path the same way the Multicoin callback does.
        $paygatedottocryptogateway_dynamic_coin_label = str_replace('_', '/', strtoupper($paygatedottocryptogateway_dynamic_paid_coin_name));
        $paygatedottocryptogateway_dynamic_info_url   = 'https://api.paygate.to/crypto/' . strtolower($paygatedottocryptogateway_dynamic_coin_label) . '/info.php';
        $paygatedottocryptogateway_dynamic_response   = wp_remote_get($paygatedottocryptogateway_dynamic_info_url, array('timeout' => 30));

        if (is_wp_error($paygatedottocryptogateway_dynamic_response)) {
            return new WP_Error('paygatedottocryptogateway_api_error', __('Failed to fetch coin data.', 'crypto-payment-gateway'), array('status' => 500));
        }

        $paygatedottocryptogateway_dynamic_body      = wp_remote_retrieve_body($paygatedottocryptogateway_dynamic_response);
        $paygatedottocryptogateway_dynamic_coin_data = json_decode($paygatedottocryptogateway_dynamic_body, true);

        if (!is_array($paygatedottocryptogateway_dynamic_coin_data) || !isset($paygatedottocryptogateway_dynamic_coin_data['prices'][$paygatedottocryptogateway_dynamic_currency])) {
            return new WP_Error('paygatedottocryptogateway_invalid_coin_data', __('Invalid coin data received from PayGate.', 'crypto-payment-gateway'), array('status' => 500));
        }

        $paygatedottocryptogateway_dynamic_coin_price = floatval($paygatedottocryptogateway_dynamic_coin_data['prices'][$paygatedottocryptogateway_dynamic_currency]);

        // Value the received coin in the order currency.
        $paygatedottocryptogateway_dynamic_received_coin = $paygatedottocryptogateway_dynamic_paid_value_coin;
        $paygatedottocryptogateway_dynamic_received_fiat = $paygatedottocryptogateway_dynamic_received_coin * $paygatedottocryptogateway_dynamic_coin_price;

        $paygatedottocryptogateway_dynamic_expected_fiat     = floatval($order->get_meta('paygatedotto_dynamic_payin_amount', true));
        $paygatedottocryptogateway_dynamic_tolerance_percent = floatval($order->get_meta('paygatedotto_dynamic_tolerance_percentage', true));
        $paygatedottocryptogateway_dynamic_fee_read_settings = $order->get_meta('paygatedotto_dynamic_fees_value_settings', true);
        $paygatedottocryptogateway_dynamic_minimum_initial_required = $paygatedottocryptogateway_dynamic_expected_fiat * $paygatedottocryptogateway_dynamic_tolerance_percent;

        if ($paygatedottocryptogateway_dynamic_fee_read_settings === '1') {
            $paygatedottocryptogateway_dynamic_feesinfo_url = 'https://api.paygate.to/crypto/' . strtolower($paygatedottocryptogateway_dynamic_coin_label) . '/fees.php';
            $paygatedottocryptogateway_dynamic_feesresponse = wp_remote_get($paygatedottocryptogateway_dynamic_feesinfo_url, array('timeout' => 30));

            $paygatedottocryptogateway_dynamic_feesbody      = wp_remote_retrieve_body($paygatedottocryptogateway_dynamic_feesresponse);
            $paygatedottocryptogateway_dynamic_feescoin_data = json_decode($paygatedottocryptogateway_dynamic_feesbody, true);

            if (!is_array($paygatedottocryptogateway_dynamic_feescoin_data) || !isset($paygatedottocryptogateway_dynamic_feescoin_data['estimated_cost_currency'][$paygatedottocryptogateway_dynamic_currency])) {
                return new WP_Error('paygatedottocryptogateway_invalid_coin_data', __('Invalid coin fee data received from PayGate.', 'crypto-payment-gateway'), array('status' => 500));
            }

            $paygatedottocryptogateway_dynamic_feescoin_price = floatval($paygatedottocryptogateway_dynamic_feescoin_data['estimated_cost_currency'][$paygatedottocryptogateway_dynamic_currency]);
            $paygatedottocryptogateway_dynamic_minimum_required = $paygatedottocryptogateway_dynamic_minimum_initial_required + $paygatedottocryptogateway_dynamic_feescoin_price;
        } else {
            $paygatedottocryptogateway_dynamic_minimum_required = $paygatedottocryptogateway_dynamic_minimum_initial_required;
        }

        if ($paygatedottocryptogateway_dynamic_received_fiat < $paygatedottocryptogateway_dynamic_minimum_required) {
            /* translators: 1: amount received, 2: coin ticker, 3: fiat amount received, 4: fiat currency, 5: minimum required fiat, 6: transaction ID */
            $order->update_status('failed', sprintf(__('[Order Failed] Received %1$s %2$s (~%3$.2f %4$s), required minimum: %5$.2f %4$s. TXID: %6$s', 'crypto-payment-gateway'),
                $paygatedottocryptogateway_dynamic_received_coin,
                esc_html(strtoupper($paygatedottocryptogateway_dynamic_paid_coin_name)),
                $paygatedottocryptogateway_dynamic_received_fiat,
                esc_html($paygatedottocryptogateway_dynamic_currency),
                $paygatedottocryptogateway_dynamic_minimum_required,
                esc_html($paygatedottocryptogateway_dynamic_paid_txid_in)
            ));

            /* translators: 1: amount received, 2: coin ticker, 3: fiat amount received, 4: fiat currency, 5: minimum required fiat, 6: transaction ID */
            $order->add_order_note(sprintf(__('[Order Failed] Received %1$s %2$s (~%3$.2f %4$s), required minimum: %5$.2f %4$s. TXID: %6$s', 'crypto-payment-gateway'),
                $paygatedottocryptogateway_dynamic_received_coin,
                esc_html(strtoupper($paygatedottocryptogateway_dynamic_paid_coin_name)),
                $paygatedottocryptogateway_dynamic_received_fiat,
                esc_html($paygatedottocryptogateway_dynamic_currency),
                $paygatedottocryptogateway_dynamic_minimum_required,
                esc_html($paygatedottocryptogateway_dynamic_paid_txid_in)
            ));
            return array('message' => 'Order status changed to failed due to partial payment. Please check order notes');
        } else {
            $order->payment_complete();
            /* translators: 1: Paid value in coin, 2: Paid coin name, 3: Transaction ID */
            $order->add_order_note(sprintf(__('[Payment completed] Customer sent %1$s %2$s TXID:%3$s', 'crypto-payment-gateway'), $paygatedottocryptogateway_dynamic_paid_value_coin, $paygatedottocryptogateway_dynamic_paid_coin_name, $paygatedottocryptogateway_dynamic_paid_txid_in));
            return array('message' => 'Payment confirmed and order status changed.');
        }
    } else {
        return new WP_Error('order_not_eligible', __('Order is not eligible for status change.', 'crypto-payment-gateway'), array('status' => 400));
    }
}

/* -------------------------------------------------------------------------
 * Block (Cart & Checkout) integration for the dynamic gateway
 * ---------------------------------------------------------------------- */

/**
 * Register (once) the dedicated block-checkout script for the dynamic gateway
 * and localise the enabled coin list so the block UI can render the coin
 * selector and submit the chosen coin.
 */
function paygatedottocryptogateway_dynamic_register_block_checkout_script() {
    $handle = 'paygatedottocryptogateway-dynamic-block-support';
    $path   = 'assets/js/paygatedottocryptogateway-dynamic-block-checkout-support.js';

    if (wp_script_is($handle, 'registered')) {
        return;
    }

    wp_register_script(
        $handle,
        plugin_dir_url(__DIR__) . $path,
        array('wc-blocks-registry', 'wp-element'),
        filemtime(plugin_dir_path(__DIR__) . $path),
        true
    );

    $paygatedottocryptogateway_dynamic_payload = array(
        'id'          => 'paygatedotto-crypto-payment-gateway-dynamic',
        'title'       => '',
        'description' => '',
        'icon_url'    => '',
        'coins'       => array(),
    );

    if (class_exists('PayGateDotTo_Crypto_Payment_Gateway_Dynamic')) {
        foreach (WC()->payment_gateways()->payment_gateways() as $paygatedottocryptogateway_dynamic_gid => $paygatedottocryptogateway_dynamic_gw) {
            if ('paygatedotto-crypto-payment-gateway-dynamic' === $paygatedottocryptogateway_dynamic_gid) {
                $paygatedottocryptogateway_dynamic_payload['title']       = sanitize_text_field($paygatedottocryptogateway_dynamic_gw->get_title());
                $paygatedottocryptogateway_dynamic_payload['description'] = wp_kses_post($paygatedottocryptogateway_dynamic_gw->get_description());

                if (method_exists($paygatedottocryptogateway_dynamic_gw, 'paygatedotto_dynamic_get_enabled_coins')) {
                    foreach ($paygatedottocryptogateway_dynamic_gw->paygatedotto_dynamic_get_enabled_coins() as $paygatedottocryptogateway_dynamic_cid => $paygatedottocryptogateway_dynamic_c) {
                        $paygatedottocryptogateway_dynamic_payload['coins'][] = array(
                            'id'    => $paygatedottocryptogateway_dynamic_cid,
                            'label' => paygatedottocryptogateway_dynamic_coin_display_label($paygatedottocryptogateway_dynamic_c),
                            'logo'  => isset($paygatedottocryptogateway_dynamic_c['logo']) ? sanitize_url($paygatedottocryptogateway_dynamic_c['logo']) : '',
                        );
                    }
                }
                break;
            }
        }
    }

    wp_localize_script($handle, 'paygatedottocryptogatewayDynamicData', $paygatedottocryptogateway_dynamic_payload);
}

if (!class_exists('PayGateDotTo_Crypto_Payment_Gateway_Dynamic_Blocks_Integration')) {
    add_action('woocommerce_blocks_loaded', function () {
        if (!class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
            return;
        }

        class PayGateDotTo_Crypto_Payment_Gateway_Dynamic_Blocks_Integration extends \Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType {

            protected $gateway;

            public function __construct($gateway = null) {
                $this->name    = 'paygatedotto-crypto-payment-gateway-dynamic';
                $this->gateway = $gateway;
            }

            public function initialize() {
            }

            public function is_active() {
                return $this->gateway && method_exists($this->gateway, 'is_available') && $this->gateway->is_available();
            }

            public function get_payment_method_script_handles() {
                paygatedottocryptogateway_dynamic_register_block_checkout_script();
                return array('paygatedottocryptogateway-dynamic-block-support');
            }

            public function get_payment_method_data() {
                $paygatedottocryptogateway_dynamic_coins = array();
                if ($this->gateway && method_exists($this->gateway, 'paygatedotto_dynamic_get_enabled_coins')) {
                    foreach ($this->gateway->paygatedotto_dynamic_get_enabled_coins() as $paygatedottocryptogateway_dynamic_cid => $paygatedottocryptogateway_dynamic_c) {
                        $paygatedottocryptogateway_dynamic_coins[] = array(
                            'id'    => $paygatedottocryptogateway_dynamic_cid,
                            'label' => paygatedottocryptogateway_dynamic_coin_display_label($paygatedottocryptogateway_dynamic_c),
                            'logo'  => isset($paygatedottocryptogateway_dynamic_c['logo']) ? sanitize_url($paygatedottocryptogateway_dynamic_c['logo']) : '',
                        );
                    }
                }

                return array(
                    'id'          => $this->name,
                    'title'       => $this->gateway ? $this->gateway->get_title() : '',
                    'description' => $this->gateway ? $this->gateway->get_description() : '',
                    'icon_url'    => '',
                    'coins'       => $paygatedottocryptogateway_dynamic_coins,
                    'supports'    => array('products'),
                );
            }
        }
    });
}
?>
