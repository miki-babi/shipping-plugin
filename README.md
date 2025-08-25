# Shipping Plugin

WooCommerce shipping methods with flexible pricing and Express Delivery time windows.

- docs/USER_MANUAL.md — End‑user setup and troubleshooting
- docs/TECHNICAL_DOCUMENTATION.md — Architecture, hooks, and extension points

## Features
- Express Delivery with time window (supports overnight windows)
- Pricing: base fee + per kg + per km, with min/max bounds
- Backward compatibility for legacy hour settings

## Quick Start
1. Install and activate the plugin in WordPress.
2. Add the shipping method to the relevant Shipping Zone.
3. Configure Express settings (base fee, per‑kg, per‑km, time window).
4. Optionally set `delivery_distance` cookie (km) to enable per‑km pricing.

## Docs
See `docs/` for detailed user and technical documentation.

## Support
When reporting issues, include:
- WordPress + WooCommerce versions
- Plugin version/commit hash
- Shipping Zone and Express settings screenshots
- Any PHP error logs
