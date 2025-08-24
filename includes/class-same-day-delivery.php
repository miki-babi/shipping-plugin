<?php
if (!defined('ABSPATH')) exit;

class Same_Day_Delivery extends WC_Shipping_Method {
    public function __construct() {
        $this->id = 'same_day_delivery';
        $this->method_title = 'Same Day Delivery';
        $this->method_description = 'Delivery within the same day';
        $this->enabled = "yes";
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
        // Determine current site-local time and compare to 11:30 AM
        $now = current_time('timestamp');
        $today = date('Y-m-d', $now);
        $cutoff = strtotime($today . ' 11:30', $now);

        $cost = ($now <= $cutoff) ? 75.00 : 150.00; // After 11:30, same as Express

        $rate = [
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $cost,
        ];
        $this->add_rate($rate);
    }




}
