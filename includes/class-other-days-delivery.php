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
            'cost' => [
                'title' => 'Cost',
                'type' => 'price',
                'default' => '0'
            ],
        ];
    }

    public function calculate_shipping($package = []) {
        $cost = floatval($this->get_option('cost', '0'));
        $rate = [
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $cost,
        ];
        $this->add_rate($rate);
    }
}
