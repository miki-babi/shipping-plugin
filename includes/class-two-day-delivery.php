<?php
if (!defined('ABSPATH')) exit;

class Two_Day_Delivery extends WC_Shipping_Method {
    public function __construct() {
        $this->id = 'two_day_delivery';
        $this->method_title = 'Two Day Delivery';
        $this->method_description = 'Delivery within two days';
        $this->enabled = 'yes';
        $this->title = 'Two Day Delivery';
        $this->init();
    }

    function init() {
        $this->init_form_fields();
        $this->init_settings();
        // Load persisted settings for common props
        $this->enabled = $this->get_option('enabled', $this->enabled);
        $this->title   = $this->get_option('title', $this->title);
        $this->is_free = $this->get_option('is_free', 'no');
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
                'default' => 'Two Day Delivery'
            ],
            'is_free' => [
                'title' => 'Free shipping',
                'type'  => 'checkbox',
                'label' => 'Make this method free (cost 0)',
                'default' => 'no',
            ],
        ];
    }

    public function calculate_shipping($package = []) {
        // Get current package metrics
        $weight   = floatval(WC()->cart ? WC()->cart->get_cart_contents_weight() : 0);
        $distance = isset($_COOKIE['delivery_distance']) ? floatval($_COOKIE['delivery_distance']) : 0.0;

        // Free override
        if ($this->get_option('is_free', 'no') === 'yes') {
            $this->add_rate([
                'id'    => $this->id,
                'label' => $this->title,
                'cost'  => 0,
            ]);
            return;
        }

        // Use shared delivery modes and choose cheapest eligible
        if (!function_exists('sp_get_delivery_modes') || !function_exists('sp_select_mode')) {
            return; // shared helpers missing
        }
        $modes = sp_get_delivery_modes();
        $selected = sp_select_mode($weight, $distance, $modes);
        if (!$selected) {
            return; // no eligible mode
        }
        $cost = floatval($selected['cost']);

        $rate = [
            'id'    => $this->id,
            'label' => $this->title,
            'cost'  => $cost,
        ];
        $this->add_rate($rate);
    }
}
