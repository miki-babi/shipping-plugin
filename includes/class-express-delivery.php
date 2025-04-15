<?php
if (!defined('ABSPATH')) exit;

class Express_Delivery extends WC_Shipping_Method {
    public function __construct() {
        $this->id = 'express_delivery';
        $this->method_title = 'Express Delivery';
        $this->method_description = 'Delivery in 1-2 hours';
        $this->enabled = "yes";
        $this->title = "Express Delivery (1-2 hours)";
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
                'default' => 'Express Delivery (1-2 hours)'
            ],
            'base_cost' => [
                'title' => 'Base Cost',
                'type' => 'price',
                'default' => '50'
            ],
            'cost_per_km' => [
                'title' => 'Cost per KM',
                'type' => 'price',
                'default' => '10'
            ],
        ];
    }

    public function calculate_shipping($package = []) {
        $base_cost = floatval($this->get_option('base_cost'));
        $per_km = floatval($this->get_option('cost_per_km'));
        $distance_km = 0;

        // Get distance from cookie
        if (isset($_COOKIE['delivery_distance'])) {
            $distance_km = floatval($_COOKIE['delivery_distance']);
        }

        // Fallback if cookie not set
        if ($distance_km <= 0) {
            $distance_km = 1; // Default minimum distance
        }

        $cost = $base_cost + ($per_km * $distance_km);

        $this->add_rate([
            'id' => $this->id,
            'label' => $this->title,
            'cost' => round($cost, 2),
        ]);
    }
}
