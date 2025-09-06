<?php


if (!defined('ABSPATH')) exit;

class Other_Days_Delivery extends WC_Shipping_Method {
    public function __construct() {
        $this->id = 'other_days_delivery';
        $this->method_title = 'Other Days';
        $this->method_description = 'Schedule delivery for another day';
        $this->enabled = 'yes';
        $this->title = 'Other Days';
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
                'default' => 'Other Days'
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

// Register all hooks related to 'Other Days' delivery
    // public static function register_hooks() {
    //     // Show a delivery date input under the 'Other Days' shipping method when selected
    //     add_action('woocommerce_after_shipping_rate', [__CLASS__, 'show_delivery_date_input'], 10, 2);

    //     // Toggle the date field visibility based on selected shipping method (checkout page)
    //     add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_date_toggle_script']);

    //     // Output a hidden field INSIDE the checkout form to persist the selected date across fragment refreshes
    //     add_action('woocommerce_checkout_after_order_review', [__CLASS__, 'output_hidden_date_field'], 15);

    //     // Validate the date when 'Other Days' is selected
    //     add_action('woocommerce_checkout_process', [__CLASS__, 'validate_delivery_date']);

    //     // Save the selected date to order meta when 'Other Days' is used
    //     add_action('woocommerce_checkout_create_order', [__CLASS__, 'save_delivery_date'], 10, 2);

    //     // Show delivery date in admin order screen
    //     add_action('woocommerce_admin_order_data_after_billing_address', [__CLASS__, 'show_delivery_date_in_admin']);
    // }

    // public static function sp_get_settings() {
    //     $defaults = [
    //         'enable_other_days_date' => 'yes',
    //         'require_other_days_date' => 'yes',
    //         'other_days_date_label' => __('Select delivery date', 'shipping-plugin'),
    //         'min_lead_days' => 0,
    //     ];
    //     $opts = get_option('sp_settings', []);
    //     if (!is_array($opts)) $opts = [];
    //     return wp_parse_args($opts, $defaults);
    // }

    // public static function show_delivery_date_input($method, $index) {
    //     if (!isset($method->method_id) || $method->method_id !== 'other_days_delivery') {
    //         return;
    //     }
    //     $settings = self::sp_get_settings();
    //     if ($settings['enable_other_days_date'] !== 'yes') {
    //         return;
    //     }
    //     $minDays = isset($settings['min_lead_days']) ? max(0, intval($settings['min_lead_days'])) : 0;
    //     $minDate = gmdate('Y-m-d', strtotime('+' . $minDays . ' days'));
    //     echo '<div class="other-days-date-wrap" style="display:none; margin:8px 0 0 24px;" data-rate-id="' . esc_attr($method->id) . '">';
    //     echo '<label for="other_days_date" style="display:block; margin-bottom:4px;">' . esc_html($settings['other_days_date_label']) . '</label>';
    //     echo '<input type="date" id="other_days_date" class="sp-other-days-date" name="other_days_date_visible" min="' . esc_attr($minDate) . '" />';
    //     echo '</div>';
    // }

    // public static function enqueue_date_toggle_script() {
    //     if (!function_exists('is_checkout') || !is_checkout()) return;
    //     $js = "function spGetChecked(){ return document.querySelector('input[name^='shipping_method']:checked'); }
    //         function spToggleOtherDays(){
    //             var checked = spGetChecked();
    //             var wraps = document.querySelectorAll('.other-days-date-wrap');
    //             wraps.forEach(function(w){
    //                 var isOther = checked && checked.value && checked.value.indexOf('other_days_delivery') === 0;
    //                 var match = isOther && w.getAttribute('data-rate-id') === checked.value;
    //                 w.style.display = match ? 'block' : 'none';
    //             });
    //         }
    //         function spFindVisibleInput(){
    //             var checked = spGetChecked();
    //             if(!(checked && checked.value && checked.value.indexOf('other_days_delivery') === 0)) return null;
    //             var wraps = document.querySelectorAll('.other-days-date-wrap');
    //             for (var i=0;i<wraps.length;i++){
    //                 if(wraps[i].getAttribute('data-rate-id') === checked.value){
    //                     return wraps[i].querySelector('.sp-other-days-date');
    //                 }
    //             }
    //             return null;
    //         }
    //         function spSyncDateVisibleHidden(){
    //             var hid = document.getElementById('other_days_date_hidden');
    //             if(!hid) return;
    //             var vis = spFindVisibleInput();
    //             if(vis){ hid.value = vis.value; }
    //         }
    //         function spRestoreVisibleFromHidden(){
    //             var hid = document.getElementById('other_days_date_hidden');
    //             if(!hid) return;
    //             var vis = spFindVisibleInput();
    //             if(vis && !vis.value && hid.value){ vis.value = hid.value; }
    //         }
    //         document.addEventListener('DOMContentLoaded',function(){
    //             function toggleOtherDays(){
    //                 spToggleOtherDays();
    //                 spRestoreVisibleFromHidden();
    //             }
    //             document.body.addEventListener('change', function(e){ if(e.target && e.target.name && e.target.name.indexOf('shipping_method')===0){ toggleOtherDays(); }});
    //             document.body.addEventListener('change', function(e){ if(e.target && e.target.classList && e.target.classList.contains('sp-other-days-date')){ spSyncDateVisibleHidden(); }});
    //             document.body.addEventListener('input', function(e){ if(e.target && e.target.classList && e.target.classList.contains('sp-other-days-date')){ spSyncDateVisibleHidden(); }});
    //             jQuery('form.checkout').on('checkout_place_order', function(){ spSyncDateVisibleHidden(); });
    //             toggleOtherDays();
    //             jQuery( document.body ).on('updated_checkout', function(){ toggleOtherDays(); });
    //         });";
    //     wp_register_script('sp-other-days-toggle', '', ['jquery'], null, true);
    //     wp_enqueue_script('sp-other-days-toggle');
    //     wp_add_inline_script('sp-other-days-toggle', $js);
    // }

    // public static function output_hidden_date_field() {
    //     if (!function_exists('is_checkout') || !is_checkout()) return;
    //     echo '<input type="hidden" id="other_days_date_hidden" name="other_days_date" value="" />';
    // }

    // public static function validate_delivery_date() {
    //     if (empty($_POST['shipping_method'][0])) return;
    //     $selected = wc_clean(wp_unslash($_POST['shipping_method'][0]));
    //     if (strpos($selected, 'other_days_delivery') === 0) {
    //         $settings = self::sp_get_settings();
    //         $date = '';
    //         if (isset($_POST['other_days_date'])) {
    //             $date = wc_clean(wp_unslash($_POST['other_days_date']));
    //         } elseif (isset($_POST['other_days_date_visible'])) {
    //             $date = wc_clean(wp_unslash($_POST['other_days_date_visible']));
    //         }
    //         if ($settings['require_other_days_date'] === 'yes' && empty($date)) {
    //             wc_add_notice(__('Please select a delivery date for Other Days.', 'shipping-plugin'), 'error');
    //         }
    //     }
    // }

    // public static function save_delivery_date($order, $data) {
    //     if (!empty($_POST['shipping_method'][0]) && strpos(wc_clean(wp_unslash($_POST['shipping_method'][0])), 'other_days_delivery') === 0) {
    //         $date = '';
    //         if (!empty($_POST['other_days_date'])) {
    //             $date = wc_clean(wp_unslash($_POST['other_days_date']));
    //         } elseif (!empty($_POST['other_days_date_visible'])) {
    //             $date = wc_clean(wp_unslash($_POST['other_days_date_visible']));
    //         }
    //         if (!empty($date)) { $order->update_meta_data('_delivery_date', $date); }
    //     }
    // }

    // public static function show_delivery_date_in_admin($order) {
    //     $date = $order->get_meta('_delivery_date');
    //     if (!empty($date)) {
    //         echo '<p><strong>' . esc_html__('Preferred delivery date', 'shipping-plugin') . ':</strong> ' . esc_html($date) . '</p>';
    //     }
    // }