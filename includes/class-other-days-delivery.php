<?php
if (!defined('ABSPATH')) exit;

class Other_Days_Delivery extends WC_Shipping_Method {
    public function __construct() {
        $this->id = 'other_days_delivery';
        $this->method_title = 'Other Days';
        $this->method_description = 'Schedule delivery for another day';
        $this->enabled = 'yes';
        $this->title = 'Other Days';
        $this->init();
    }

    function init() {
        $this->init_form_fields();
        $this->init_settings();
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'title' => [
                'title' => 'Title',
                'type' => 'text',
                'default' => 'Other Days'
            ],
            'enable_advanced_pricing' => [
                'title' => 'Enable weight/distance pricing',
                'type' => 'checkbox',
                'label' => 'Use base + per kg + per km',
                'default' => 'no',
            ],
            'base_fee' => [
                'title' => 'Base fee',
                'type' => 'price',
                'default' => '0',
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
            'fallback_cost' => [
                'title' => 'Fallback flat rate',
                'type' => 'price',
                'default' => '0',
                'description' => 'Used when advanced pricing is disabled.',
            ],
        ];
    }

    public function calculate_shipping($package = []) {
        $useAdvanced = $this->get_option('enable_advanced_pricing', 'no') === 'yes';
        if ($useAdvanced) {
            $base   = floatval($this->get_option('base_fee', '0'));
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
        } else {
            $cost = floatval($this->get_option('fallback_cost', $this->get_option('cost', '0')));
        }
        $rate = [
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $cost,
        ];
        $this->add_rate($rate);
    }
}
