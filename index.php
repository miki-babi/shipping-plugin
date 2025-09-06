<?php
/**
 * Plugin Name: Shipping Plugin distance and weight based
 * Description: A plugin for managing shipping functionality.
 * Version: 1.0.0
 * Author: mikiyas
 * Author URI: https://t.me/mikiyas_sh
 * License: GPL-2.0
 */

// Prevent direct access to the file


if (!defined('ABSPATH')) exit;

// -------------------------
// Plugin Settings (admin)
// -------------------------
function log_step($message) {
    $prefix = '[' . (isset($this->id) ? $this->id : 'same_day_delivery') . '] ';
    $line   = date('Y-m-d H:i:s') . ' ' . $prefix . $message . PHP_EOL;
    $path   = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'debug.log';
    @file_put_contents($path, $line, FILE_APPEND);
}

// Select cheapest eligible mode by weight/distance



add_action('woocommerce_shipping_init', function() {
    require_once __DIR__ . '/includes/class-express-delivery.php';
    require_once __DIR__ . '/includes/class-same-day-delivery.php';
    require_once __DIR__ . '/includes/class-next-day-delivery.php';
    require_once __DIR__ . '/includes/class-other-days-delivery.php';
    require_once __DIR__ . '/includes/class-two-day-delivery.php';
    require_once __DIR__ . '/includes/class-shop-pickup.php';
});

add_filter('woocommerce_shipping_methods', function($methods) {
    $methods['next_day_delivery'] = 'Next_Day_Delivery';
    $methods['express_delivery'] = 'Express_Delivery';
    $methods['same_day_delivery'] = 'Same_Day_Delivery';
    $methods['other_days_delivery'] = 'Other_Days_Delivery';
    $methods['two_day_delivery'] = 'Two_Day_Delivery';
    $methods['shop_pickup'] = 'Shop_Pickup';
    return $methods;
});




// ...existing code...
