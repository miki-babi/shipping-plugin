<?php
if (!defined('ABSPATH')) exit;

class Same_Day_Delivery extends WC_Shipping_Method {
    public function __construct() {
        $this->id = 'same_day_delivery';
        $this->method_title = 'Same Day Delivery';
        $this->method_description = 'Delivery within the same day';
        // $this->enabled = "yes";
        $this->title = "Same Day Delivery";
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
                'default' => 'Same Day Delivery'
            ],
            'cost' => [
                'title' => 'Cost',
                'type' => 'price',
                'default' => '70'
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
