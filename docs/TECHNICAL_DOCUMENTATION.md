# Shipping Plugin — Technical Documentation

## Overview
This plugin adds WooCommerce shipping methods, notably `Express_Delivery`, with flexible cost calculation and time-window availability.

Repository layout (relevant):
- `index.php` — Plugin bootstrap and admin wiring
- `includes/class-express-delivery.php` — Express method
- `includes/class-same-day-delivery.php` — Optional same-day method
- `includes/class-next-day-delivery.php` — Optional next-day method
- `includes/class-other-days-delivery.php` — Optional other-days method

## Express_Delivery class
File: `includes/class-express-delivery.php`

### Initialization
- `__construct()` sets identifiers and calls `init()`
- `init()` registers settings and loads saved options
  - Hooks: `woocommerce_update_options_shipping_express_delivery` → `process_admin_options`

### Settings (init_form_fields)
Fields:
- `enabled` (checkbox)
- `title` (text)
- `base_fee` (price)
- `per_kg` (price)
- `per_km` (price)
- `min_cost` (price)
- `max_cost` (price)
- `enable_time_window` (checkbox)
- `start_time` (time HH:MM)
- `end_time` (time HH:MM)

Custom admin field rendering:
- `generate_time_html($key, $data)` renders HTML5 `input[type=time]`
- Local fallbacks avoid dependency on older WC internals:
  - `build_custom_attribute_html(array $attributes)`
  - `build_field_description_parts(array $data)`
- Validation:
  - `validate_time_field($key, $value)` → `sanitize_time_string()`

### Availability
`is_available($package)`:
- Calls `parent::is_available($package)`
- If `enable_time_window` is disabled, returns true
- Reads `start_time`/`end_time`; if absent, falls back to legacy `start_hour`/`end_hour`
- Applies filter `sp_express_hours` allowing override
  - Supports integers 0–23 (hours) or strings `HH:MM`
- Converts times to minutes since midnight
- Uses site time (`current_time('timestamp')`) via WP timezone
- Window logic:
  - Equal start/end → unavailable
  - `start < end` → same‑day window
  - `start > end` → overnight window

### Cost calculation
`calculate_shipping($package)`:
- Pulls options: `base_fee`, `per_kg`, `per_km`, `min_cost`, `max_cost`
- Weight: `WC()->cart->get_cart_contents_weight()`
- Distance: from cookie `delivery_distance` (km), default `0.0`
- Cost = base + (weight × per_kg) + (distance × per_km)
- Applies min/max bounds
- Adds rate with id `${this->id}`, label `${this->title}`

### Helpers
- `parse_time_to_minutes($value, $default)` — HH:MM → minutes
- `sanitize_time_string($value, $fallback)` — normalize to HH:MM; accepts pure hour

## Backward compatibility
- If `start_time`/`end_time` are missing from saved settings, derive from legacy `start_hour`/`end_hour`.
- Admin UI uses local helpers instead of `WC_Settings_API::get_field_description()` and `::get_custom_attribute_html()`.

## Hooks & Filters
- Action: `woocommerce_update_options_shipping_express_delivery` → settings save
- Filter: `sp_express_hours`
  - Signature: array `['start' => 'HH:MM'|int, 'end' => 'HH:MM'|int]`
  - Example:
    ```php
    add_filter('sp_express_hours', function ($hours) {
        // Force a shorter window during weekends
        if (in_array((int) current_time('w'), [0, 6], true)) {
            return ['start' => '10:00', 'end' => '14:00'];
        }
        return $hours;
    });
    ```

## Extension points
- Customize the cost calculation by subclassing `Express_Delivery` and overriding `calculate_shipping()`; or hook earlier to set `delivery_distance`.
- Use the filter to alter availability hours dynamically (e.g., holidays, weekends).

## Error handling & Logging
- To debug availability, add temporary logging inside `is_available()` (e.g., `error_log()` start/end/now minutes) and remove afterward.
- Fatal in older WooCommerce caused by missing `get_field_description()` is avoided via local fallbacks.

## Constraints & Assumptions
- Distance input arrives via cookie `delivery_distance` in kilometers.
- Site timezone must reflect business timezone for accurate window checks.
- Products must require shipping for methods to appear.

## Versioning & Deployment
- Commit changes to the repo and deploy to the WordPress environment.
- Clear OPCache/object cache after deployment if enabled.

## Security
- Do not trust external inputs for pricing without validation. Cookie-based distance is assumed to be set by trusted UI logic.

## Files of interest
- `includes/class-express-delivery.php` — primary logic
- `index.php` — registers settings screens and hooks

## Code Samples

### Override availability hours via filter
```php
// functions.php or a small mu-plugin
add_filter('sp_express_hours', function ($hours) {
    // Example: shorter window on weekends
    $weekday = (int) current_time('w'); // 0=Sun, 6=Sat in site timezone
    if (in_array($weekday, [0, 6], true)) {
        return ['start' => '10:00', 'end' => '14:00'];
    }
    return $hours; // keep configured values
});
```

### Set the delivery_distance cookie (km)
```html
<!-- Example: set cookie after user selects address and distance is computed -->
<script>
  function setDeliveryDistance(km) {
    const days = 1;
    const expires = new Date(Date.now() + days*24*60*60*1000).toUTCString();
    document.cookie = `delivery_distance=${encodeURIComponent(km)}; path=/; expires=${expires}`;
  }
  // setDeliveryDistance(7.8);
</script>
```

### Customize cost calculation (advanced)
```php
// Create a child class and load it instead of the default one
class My_Express_Delivery extends Express_Delivery {
    public function calculate_shipping($package = []) {
        parent::calculate_shipping($package); // get the base rate first

        // Example: add surge pricing during peak hours
        $now = current_time('timestamp');
        $hour = (int) date_i18n('G', $now);
        $is_peak = ($hour >= 12 && $hour < 14);
        if ($is_peak) {
            foreach ($this->rates as $rate_id => $rate) {
                $rate['cost'] = round($rate['cost'] * 1.15, 2);
                $this->rates[$rate_id] = $rate;
            }
        }
    }
}
```

### Temporary logging for availability debugging
```php
// In Express_Delivery::is_available($package), add temporarily:
error_log('Express check: start_min=' . $start_min . ', end_min=' . $end_min . ', now_min=' . $now_min);
// Remember to remove after verifying logs to avoid noise.
```
