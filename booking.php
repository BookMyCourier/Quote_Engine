<?php
if (!defined('ABSPATH')) exit;

add_shortcode('bookmycourier_payment', 'bmcqe_render_payment_page');

add_action('wp_ajax_bmcqe_create_test_booking', 'bmcqe_create_test_booking');
add_action('wp_ajax_nopriv_bmcqe_create_test_booking', 'bmcqe_create_test_booking');

function bmcqe_vehicle_label($vehicle) {
    $labels = [
        'small' => 'Small Van',
        'medium' => 'Medium Van',
        'large' => 'Large Van',
        'luton' => 'Luton Van',
    ];
    return $labels[$vehicle] ?? ucfirst($vehicle);
}

function bmcqe_delivery_label($delivery_option) {
    $labels = [
        'same_day' => 'Same Day',
        'next_day' => 'Next Day',
        'within_2_days' => 'Within 2 days',
    ];
    return $labels[$delivery_option] ?? $delivery_option;
}

function bmcqe_collection_label($collection_option, $collection_date, $collection_period) {
    if ($collection_option === 'asap') return 'ASAP collection';
    $period = $collection_period ? strtoupper($collection_period) : '';
    return trim($collection_date . ' ' . $period);
}

function bmcqe_render_payment_page() {
    wp_enqueue_style('bmcqe-style', BMCQE_URL . 'assets/css/style.css', [], BMCQE_VERSION);
    wp_enqueue_script('bmcqe-booking', BMCQE_URL . 'assets/js/booking.js', [], BMCQE_VERSION, true);

    $settings = get_option('bmcqe_settings', []);
    $terms_url = isset($settings['terms_url']) ? trim($settings['terms_url']) : '/terms-and-conditions/';

    wp_localize_script('bmcqe-booking', 'bmcqeBookingData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('bmcqe_booking_nonce'),
    ]);

    $allowed = [
        'collection','delivery','vehicle','price','miles','collection_option',
        'collection_date','collection_period','delivery_option','discount_percent'
    ];

    $data = [];
    foreach ($allowed as $key) {
        $data[$key] = sanitize_text_field(wp_unslash($_GET[$key] ?? ''));
    }

    $has_quote = !empty($data['collection']) && !empty($data['delivery']) && !empty($data['price']);

    ob_start();
    ?>
    <div class="bmcqe-booking-page">
        <div class="bmcqe-booking-header">
            <h2>Complete Your Booking</h2>
            <p>Just a few details, then you can complete a test payment. Stripe can be connected later.</p>
        </div>

        <?php if (!$has_quote): ?>
            <div class="bmcqe-admin-warning">
                No quote details were found. Please return to the quote page and click <strong>Book Now</strong>.
            </div>
        <?php else: ?>
            <div class="bmcqe-booking-layout">
                <form id="bmcqe_booking_form" class="bmcqe-booking-form">
                    <input type="hidden" name="collection" value="<?php echo esc_attr($data['collection']); ?>">
                    <input type="hidden" name="delivery" value="<?php echo esc_attr($data['delivery']); ?>">
                    <input type="hidden" name="vehicle" value="<?php echo esc_attr($data['vehicle']); ?>">
                    <input type="hidden" name="price" value="<?php echo esc_attr($data['price']); ?>">
                    <input type="hidden" name="miles" value="<?php echo esc_attr($data['miles']); ?>">
                    <input type="hidden" name="collection_option" value="<?php echo esc_attr($data['collection_option']); ?>">
                    <input type="hidden" name="collection_date" value="<?php echo esc_attr($data['collection_date']); ?>">
                    <input type="hidden" name="collection_period" value="<?php echo esc_attr($data['collection_period']); ?>">
                    <input type="hidden" name="delivery_option" value="<?php echo esc_attr($data['delivery_option']); ?>">
                    <input type="hidden" name="discount_percent" value="<?php echo esc_attr($data['discount_percent']); ?>">

                    <div class="bmcqe-booking-card">
                        <h3>Your details</h3>
                        <div class="bmcqe-form-grid">
                            <label>First name<input type="text" name="first_name" required></label>
                            <label>Last name<input type="text" name="last_name" required></label>
                            <label>Email<input type="email" name="email" required></label>
                            <label>Mobile number<input type="tel" name="mobile" required></label>
                        </div>
                    </div>

                    <div class="bmcqe-booking-card">
                        <h3>Contacts</h3>
                        <label class="bmcqe-checkbox">
                            <input type="checkbox" name="collection_same_as_customer" value="1" checked>
                            <span>Collection contact is me</span>
                        </label>
                        <div class="bmcqe-form-grid bmcqe-collection-contact-fields bmcqe-hidden">
                            <label>Collection contact name<input type="text" name="collection_contact_name"></label>
                            <label>Collection contact number<input type="tel" name="collection_contact_phone"></label>
                        </div>

                        <div class="bmcqe-form-grid">
                            <label>Delivery contact name<input type="text" name="delivery_contact_name"></label>
                            <label>Delivery contact number<input type="tel" name="delivery_contact_phone"></label>
                        </div>
                    </div>

                    <div class="bmcqe-booking-card">
                        <h3>Goods</h3>
                        <label>What are we collecting?
                            <textarea name="goods_description" rows="4" placeholder="Example: 6 boxes and one small desk" required></textarea>
                        </label>
                        <label>Special instructions
                            <textarea name="special_instructions" rows="3" placeholder="Access details, parking notes, fragile items, etc."></textarea>
                        </label>
                    </div>


                    <div class="bmcqe-booking-card bmcqe-terms-card">
                        <h3>Terms and Conditions</h3>
                        <label class="bmcqe-checkbox bmcqe-terms-checkbox">
                            <input type="checkbox" name="accepted_terms" value="1" required>
                            <span>I have read and agree to the <a href="<?php echo esc_url($terms_url); ?>" target="_blank" rel="noopener">BookMyCourier Terms and Conditions</a>.</span>
                        </label>
                    </div>

                    <div class="bmcqe-booking-actions">
                        <button type="submit" id="bmcqe_test_payment_button">Complete Test Payment</button>
                        <p id="bmcqe_booking_message"></p>
                        <small>No real payment will be taken in this version.</small>
                    </div>
                </form>

                <aside class="bmcqe-summary-card">
                    <h3>Booking Summary</h3>
                    <dl>
                        <dt>Vehicle</dt>
                        <dd><?php echo esc_html(bmcqe_vehicle_label($data['vehicle'])); ?></dd>

                        <dt>Collection</dt>
                        <dd><?php echo esc_html($data['collection']); ?></dd>

                        <dt>Delivery</dt>
                        <dd><?php echo esc_html($data['delivery']); ?></dd>

                        <dt>Collection time</dt>
                        <dd><?php echo esc_html(bmcqe_collection_label($data['collection_option'], $data['collection_date'], $data['collection_period'])); ?></dd>

                        <dt>Delivery option</dt>
                        <dd><?php echo esc_html(bmcqe_delivery_label($data['delivery_option'])); ?></dd>

                        <?php if (!empty($data['discount_percent']) && (float)$data['discount_percent'] > 0): ?>
                            <dt>Discount</dt>
                            <dd><?php echo esc_html($data['discount_percent']); ?>% saving applied</dd>
                        <?php endif; ?>

                        <dt>Total</dt>
                        <dd class="bmcqe-summary-price">£<?php echo esc_html($data['price']); ?></dd>
                    </dl>
                </aside>
            </div>

            <div id="bmcqe_confirmation" class="bmcqe-confirmation bmcqe-hidden">
                <h2>Test Booking Confirmed</h2>
                <p>Your test booking reference is:</p>
                <strong id="bmcqe_booking_reference"></strong>
                <p>No money has been taken. Stripe can be connected in the next version.</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function bmcqe_create_test_booking() {
    check_ajax_referer('bmcqe_booking_nonce', 'nonce');

    $fields = [
        'collection','delivery','vehicle','price','miles','collection_option','collection_date',
        'collection_period','delivery_option','discount_percent','first_name','last_name',
        'email','mobile','collection_same_as_customer','collection_contact_name','collection_contact_phone',
        'delivery_contact_name','delivery_contact_phone','goods_description','special_instructions','accepted_terms'
    ];

    $data = [];
    foreach ($fields as $field) {
        $data[$field] = sanitize_textarea_field(wp_unslash($_POST[$field] ?? ''));
    }

    if (empty($data['first_name']) || empty($data['last_name']) || empty($data['email']) || empty($data['mobile']) || empty($data['goods_description'])) {
        wp_send_json_error(['message' => 'Please complete your name, email, mobile and goods description.']);
    }

    if (!is_email($data['email'])) {
        wp_send_json_error(['message' => 'Please enter a valid email address.']);
    }

    if (empty($data['accepted_terms']) || $data['accepted_terms'] !== '1') {
        wp_send_json_error(['message' => 'Please confirm that you have read and agree to the Terms and Conditions.']);
    }

    $reference = 'BMC-' . date_i18n('ymd') . '-' . strtoupper(wp_generate_password(5, false, false));

    $booking = [
        'reference' => $reference,
        'created_at' => current_time('mysql'),
        'status' => 'test_paid',
        'data' => $data,
    ];

    $bookings = get_option('bmcqe_test_bookings', []);
    if (!is_array($bookings)) $bookings = [];
    $bookings[$reference] = $booking;
    update_option('bmcqe_test_bookings', $bookings);

    bmcqe_send_test_booking_emails($booking);

    wp_send_json_success([
        'reference' => $reference,
        'message' => 'Test booking confirmed.',
    ]);
}

