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
            'rules_json' => [
                'title'       => 'Pricing rules',
                'type'        => 'rules_table',
                'default'     => '[{"max_weight":3,"max_distance":5,"base_price":80,"price_per_km":25},{"max_weight":10,"max_distance":10,"base_price":150,"price_per_km":25},{"max_weight":300,"max_distance":25,"base_price":200,"price_per_km":25}]',
                'description' => 'Define rows of rules. The first matching rule (by weight and distance) will be used.'
            ],
        ];
    }

    public function calculate_shipping($package = []) {
        // Get current package metrics
        $weight   = floatval(WC()->cart ? WC()->cart->get_cart_contents_weight() : 0);
        $distance = isset($_COOKIE['delivery_distance']) ? floatval($_COOKIE['delivery_distance']) : 0.0;

        // Load pricing rules from settings (JSON)
        $default_json = '[{"max_weight":3,"max_distance":5,"base_price":80,"price_per_km":25},{"max_weight":10,"max_distance":10,"base_price":150,"price_per_km":25},{"max_weight":300,"max_distance":25,"base_price":200,"price_per_km":25}]';
        $rules_json = (string) $this->get_option('rules_json', $default_json);
        $rules = json_decode($rules_json, true);
        if (!is_array($rules)) {
            $rules = json_decode($default_json, true);
        }

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
    
    // Custom field renderer: 'rules_table'
    public function generate_rules_table_html($key, $data) {
        $defaults = [
            'title'       => '',
            'description' => '',
            'default'     => '[]',
        ];
        $data = wp_parse_args($data, $defaults);
        $field_key = $this->get_field_key($key);
        $default_json = (string) $data['default'];
        $json = (string) $this->get_option($key, $default_json);
        $rules = json_decode($json, true);
        if (!is_array($rules)) { $rules = json_decode($default_json, true); }
        if (!is_array($rules)) { $rules = []; }
        $desc_parts = $this->build_field_description_parts($data);

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?></label>
                <?php echo $desc_parts['tooltip_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </th>
            <td class="forminp">
                <input type="hidden" id="<?php echo esc_attr($field_key); ?>" name="<?php echo esc_attr($field_key); ?>" value="<?php echo esc_attr($json); ?>" />
                <table class="widefat wc_input_table" id="<?php echo esc_attr($field_key); ?>_table" style="max-width: 920px;">
                    <thead>
                        <tr>
                            <th>Max weight (kg)</th>
                            <th>Max distance (km)</th>
                            <th>Base price</th>
                            <th>Price per km</th>
                            <th style="width: 40px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rules)) : ?>
                            <tr class="no-items"><td colspan="5">No rules. Click "Add rule".</td></tr>
                        <?php else : foreach ($rules as $i => $r) : ?>
                            <tr>
                                <td><input type="number" step="0.001" min="0" class="small-text" value="<?php echo esc_attr($r['max_weight'] ?? ''); ?>" /></td>
                                <td><input type="number" step="0.001" min="0" class="small-text" value="<?php echo esc_attr($r['max_distance'] ?? ''); ?>" /></td>
                                <td><input type="number" step="0.01" min="0" class="small-text" value="<?php echo esc_attr($r['base_price'] ?? ''); ?>" /></td>
                                <td><input type="number" step="0.01" min="0" class="small-text" value="<?php echo esc_attr($r['price_per_km'] ?? ''); ?>" /></td>
                                <td><button type="button" class="button link-delete">Delete</button></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
                <p><button type="button" class="button" id="<?php echo esc_attr($field_key); ?>_add">Add rule</button></p>
                <?php echo $desc_parts['description']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <script>
                (function(){
                    const table = document.getElementById('<?php echo esc_js($field_key); ?>_table');
                    const hidden = document.getElementById('<?php echo esc_js($field_key); ?>');
                    const addBtn = document.getElementById('<?php echo esc_js($field_key); ?>_add');
                    function sync(){
                        const rows = Array.from(table.tBodies[0].rows).filter(r => !r.classList.contains('no-items'));
                        const data = rows.map(r => {
                            const inputs = r.querySelectorAll('input');
                            return {
                                max_weight: parseFloat(inputs[0].value||'') || 0,
                                max_distance: parseFloat(inputs[1].value||'') || 0,
                                base_price: parseFloat(inputs[2].value||'') || 0,
                                price_per_km: parseFloat(inputs[3].value||'') || 0,
                            };
                        });
                        hidden.value = JSON.stringify(data);
                    }
                    function addRow(values){
                        const tbody = table.tBodies[0];
                        const tr = document.createElement('tr');
                        tr.innerHTML = '<td><input type="number" step="0.001" min="0" class="small-text" /></td>'+
                                       '<td><input type="number" step="0.001" min="0" class="small-text" /></td>'+
                                       '<td><input type="number" step="0.01" min="0" class="small-text" /></td>'+
                                       '<td><input type="number" step="0.01" min="0" class="small-text" /></td>'+
                                       '<td><button type="button" class="button link-delete">Delete</button></td>';
                        tbody.appendChild(tr);
                        const inputs = tr.querySelectorAll('input');
                        if(values){
                          inputs[0].value = values.max_weight ?? '';
                          inputs[1].value = values.max_distance ?? '';
                          inputs[2].value = values.base_price ?? '';
                          inputs[3].value = values.price_per_km ?? '';
                        }
                        inputs.forEach(inp => inp.addEventListener('input', sync));
                        tr.querySelector('.link-delete').addEventListener('click', function(){
                            tr.remove();
                            if (!table.tBodies[0].rows.length) {
                                const empty = document.createElement('tr');
                                empty.className = 'no-items';
                                empty.innerHTML = '<td colspan="5">No rules. Click "Add rule".</td>';
                                table.tBodies[0].appendChild(empty);
                            }
                            sync();
                        });
                        sync();
                    }
                    addBtn.addEventListener('click', function(){
                        const empty = table.querySelector('tr.no-items');
                        if (empty) empty.remove();
                        addRow();
                    });
                    table.querySelectorAll('tbody tr').forEach(function(tr){
                        tr.querySelectorAll('input').forEach(inp => inp.addEventListener('input', sync));
                        const del = tr.querySelector('.link-delete');
                        if (del) del.addEventListener('click', function(){ tr.remove(); sync(); });
                    });
                    sync();
                })();
                </script>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    // Sanitize on save: ensure valid JSON array of objects
    public function validate_rules_table_field($key, $value) {
        $default = (string) ($this->form_fields[$key]['default'] ?? '[]');
        $arr = json_decode((string)$value, true);
        if (!is_array($arr)) {
            return $default;
        }
        // Normalize numeric fields
        $out = [];
        foreach ($arr as $r) {
            if (!is_array($r)) { continue; }
            $out[] = [
                'max_weight'    => isset($r['max_weight']) ? floatval($r['max_weight']) : 0,
                'max_distance'  => isset($r['max_distance']) ? floatval($r['max_distance']) : 0,
                'base_price'    => isset($r['base_price']) ? floatval($r['base_price']) : 0,
                'price_per_km'  => isset($r['price_per_km']) ? floatval($r['price_per_km']) : 0,
            ];
        }
        return wp_json_encode($out);
    }
    
}
