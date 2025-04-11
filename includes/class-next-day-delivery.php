<?php
if (!defined('ABSPATH')) exit;

class Next_Day_Delivery extends WC_Shipping_Method {
    public function __construct() {
        $this->id = 'next_day_delivery';
        $this->method_title = 'Next Day Delivery';
        $this->method_description = 'Delivery by the next day';
        // $this->enabled = "yes";
        $this->title = "Next Day Delivery";
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
                'default' => 'Next Day Delivery'
            ],
            'cost' => [
                'title' => 'Cost',
                'type' => 'price',
                'default' => '50'
            ],
        ];
    }

    public function calculate_shipping($package = []) {
        $cost = $this->get_option('cost');
        $rate = [
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $cost,
        ];
        $this->add_rate($rate);
    }
}
