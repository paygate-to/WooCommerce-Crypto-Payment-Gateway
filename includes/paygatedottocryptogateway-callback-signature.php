<?php
/**
 * PayGate.to Callback Signature Verification.
 *
 * Every callback PayGate sends is signed with PayGate's private key. This file
 * verifies that signature before any order state is changed, which proves the
 * request genuinely came from PayGate and that no query parameter (notably
 * value_coin) was altered in transit.
 *
 * Scheme, per https://paygate.to/docs/payment-gateway-api/#callback-event :
 *   - Header  X-PayGate-Signature : base64-encoded signature.
 *   - Header  X-PayGate-Key-Id    : which published key signed the callback.
 *   - Algorithm: RSA 2048-bit, SHA-256, PKCS#1 v1.5  (OPENSSL_ALGO_SHA256).
 *   - Signed content: the exact, complete URL PayGate requested — the
 *     registered callback URL plus every query parameter, in the order sent,
 *     with the encoding sent.
 *
 * "Rebuild nothing": the signed string is reconstructed from the scheme, host
 * and path of the callback URL this plugin itself registered, plus the RAW
 * query string exactly as it arrived. The Host header is never trusted (a
 * reverse proxy may rewrite it) and the query is never reassembled from parsed
 * parameters (http_build_query() turns %20 into +, normalises %7b to %7B,
 * re-encodes ~ as %7E and collapses repeated keys — one changed byte and a
 * valid signature is rejected).
 *
 * @package crypto-payment-gateway
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('PAYGATEDOTTOCRYPTOGATEWAY_SIGNATURE_DIR')) {
    define('PAYGATEDOTTOCRYPTOGATEWAY_SIGNATURE_DIR', __DIR__ . '/keys');
}

/**
 * Map a PayGate key id to the locally shipped public key PEM.
 *
 * Keys are held by id rather than assuming a single key, so PayGate can
 * introduce a new key without breaking this plugin. An unknown id is treated as
 * "cannot verify" rather than "invalid": the caller decides how to handle that
 * (we fail closed, but log a distinct reason so the cause is diagnosable).
 *
 * @param string $paygatedottocryptogateway_signature_key_id Key id from X-PayGate-Key-Id.
 * @return string|false PEM contents, or false when the id is not shipped.
 */
function paygatedottocryptogateway_signature_get_public_key($paygatedottocryptogateway_signature_key_id) {
    // Whitelist the id to a bare filename; never let a header reach the filesystem.
    if (!preg_match('/^[A-Za-z0-9_-]{1,32}$/', $paygatedottocryptogateway_signature_key_id)) {
        return false;
    }

    $paygatedottocryptogateway_signature_path = PAYGATEDOTTOCRYPTOGATEWAY_SIGNATURE_DIR . '/paygate_callback_public_' . $paygatedottocryptogateway_signature_key_id . '.pem';

    if (!is_readable($paygatedottocryptogateway_signature_path)) {
        return false;
    }

    $paygatedottocryptogateway_signature_pem = file_get_contents($paygatedottocryptogateway_signature_path);

    return (is_string($paygatedottocryptogateway_signature_pem) && '' !== $paygatedottocryptogateway_signature_pem)
        ? $paygatedottocryptogateway_signature_pem
        : false;
}

/**
 * Derive the signature base (scheme + host + path, no query) from a callback URL.
 *
 * The plugin registers callbacks that already carry their own query string
 * (?order_id=..&nonce=..), so the query must be stripped here: PayGate appends
 * its payment parameters to that same query string, and the raw incoming query
 * string therefore already contains order_id and nonce. Appending the raw query
 * to a base that still had a query would duplicate it.
 *
 * Works for both WordPress REST URL shapes:
 *   - pretty permalinks: https://site/wp-json/ns/v1/route/
 *   - plain permalinks:  https://site/index.php?rest_route=/ns/v1/route
 * In the plain case the path is /index.php and rest_route travels in the query
 * string, which the raw query string preserves verbatim.
 *
 * @param string $paygatedottocryptogateway_signature_callback_url The registered callback URL.
 * @return string|false Base URL without query/fragment, or false if unparsable.
 */
function paygatedottocryptogateway_signature_base_from_callback($paygatedottocryptogateway_signature_callback_url) {
    if (!is_string($paygatedottocryptogateway_signature_callback_url) || '' === $paygatedottocryptogateway_signature_callback_url) {
        return false;
    }

    $paygatedottocryptogateway_signature_parts = wp_parse_url($paygatedottocryptogateway_signature_callback_url);

    if (!is_array($paygatedottocryptogateway_signature_parts) || empty($paygatedottocryptogateway_signature_parts['scheme']) || empty($paygatedottocryptogateway_signature_parts['host'])) {
        return false;
    }

    $paygatedottocryptogateway_signature_port = isset($paygatedottocryptogateway_signature_parts['port'])
        ? ':' . (int) $paygatedottocryptogateway_signature_parts['port']
        : '';

    $paygatedottocryptogateway_signature_path = isset($paygatedottocryptogateway_signature_parts['path'])
        ? $paygatedottocryptogateway_signature_parts['path']
        : '/';

    return $paygatedottocryptogateway_signature_parts['scheme'] . '://' . $paygatedottocryptogateway_signature_parts['host'] . $paygatedottocryptogateway_signature_port . $paygatedottocryptogateway_signature_path;
}

