<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_bmcqe_get_quote', 'bmcqe_get_quote');
add_action('wp_ajax_nopriv_bmcqe_get_quote', 'bmcqe_get_quote');

function bmcqe_get_quote() {
    check_ajax_referer('bmcqe_quote_nonce', 'nonce');

    $settings = get_option('bmcqe_settings', []);
    $api_key = trim($settings['google_api_key'] ?? '');
    if (!$api_key) wp_send_json_error('Google API key has not been installed.');

    $collection = sanitize_text_field(wp_unslash($_POST['collection'] ?? ''));
    $delivery = sanitize_text_field(wp_unslash($_POST['delivery'] ?? ''));
    $vehicle = sanitize_key($_POST['vehicle'] ?? 'small');

    if (!$collection || !$delivery) wp_send_json_error('Please enter both addresses.');

    $vehicles = [];
    foreach (['small','medium','large','luton'] as $v) {
        $vehicles[$v] = [
            'base' => (float)($settings[$v . '_base'] ?? 0),
            'included' => (float)($settings[$v . '_included'] ?? 0),
            'rate' => (float)($settings[$v . '_rate'] ?? 0),
        ];
    }
    if (!isset($vehicles[$vehicle])) $vehicle = 'small';

    $body = [
        'origin' => ['address' => $collection],
        'destination' => ['address' => $delivery],
        'travelMode' => 'DRIVE',
        'routingPreference' => 'TRAFFIC_AWARE',
        'units' => 'IMPERIAL'
    ];

    $response = wp_remote_post('https://routes.googleapis.com/directions/v2:computeRoutes', [
        'headers' => [
            'Content-Type' => 'application/json',
            'X-Goog-Api-Key' => $api_key,
            'X-Goog-FieldMask' => 'routes.distanceMeters'
        ],
        'body' => wp_json_encode($body),
        'timeout' => 20,
    ]);

    if (is_wp_error($response)) wp_send_json_error('Google route request failed.');

    $code = wp_remote_retrieve_response_code($response);
    $raw = wp_remote_retrieve_body($response);
    $data = json_decode($raw, true);

    if ($code < 200 || $code >= 300) {
        $msg = $data['error']['message'] ?? 'Google returned an error.';
        wp_send_json_error($msg);
    }

    if (empty($data['routes'][0]['distanceMeters'])) wp_send_json_error('Could not calculate that route.');

    $miles = (int)ceil($data['routes'][0]['distanceMeters'] / 1609.344);
    $rate = $vehicles[$vehicle];
    $extra = max(0, $miles - $rate['included']);
    $price = $rate['base'] + ($extra * $rate['rate']);

    wp_send_json_success([
        'miles' => $miles,
        'price' => number_format($price, 2),
        'included' => number_format((float)$rate['included'], 0),
        'rate' => number_format((float)$rate['rate'], 2),
    ]);
}