function bmcqe_send_test_booking_emails($booking) {
    $settings = get_option('bmcqe_settings', []);
    $admin_email = sanitize_email($settings['admin_email'] ?? get_option('admin_email'));
    $data = $booking['data'];
    $reference = $booking['reference'];

    $summary = "Booking Reference: {$reference}\n\n";
    $summary .= "Customer: {$data['first_name']} {$data['last_name']}\n";
    $summary .= "Email: {$data['email']}\n";
    $summary .= "Mobile: {$data['mobile']}\n\n";
    $summary .= "Collection: {$data['collection']}\n";
    $summary .= "Delivery: {$data['delivery']}\n";
    $summary .= "Vehicle: " . bmcqe_vehicle_label($data['vehicle']) . "\n";
    $summary .= "Collection Option: " . bmcqe_collection_label($data['collection_option'], $data['collection_date'], $data['collection_period']) . "\n";
    $summary .= "Delivery Option: " . bmcqe_delivery_label($data['delivery_option']) . "\n";
    $summary .= "Price: £{$data['price']}\n\n";
    $summary .= "Goods:\n{$data['goods_description']}\n\n";
    $summary .= "Special Instructions:\n{$data['special_instructions']}\n";

    if ($admin_email) {
        wp_mail($admin_email, "New BookMyCourier Test Booking {$reference}", $summary);
    }

    wp_mail(
        sanitize_email($data['email']),
        "Your BookMyCourier test booking {$reference}",
        "Thanks for your booking.\n\n{$summary}\n\nThis is currently a test payment flow, so no payment has been taken."
    );
}
