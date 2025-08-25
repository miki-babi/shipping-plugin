<?php
if (!defined('ABSPATH')) exit;

class Next_Day_Delivery extends WC_Shipping_Method {
    public function __construct() {
        $this->id = 'next_day_delivery';
        $this->method_title = 'Next Day Delivery';
        $this->method_description = 'Delivery by the next day';
        $this->enabled = "yes";
        $this->title = "Next Day Delivery";
        $this->init();
    }

    function init() {
        $this->init_form_fields();
        $this->init_settings();
        // Load persisted settings for common props
        $this->enabled = $this->get_option('enabled', $this->enabled);
        $this->title   = $this->get_option('title', $this->title);
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title' => 'Enabled',
                'type' => 'checkbox',
                'label' => 'Enable this shipping method',
                'default' => 'yes',
            ],
            'title' => [
                'title' => 'Title',
                'type' => 'text',
                'default' => 'Next Day Delivery'
            ],
        ];
    }

    public function calculate_shipping($package = []) {
        // Metrics
        $weight   = floatval(WC()->cart ? WC()->cart->get_cart_contents_weight() : 0);
        $distance = isset($_COOKIE['delivery_distance']) ? floatval($_COOKIE['delivery_distance']) : 0.0;

        // Use shared delivery modes and choose cheapest eligible
        if (!function_exists('sp_get_delivery_modes') || !function_exists('sp_select_mode')) {
            return; // shared helpers missing
        }
        $modes = sp_get_delivery_modes();
        $selected = sp_select_mode($weight, $distance, $modes);
        if (!$selected) {
            return; // no eligible mode
        }
        $cost = floatval($selected['cost']);

        $rate = [
            'id'    => $this->id,
            'label' => $this->title,
            'cost'  => $cost,
        ];
        $this->add_rate($rate);
    }
}
