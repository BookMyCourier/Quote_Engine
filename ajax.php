<?php
if (!defined('ABSPATH')) exit;

add_action('wp_ajax_bmcqe_get_quote', 'bmcqe_get_quote');
add_action('wp_ajax_nopriv_bmcqe_get_quote', 'bmcqe_get_quote');

function bmcqe_get_quote() {
    check_ajax_referer('bmcqe_quote_nonce', 'nonce');

    $settings = get_option('bmcqe_settings', []);
    $api_key = trim($settings['google_api_key'] ?? '');
    if (!$api_key) wp_send_json_error(['message' => 'Google API key has not been installed.']);

    $collection = sanitize_text_field(wp_unslash($_POST['collection'] ?? ''));
    $delivery = sanitize_text_field(wp_unslash($_POST['delivery'] ?? ''));
    $vehicle = sanitize_key($_POST['vehicle'] ?? 'small');
    $collection_date = sanitize_text_field(wp_unslash($_POST['collection_date'] ?? ''));
    $collection_option = sanitize_key($_POST['collection_option'] ?? 'asap');
    $collection_period = sanitize_key($_POST['collection_period'] ?? 'am');
    $delivery_option = sanitize_key($_POST['delivery_option'] ?? 'same_day');

    if (!$collection || !$delivery) wp_send_json_error(['message' => 'Please enter both addresses.']);

    $today = current_time('Y-m-d');

    if (!in_array($collection_option, ['asap', 'dated'], true)) $collection_option = 'asap';

    if ($collection_option === 'asap') {
        $collection_date = $today;
        $collection_period = 'ASAP';
    } else {
        if (!$collection_date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $collection_date)) {
            wp_send_json_error(['message' => 'Please choose a valid collection date.']);
        }
        if ($collection_date < $today) {
            wp_send_json_error(['message' => 'Collection date cannot be in the past.']);
        }
        if (!in_array($collection_period, ['am', 'pm'], true)) {
            wp_send_json_error(['message' => 'Please choose AM or PM for the collection window.']);
        }
        $collection_period = strtoupper($collection_period);
    }

    if (!in_array($delivery_option, ['same_day', 'next_day', 'within_2_days'], true)) $delivery_option = 'same_day';

    if ($delivery_option === 'same_day' && $collection_date === $today && (int) current_time('G') >= 12) {
        wp_send_json_error(['message' => 'Same Day delivery is only available before 12pm for today.']);
    }

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

    if (is_wp_error($response)) wp_send_json_error(['message' => 'Google route request failed.']);

    $code = wp_remote_retrieve_response_code($response);
    $raw = wp_remote_retrieve_body($response);
    $data = json_decode($raw, true);

    if ($code < 200 || $code >= 300) {
        $msg = $data['error']['message'] ?? 'Google returned an error.';
        wp_send_json_error(['message' => $msg]);
    }

    if (empty($data['routes'][0]['distanceMeters'])) wp_send_json_error(['message' => 'Could not calculate that route.']);

    $miles = (int)ceil($data['routes'][0]['distanceMeters'] / 1609.344);
    $rate = $vehicles[$vehicle];
    $extra = max(0, $miles - $rate['included']);
    $price_before_discount = $rate['base'] + ($extra * $rate['rate']);
    $discount_percent = 0;

    if ($delivery_option === 'next_day') {
        $discount_percent = 5;
    } elseif ($delivery_option === 'within_2_days') {
        $discount_percent = 10;
    }

    $price = $price_before_discount * (1 - ($discount_percent / 100));

    wp_send_json_success([
        'miles' => $miles,
        'price' => number_format($price, 2, '.', ''),
        'price_before_discount' => number_format($price_before_discount, 2, '.', ''),
        'included' => number_format((float)$rate['included'], 0),
        'rate' => number_format((float)$rate['rate'], 2, '.', ''),
        'collection_date' => $collection_date,
        'collection_option' => $collection_option,
        'collection_period' => $collection_period,
        'delivery_option' => $delivery_option,
        'discount_percent' => $discount_percent,
    ]);
}
