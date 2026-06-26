<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', 'bmcqe_admin_menu');
function bmcqe_admin_menu() {
    add_options_page('BookMyCourier Quote', 'BookMyCourier Quote', 'manage_options', 'bmcqe-settings', 'bmcqe_settings_page');
}

add_action('admin_init', 'bmcqe_register_settings');
function bmcqe_register_settings() {
    register_setting('bmcqe_settings_group', 'bmcqe_settings', 'bmcqe_sanitize_settings');
}

function bmcqe_sanitize_settings($input) {
    $out = [];
    $out['google_api_key'] = sanitize_text_field($input['google_api_key'] ?? '');
    $out['payment_url'] = esc_url_raw($input['payment_url'] ?? '/payment-details/');
    foreach (['small','medium','large','luton'] as $v) {
        $out[$v . '_base'] = bmcqe_clean_money($input[$v . '_base'] ?? '0');
        $out[$v . '_included'] = bmcqe_clean_money($input[$v . '_included'] ?? '0');
        $out[$v . '_rate'] = bmcqe_clean_money($input[$v . '_rate'] ?? '0');
    }
    return $out;
}

function bmcqe_clean_money($value) {
    return preg_replace('/[^0-9.]/', '', (string)$value);
}

function bmcqe_settings_page() {
    if (!current_user_can('manage_options')) return;
    $s = get_option('bmcqe_settings', []);
    $vehicles = [
        'small' => 'Small Van',
        'medium' => 'Medium Van',
        'large' => 'Large Van',
        'luton' => 'Luton Van',
    ];
    ?>
    <div class="wrap">
        <h1>BookMyCourier Quote Engine</h1>
        <p>Use shortcode <code>[bookmycourier_quote]</code> on any page.</p>
        <form method="post" action="options.php">
            <?php settings_fields('bmcqe_settings_group'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="google_api_key">Google API Key</label></th>
                    <td>
                        <input name="bmcqe_settings[google_api_key]" id="google_api_key" type="text" class="regular-text" value="<?php echo esc_attr($s['google_api_key'] ?? ''); ?>">
                        <p class="description">Enable Maps JavaScript API, Places API and Routes API in Google Cloud.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="payment_url">Book Now Payment URL</label></th>
                    <td>
                        <input name="bmcqe_settings[payment_url]" id="payment_url" type="text" class="regular-text" value="<?php echo esc_attr($s['payment_url'] ?? '/payment-details/'); ?>">
                        <p class="description">The Book Now button will send customers here with quote details added to the URL. Example: <code>/payment-details/</code></p>
                    </td>
                </tr>
            </table>

            <h2>Vehicle Pricing</h2>
            <table class="widefat striped" style="max-width:900px;">
                <thead><tr><th>Vehicle</th><th>Base price £</th><th>Included miles</th><th>Rate per extra mile £</th></tr></thead>
                <tbody>
                <?php foreach ($vehicles as $key => $label): ?>
                    <tr>
                        <td><strong><?php echo esc_html($label); ?></strong></td>
                        <td><input type="text" name="bmcqe_settings[<?php echo esc_attr($key); ?>_base]" value="<?php echo esc_attr($s[$key . '_base'] ?? ''); ?>"></td>
                        <td><input type="text" name="bmcqe_settings[<?php echo esc_attr($key); ?>_included]" value="<?php echo esc_attr($s[$key . '_included'] ?? ''); ?>"></td>
                        <td><input type="text" name="bmcqe_settings[<?php echo esc_attr($key); ?>_rate]" value="<?php echo esc_attr($s[$key . '_rate'] ?? ''); ?>"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
