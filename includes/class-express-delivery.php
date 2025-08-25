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
            'start_time' => [
                'title' => 'Start time',
                'type' => 'time',
                'default' => '09:00',
                'placeholder' => 'HH:MM',
                'desc_tip' => true,
                'description' => 'Time of day (site timezone) when Express becomes available (e.g., 09:00).',
            ],
            'end_time' => [
                'title' => 'End time',
                'type' => 'time',
                'default' => '17:00',
                'placeholder' => 'HH:MM',
                'desc_tip' => true,
                'description' => 'Time of day (site timezone) when Express stops being available (e.g., 17:00).',
            ],
        ];
    }

    public function calculate_shipping($package = []) {
        // Get current package metrics
        $weight   = floatval(WC()->cart ? WC()->cart->get_cart_contents_weight() : 0);
        $distance = isset($_COOKIE['delivery_distance']) ? floatval($_COOKIE['delivery_distance']) : 0.0;

        // Define pricing rules (ordered by priority)
        $rules = [
            [ 'max_weight' => 3,   'max_distance' => 5,  'base_price' => 80,  'price_per_km' => 25 ],
            [ 'max_weight' => 10,  'max_distance' => 10, 'base_price' => 150, 'price_per_km' => 25 ],
            [ 'max_weight' => 300, 'max_distance' => 25, 'base_price' => 200, 'price_per_km' => 25 ],
        ];

        $matched_rule = null;
        foreach ($rules as $rule) {
            if ($weight <= $rule['max_weight'] && $distance <= $rule['max_distance']) {
                $matched_rule = $rule;
                break;
            }
        }

        if (!$matched_rule) {
            // No matching rule -> do not offer this method
            return;
        }

        $cost = floatval($matched_rule['base_price']) + ($distance * floatval($matched_rule['price_per_km']));

        $this->add_rate([
            'id'    => $this->id,
            'label' => $this->title,
            'cost'  => $cost,
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
        // Read times from settings with sane defaults
        // Back-compat: honor legacy start_hour/end_hour if time fields not present
        $settings = is_array($this->settings) ? $this->settings : [];
        if (array_key_exists('start_time', $settings)) {
            $start_str = (string)$this->get_option('start_time', '09:00');
        } else {
            $legacy_start = $this->get_option('start_hour', 9);
            $start_str = sprintf('%02d:00', intval($legacy_start));
        }
        if (array_key_exists('end_time', $settings)) {
            $end_str = (string)$this->get_option('end_time', '17:00');
        } else {
            $legacy_end = $this->get_option('end_hour', 17);
            $end_str = sprintf('%02d:00', intval($legacy_end));
        }
        // Allow theme/plugins to override via filter. Back-compat: integers 0-23 mean hours.
        $hours = apply_filters('sp_express_hours', [ 'start' => $start_str, 'end' => $end_str ]);
        if (isset($hours['start']) && is_int($hours['start']) && isset($hours['end']) && is_int($hours['end'])) {
            $start_str = sprintf('%02d:00', max(0, min(23, $hours['start'])));
            $end_str   = sprintf('%02d:00', max(0, min(23, $hours['end'])));
        } else {
            $start_str = isset($hours['start']) ? (string)$hours['start'] : $start_str;
            $end_str   = isset($hours['end']) ? (string)$hours['end'] : $end_str;
        }

        // Parse HH:MM to minutes since midnight, with validation
        $start_min = $this->parse_time_to_minutes($start_str, 9 * 60);
        $end_min   = $this->parse_time_to_minutes($end_str, 17 * 60);

        // Current time (site timezone) as minutes since midnight
        $now_ts = current_time('timestamp');
        $cur_h  = intval(date_i18n('G', $now_ts));
        $cur_m  = intval(date_i18n('i', $now_ts));
        $now_min = ($cur_h * 60) + $cur_m;

        // If start == end treat as disabled window
        if ($start_min === $end_min) { return false; }
        if ($start_min < $end_min) {
            return ($now_min >= $start_min && $now_min < $end_min);
        }
        // Overnight window (e.g., 22:00 -> 06:00)
        return ($now_min >= $start_min || $now_min < $end_min);
    }

    protected function parse_time_to_minutes($value, $default) {
        if (!is_string($value)) { return $default; }
        if (!preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d$/', $value)) { return $default; }
        list($h, $m) = explode(':', $value, 2);
        return intval($h) * 60 + intval($m);
    }

    protected function sanitize_time_string($value, $fallback) {
        $val = trim((string)$value);
        if (preg_match('/^(?:[01]?\d|2[0-3]):[0-5]\d$/', $val)) {
            // Normalize to HH:MM
            list($h, $m) = explode(':', $val, 2);
            return sprintf('%02d:%02d', intval($h), intval($m));
        }
        // Accept pure hour like "9" or "17" and normalize
        if (preg_match('/^(?:[01]?\d|2[0-3])$/', $val)) {
            return sprintf('%02d:00', intval($val));
        }
        return $fallback;
    }

    // Render a proper HTML5 time input in WooCommerce settings
    public function generate_time_html($key, $data) {
        $defaults = [
            'title'             => '',
            'desc_tip'          => false,
            'description'       => '',
            'custom_attributes' => [],
            'placeholder'       => 'HH:MM',
            'default'           => '',
        ];
        $data = wp_parse_args($data, $defaults);
        $field_key = $this->get_field_key($key);
        $value = $this->get_option($key, $data['default']);
        $desc_parts = $this->build_field_description_parts($data);
        $attrs = $this->build_custom_attribute_html(array_merge(['step' => '60'], $data['custom_attributes']));

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?></label>
                <?php echo $desc_parts['tooltip_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </th>
            <td class="forminp">
                <input
                    type="time"
                    id="<?php echo esc_attr($field_key); ?>"
                    name="<?php echo esc_attr($field_key); ?>"
                    value="<?php echo esc_attr($value); ?>"
                    placeholder="<?php echo esc_attr($data['placeholder']); ?>"
                    <?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                />
                <?php echo $desc_parts['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    // Sanitize values for custom 'time' field type when saving
    public function validate_time_field($key, $value) {
        $default = ($key === 'start_time') ? '09:00' : '17:00';
        return $this->sanitize_time_string($value, $default);
    }

    // Local fallback: build attribute HTML from array
    protected function build_custom_attribute_html($attributes) {
        if (empty($attributes) || !is_array($attributes)) { return ''; }
        $pairs = [];
        foreach ($attributes as $attr => $val) {
            if ($val === true) {
                $pairs[] = sprintf('%s', esc_attr($attr));
            } elseif ($val !== false && $val !== null) {
                $pairs[] = sprintf('%s="%s"', esc_attr($attr), esc_attr((string)$val));
            }
        }
        return implode(' ', $pairs);
    }

    // Local fallback: build description and tooltip parts similar to WC_Settings_API
    protected function build_field_description_parts($data) {
        $tooltip_html = '';
        $description_html = '';
        $desc_tip = !empty($data['desc_tip']);
        $desc = isset($data['description']) ? (string)$data['description'] : '';
        if ($desc_tip && $desc) {
            if (function_exists('wc_help_tip')) {
                $tooltip_html = wc_help_tip($desc);
            } else {
                $tooltip_html = '<span class="woocommerce-help-tip" title="' . esc_attr($desc) . '">?</span>';
            }
        } elseif (!$desc_tip && $desc) {
            $description_html = '<p class="description">' . wp_kses_post($desc) . '</p>';
        }
        return [
            'tooltip_html' => $tooltip_html,
            'description'  => $description_html,
        ];
    }
    
}
