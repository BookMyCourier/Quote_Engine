<?php
/**
 * Plugin Name: BookMyCourier Quote Engine
 * Description: Instant courier quote calculator using Google Places Autocomplete and Google Routes API.
 * Version: 2.0.0
 * Author: BookMyCourier
 */

if (!defined('ABSPATH')) exit;

define('BMCQE_VERSION', '2.0.0');
define('BMCQE_PATH', plugin_dir_path(__FILE__));
define('BMCQE_URL', plugin_dir_url(__FILE__));

require_once BMCQE_PATH . 'includes/settings.php';
require_once BMCQE_PATH . 'includes/ajax.php';
require_once BMCQE_PATH . 'includes/booking.php';

register_activation_hook(__FILE__, 'bmcqe_activate');
function bmcqe_activate() {
    $defaults = [
        'google_api_key' => '',
        'payment_url' => '/payment-details/',
        'admin_email' => get_option('admin_email'),
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

    wp_localize_script('bmcqe-quote', 'bmcqeData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bmcqe_quote_nonce'),
        'hasApiKey' => !empty($api_key),
        'paymentUrl' => esc_url_raw($payment_url),
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

            <div class="bmcqe-result-panel" aria-live="polite">
                <div class="bmcqe-price-box">
                    <span>Your Estimated Price</span>
                    <strong id="bmcqe_price">—</strong>
                    <small id="bmcqe_price_note">Enter both addresses to calculate your quote.</small>
                </div>
                <div class="bmcqe-book-box">
                    <button id="bmcqe_book_now" type="button" disabled>Book Now</button>
                    <small>Enter details and continue to secure payment</small>
                </div>
                <p id="bmcqe_message"></p>
            </div>

            <div id="bmcqe_booking_panel" class="bmcqe-booking-panel" hidden>
                <div class="bmcqe-booking-head">
                    <div>
                        <h3>Complete Your Booking</h3>
                        <p>We only ask for the details needed to secure the courier.</p>
                    </div>
                    <button id="bmcqe_edit_quote" type="button">Edit quote</button>
                </div>

                <div class="bmcqe-booking-grid">
                    <label>
                        Full Name
                        <input id="bmcqe_customer_name" type="text" autocomplete="name" placeholder="Your name">
                    </label>
                    <label>
                        Mobile Number
                        <input id="bmcqe_customer_phone" type="tel" autocomplete="tel" placeholder="Best contact number">
                    </label>
                    <label>
                        Email Address
                        <input id="bmcqe_customer_email" type="email" autocomplete="email" placeholder="Booking confirmation email">
                    </label>
                    <label>
                        Collection Date
                        <input id="bmcqe_collection_date" type="date">
                    </label>
                    <label>
                        Preferred Time
                        <select id="bmcqe_collection_time">
                            <option value="ASAP">As soon as possible</option>
                            <option value="Morning">Morning</option>
                            <option value="Afternoon">Afternoon</option>
                            <option value="Evening">Evening</option>
                            <option value="Specific time requested">Specific time requested</option>
                        </select>
                    </label>
                    <label class="bmcqe-full">
                        What are you sending?
                        <textarea id="bmcqe_items" rows="3" placeholder="Briefly describe the items, size and any access notes"></textarea>
                    </label>
                </div>

                <div class="bmcqe-booking-summary">
                    <div>
                        <span>Quote total</span>
                        <strong id="bmcqe_booking_price">—</strong>
                    </div>
                    <button id="bmcqe_continue_payment" type="button">Continue to Payment</button>
                </div>
                <p id="bmcqe_booking_message" class="bmcqe-booking-message"></p>
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
