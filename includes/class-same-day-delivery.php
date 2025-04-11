<?php

class SameDay_Delivery extends WC_Shipping_Method {
    public function __construct() {
        $this->id = 'express_delivery';
        $this->method_title = 'Express Delivery';
        $this->method_description = 'Fastest delivery option';

        $this->enabled = "yes";
        $this->title = "Express Delivery (1-2 hours)";

        $this->init();
    }

    function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'title' => [
                'title' => 'Method Title',
                'type' => 'text',
                'default' => 'Express Delivery (1-2 hours)'
            ],
            'cost' => [
                'title' => 'Cost',
                'type' => 'price',
                'default' => 100
            ]
        ];
    }

    public function calculate_shipping($package = []) {
        $rate = [
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $this->get_option('cost'),
        ];
        $this->add_rate($rate);
    }
}