/**
 * The raw query string exactly as received, with no re-encoding.
 *
 * @return string
 */
function paygatedottocryptogateway_signature_raw_query_string() {
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- must stay byte-exact for signature verification; never used as output or in a query.
    return isset($_SERVER['QUERY_STRING']) ? (string) wp_unslash($_SERVER['QUERY_STRING']) : '';
}

/**
 * Read a request header, preferring the WP_REST_Request copy.
 *
 * @param WP_REST_Request|null $paygatedottocryptogateway_signature_request Request object, if available.
 * @param string               $paygatedottocryptogateway_signature_header  Header name, e.g. 'X-PayGate-Signature'.
 * @return string Header value, or '' when absent.
 */
function paygatedottocryptogateway_signature_read_header($paygatedottocryptogateway_signature_request, $paygatedottocryptogateway_signature_header) {
    if ($paygatedottocryptogateway_signature_request instanceof WP_REST_Request) {
        $paygatedottocryptogateway_signature_value = $paygatedottocryptogateway_signature_request->get_header($paygatedottocryptogateway_signature_header);
        if (is_string($paygatedottocryptogateway_signature_value) && '' !== $paygatedottocryptogateway_signature_value) {
            return trim($paygatedottocryptogateway_signature_value);
        }
    }

    $paygatedottocryptogateway_signature_server_key = 'HTTP_' . strtoupper(str_replace('-', '_', $paygatedottocryptogateway_signature_header));

    if (isset($_SERVER[$paygatedottocryptogateway_signature_server_key])) {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- compared cryptographically / whitelisted by regex, never output.
        return trim((string) wp_unslash($_SERVER[$paygatedottocryptogateway_signature_server_key]));
    }

    return '';
}

/**
 * Verify the PayGate signature on the current callback request.
 *
 * @param WP_REST_Request|null $paygatedottocryptogateway_signature_request      Current REST request.
 * @param string               $paygatedottocryptogateway_signature_callback_url Callback URL registered for this order.
 * @return array {
 *     @type bool   $verified True only when a signature was present and valid.
 *     @type bool   $present  Whether a signature header was supplied at all.
 *     @type string $reason   Machine-readable reason when not verified.
 * }
 */
function paygatedottocryptogateway_signature_verify_callback($paygatedottocryptogateway_signature_request, $paygatedottocryptogateway_signature_callback_url) {
    $paygatedottocryptogateway_signature_b64 = paygatedottocryptogateway_signature_read_header($paygatedottocryptogateway_signature_request, 'X-PayGate-Signature');

    if ('' === $paygatedottocryptogateway_signature_b64) {
        return array('verified' => false, 'present' => false, 'reason' => 'signature_absent');
    }

    if (!function_exists('openssl_verify')) {
        return array('verified' => false, 'present' => true, 'reason' => 'openssl_unavailable');
    }

    $paygatedottocryptogateway_signature_key_id = paygatedottocryptogateway_signature_read_header($paygatedottocryptogateway_signature_request, 'X-PayGate-Key-Id');
    if ('' === $paygatedottocryptogateway_signature_key_id) {
        $paygatedottocryptogateway_signature_key_id = 'v1';
    }

    $paygatedottocryptogateway_signature_pem = paygatedottocryptogateway_signature_get_public_key($paygatedottocryptogateway_signature_key_id);
    if (false === $paygatedottocryptogateway_signature_pem) {
        return array('verified' => false, 'present' => true, 'reason' => 'unknown_key_id');
    }

    $paygatedottocryptogateway_signature_public_key = openssl_pkey_get_public($paygatedottocryptogateway_signature_pem);
    if (false === $paygatedottocryptogateway_signature_public_key) {
        return array('verified' => false, 'present' => true, 'reason' => 'bad_public_key');
    }

    $paygatedottocryptogateway_signature_raw = base64_decode($paygatedottocryptogateway_signature_b64, true);
    if (false === $paygatedottocryptogateway_signature_raw || '' === $paygatedottocryptogateway_signature_raw) {
        return array('verified' => false, 'present' => true, 'reason' => 'signature_not_base64');
    }

    $paygatedottocryptogateway_signature_base = paygatedottocryptogateway_signature_base_from_callback($paygatedottocryptogateway_signature_callback_url);
    if (false === $paygatedottocryptogateway_signature_base) {
        return array('verified' => false, 'present' => true, 'reason' => 'no_registered_callback');
    }

    $paygatedottocryptogateway_signature_query  = paygatedottocryptogateway_signature_raw_query_string();
    $paygatedottocryptogateway_signature_signed = ('' === $paygatedottocryptogateway_signature_query)
        ? $paygatedottocryptogateway_signature_base
        : $paygatedottocryptogateway_signature_base . '?' . $paygatedottocryptogateway_signature_query;

    $paygatedottocryptogateway_signature_result = openssl_verify(
        $paygatedottocryptogateway_signature_signed,
        $paygatedottocryptogateway_signature_raw,
        $paygatedottocryptogateway_signature_public_key,
        OPENSSL_ALGO_SHA256
    );

    if (1 === $paygatedottocryptogateway_signature_result) {
        return array('verified' => true, 'present' => true, 'reason' => '');
    }

    // 0 = signature does not match; -1 = openssl error. Both fail closed.
    return array(
        'verified' => false,
        'present'  => true,
        'reason'   => (-1 === $paygatedottocryptogateway_signature_result) ? 'openssl_error' : 'signature_mismatch',
    );
}

