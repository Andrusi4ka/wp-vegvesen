# Bruktdeler – Vegvesen Vehicle Lookup

WordPress plugin that looks up vehicles in the Statens vegvesen (Norwegian Public Roads Administration) register by registration number or VIN, and shows the technical data on your site.

Built for a WoodMart / WPBakery site, but the logic lives in the plugin, so it works with any theme.

## Features

- Search field (`[bv_search]`): inline, hero banner or compact (for the header).
- Ready-made vehicle card (`[bv_card]`): number plate, status, picture, key figure tiles and tables.
- Variables for your own layouts:
  - shortcodes for text blocks: `[bv field="merke"]`;
  - tokens for one-line fields (titles, buttons, list items): `{bv:merke}`, `{bv:egenvekt|unit}`.
- Conditions `[bv_if …]` and visibility classes (`bv-if-found`, `bv-if-notfound`, `bv-if-empty`, `bv-if-searched`).
- WPBakery elements (category "Vegvesen") with Design Options.
- Follows the theme's colours and fonts through CSS variables (WoodMart `--wd-*`, with fallbacks).
- Templates can be overridden in the theme: `your-theme/bruktdeler-vegvesen/*.php`.
- Response cache, per-IP rate limit, the API key never reaches the browser.
- English source with Norwegian bokmål translation.

## Setup

1. Get an API key from Statens vegvesen ("API for tekniske kjøretøyopplysninger").
2. Add it to `wp-config.php`:
   ```php
   define( 'BV_API_KEY', 'your-key' );
   ```
3. Activate the plugin and go to **Vegvesen → Settings**: turn off demo mode and choose the result page.
4. **Vegvesen → Variables** lists every field with a live test lookup.

The Vegvesen API allows 50,000 requests per API key per day. Responses are cached (24 h by default) to save the quota. Owner data is not available through the API.

## Requirements

WordPress 6.5+, PHP 7.4+.
