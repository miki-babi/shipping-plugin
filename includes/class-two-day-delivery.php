<?php
if (!defined('ABSPATH')) exit;

class Two_Day_Delivery extends WC_Shipping_Method {
    public function __construct() {
        $this->id = 'two_day_delivery';
        $this->method_title = 'Two Day Delivery';
        $this->method_description = 'Delivery within two days';
        $this->enabled = 'yes';
        $this->title = 'Two Day Delivery';
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
                'default' => 'Two Day Delivery'
            ],
            'base_fee' => [
                'title' => 'Base fee',
                'type' => 'price',
                'default' => '50',
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
        // Get current package metrics
        $weight   = floatval(WC()->cart ? WC()->cart->get_cart_contents_weight() : 0);
        $distance = isset($_COOKIE['delivery_distance']) ? floatval($_COOKIE['delivery_distance']) : 0.0;

        // Define pricing rules (ordered by priority)
        $rules = [
            [ 'max_weight' => 3,   'max_distance' => 5,  'base_price' => 80,  'price_per_km' => 25 ],
            [ 'max_weight' => 10,  'max_distance' => 10, 'base_price' => 150, 'price_per_km' => 25 ],
            [ 'max_weight' => 300, 'max_distance' => 25, 'base_price' => 200, 'price_per_km' => 25 ],
        ];

        $matched_rule = null;
        foreach ($rules as $rule) {
            if ($weight <= $rule['max_weight'] && $distance <= $rule['max_distance']) {
                $matched_rule = $rule;
                break;
            }
        }

        if (!$matched_rule) {
            // No matching rule -> do not offer this method
            return;
        }

        $cost = floatval($matched_rule['base_price']) + ($distance * floatval($matched_rule['price_per_km']));

        $rate = [
            'id'    => $this->id,
            'label' => $this->title,
            'cost'  => $cost,
        ];
        $this->add_rate($rate);
    }
}
