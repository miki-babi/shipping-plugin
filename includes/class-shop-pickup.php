<?php
if (!defined('ABSPATH')) exit;

class Shop_Pickup extends WC_Shipping_Method {
    public function __construct() {
        $this->id = 'shop_pickup';
        $this->method_title = 'Shop Pickup';
        $this->method_description = 'Customer picks up the order at the shop. Free of charge.';
        $this->enabled = 'yes';
        $this->title = 'Shop Pickup (Free)';
        $this->init();
    }

    public function init() {
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
                'default' => 'Shop Pickup (Free)'
            ],
        ];
    }

    public function is_available($package) {
        // Defer to WooCommerce default checks (zone match, requires shipping, etc.)
        if (!parent::is_available($package)) {
            return false;
        }
        return $this->enabled === 'yes';
    }

    public function calculate_shipping($package = []) {
        $this->add_rate([
            'id' => $this->id,
            'label' => $this->title,
            'cost' => 0,
        ]);
    }
}
