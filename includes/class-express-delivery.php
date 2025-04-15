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
        $weight = 0;
    
        if (!empty($package['contents'])) {
            foreach ($package['contents'] as $item) {
                if (isset($item['data']) && $item['data']->has_weight()) {
                    $weight += floatval($item['data']->get_weight()) * $item['quantity'];
                }
            }
        }
    
        $base_cost = floatval($this->get_option('base_cost'));
    
        if ($weight > 10) {
            $base_cost += 20;
        } else if ($weight > 5) {
            $base_cost += 10;
        }
    
        $per_km = floatval($this->get_option('cost_per_km'));
        $distance_km = isset($_COOKIE['delivery_distance']) ? floatval($_COOKIE['delivery_distance']) : 1;
        if ($distance_km <= 0) {
            $distance_km = 1;
        }
    
        $cost = $base_cost + ($per_km * $distance_km);
    
        $this->add_rate([
            'id' => $this->id,
            'label' => $this->title,
            'cost' => round($cost, 2),
        ]);
    }
    
}
