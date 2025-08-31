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
                'default' => 'Next Day Delivery'
            ],
            'is_free' => [
                'title' => 'Free shipping',
                'type'  => 'checkbox',
                'label' => 'Make this method free (cost 0)',
                'default' => 'no',
            ],
        ];
    }

    // public function calculate_shipping($package = []) {
    //     // Metrics
    //     $weight   = floatval(WC()->cart ? WC()->cart->get_cart_contents_weight() : 0);
    //     $distance = function_exists('sp_get_distance_km') ? sp_get_distance_km() : (isset($_COOKIE['delivery_distance']) ? floatval($_COOKIE['delivery_distance']) : 0.0);

    //     // Free override
    //     if ($this->get_option('is_free', 'no') === 'yes') {
    //         $this->add_rate([
    //             'id'    => $this->id,
    //             'label' => $this->title,
    //             'cost'  => 0,
    //         ]);
    //         return;
    //     }

    //     // Use shared delivery modes and choose cheapest eligible
    //     if (!function_exists('sp_get_delivery_modes') || !function_exists('sp_select_mode')) {
    //         return; // shared helpers missing
    //     }
    //     $modes = sp_get_delivery_modes();
    //     $selected = sp_select_mode($weight, $distance, $modes);
    //     if (!$selected) {
    //         return; // no eligible mode
    //     }
    //     $cost = floatval($selected['cost']);

    //     $rate = [
    //         'id'    => $this->id,
    //         'label' => $this->title,
    //         'cost'  => $cost,
    //     ];
    //     $this->add_rate($rate);
    // }
    
    public function calculate_shipping($package = []) {
        // $this->log_step('calculate_shipping(): start');
    
        $items = isset($package['contents']) ? $package['contents'] : [];
        // $this->log_step('calculate_shipping(): items_count=' . (is_array($items) ? count($items) : 0));
    
        // Calculate weight directly from package (in grams)
        $weight = 0;
        if (!empty($items) && is_array($items)) {
            foreach ($items as $key => $line) {
                $product = isset($line['data']) ? $line['data'] : null;
                $qty     = isset($line['quantity']) ? $line['quantity'] : 0;
    
                if ($product && method_exists($product, 'get_weight')) {
                    // WooCommerce weight is in kg → convert to grams
                    $pweight = floatval($product->get_weight()) * 1000;
                    $weight += ($pweight * $qty);
                } else {
                    $pweight = 0;
                }
    
                $sku = ($product && method_exists($product, 'get_sku')) ? $product->get_sku() : '';
    
                // $this->log_step("calculate_shipping(): item key={$key} sku={$sku} qty={$qty} product_weight_g={$pweight}");
            }
        }
        // $this->log_step('calculate_shipping(): total_weight_g=' . $weight);
    
        // Free override
        if ($this->is_free === 'yes') {
            // $this->log_step('calculate_shipping(): is_free = yes -> adding 0 cost rate');
            $this->add_rate([
                'id'    => $this->id,
                'label' => $this->title,
                'cost'  => 0,
            ]);
            // $this->log_step('calculate_shipping(): end (free)');
            return;
        }
    
        // 📦 Weight-based pricing (all in grams now)
        if ($weight <= 500) {
            $cost = 100 + (100* 0.05);
        } elseif ($weight <= 1000) {
            $cost = 150 + (150* 0.05);
        } elseif ($weight <= 2000) {
            $cost = 200 + (200* 0.05);
        } else {
            // Over 2000g → 200 + (25 for every extra 500g)
            $extra_weight = $weight - 2000;
            $extra_blocks = ceil($extra_weight / 500);
            $before_tax = 200 + ($extra_blocks * 25) ;
            $cost = $before_tax + ($before_tax * 0.05);
        }
        // $this->log_step('calculate_shipping(): calculated_cost=' . $cost);
    
        // Add the shipping rate
        $rate = [
            'id'    => $this->id,
            'label' => $this->title,
            'cost'  => $cost,
        ];
    
        $this->add_rate($rate);
        // $this->log_step('calculate_shipping(): rate added and end');
    }
}
