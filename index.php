<?php
/**
 * Plugin Name: Shipping Plugin
 * Description: A starter plugin for managing shipping functionality.
 * Version: 1.0.0
 * Author: mikiyas
 * Author URI: https://t.me/mikiyas_sh
 * License: GPL-2.0+
 */

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Initialize the plugin
function shipping_plugin_init() {
    // Add your initialization code here
}
add_action('init', 'shipping_plugin_init');

// Example function to calculate shipping cost
function calculate_shipping_cost($weight, $distance) {
    $base_cost = 5.00; // Base cost in USD
    $cost_per_kg = 1.50; // Cost per kilogram
    $cost_per_km = 0.10; // Cost per kilometer

    return $base_cost + ($weight * $cost_per_kg) + ($distance * $cost_per_km);
}
?>