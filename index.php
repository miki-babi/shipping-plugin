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


// Disable express_delivery if cart weight > 20kg

// if ( ! defined( 'ABSPATH' ) ) {
//     exit; // Exit if accessed directly
// }

// if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

//     function launch_shipping_method() {
//         if (!class_exists('Launch_Shipping_Method')) {

//             class Launch_Shipping_Method extends WC_Shipping_Method {

//                 public function __construct( $instance_id = 0 ) {
//                     $this->id = 'launch_shipping';
//                     $this->instance_id          = absint( $instance_id );
//                     $this->method_title         = __('Launch Simple Shipping', 'launch_shipping');
//                     $this->method_description   = __('Custom Simple Shipping Method', 'launch_shipping');
//                     $this->supports             = array(
//                         'shipping-zones',
//                         'instance-settings',
//                         'instance-settings-modal',
//                     );

//                     $this->init();
//                 }

//                 /**
//                  * Initialize Launch Simple Shipping.
//                  */
//                 public function init() {
//                     // Load the settings.
//                     $this->init_form_fields();
//                     $this->init_settings();

//                     // Define user set variables.
//                     $this->title    = isset($this->settings['title']) ? $this->settings['title'] : __('Launch Shipping', 'launch_shipping');

//                     add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
//                 }

//                 /**
//                  * Init form fields.
//                  */
//                 public function init_form_fields() {
//                     $this->form_fields = array(
//                         'title'      => array(
//                             'title'         => __( 'Title', 'launch_shipping' ),
//                             'type'          => 'text',
//                             'description'   => __( 'This controls the title which the user sees during checkout.', 'launch_shipping' ),
//                             'default'       => $this->method_title,
//                             'desc_tip'      => true,
//                         )
//                     );
//                 }

//                 /**
//                  * Get setting form fields for instances of this shipping method within zones.
//                  *
//                  * @return array
//                  */
//                 public function get_instance_form_fields() {
//                     return parent::get_instance_form_fields();
//                 }

//                 /**
//                  * Always return shipping method is available
//                  *
//                  * @param array $package Shipping package.
//                  * @return bool
//                  */
//                 public function is_available( $package ) {
//                     $is_available = true;
//                     return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', $is_available, $package, $this );
//                 }

//                 /**
//                  * Free shipping rate applied for this method.
//                  *
//                  * @uses WC_Shipping_Method::add_rate()
//                  *
//                  * @param array $package Shipping package.
//                  */
//                 public function calculate_shipping( $package = array() ) {
//                     $this->add_rate(
//                         array(
//                             'label'   => $this->title,
//                             'cost'    => 0,
//                             'taxes'   => false,
//                             'package' => $package,
//                         )
//                     );
//                 }
//             }
//         }
//     }
//     add_action('woocommerce_shipping_init', 'Launch_Shipping_Method');

//     function add_launch_shipping_method($methods) {
//     $methods['launch_shipping'] = 'launch_shipping_method';
//     return $methods;
//     }
//     add_filter('woocommerce_shipping_methods', 'add_launch_shipping_method');

// }