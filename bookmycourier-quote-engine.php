<?php
/**
 * Plugin Name: BookMyCourier Quote Engine
 * Description: Instant courier quote, same-page booking details and test payment flow for BookMyCourier.
 * Version: 2.0.3
 * Author: BookMyCourier
 */

if (!defined('ABSPATH')) exit;

define('BMCQE_VERSION', '2.0.3');
define('BMCQE_PATH', plugin_dir_path(__FILE__));
define('BMCQE_URL', plugin_dir_url(__FILE__));

require_once BMCQE_PATH . 'includes/settings.php';
require_once BMCQE_PATH . 'includes/ajax.php';
require_once BMCQE_PATH . 'includes/booking.php';

register_activation_hook(__FILE__, 'bmcqe_activate');
function bmcqe_activate() {
    bmcqe_install_defaults();
    bmcqe_maybe_create_booking_page();
    bmcqe_maybe_create_terms_page();
}

add_action('admin_init', 'bmcqe_upgrade_checks');
function bmcqe_upgrade_checks() {
    bmcqe_install_defaults();
    bmcqe_maybe_create_booking_page();
    bmcqe_maybe_create_terms_page();
}

function bmcqe_install_defaults() {
    $defaults = [
        'google_api_key' => '',
        'payment_url' => '/complete-your-booking/',
        'terms_url' => '/terms-and-conditions/',
        'small_base' => '35', 'small_included' => '10', 'small_rate' => '1.50',
        'medium_base' => '50', 'medium_included' => '10', 'medium_rate' => '1.80',
        'large_base' => '65', 'large_included' => '10', 'large_rate' => '2.10',
        'luton_base' => '80', 'luton_included' => '10', 'luton_rate' => '2.50',
        'admin_email' => get_option('admin_email'),
    ];

    $existing = get_option('bmcqe_settings', []);
    if (!is_array($existing)) $existing = [];

    // Preserve existing settings while adding any new defaults.
    update_option('bmcqe_settings', array_merge($defaults, $existing));
}

function bmcqe_maybe_create_booking_page() {
    if (!current_user_can('manage_options')) return;

    $existing_page = get_page_by_path('complete-your-booking');
    if ($existing_page && $existing_page->post_status !== 'trash') {
        $settings = get_option('bmcqe_settings', []);
        if (is_array($settings)) {
            $settings['payment_url'] = '/complete-your-booking/';
            update_option('bmcqe_settings', $settings);
        }
        return;
    }

    $page_id = wp_insert_post([
        'post_title'   => 'Complete Your Booking',
        'post_name'    => 'complete-your-booking',
        'post_content' => '[bookmycourier_payment]',
        'post_status'  => 'publish',
        'post_type'    => 'page',
    ]);

    if (!is_wp_error($page_id) && $page_id) {
        $settings = get_option('bmcqe_settings', []);
        if (!is_array($settings)) $settings = [];
        $settings['payment_url'] = '/complete-your-booking/';
        update_option('bmcqe_settings', $settings);
    }
}


function bmcqe_maybe_create_terms_page() {
    if (!current_user_can('manage_options')) return;

    $existing_page = get_page_by_path('terms-and-conditions');
    if ($existing_page && $existing_page->post_status !== 'trash') {
        $settings = get_option('bmcqe_settings', []);
        if (is_array($settings)) {
            $settings['terms_url'] = '/terms-and-conditions/';
            update_option('bmcqe_settings', $settings);
        }
        return;
    }

    $page_id = wp_insert_post([
        'post_title'   => 'Terms and Conditions',
        'post_name'    => 'terms-and-conditions',
        'post_content' => '<h2>BookMyCourier Terms and Conditions</h2><p>Please replace this page with your full terms and conditions before taking live customer bookings.</p><p>This should cover pricing, cancellation, liability, prohibited goods, collection windows, delivery windows and payment terms.</p>',
        'post_status'  => 'publish',
        'post_type'    => 'page',
    ]);

    if (!is_wp_error($page_id) && $page_id) {
        $settings = get_option('bmcqe_settings', []);
        if (!is_array($settings)) $settings = [];
        $settings['terms_url'] = '/terms-and-conditions/';
        update_option('bmcqe_settings', $settings);
    }
}

