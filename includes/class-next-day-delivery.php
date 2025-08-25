<?php
if (!defined('ABSPATH')) exit;

class Next_Day_Delivery extends WC_Shipping_Method {
    public function __construct() {
        $this->id = 'next_day_delivery';
        $this->method_title = 'Next Day Delivery';
        $this->method_description = 'Delivery by the next day';
        $this->enabled = "yes";
        $this->title = "Next Day Delivery";
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
                'default' => 'Next Day Delivery'
            ],
            'base_fee' => [
                'title' => 'Base fee',
                'type' => 'price',
                'default' => '75',
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
        // Metrics
        $weight   = floatval(WC()->cart ? WC()->cart->get_cart_contents_weight() : 0);
        $distance = isset($_COOKIE['delivery_distance']) ? floatval($_COOKIE['delivery_distance']) : 0.0;

        // Settings
        $base_fee = floatval($this->get_option('base_fee', 0));
        $per_kg   = floatval($this->get_option('per_kg', 0));
        $per_km   = floatval($this->get_option('per_km', 0));
        $min_cost_opt = $this->get_option('min_cost', '');
        $max_cost_opt = $this->get_option('max_cost', '');

        // Compute
        $cost = $base_fee + ($per_kg * $weight) + ($per_km * $distance);

        // Apply caps
        if ($max_cost_opt !== '' && is_numeric($max_cost_opt)) {
            $max_cost = floatval($max_cost_opt);
            if ($max_cost >= 0 && $cost > $max_cost) {
                $cost = $max_cost;
            }
        }
        if ($min_cost_opt !== '' && is_numeric($min_cost_opt)) {
            $min_cost = floatval($min_cost_opt);
            if ($min_cost >= 0 && $cost < $min_cost) {
                $cost = $min_cost;
            }
        }

        $rate = [
            'id'    => $this->id,
            'label' => $this->title,
            'cost'  => $cost,
        ];
        $this->add_rate($rate);
    }

    // No rules UI or custom field handlers for Next Day Delivery.
}
