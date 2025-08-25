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
            'base_fee' => [
                'title' => 'Base fee',
                'type' => 'price',
                'default' => '75',
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

        $rate = [
            'id'    => $this->id,
            'label' => $this->title,
            'cost'  => $cost,
        ];
        $this->add_rate($rate);
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

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field_key); ?>"><?php echo esc_html($data['title']); ?></label>
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
                <?php if (!empty($data['description'])) : ?><p class="description"><?php echo wp_kses_post($data['description']); ?></p><?php endif; ?>
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