add_shortcode('bookmycourier_quote', 'bmcqe_render_quote');
function bmcqe_render_quote() {
    $settings = get_option('bmcqe_settings', []);
    $api_key = isset($settings['google_api_key']) ? trim($settings['google_api_key']) : '';
    $payment_url = isset($settings['payment_url']) ? trim($settings['payment_url']) : '/complete-your-booking/';

    wp_enqueue_style('bmcqe-style', BMCQE_URL . 'assets/css/style.css', [], BMCQE_VERSION);
    wp_enqueue_script('bmcqe-quote', BMCQE_URL . 'assets/js/quote.js', [], BMCQE_VERSION, true);

    $today = current_time('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime($today . ' +1 day'));
    $current_hour = (int) current_time('G');

    wp_localize_script('bmcqe-quote', 'bmcqeData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bmcqe_quote_nonce'),
        'hasApiKey' => !empty($api_key),
        'paymentUrl' => esc_url_raw($payment_url),
        'today' => $today,
        'tomorrow' => $tomorrow,
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
                <h3><span>4</span> Collection Options</h3>
                <p class="bmcqe-section-intro">Choose whether you need collection as soon as possible or on a specific date.</p>

                <div class="bmcqe-selector-panel bmcqe-collection-panel">
                    <div class="bmcqe-selector-grid bmcqe-selector-grid-two">
                        <label class="bmcqe-selector-card selected">
                            <input type="radio" name="bmcqe_collection_option" value="asap" checked>
                            <span class="bmcqe-selector-icon">⚡</span>
                            <strong>ASAP Collection</strong>
                            <em>Collect as soon as possible, subject to availability.</em>
                        </label>

                        <label class="bmcqe-selector-card">
                            <input type="radio" name="bmcqe_collection_option" value="dated">
                            <span class="bmcqe-selector-icon">📅</span>
                            <strong>Dated Collection</strong>
                            <em>Choose a collection date and a simple AM or PM window.</em>
                            <b>Save 5%</b>
                        </label>
                    </div>

                    <div class="bmcqe-dated-collection bmcqe-hidden">
                        <div class="bmcqe-date-window">
                            <label for="bmcqe_collection_date">Collection Date
                                <input id="bmcqe_collection_date" type="date" min="<?php echo esc_attr($tomorrow); ?>" value="<?php echo esc_attr($tomorrow); ?>">
                            </label>

                            <div>
                                <span class="bmcqe-sub-label">Collection Window</span>
                                <div class="bmcqe-selector-grid bmcqe-selector-grid-two bmcqe-period-row">
                                    <label class="bmcqe-selector-card bmcqe-mini-card selected">
                                        <input type="radio" name="bmcqe_collection_period" value="am" checked>
                                        <strong>AM</strong>
                                        <em>Morning collection window</em>
                                    </label>
                                    <label class="bmcqe-selector-card bmcqe-mini-card">
                                        <input type="radio" name="bmcqe_collection_period" value="pm">
                                        <strong>PM</strong>
                                        <em>Afternoon collection window</em>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bmcqe-section bmcqe-options-section">
                <h3><span>5</span> Delivery Options</h3>
                <p class="bmcqe-section-intro">Pick the delivery speed that suits the job. Flexible delivery options reduce the quote automatically.</p>

                <div class="bmcqe-selector-grid bmcqe-delivery-selector-grid">
                    <label class="bmcqe-selector-card selected" id="bmcqe_same_day_choice">
                        <input type="radio" name="bmcqe_delivery_option" value="same_day" checked>
                        <span class="bmcqe-selector-icon">🚀</span>
                        <strong>Same Day</strong>
                        <em>Priority delivery on the same day.</em>
                    </label>

                    <label class="bmcqe-selector-card">
                        <input type="radio" name="bmcqe_delivery_option" value="next_day">
                        <span class="bmcqe-selector-icon">🌙</span>
                        <strong>Next Day</strong>
                        <em>Save 5% when delivery can be completed next day.</em>
                        <b>Save 5%</b>
                    </label>

                    <label class="bmcqe-selector-card">
                        <input type="radio" name="bmcqe_delivery_option" value="within_2_days">
                        <span class="bmcqe-selector-icon">📦</span>
                        <strong>Within 2 Days</strong>
                        <em>Best value option for non-urgent deliveries.</em>
                        <b>Save 10%</b>
                    </label>
                </div>

                <small id="bmcqe_delivery_note" class="bmcqe-delivery-note">Same Day is available when booked before 12pm.</small>
            </div>

            <div class="bmcqe-result-panel" aria-live="polite">
                <div class="bmcqe-price-box">
                    <span>Your Estimated Price</span>
                    <strong id="bmcqe_price">—</strong>
                    <small id="bmcqe_price_note">Enter both addresses to calculate your quote.</small>
                </div>
                <div class="bmcqe-book-box">
                    <button id="bmcqe_book_now" type="button" disabled>Book Now</button>
                    <small>Complete your booking in the next step</small>
                </div>
                <p id="bmcqe_message"></p>
            </div>

            <div class="bmcqe-trust-row">
                <div><strong>Fully Insured</strong><span>Your goods are in safe hands</span></div>
                <div><strong>Fast & Reliable</strong><span>Professional courier service</span></div>
                <div><strong>Secure Booking</strong><span>Test payment active for now</span></div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
