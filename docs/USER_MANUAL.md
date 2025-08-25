# Shipping Plugin — End-User Manual

## Overview
The Shipping Plugin extends WooCommerce with multiple shipping methods, including Express Delivery with a configurable time window and pricing based on weight and distance.

Key features:
- Express Delivery with time window (supports overnight, e.g., 22:00–06:00)
- Pricing: base fee + per kg + per km, with optional min/max caps
- Backward compatibility with legacy hour settings

## Requirements
- WordPress + WooCommerce (active)
- PHP compatible with your WooCommerce version
- WordPress site timezone set correctly (Settings > General > Timezone)

## Installation
1. Upload the plugin to `wp-content/plugins/` or via Admin > Plugins > Add New > Upload.
2. Activate the plugin in Admin > Plugins.

## Setup
### 1) Add shipping method to a zone
- WooCommerce > Settings > Shipping > Shipping zones
- Edit the relevant zone
- Add “Express Delivery” (and any other methods) and enable them

### 2) Configure Express Delivery
WooCommerce > Settings > Shipping > Express Delivery

Fields:
- Enabled — turn the method on/off
- Title — label shown at checkout
- Base fee — fixed component
- Cost per kg — multiplied by total cart weight
- Cost per km — multiplied by distance (see “Distance”)
- Minimum cost — lower bound for total
- Maximum cost — upper bound (leave empty for no cap)
- Limit by time window — only show Express during specific hours
- Start time (HH:MM) — when Express becomes available
- End time (HH:MM) — when Express stops being available

Time window behavior:
- If Start == End, Express is disabled
- If Start < End, same‑day window (e.g., 09:00–17:00)
- If Start > End, overnight window (e.g., 22:00–06:00)
- Time uses site timezone (Settings > General)

### 3) Distance
The plugin reads distance (km) from a cookie named `delivery_distance`. If not set, 0 km is used. Ensure your theme or a separate script sets this cookie if you charge per km.

## Pricing formula
Displayed price = Base fee + (Cart Weight × Cost per kg) + (Distance × Cost per km)
- Enforced bounds:
  - If result < Minimum cost, use Minimum cost
  - If Maximum cost is set and result > Maximum, use Maximum

## When Express shows at checkout
Express appears when:
- It’s added and enabled in the matching Shipping Zone
- The cart requires shipping (not purely virtual)
- The current time is within the configured time window (if enabled)
- All WooCommerce availability checks pass for the zone/destination

## Troubleshooting
- Express doesn’t appear:
  - Ensure it’s added to the active Shipping Zone for the address
  - Ensure products require shipping
  - Check timezone and confirm current time falls within Start/End
  - Temporarily disable “Limit by time window”; if it appears, the time window was blocking it
  - Verify no theme/plugin filter overrides the window
- Prices look wrong:
  - Confirm cart weight units in WooCommerce settings
  - Ensure `delivery_distance` cookie is set (km)
  - Check min/max cost caps
- Overnight window:
  - Use Start > End to span midnight (e.g., 22:00–06:00)

## FAQ
- Which timezone is used? Site timezone from WordPress Settings.
- Can I override the hours via code? Yes; developers can use a filter (see Technical Docs).
- Do I need Maps? Not required. You only need to set the `delivery_distance` cookie if you bill per km.

## Support info to collect
- WooCommerce version, WordPress version
- Plugin version/commit
- Shipping Zone config screenshot
- Express settings screenshot
- PHP error log snippet if there’s an error
