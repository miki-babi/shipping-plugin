<?php
if (!defined('ABSPATH')) exit;


class Same_Day_Delivery extends WC_Shipping_Method {
    private function log_step($message) {
        $prefix = '[' . (isset($this->id) ? $this->id : 'same_day_delivery') . '] ';
        $line   = date('Y-m-d H:i:s') . ' ' . $prefix . $message . PHP_EOL;
        $path   = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'debug.log';
        @file_put_contents($path, $line, FILE_APPEND);
    }

    public function __construct() {
        $this->id                 = 'same_day_delivery';
        $this->method_title       = 'Same Day Delivery';
        $this->method_description = 'Delivery within the same day';
        $this->enabled            = "yes";
        $this->title              = "Same Day Delivery";

        $this->init();
    }

    function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled', $this->enabled);
        $this->title   = $this->get_option('title', $this->title);
        $this->is_free = $this->get_option('is_free', 'no');

        add_action(
            'woocommerce_update_options_shipping_' . $this->id,
            [ $this, 'process_admin_options' ]
        );

        // Removed checkout update hook to avoid recalculation loops
    }
    public function calculate_shipping($package = []) {
        $this->log_step('calculate_shipping(): start');
    
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
    
        // Get current package weight
        $has_cart = (WC()->cart && method_exists(WC()->cart, 'get_cart_contents_weight'));
        $this->log_step('calculate_shipping(): has_cart=' . ($has_cart ? 'yes' : 'no'));
    
        $weight   = floatval($has_cart ? WC()->cart->get_cart_contents_weight() : 0);
        $this->log_step('calculate_shipping(): total_weight=' . $weight);
    
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
    
        // 📦 Weight-based pricing
        if ($weight <= 500) {
            $cost = 100;
        } elseif ($weight <= 1000) {
            $cost = 150;
        } elseif ($weight <= 2000) {
            $cost = 200;
        } else {
            // Over 2000 → 200 + (25 for every extra 500g)
            $extra_weight = $weight - 2000;
            $extra_blocks = ceil($extra_weight / 500); 
            $cost = 200 + ($extra_blocks * 25);
        }
    
        $this->log_step('calculate_shipping(): calculated_cost=' . $cost);
    
        // Add the shipping rate
        $rate = [
            'id'    => $this->id,
            'label' => $this->title,
            'cost'  => $cost,
        ];
    
        $this->add_rate($rate);
        $this->log_step('calculate_shipping(): rate added and end');
    }
    
    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => 'Enabled',
                'type'    => 'checkbox',
                'label'   => 'Enable this shipping method',
                'default' => 'yes',
            ],
            'title' => [
                'title'   => 'Title',
                'type'    => 'text',
                'default' => 'Same Day Delivery'
            ],
            'is_free' => [
                'title'   => 'Free shipping',
                'type'    => 'checkbox',
                'label'   => 'Make this method free (cost 0)',
                'default' => 'no',
            ],
        ];
    }
}
