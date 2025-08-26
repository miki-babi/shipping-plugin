<?php
if (!defined('ABSPATH')) exit;

class Same_Day_Delivery extends WC_Shipping_Method {
    /**
     * Append a log line to the plugin's local debug.log file.
     */
    private function log_step($message) {
        $prefix = '[' . (isset($this->id) ? $this->id : 'same_day_delivery') . '] ';
        $line   = date('Y-m-d H:i:s') . ' ' . $prefix . $message . PHP_EOL;
        $path   = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'debug.log'; // plugin-root/debug.log
        @file_put_contents($path, $line, FILE_APPEND);
    }

    public function __construct() {
        $this->log_step('Constructor: start');
        $this->id = 'same_day_delivery';
        $this->method_title = 'Same Day Delivery';
        $this->method_description = 'Delivery within the same day';
        $this->enabled = "yes";
        $this->title = "Same Day Delivery";
        // $this->log_step('Constructor: defaults set (enabled=' . $this->enabled . ', title=' . $this->title . ')');
        $this->init();
        // $this->log_step('Constructor: end');
    }

    function init() {
        $this->log_step('init(): start');
        $this->init_form_fields();
        // $this->log_step('init(): init_form_fields done');
        $this->init_settings();
        // $this->log_step('init(): init_settings done');
        // Load persisted settings for common props
        $this->enabled = $this->get_option('enabled', $this->enabled);
        $this->title   = $this->get_option('title', $this->title);
        $this->is_free = $this->get_option('is_free', 'no');
        // $this->log_step('init(): options loaded (enabled=' . $this->enabled . ', title=' . $this->title . ', is_free=' . $this->is_free . ')');
        add_action('woocommerce_update_options_shipping_' . $this->id, [$this, 'process_admin_options']);
        // $this->log_step('init(): hook registered (woocommerce_update_options_shipping_' . $this->id . ')');
        // $this->log_step('init(): end');
    }

    public function init_form_fields() {
        // $this->log_step('init_form_fields(): start');
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
                'default' => 'Same Day Delivery'
            ],
            'is_free' => [
                'title' => 'Free shipping',
                'type'  => 'checkbox',
                'label' => 'Make this method free (cost 0)',
                'default' => 'no',
            ],
        ];
        // $this->log_step('init_form_fields(): end');
    }

    public function calculate_shipping($package = []) {
        $this->log_step('calculate_shipping(): start');
        // Destination and package contents overview
        $dest = isset($package['destination']) ? $package['destination'] : [];
        $this->log_step('calculate_shipping(): destination=' . (function_exists('wp_json_encode') ? wp_json_encode($dest) : json_encode($dest)));
        $items = isset($package['contents']) ? $package['contents'] : [];
        $this->log_step('calculate_shipping(): items_count=' . (is_array($items) ? count($items) : 0));
        if (is_array($items)) {
            foreach ($items as $key => $line) {
                $product = isset($line['data']) ? $line['data'] : null;
                $sku = ($product && method_exists($product, 'get_sku')) ? $product->get_sku() : '';
                $pweight = ($product && method_exists($product, 'get_weight')) ? $product->get_weight() : '';
                $qty = isset($line['quantity']) ? $line['quantity'] : 0;
                $this->log_step('calculate_shipping(): item key=' . $key . ' sku=' . $sku . ' qty=' . $qty . ' product_weight=' . $pweight);
            }
        }

        // Get current package metrics
        $has_cart = (WC()->cart && method_exists(WC()->cart, 'get_cart_contents_weight'));
        $this->log_step('calculate_shipping(): has_cart=' . ($has_cart ? 'yes' : 'no'));
        $weight   = floatval($has_cart ? WC()->cart->get_cart_contents_weight() : 0);
        $distance_source = function_exists('sp_get_distance_km') ? 'sp_get_distance_km' : (isset($_COOKIE['delivery_distance']) ? 'cookie:delivery_distance' : 'none');
        $distance = function_exists('sp_get_distance_km') ? sp_get_distance_km() : (isset($_COOKIE['delivery_distance']) ? floatval($_COOKIE['delivery_distance']) : 0.0);
        $this->log_step('calculate_shipping(): distance_source=' . $distance_source);
        $this->log_step('calculate_shipping(): metrics (weight=' . $weight . ', distance=' . $distance . ')');

        // Free override
        if ($this->get_option('is_free', 'no') === 'yes') {
            $this->log_step('calculate_shipping(): is_free = yes -> adding 0 cost rate');
            $this->add_rate([
                'id'    => $this->id,
                'label' => $this->title,
                'cost'  => 0,
            ]);
            $this->log_step('calculate_shipping(): end (free)');
            return;
        }

        // Use shared delivery modes and choose cheapest eligible
        if (!function_exists('sp_get_delivery_modes') || !function_exists('sp_select_mode')) {
            $this->log_step('calculate_shipping(): helper functions missing -> abort');
            return; // shared helpers missing
        }
        $modes = sp_get_delivery_modes();
        $this->log_step('calculate_shipping(): modes loaded count=' . (is_array($modes) ? count($modes) : 0));
        if (is_array($modes)) {
            // Log a compact snapshot of up to first 3 modes
            $snapshot = array_slice($modes, 0, 3);
            $this->log_step('calculate_shipping(): modes snapshot=' . (function_exists('wp_json_encode') ? wp_json_encode($snapshot) : json_encode($snapshot)));
        }
        $selected = sp_select_mode($weight, $distance, $modes);
        if (!$selected) {
            $this->log_step('calculate_shipping(): no eligible mode -> abort');
            return; // no eligible mode
        }
        $this->log_step('calculate_shipping(): selected mode details=' . (function_exists('wp_json_encode') ? wp_json_encode($selected) : json_encode($selected)));
        $cost = floatval($selected['cost']);
        $this->log_step('calculate_shipping(): computed cost=' . $cost);

        $rate = [
            'id'    => $this->id,
            'label' => $this->title,
            'cost'  => $cost,
        ];
        $this->log_step('calculate_shipping(): final rate payload=' . (function_exists('wp_json_encode') ? wp_json_encode($rate) : json_encode($rate)));
        $this->add_rate($rate);
        $this->log_step('calculate_shipping(): rate added and end');
    }
}
