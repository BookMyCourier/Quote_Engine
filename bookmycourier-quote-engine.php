<?php
/**
 * Plugin Name: BookMyCourier Quote Engine
 * Description: Instant courier quote calculator using Google Places Autocomplete and Google Routes API.
 * Version: 1.4.0
 * Author: BookMyCourier
 */

if (!defined('ABSPATH')) exit;

define('BMCQE_VERSION', '1.4.0');
define('BMCQE_PATH', plugin_dir_path(__FILE__));
define('BMCQE_URL', plugin_dir_url(__FILE__));

require_once BMCQE_PATH . 'includes/settings.php';
require_once BMCQE_PATH . 'includes/ajax.php';

register_activation_hook(__FILE__, 'bmcqe_activate');
function bmcqe_activate() {
    $defaults = [
        'google_api_key' => '',
        'payment_url' => '/payment-details/',
        'small_base' => '35', 'small_included' => '10', 'small_rate' => '1.50',
        'medium_base' => '50', 'medium_included' => '10', 'medium_rate' => '1.80',
        'large_base' => '65', 'large_included' => '10', 'large_rate' => '2.10',
        'luton_base' => '80', 'luton_included' => '10', 'luton_rate' => '2.50',
    ];
    if (!get_option('bmcqe_settings')) {
        add_option('bmcqe_settings', $defaults);
    } else {
        $existing = get_option('bmcqe_settings', []);
        update_option('bmcqe_settings', array_merge($defaults, is_array($existing) ? $existing : []));
    }
}

