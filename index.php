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
    $methods['express_delivery'] = 'Express_Delivery';
    $methods['same_day_delivery'] = 'Same_Day_Delivery';
    $methods['next_day_delivery'] = 'Next_Day_Delivery';
    return $methods;
});

