<?php
if (!defined('ABSPATH')) exit;

class Express_Delivery extends WC_Shipping_Method {
    public function __construct() {
        $this->id = 'express_delivery';
        $this->method_title = 'Express Delivery';
        $this->method_description = 'Delivery in 1-2 hours';
        $this->enabled = "no";
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
        // Express Delivery is a flat rate regardless of time: Br. 150.00
        $cost = 150.00;

        $this->add_rate([
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $cost,
        ]);
    }
    
}