add_shortcode('bookmycourier_quote', 'bmcqe_render_quote');
function bmcqe_render_quote() {
    $settings = get_option('bmcqe_settings', []);
    $api_key = isset($settings['google_api_key']) ? trim($settings['google_api_key']) : '';
    $payment_url = isset($settings['payment_url']) ? trim($settings['payment_url']) : '/payment-details/';

    wp_enqueue_style('bmcqe-style', BMCQE_URL . 'assets/css/style.css', [], BMCQE_VERSION);
    wp_enqueue_script('bmcqe-quote', BMCQE_URL . 'assets/js/quote.js', [], BMCQE_VERSION, true);

    $today = current_time('Y-m-d');
    $current_hour = (int) current_time('G');

    wp_localize_script('bmcqe-quote', 'bmcqeData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bmcqe_quote_nonce'),
        'hasApiKey' => !empty($api_key),
        'paymentUrl' => esc_url_raw($payment_url),
        'today' => $today,
        'currentHour' => $current_hour,
        'sameDayCutoffHour' => 12,
        'nextDayDiscountPercent' => 5,
        'twoDayDiscountPercent' => 10,
    ]);

    if (!empty($api_key)) {
        wp_enqueue_script('google-maps-bmcqe', 'https://maps.googleapis.com/maps/api/js?key=' . esc_attr($api_key) . '&libraries=places&callback=bmcqeInitGoogle', [], null, true);
    }

    $vehicles = [
        'small' => [
            'name' => 'Small Van',
            'desc' => 'Best for small parcels, boxes, single items and light loads.',
            'image' => 'small-van.png',
        ],
        'medium' => [
            'name' => 'Medium Van',
            'desc' => 'Best for larger items, multiple boxes, appliances and small moves.',
            'image' => 'medium-van.png',
        ],
        'large' => [
            'name' => 'Large Van',
            'desc' => 'Best for bulky furniture, trade loads and larger multi-item deliveries.',
            'image' => 'large-van.png',
        ],
        'luton' => [
            'name' => 'Luton Van',
            'desc' => 'Best for house moves, large furniture and higher-volume loads.',
            'image' => 'luton-van.png',
        ],
    ];

    ob_start();
    ?>
    <div class="bmcqe-wrap">
        <div class="bmcqe-header">
            <h2>Get a courier quote in seconds</h2>
            <p>Choose your vehicle, enter your collection and delivery addresses and your quote will calculate automatically.</p>
        </div>

        <?php if (empty($api_key)) : ?>
            <div class="bmcqe-admin-warning">Google API key has not been installed. Add it in <strong>Settings → BookMyCourier Quote</strong>.</div>
        <?php endif; ?>

        <div class="bmcqe-card">
            <div class="bmcqe-section bmcqe-vehicle-section">
                <h3><span>1</span> Choose Vehicle</h3>
                <div class="bmcqe-vehicle-grid">
                    <?php foreach ($vehicles as $key => $vehicle) : ?>
                        <label class="bmcqe-vehicle<?php echo $key === 'small' ? ' selected' : ''; ?>">
                            <input type="radio" name="bmcqe_vehicle" value="<?php echo esc_attr($key); ?>" <?php checked($key, 'small'); ?>>
                            <span class="bmcqe-radio" aria-hidden="true"></span>
                            <img src="<?php echo esc_url(BMCQE_URL . 'assets/vehicles/' . $vehicle['image']); ?>" alt="<?php echo esc_attr($vehicle['name']); ?> illustration" loading="lazy">
                            <strong><?php echo esc_html($vehicle['name']); ?></strong>
                            <em><?php echo esc_html($vehicle['desc']); ?></em>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="bmcqe-section bmcqe-address-section">
                <h3><span>2</span> Collection Address</h3>
                <div class="bmcqe-input-wrap">
                    <span aria-hidden="true">⌖</span>
                    <input id="bmcqe_collection" type="text" placeholder="Enter collection address or postcode" autocomplete="off">
                </div>

                <h3><span>3</span> Delivery Address</h3>
                <div class="bmcqe-input-wrap">
                    <span aria-hidden="true">⌖</span>
                    <input id="bmcqe_delivery" type="text" placeholder="Enter delivery address or postcode" autocomplete="off">
                </div>
            </div>

            <div class="bmcqe-section bmcqe-options-section">
                <h3><span>4</span> Collection & Delivery Options</h3>

                <div class="bmcqe-options-grid">
                    <div class="bmcqe-option-group bmcqe-collection-options">
                        <label>Collection</label>
                        <div class="bmcqe-choice-row">
                            <label class="bmcqe-choice selected">
                                <input type="radio" name="bmcqe_collection_option" value="asap" checked>
                                <span>ASAP collection</span>
                            </label>
                            <label class="bmcqe-choice">
                                <input type="radio" name="bmcqe_collection_option" value="dated">
                                <span>Dated collection</span>
                            </label>
                        </div>
                    </div>

                    <div class="bmcqe-option-group bmcqe-dated-collection bmcqe-hidden">
                        <label for="bmcqe_collection_date">Collection Date</label>
                        <input id="bmcqe_collection_date" type="date" min="<?php echo esc_attr($today); ?>" value="<?php echo esc_attr($today); ?>">

                        <label class="bmcqe-sub-label">Collection Window</label>
                        <div class="bmcqe-choice-row bmcqe-period-row">
                            <label class="bmcqe-choice selected">
                                <input type="radio" name="bmcqe_collection_period" value="am" checked>
                                <span>AM</span>
                            </label>
                            <label class="bmcqe-choice">
                                <input type="radio" name="bmcqe_collection_period" value="pm">
                                <span>PM</span>
                            </label>
                        </div>
                    </div>

                    <div class="bmcqe-option-group bmcqe-delivery-options">
                        <label>Delivery Option</label>
                        <div class="bmcqe-choice-row">
                            <label class="bmcqe-choice selected" id="bmcqe_same_day_choice">
                                <input type="radio" name="bmcqe_delivery_option" value="same_day" checked>
                                <span>Same Day</span>
                            </label>
                            <label class="bmcqe-choice">
                                <input type="radio" name="bmcqe_delivery_option" value="next_day">
                                <span>Next Day <strong>Save 5%</strong></span>
                            </label>
                            <label class="bmcqe-choice">
                                <input type="radio" name="bmcqe_delivery_option" value="within_2_days">
                                <span>Within 2 days <strong>Save 10%</strong></span>
                            </label>
                        </div>
                        <small id="bmcqe_delivery_note">Same Day is available when booked before 12pm.</small>
                    </div>
                </div>
            </div>

            <div class="bmcqe-result-panel" aria-live="polite">
                <div class="bmcqe-price-box">
                    <span>Your Estimated Price</span>
                    <strong id="bmcqe_price">—</strong>
                    <small id="bmcqe_price_note">Enter both addresses to calculate your quote.</small>
                </div>
                <div class="bmcqe-book-box">
                    <button id="bmcqe_book_now" type="button" disabled>Book Now</button>
                    <small>Proceed to payment details</small>
                </div>
                <p id="bmcqe_message"></p>
            </div>

            <div class="bmcqe-trust-row">
                <div><strong>Fully Insured</strong><span>Your goods are in safe hands</span></div>
                <div><strong>Fast & Reliable</strong><span>Professional courier service</span></div>
                <div><strong>Secure Booking</strong><span>Payment details handled securely</span></div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
