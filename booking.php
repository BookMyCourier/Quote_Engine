<?php
if (!defined('ABSPATH')) exit;

/**
 * Version 2.0 payment details page shortcode.
 * This is intentionally gateway-agnostic: it collects the quote/booking details
 * from the quote engine URL and presents a clean payment handoff page.
 * A Stripe Checkout implementation can be added in v2.1 without changing the quote UI.
 */
add_shortcode('bookmycourier_payment', 'bmcqe_render_payment_page');

function bmcqe_render_payment_page() {
    $fields = [
        'collection' => 'Collection',
        'delivery' => 'Delivery',
        'vehicle' => 'Vehicle',
        'price' => 'Price',
        'name' => 'Name',
        'phone' => 'Phone',
        'email' => 'Email',
        'date' => 'Collection date',
        'time' => 'Preferred time',
        'items' => 'Items',
    ];

    $data = [];
    foreach ($fields as $key => $label) {
        $data[$key] = sanitize_text_field(wp_unslash($_GET[$key] ?? ''));
    }

    ob_start();
    ?>
    <div class="bmcqe-payment-page">
        <div class="bmcqe-payment-card">
            <h2>Payment Details</h2>
            <p class="bmcqe-payment-intro">Please check your booking summary before continuing to secure payment.</p>

            <div class="bmcqe-payment-summary">
                <?php foreach ($fields as $key => $label): ?>
                    <?php if (!empty($data[$key])): ?>
                        <div>
                            <span><?php echo esc_html($label); ?></span>
                            <strong><?php echo $key === 'price' ? '£' . esc_html($data[$key]) : esc_html($data[$key]); ?></strong>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="bmcqe-payment-placeholder">
                <h3>Secure payment</h3>
                <p>Stripe payment will be connected in the next version. For now, this page confirms that the full quote and booking details are being passed correctly.</p>
                <button type="button" disabled>Card payment coming next</button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_action('wp_enqueue_scripts', function(){
    wp_register_style('bmcqe-payment-inline', false, [], BMCQE_VERSION);
    wp_enqueue_style('bmcqe-payment-inline');
    wp_add_inline_style('bmcqe-payment-inline', '.bmcqe-payment-page{max-width:900px;margin:40px auto;padding:0 16px;font-family:Inter,Arial,sans-serif;color:#071f4a}.bmcqe-payment-card{background:#fff;border:1px solid #e3e8f2;border-radius:22px;padding:28px;box-shadow:0 18px 45px rgba(7,31,74,.10)}.bmcqe-payment-card h2{font-size:34px;margin:0 0 8px}.bmcqe-payment-intro{color:#5b6575;font-size:17px}.bmcqe-payment-summary{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:24px 0}.bmcqe-payment-summary div{background:#fbfdff;border:1px solid #e8edf5;border-radius:14px;padding:14px}.bmcqe-payment-summary span{display:block;color:#64748b;font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.04em}.bmcqe-payment-summary strong{display:block;margin-top:5px}.bmcqe-payment-placeholder{background:linear-gradient(135deg,#071f4a,#06346f);color:#fff;border-radius:18px;padding:24px;text-align:center}.bmcqe-payment-placeholder h3{margin:0 0 8px}.bmcqe-payment-placeholder p{color:#d7e9ff}.bmcqe-payment-placeholder button{background:#0b78ff;color:#fff;border:0;border-radius:12px;padding:15px 22px;font-weight:900;font-size:16px}@media(max-width:640px){.bmcqe-payment-summary{grid-template-columns:1fr}}');
});
