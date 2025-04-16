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
        $weight = 0;
        if (!empty($package['contents'])) {
            foreach ($package['contents'] as $item) {
                if (isset($item['data']) && $item['data']->has_weight()) {
                    $weight += floatval($item['data']->get_weight()) * $item['quantity'];
                }
            }
        }

        $base_cost = floatval($this->get_option('cost'));
        $per_km = 25;
        $distance_km = isset($_COOKIE['delivery_distance']) ? floatval($_COOKIE['delivery_distance']) : 1;

        if ($weight > 10 || $distance_km > 3) {
            $cost = $base_cost + ($distance_km * $per_km);
        } else {
            $cost = 0; // Free for weight under 10 and distance 3 or less
        }

        $rate = [
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $cost,
        ];
        $this->add_rate($rate);
    }




}
