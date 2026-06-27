<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'bmcqe_add_settings_page');
function bmcqe_add_settings_page() {
    add_options_page(
        'BookMyCourier Quote',
        'BookMyCourier Quote',
        'manage_options',
        'bmcqe-settings',
        'bmcqe_render_settings_page'
    );
}

add_action('admin_init', 'bmcqe_register_settings');
function bmcqe_register_settings() {
    register_setting('bmcqe_settings_group', 'bmcqe_settings', [
        'sanitize_callback' => 'bmcqe_sanitize_settings'
    ]);
}

function bmcqe_sanitize_settings($input) {
    $existing = get_option('bmcqe_settings', []);
    if (!is_array($existing)) $existing = [];

    $output = array_merge($existing, is_array($input) ? $input : []);

    $text_fields = ['google_api_key', 'payment_url', 'terms_url', 'admin_email'];
    foreach ($text_fields as $field) {
        if (isset($output[$field])) $output[$field] = sanitize_text_field($output[$field]);
    }

    foreach (['small','medium','large','luton'] as $v) {
        foreach (['base','included','rate'] as $k) {
            $key = $v . '_' . $k;
            if (isset($output[$key])) $output[$key] = preg_replace('/[^0-9.]/', '', (string)$output[$key]);
        }
    }

    return $output;
}

function bmcqe_render_settings_page() {
    if (!current_user_can('manage_options')) return;

    $settings = get_option('bmcqe_settings', []);
    if (!is_array($settings)) $settings = [];

    $vehicles = [
        'small' => 'Small Van',
        'medium' => 'Medium Van',
        'large' => 'Large Van',
        'luton' => 'Luton Van',
    ];
    ?>
    <div class="wrap">
        <h1>BookMyCourier Quote Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('bmcqe_settings_group'); ?>

            <h2>Google API</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="bmcqe_google_api_key">Google API Key</label></th>
                    <td>
                        <input id="bmcqe_google_api_key" type="text" name="bmcqe_settings[google_api_key]" value="<?php echo esc_attr($settings['google_api_key'] ?? ''); ?>" class="regular-text" />
                        <p class="description">Requires Maps JavaScript API, Places API and Routes API.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bmcqe_payment_url">Booking Page URL</label></th>
                    <td>
                        <input id="bmcqe_payment_url" type="text" name="bmcqe_settings[payment_url]" value="<?php echo esc_attr($settings['payment_url'] ?? '/complete-your-booking/'); ?>" class="regular-text" />
                        <p class="description">Usually <code>/complete-your-booking/</code>. This page should contain <code>[bookmycourier_payment]</code>.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bmcqe_terms_url">Terms Page URL</label></th>
                    <td>
                        <input id="bmcqe_terms_url" type="text" name="bmcqe_settings[terms_url]" value="<?php echo esc_attr($settings['terms_url'] ?? '/terms-and-conditions/'); ?>" class="regular-text" />
                        <p class="description">Usually <code>/terms-and-conditions/</code>. Customers must tick to confirm they have read this before completing a test payment.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="bmcqe_admin_email">Admin Email</label></th>
                    <td>
                        <input id="bmcqe_admin_email" type="email" name="bmcqe_settings[admin_email]" value="<?php echo esc_attr($settings['admin_email'] ?? get_option('admin_email')); ?>" class="regular-text" />
                        <p class="description">Test booking notifications are sent here.</p>
                    </td>
                </tr>
            </table>

            <h2>Vehicle Pricing</h2>
            <table class="widefat striped" style="max-width:900px;">
                <thead>
                    <tr>
                        <th>Vehicle</th>
                        <th>Base Charge</th>
                        <th>Included Miles</th>
                        <th>Rate Per Extra Mile</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehicles as $key => $label): ?>
                        <tr>
                            <td><strong><?php echo esc_html($label); ?></strong></td>
                            <td><input type="text" name="bmcqe_settings[<?php echo esc_attr($key); ?>_base]" value="<?php echo esc_attr($settings[$key . '_base'] ?? ''); ?>" /></td>
                            <td><input type="text" name="bmcqe_settings[<?php echo esc_attr($key); ?>_included]" value="<?php echo esc_attr($settings[$key . '_included'] ?? ''); ?>" /></td>
                            <td><input type="text" name="bmcqe_settings[<?php echo esc_attr($key); ?>_rate]" value="<?php echo esc_attr($settings[$key . '_rate'] ?? ''); ?>" /></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
