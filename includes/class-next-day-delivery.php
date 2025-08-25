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
            'base_fee' => [
                'title' => 'Base fee',
                'type' => 'price',
                'default' => '75',
            ],
            'per_kg' => [
                'title' => 'Cost per kg',
                'type' => 'price',
                'default' => '0',
            ],
            'per_km' => [
                'title' => 'Cost per km',
                'type' => 'price',
                'default' => '0',
            ],
            'min_cost' => [
                'title' => 'Minimum cost',
                'type' => 'price',
                'default' => '0',
            ],
            'max_cost' => [
                'title' => 'Maximum cost',
                'type' => 'price',
                'default' => '',
                'description' => 'Leave empty for no cap',
            ],
        ];
    }

    public function calculate_shipping($package = []) {
        $base   = floatval($this->get_option('base_fee', '75'));
        $perKg  = floatval($this->get_option('per_kg', '0'));
        $perKm  = floatval($this->get_option('per_km', '0'));
        $min    = floatval($this->get_option('min_cost', '0'));
        $maxOpt = $this->get_option('max_cost', '');
        $max    = ($maxOpt === '' ? null : floatval($maxOpt));

        $weight = floatval(WC()->cart ? WC()->cart->get_cart_contents_weight() : 0);
        $distance = isset($_COOKIE['delivery_distance']) ? floatval($_COOKIE['delivery_distance']) : 0.0;

        $cost = $base + ($weight * $perKg) + ($distance * $perKm);
        if ($cost < $min) { $cost = $min; }
        if (!is_null($max) && $max >= 0 && $cost > $max) { $cost = $max; }
        
        $rate = [
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $cost,
        ];
        $this->add_rate($rate);
    }
}
