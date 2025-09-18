# PhantomViews – Virtual Tour Plugin

PhantomViews is a WordPress plugin crafted for real estate marketers who want to build immersive, interactive 360° virtual tours without leaving the WordPress dashboard. The plugin provides a drag-and-drop tour builder, manual hotspot controls, licensing for Pro features, and payment integration scaffolding for Paystack and Flutterwave.

## Key Features

- **360° Tour Builder** – Upload panoramas, structure multi-scene tours, and preview them directly in WordPress.
- **Manual Hotspots** – Create scene transitions, tooltips, media embeds, and external links with precise positional control.
- **Responsive Frontend Viewer** – A Panolens.js/Three.js powered viewer renders tours beautifully on desktop and mobile.
- **Licensing & Subscriptions** – Free vs. Pro feature gating with license activation, admin notices, and automated expiry management.
- **Paystack & Flutterwave Billing** – Secure checkout creation, webhook validation, and automatic license issuance straight from both gateways.
- **Shortcode Embedding** – Place `[phantomviews_tour id="123"]` inside posts, pages, or custom layouts.

## Getting Started

1. Copy the plugin directory into your WordPress installation under `wp-content/plugins/phantomviews`.
2. Activate **PhantomViews** from the WordPress Plugins screen.
3. Navigate to **PhantomViews → Settings** to configure Paystack/Flutterwave keys and pricing.
4. Visit **PhantomViews → Licensing** to activate a license and unlock Pro-specific functionality.
5. Create a new **Virtual Tour** post, upload 360° images, add scenes/hotspots, and publish.
6. Embed tours anywhere using the shortcode:

   ```php
   echo do_shortcode( '[phantomviews_tour id="123" width="100%" height="600px"]' );
   ```

## Development Notes

- Built for WordPress 5.8+ and PHP 7.4+.
- Uses the WordPress REST API to persist tour scenes and licensing actions.
- Hotspot and scene data are stored as structured post meta (`_phantomviews_scenes`).
- Frontend relies on Panolens.js delivered via CDN. Replace with self-hosted assets if required.
- Production-ready webhook endpoints validate signatures from Paystack and Flutterwave before issuing licenses.
- The admin React-like interface is powered by WordPress’ `wp.element`, `wp.components`, and `wp.apiFetch` packages.

## Security & Performance

- All REST endpoints require capability checks or are whitelisted for gateway callbacks.
- Inputs are sanitized before persisting to the database.
- Scene limits for free plans can be filtered via `phantomviews_free_scene_limit`.
- Viewer styles and scripts are optimized for responsive, touch-friendly experiences.

## Extensibility

- Hook into `phantomviews_license_activated`, `phantomviews_license_deactivated`, and `phantomviews_license_issued` to integrate custom flows.
- Filter `phantomviews_has_pro_license` to adjust Pro gating logic.
- Extend `Payment_Gateway_Manager` to support additional gateways or custom billing workflows.

## Roadmap Highlights

- Visual hotspot placement within the panorama preview.
- Advanced analytics and lead capture forms within tours.
- Gutenberg blocks and Elementor widgets for easier embedding.
- Deeper integration with CRM and IDX systems.

Contributions, feedback, and feature requests are welcome!
