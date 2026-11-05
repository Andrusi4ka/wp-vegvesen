=== WP Vegvesen ===
Tags: vegvesen, vehicle, registration number, car parts, wpbakery
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Vehicle lookup in Statens vegvesen by registration number or VIN, with ready-made blocks, variables and WPBakery elements that follow the theme's styling.

== Description ==

WP Vegvesen looks up vehicles in the Statens vegvesen (Norwegian Public Roads Administration) register by registration number or VIN and shows the technical data on your site. It is made by [Relevant AS](https://relevant.no).

Built for a WoodMart / WPBakery site, but the logic lives in the plugin, so it works with any theme.

* Search field `[bv_search]`: inline, hero banner or compact (for the header).
* Ready-made vehicle card `[bv_card]`: number plate, status, picture, key figure tiles and tables.
* Vehicle picture `[bv_image]`: your own photo from the media library, otherwise a silhouette of the body type (sedan, hatchback, estate, van, truck, bus…) in the vehicle's colour from Vegvesen.
* Variables for your own layouts: shortcodes such as `[bv field="merke"]` and tokens such as `{bv:merke}` or `{bv:egenvekt|unit}` for titles, buttons and list items.
* Conditions `[bv_if …]` and visibility classes (`bv-if-found`, `bv-if-notfound`, `bv-if-empty`, `bv-if-searched`).
* WPBakery elements (category "Vegvesen") with Design Options.
* Follows the theme's colours and fonts through CSS variables (WoodMart `--wd-*`, with fallbacks).
* Brand logos `[bv_logo]` and `{bv:merke_logo}`: 387 logos bundled; your own uploads take priority.
* Templates can be overridden in the theme: `your-theme/bruktdeler-vegvesen/*.php`.
* Response cache, per-IP rate limit, and the API key never reaches the browser.
* English, with a Norwegian bokmål translation.

Bundles [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) 5 (MIT) and icons from [Lucide](https://lucide.dev) (ISC).

== Installation ==

1. Download `wp-vegvesen.zip` from the latest release on https://github.com/Andrusi4ka/wp-vegvesen/releases.
2. In WordPress go to Plugins > Add New > Upload Plugin and upload the zip, then activate it.
3. Get an API key from Statens vegvesen ("API for tekniske kjøretøyopplysninger") and add it to `wp-config.php`: `define( 'BV_API_KEY', 'your-key' );`
4. Go to WP Vegvesen > Settings: turn off demo mode and choose the result page.
5. WP Vegvesen > Variables lists every field with a live test lookup.

Later versions are offered under Plugins like any other update.

== Frequently Asked Questions ==

= Where do updates come from? =

From this plugin's GitHub releases. The plugin header's "Update URI" stops WordPress from offering a wordpress.org plugin that happens to share the folder name.

= What is removed when I delete the plugin? =

Deleting it under Plugins removes its settings (the API key saved there too), the cached Vegvesen answers and the update check data. The "Vehicle" result page is removed only if it is still exactly as the plugin created it; once you have edited it, it stays. Your own files in `wp-content/uploads/bv-logos` and `bv-bodies`, images in the media library and an API key in `wp-config.php` are kept. Deactivating or updating the plugin removes nothing.

= How many lookups can I make? =

The Vegvesen API allows 50,000 requests per API key per day. Responses are cached (24 hours by default) to save the quota.

= Can it show the owner of a vehicle? =

No. Owner data is not available through the Vegvesen API.

= Why is a car shown as a silhouette and not a photo? =

Vegvesen has no photos. Upload a photo to the media library and give it the title "Make Model" (for example "Volkswagen Passat") or just "Make", and it is shown instead. The silhouette is picked from the body type code and painted in the registered colour.

= Can I draw my own silhouettes? =

Yes. Put an SVG file in `wp-content/uploads/bv-bodies/`, named after the body type (`sedan.svg`, `hatchback.svg`, `estate.svg`, `coupe.svg`, `convertible.svg`, `mpv.svg`, `pickup.svg`, `pickup-double.svg`, `van.svg`, `truck.svg`, `tractor.svg`, `bus.svg`, `camper.svg`, `trailer.svg`). It replaces the bundled one and survives plugin updates.

== Changelog ==

= 1.5.0 =
* Next EU inspection: a status icon to the left of the date – a check when it is fine, a warning sign when the deadline is close, a cross when it has passed.
* A button to the left of the icon when the deadline is close or passed, e.g. to a contact form. Set in the elements (tab "EU inspection" of the Vehicle card and Field table): when to show it, title, link, custom attributes and a CSS class (to trigger a popup or script). `{regnr}` in the link is replaced with the registration number (`/kontakt/?regnr={regnr}`). Without a link the button is still shown and works as a button for scripts; the registration number is in its `data-regnr` attribute.
* With WoodMart the button is the theme's own Button element, with the same style settings: style, shape, size, predefined colour, background and text colours (idle and hover) and typography.
* `[bv_eu_button]` shortcode and WPBakery element to place the button anywhere.
* Printing (the Print button or Ctrl+P) prints only the vehicle information, in a compact layout that fits one A4 page. Mark your own blocks with the class `bv-print-area` to print those instead.

= 1.4.2 =
* "What's new" after an update: a window with the changes since the version you used before opens on the WP Vegvesen screens, and the Dashboard shows a notice that opens it. Once for each user who can see WP Vegvesen; not after a first install.

= 1.4.1 =
* Settings: sections in boxes with a short overview on top (API key, live or demo data, result page, requests today); the API address is folded under "Advanced"; the Save button stays in view; WordPress's colour picker for the primary colour.
* Access: choose which user roles see WP Vegvesen (administrators always). Only administrators can change it.
* Variables: the icon of each field, changeable with one click (70 icons, Lucide set added) or removed; click a shortcode or token to copy it, with a "Copied" bubble (also on sites without https).
* User guide (PDF) in English and Norwegian, linked next to the page heading in the admin language.
* Title tags: choose h1–h6, p, div or span for the titles of the search field, the vehicle card and the tables (SEO).
* Search limit behind Cloudflare or a proxy: choose where the visitor's real IP comes from.
* Daily limit of requests to Vegvesen (45,000 by default) with a notice in the admin when searches stop; search engines and scripts no longer use the quota; vehicle pages are kept out of robots.txt.
* Page caches (WP Rocket, LiteSpeed, W3 Total Cache, WP Super Cache, CDNs) no longer keep pages with an error, "not found" or demo data.
* Deleting the plugin removes its settings and cached data (an edited result page is kept).
* Vehicle card without its own frame and padding; tiles and tables line up.
* Fixed: visibility classes hid blocks inside page builders; the search limit never reset for steady traffic; a maintenance page from the API counted as "not found"; renaming the result page broke vehicle addresses; the search form lost the result page with plain permalinks; an empty tiles box; brand logos were looked up on every page view.

= 1.4.0 =
* The plugin is now called WP Vegvesen, by Relevant AS.
* Updates from GitHub: new versions show up under Plugins with "View details", and install with one click.
* Vehicle picture: without a photo, a silhouette of the body type (14 kinds: sedan, hatchback, estate, coupé, convertible, MPV, pickup, double cab, van, truck, tractor unit, bus, camper, trailer) in the vehicle's colour, with dark tyres and an outline for light colours. Picked from the Vegvesen body code, otherwise from the vehicle category.
* New fields: `karosseri_kode` (Vegvesen body type code) and `farge_kode` (Vegvesen colour code).
* The colour swatch follows the Vegvesen colour code; turquoise added.

= 1.3.0 =
* Brand logos: `[bv_logo]` shortcode, WPBakery element, `{bv:merke_logo}` token and the logo in the vehicle card. 387 logos bundled; a media library image titled "Make logo" or a file in `uploads/bv-logos` takes priority.
* After a search the page scrolls smoothly to the result, with an offset for sticky headers. New settings: on/off and offset.
* A message under the search field when nothing is found, the number is invalid or the API fails.
* Search button hover darkens the primary colour instead of turning grey.

= 1.1.1 =
* First version: search field, vehicle card and building blocks, shortcode and token variables, conditions and visibility classes, WPBakery elements, cache, per-IP rate limit, Norwegian bokmål translation.
