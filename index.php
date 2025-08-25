<?php
/**
 * Plugin Name: Shipping Plugin
 * Description: A plugin for managing shipping functionality.
 * Version: 1.0.3
 * Author: mikiyas
 * Author URI: https://t.me/mikiyas_sh
 * License: GPL-2.0+
 */

// Prevent direct access to the file


if (!defined('ABSPATH')) exit;

// -------------------------
// Plugin Settings (admin)
// -------------------------
function sp_get_settings() {
    $defaults = [
        'enable_other_days_date' => 'yes',
        'require_other_days_date' => 'yes',
        'other_days_date_label' => __('Select delivery date', 'shipping-plugin'),
        'min_lead_days' => 0,
    ];
    $opts = get_option('sp_settings', []);
    if (!is_array($opts)) $opts = [];
    return wp_parse_args($opts, $defaults);
}

add_action('admin_menu', function() {
    add_submenu_page(
        'woocommerce',
        __('Shipping Plugin Settings', 'shipping-plugin'),
        __('Shipping Plugin', 'shipping-plugin'),
        'manage_woocommerce',
        'sp-settings',
        'sp_render_settings_page'
    );
});

function sp_render_settings_page() {
    if (!current_user_can('manage_woocommerce')) return;
    if (isset($_POST['sp_settings_nonce']) && wp_verify_nonce($_POST['sp_settings_nonce'], 'sp_save_settings')) {
        $new = [
            'enable_other_days_date' => !empty($_POST['enable_other_days_date']) ? 'yes' : 'no',
            'require_other_days_date' => !empty($_POST['require_other_days_date']) ? 'yes' : 'no',
            'other_days_date_label' => isset($_POST['other_days_date_label']) ? sanitize_text_field($_POST['other_days_date_label']) : '',
            'min_lead_days' => isset($_POST['min_lead_days']) ? max(0, intval($_POST['min_lead_days'])) : 0,
        ];
        update_option('sp_settings', $new);
        echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'shipping-plugin') . '</p></div>';
    }
    $s = sp_get_settings();
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Shipping Plugin Settings', 'shipping-plugin'); ?></h1>
        <form method="post">
            <?php wp_nonce_field('sp_save_settings', 'sp_settings_nonce'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php echo esc_html__('Enable date for Other Days', 'shipping-plugin'); ?></th>
                    <td>
                        <label><input type="checkbox" name="enable_other_days_date" value="1" <?php checked($s['enable_other_days_date'], 'yes'); ?>> <?php echo esc_html__('Enable', 'shipping-plugin'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Require date for Other Days', 'shipping-plugin'); ?></th>
                    <td>
                        <label><input type="checkbox" name="require_other_days_date" value="1" <?php checked($s['require_other_days_date'], 'yes'); ?>> <?php echo esc_html__('Require', 'shipping-plugin'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Date field label', 'shipping-plugin'); ?></th>
                    <td>
                        <input type="text" name="other_days_date_label" class="regular-text" value="<?php echo esc_attr($s['other_days_date_label']); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php echo esc_html__('Minimum lead days', 'shipping-plugin'); ?></th>
                    <td>
                        <input type="number" min="0" name="min_lead_days" value="<?php echo esc_attr(intval($s['min_lead_days'])); ?>"> 
                        <p class="description"><?php echo esc_html__('Number of days from today to start allowing selection (0 = today).', 'shipping-plugin'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

add_action('woocommerce_shipping_init', function() {
    require_once __DIR__ . '/includes/class-express-delivery.php';
    require_once __DIR__ . '/includes/class-same-day-delivery.php';
    require_once __DIR__ . '/includes/class-next-day-delivery.php';
    require_once __DIR__ . '/includes/class-other-days-delivery.php';
    require_once __DIR__ . '/includes/class-two-day-delivery.php';
});

add_filter('woocommerce_shipping_methods', function($methods) {
    $methods['next_day_delivery'] = 'Next_Day_Delivery';
    $methods['express_delivery'] = 'Express_Delivery';
    $methods['same_day_delivery'] = 'Same_Day_Delivery';
    $methods['other_days_delivery'] = 'Other_Days_Delivery';
    $methods['two_day_delivery'] = 'Two_Day_Delivery';
    return $methods;
});


// Create Ethiopia shipping zone on plugin activation (or defer until WC is loaded)
register_activation_hook(__FILE__, function() {
    if (class_exists('WC_Shipping_Zones')) {
        sp_create_ethiopia_shipping_zone();
    } else {
        // Defer setup until WooCommerce is fully loaded
        add_option('sp_deferred_zone_setup', 1);
    }
});

// If deferred, run after plugins are loaded
add_action('plugins_loaded', function() {
    if (get_option('sp_deferred_zone_setup') && class_exists('WC_Shipping_Zones')) {
        sp_create_ethiopia_shipping_zone();
        delete_option('sp_deferred_zone_setup');
    }
});

if (!function_exists('sp_create_ethiopia_shipping_zone')) {
    /**
     * Create a WooCommerce shipping zone for Ethiopia (ET) if it does not already exist.
     */
    function sp_create_ethiopia_shipping_zone() {
        if (!class_exists('WC_Shipping_Zones')) {
            return; // WC not available
        }
        
        // Check if a zone named 'Ethiopia' or a zone that already targets country ET exists
        $existing_zones = WC_Shipping_Zones::get_zones();
        foreach ($existing_zones as $z) {
            if (!empty($z['zone_name']) && strtolower($z['zone_name']) === 'ethiopia') {
                return; // Zone already exists by name
            }
            if (!empty($z['zone_id'])) {
                $zone_obj = new WC_Shipping_Zone($z['zone_id']);
                $locations = $zone_obj->get_zone_locations();
                foreach ($locations as $loc) {
                    if (!empty($loc->type) && !empty($loc->code) && $loc->type === 'country' && strtoupper($loc->code) === 'ET') {
                        return; // Zone already covers Ethiopia
                    }
                }
            }
        }

        // Create the Ethiopia zone
        $zone = new WC_Shipping_Zone();
        $zone->set_zone_name('Ethiopia');
        // Optional: position can be set; default is appended to end
        $zone->save();

        // Add country ET to the zone
        $zone->add_location('ET', 'country');
    }
}

// Show a delivery date input under the 'Other Days' shipping method when selected
add_action('woocommerce_after_shipping_rate', function($method, $index) {
    // $method is WC_Shipping_Rate; method_id is the shipping method slug, id may include instance suffix
    if (!isset($method->method_id) || $method->method_id !== 'other_days_delivery') {
        return;
    }
    $settings = sp_get_settings();
    if ($settings['enable_other_days_date'] !== 'yes') {
        return; // Disabled via settings
    }
    $minDays = isset($settings['min_lead_days']) ? max(0, intval($settings['min_lead_days'])) : 0;
    $minDate = gmdate('Y-m-d', strtotime('+' . $minDays . ' days'));
    echo '<div class="other-days-date-wrap" style="display:none; margin:8px 0 0 24px;" data-rate-id="' . esc_attr($method->id) . '">';
    echo '<label for="other_days_date" style="display:block; margin-bottom:4px;">' . esc_html($settings['other_days_date_label']) . '</label>';
    // Use a visible name that won't collide across multiple rates; hidden field will carry the posted value
    echo '<input type="date" id="other_days_date" class="sp-other-days-date" name="other_days_date_visible" min="' . esc_attr($minDate) . '" />';
    echo '</div>';
}, 10, 2);

// Toggle the date field visibility based on selected shipping method (checkout page)
add_action('wp_enqueue_scripts', function() {
    if (!function_exists('is_checkout') || !is_checkout()) return;
    $js = "function spGetChecked(){ return document.querySelector('input[name^=\'shipping_method\']:checked'); }
        function spToggleOtherDays(){
            var checked = spGetChecked();
            var wraps = document.querySelectorAll('.other-days-date-wrap');
            wraps.forEach(function(w){
                var isOther = checked && checked.value && checked.value.indexOf('other_days_delivery') === 0;
                var match = isOther && w.getAttribute('data-rate-id') === checked.value;
                w.style.display = match ? 'block' : 'none';
            });
        }
        function spFindVisibleInput(){
            var checked = spGetChecked();
            if(!(checked && checked.value && checked.value.indexOf('other_days_delivery') === 0)) return null;
            var wraps = document.querySelectorAll('.other-days-date-wrap');
            for (var i=0;i<wraps.length;i++){
                if(wraps[i].getAttribute('data-rate-id') === checked.value){
                    return wraps[i].querySelector('.sp-other-days-date');
                }
            }
            return null;
        }
        function spSyncDateVisibleHidden(){
            var hid = document.getElementById('other_days_date_hidden');
            if(!hid) return;
            var vis = spFindVisibleInput();
            if(vis){ hid.value = vis.value; }
        }
        function spRestoreVisibleFromHidden(){
            var hid = document.getElementById('other_days_date_hidden');
            if(!hid) return;
            var vis = spFindVisibleInput();
            if(vis && !vis.value && hid.value){ vis.value = hid.value; }
        }
        document.addEventListener('DOMContentLoaded',function(){
            function toggleOtherDays(){
                spToggleOtherDays();
                spRestoreVisibleFromHidden();
            }
            document.body.addEventListener('change', function(e){ if(e.target && e.target.name && e.target.name.indexOf('shipping_method')===0){ toggleOtherDays(); }});
            document.body.addEventListener('change', function(e){ if(e.target && e.target.classList && e.target.classList.contains('sp-other-days-date')){ spSyncDateVisibleHidden(); }});
            document.body.addEventListener('input', function(e){ if(e.target && e.target.classList && e.target.classList.contains('sp-other-days-date')){ spSyncDateVisibleHidden(); }});
            // Sync right before submission as well
            jQuery('form.checkout').on('checkout_place_order', function(){ spSyncDateVisibleHidden(); });
            toggleOtherDays();
            jQuery( document.body ).on('updated_checkout', function(){ toggleOtherDays(); });
        });";
    wp_register_script('sp-other-days-toggle', '', ['jquery'], null, true);
    wp_enqueue_script('sp-other-days-toggle');
    wp_add_inline_script('sp-other-days-toggle', $js);
});

// Output a hidden field INSIDE the checkout form to persist the selected date across fragment refreshes
add_action('woocommerce_checkout_after_order_review', function() {
    if (!function_exists('is_checkout') || !is_checkout()) return;
    echo '<input type="hidden" id="other_days_date_hidden" name="other_days_date" value="" />';
}, 15);

// Validate the date when 'Other Days' is selected
add_action('woocommerce_checkout_process', function() {
    if (empty($_POST['shipping_method'][0])) return;
    $selected = wc_clean(wp_unslash($_POST['shipping_method'][0]));
    if (strpos($selected, 'other_days_delivery') === 0) {
        $settings = sp_get_settings();
        $date = '';
        if (isset($_POST['other_days_date'])) {
            $date = wc_clean(wp_unslash($_POST['other_days_date']));
        } elseif (isset($_POST['other_days_date_visible'])) {
            $date = wc_clean(wp_unslash($_POST['other_days_date_visible']));
        }
        if ($settings['require_other_days_date'] === 'yes' && empty($date)) {
            wc_add_notice(__('Please select a delivery date for Other Days.', 'shipping-plugin'), 'error');
        }
    }
});

// Save the selected date to order meta when 'Other Days' is used
add_action('woocommerce_checkout_create_order', function($order, $data) {
    if (!empty($_POST['shipping_method'][0]) && strpos(wc_clean(wp_unslash($_POST['shipping_method'][0])), 'other_days_delivery') === 0) {
        $date = '';
        if (!empty($_POST['other_days_date'])) {
            $date = wc_clean(wp_unslash($_POST['other_days_date']));
        } elseif (!empty($_POST['other_days_date_visible'])) {
            $date = wc_clean(wp_unslash($_POST['other_days_date_visible']));
        }
        if (!empty($date)) { $order->update_meta_data('_delivery_date', $date); }
    }
}, 10, 2);

// Show delivery date in admin order screen
add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
    $date = $order->get_meta('_delivery_date');
    if (!empty($date)) {
        echo '<p><strong>' . esc_html__('Preferred delivery date', 'shipping-plugin') . ':</strong> ' . esc_html($date) . '</p>';
    }
});

