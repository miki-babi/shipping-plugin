<?php
/**
 * Plugin Name: Shipping Plugin
 * Description: A plugin for managing shipping functionality.
 * Version: 1.0.0
 * Author: mikiyas
 * Author URI: https://t.me/mikiyas_sh
 * License: GPL-2.0+
 */

// Prevent direct access to the file


if (!defined('ABSPATH')) exit;

add_action('woocommerce_shipping_init', function() {
    require_once __DIR__ . '/includes/class-express-delivery.php';
    require_once __DIR__ . '/includes/class-same-day-delivery.php';
    require_once __DIR__ . '/includes/class-next-day-delivery.php';
    require_once __DIR__ . '/includes/class-other-days-delivery.php';
});

add_filter('woocommerce_shipping_methods', function($methods) {
    $methods['next_day_delivery'] = 'Next_Day_Delivery';
    $methods['express_delivery'] = 'Express_Delivery';
    $methods['same_day_delivery'] = 'Same_Day_Delivery';
    $methods['other_days_delivery'] = 'Other_Days_Delivery';
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
    echo '<div class="other-days-date-wrap" style="display:none; margin:8px 0 0 24px;" data-rate-id="' . esc_attr($method->id) . '">';
    echo '<label for="other_days_date" style="display:block; margin-bottom:4px;">' . esc_html__('Select delivery date', 'shipping-plugin') . '</label>';
    echo '<input type="date" id="other_days_date" name="other_days_date" min="' . esc_attr(gmdate('Y-m-d')) . '" />';
    echo '</div>';
}, 10, 2);

// Toggle the date field visibility based on selected shipping method (checkout page)
add_action('wp_enqueue_scripts', function() {
    if (!function_exists('is_checkout') || !is_checkout()) return;
    $js = "document.addEventListener('DOMContentLoaded',function(){
        function toggleOtherDays(){
            var checked = document.querySelector('input[name^=\\'shipping_method\\']:checked');
            var wrap = document.querySelector('.other-days-date-wrap');
            if(!wrap) return;
            if(checked && checked.value && checked.value.indexOf('other_days_delivery') === 0){
                wrap.style.display = 'block';
            } else {
                wrap.style.display = 'none';
            }
        }
        document.body.addEventListener('change', function(e){ if(e.target && e.target.name && e.target.name.indexOf('shipping_method')===0){ toggleOtherDays(); }});
        toggleOtherDays();
    });";
    wp_register_script('sp-other-days-toggle', '', [], null, true);
    wp_enqueue_script('sp-other-days-toggle');
    wp_add_inline_script('sp-other-days-toggle', $js);
});

// Validate the date when 'Other Days' is selected
add_action('woocommerce_checkout_process', function() {
    if (empty($_POST['shipping_method'][0])) return;
    $selected = wc_clean(wp_unslash($_POST['shipping_method'][0]));
    if (strpos($selected, 'other_days_delivery') === 0) {
        $date = isset($_POST['other_days_date']) ? wc_clean(wp_unslash($_POST['other_days_date'])) : '';
        if (empty($date)) {
            wc_add_notice(__('Please select a delivery date for Other Days.', 'shipping-plugin'), 'error');
        }
    }
});

// Save the selected date to order meta when 'Other Days' is used
add_action('woocommerce_checkout_create_order', function($order, $data) {
    if (!empty($_POST['shipping_method'][0]) && strpos(wc_clean(wp_unslash($_POST['shipping_method'][0])), 'other_days_delivery') === 0) {
        if (!empty($_POST['other_days_date'])) {
            $date = wc_clean(wp_unslash($_POST['other_days_date']));
            $order->update_meta_data('_delivery_date', $date);
        }
    }
}, 10, 2);

// Show delivery date in admin order screen
add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
    $date = $order->get_meta('_delivery_date');
    if (!empty($date)) {
        echo '<p><strong>' . esc_html__('Preferred delivery date', 'shipping-plugin') . ':</strong> ' . esc_html($date) . '</p>';
    }
});