/**
 * Gate a payment-confirmation callback on a valid PayGate signature.
 *
 * Rollout behaviour, so updating the plugin never strands an in-flight order:
 *
 *   - Orders created by this plugin version are stamped
 *     {$prefix}_signature_required = 'yes' at wallet-creation time. For those a
 *     valid signature is mandatory; unsigned or invalid callbacks are rejected.
 *   - Orders created before the update carry no stamp. For those an invalid
 *     signature is still rejected (fail closed on tampering), but an absent
 *     signature is allowed through so a pending legacy order can still settle.
 *
 * In both cases a present-but-invalid signature always fails.
 *
 * @param WP_REST_Request|null $paygatedottocryptogateway_signature_request Current REST request.
 * @param WC_Order             $paygatedottocryptogateway_signature_order   Order being confirmed.
 * @param string               $paygatedottocryptogateway_signature_prefix  Meta prefix, e.g. 'paygatedotto_dynamic'.
 * @return WP_Error|null WP_Error to return from the callback, or null to proceed.
 */
function paygatedottocryptogateway_signature_guard($paygatedottocryptogateway_signature_request, $paygatedottocryptogateway_signature_order, $paygatedottocryptogateway_signature_prefix) {
    $paygatedottocryptogateway_signature_callback_url = $paygatedottocryptogateway_signature_order->get_meta($paygatedottocryptogateway_signature_prefix . '_signature_base', true);

    if ('' === $paygatedottocryptogateway_signature_callback_url) {
        // Fall back to the callback URL echoed back by PayGate at wallet creation.
        $paygatedottocryptogateway_signature_callback_url = $paygatedottocryptogateway_signature_order->get_meta($paygatedottocryptogateway_signature_prefix . '_callback', true);
    }

    $paygatedottocryptogateway_signature_required = ('yes' === $paygatedottocryptogateway_signature_order->get_meta($paygatedottocryptogateway_signature_prefix . '_signature_required', true));

    $paygatedottocryptogateway_signature_check = paygatedottocryptogateway_signature_verify_callback($paygatedottocryptogateway_signature_request, $paygatedottocryptogateway_signature_callback_url);

    if (true === $paygatedottocryptogateway_signature_check['verified']) {
        return null;
    }

    // Present but not valid: always reject, whatever the order's vintage.
    if (true === $paygatedottocryptogateway_signature_check['present']) {
        $paygatedottocryptogateway_signature_order->add_order_note(
            sprintf(
                /* translators: 1: machine-readable failure reason */
                __('[Security] Rejected a payment callback whose PayGate signature did not verify (%1$s). No order status was changed.', 'crypto-payment-gateway'),
                $paygatedottocryptogateway_signature_check['reason']
            )
        );

        return new WP_Error(
            'paygatedotto_invalid_signature',
            __('Invalid callback signature.', 'crypto-payment-gateway'),
            array('status' => 403)
        );
    }

    // Absent signature on an order that requires one: reject.
    if ($paygatedottocryptogateway_signature_required) {
        $paygatedottocryptogateway_signature_order->add_order_note(
            __('[Security] Rejected an unsigned payment callback. This order requires a signed PayGate callback. No order status was changed.', 'crypto-payment-gateway')
        );

        return new WP_Error(
            'paygatedotto_missing_signature',
            __('Missing callback signature.', 'crypto-payment-gateway'),
            array('status' => 403)
        );
    }

    // Absent signature on a legacy order: allow, but record it.
    $paygatedottocryptogateway_signature_order->add_order_note(
        __('[Notice] Payment callback arrived without a PayGate signature and was accepted because this order predates signature verification.', 'crypto-payment-gateway')
    );

    return null;
}
