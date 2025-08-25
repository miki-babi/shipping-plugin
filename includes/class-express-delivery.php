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
            'enable_time_window' => [
                'title' => 'Limit by time window',
                'type' => 'checkbox',
                'label' => 'Only offer Express during specified hours',
                'default' => 'yes',
            ],
            'start_hour' => [
                'title' => 'Start hour (0-23)',
                'type' => 'number',
                'custom_attributes' => [ 'min' => 0, 'max' => 23, 'step' => 1 ],
                'default' => '9',
                'desc_tip' => true,
                'description' => 'Hour of day (site timezone) when Express becomes available.',
            ],
            'end_hour' => [
                'title' => 'End hour (0-23)',
                'type' => 'number',
                'custom_attributes' => [ 'min' => 0, 'max' => 23, 'step' => 1 ],
                'default' => '17',
                'desc_tip' => true,
                'description' => 'Hour of day (site timezone) when Express stops being available.',
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
        // If time window is disabled in settings, allow method (still subject to other conditions)
        $limit = $this->get_option('enable_time_window', 'yes') === 'yes';
        if (!$limit) {
            return true;
        }
        // Read hours from settings with sane defaults
        $start = intval($this->get_option('start_hour', 9));
        $end   = intval($this->get_option('end_hour', 17));
        // Allow theme/plugins to override via filter
        $hours = apply_filters('sp_express_hours', [ 'start' => $start, 'end' => $end ]);
        $start = isset($hours['start']) ? intval($hours['start']) : 9;
        $end   = isset($hours['end']) ? intval($hours['end']) : 17;

        // Current hour in site timezone
        $hour = intval(current_time('H'));
        // Handle normal daytime window; if start == end treat as disabled window
        if ($start === $end) { return false; }
        if ($start < $end) {
            return ($hour >= $start && $hour < $end);
        }
        // Overnight window (e.g., 22 -> 6)
        return ($hour >= $start || $hour < $end);
    }
    
}
