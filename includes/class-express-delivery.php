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
            'base_fee' => [
                'title' => 'Base fee',
                'type' => 'price',
                'default' => '150',
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
        $base   = floatval($this->get_option('base_fee', '150'));
        $perKg  = floatval($this->get_option('per_kg', '0'));
        $perKm  = floatval($this->get_option('per_km', '0'));
        $min    = floatval($this->get_option('min_cost', '0'));
        $maxOpt = $this->get_option('max_cost', '');
        $max    = ($maxOpt === '' ? null : floatval($maxOpt));

        $weight = floatval(WC()->cart ? WC()->cart->get_cart_contents_weight() : 0);
        $distance = isset($_COOKIE['delivery_distance']) ? floatval($_COOKIE['delivery_distance']) : 0.0;

        $cost = $base + ($weight * $perKg) + ($distance * $perKm);
        if ($cost < $min) { $cost = $min; }
        if (!is_null($max) && $max >= 0 && $cost > $max) { $cost = $max; }

        $this->add_rate([
            'id' => $this->id,
            'label' => $this->title,
            'cost' => $cost,
        ]);
    }

    /**
     * Limit Express Delivery availability by time window.
     * Default window: 09:00–17:00 (site local time). Adjustable via 'sp_express_hours' filter.
     */
    public function is_available($package) {
        if (!parent::is_available($package)) {
            return false;
        }
        // Allow theme/plugins to override hours: ['start' => 9, 'end' => 17]
        $hours = apply_filters('sp_express_hours', [ 'start' => 9, 'end' => 17 ]);
        $start = isset($hours['start']) ? intval($hours['start']) : 9;
        $end   = isset($hours['end']) ? intval($hours['end']) : 17;

        // Current hour in site timezone
        $hour = intval(current_time('H'));
        return ($hour >= $start && $hour < $end);
    }
    
}
