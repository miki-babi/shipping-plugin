<?php
if (!defined('ABSPATH')) exit;

class Shop_Pickup extends WC_Shipping_Method {
    public function __construct() {
        $this->id                 = 'shop_pickup';
        $this->method_title       = __('Shop Pickup', 'shipping-plugin');
        $this->method_description = __('Customer picks up the order at the shop. Free of charge.', 'shipping-plugin');
        $this->enabled            = 'yes';
        $this->title              = __('Shop Pickup (Free)', 'shipping-plugin');
        $this->supports           = [ 'shipping-zones', 'instance-settings' ];
        $this->init();
    }

    public function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option('enabled', $this->enabled);
        $this->title   = $this->get_option('title', $this->title);

        add_action('woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ]);
    }

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enabled', 'shipping-plugin'),
                'type'    => 'checkbox',
                'label'   => __('Enable this shipping method', 'shipping-plugin'),
                'default' => 'yes',
            ],
            'title' => [
                'title'       => __('Title', 'shipping-plugin'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'shipping-plugin'),
                'default'     => __('Shop Pickup (Free)', 'shipping-plugin'),
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
            'id'    => $this->id,
            'label' => $this->title,
            'cost'  => 0,
        ]);
    }
}
