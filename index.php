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
});

add_filter('woocommerce_shipping_methods', function($methods) {
    $methods['next_day_delivery'] = 'Next_Day_Delivery';
    $methods['express_delivery'] = 'Express_Delivery';
    $methods['same_day_delivery'] = 'Same_Day_Delivery';
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

// Add a delivery date picker to checkout
add_filter('woocommerce_checkout_fields', function($fields) {
    $fields['order']['delivery_date'] = [
        'type'        => 'date',
        'label'       => __('Preferred delivery date', 'shipping-plugin'),
        'required'    => false,
        'class'       => ['form-row-wide'],
        'priority'    => 25,
    ];
    return $fields;
});

// Save delivery date to order meta
add_action('woocommerce_checkout_create_order', function($order, $data) {
    if (isset($_POST['delivery_date'])) {
        $date = sanitize_text_field(wp_unslash($_POST['delivery_date']));
        if (!empty($date)) {
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

// Enqueue jQuery UI datepicker fallback and initialize if available
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_script('jquery-ui-datepicker');
    $inline_js = "jQuery(function($){ var $f = jQuery('#delivery_date'); if ($f.length && jQuery.fn.datepicker) { $f.attr('type','text'); $f.datepicker({ dateFormat: 'yy-mm-dd', minDate: 0 }); } });";
    wp_add_inline_script('jquery-ui-datepicker', $inline_js);
});

