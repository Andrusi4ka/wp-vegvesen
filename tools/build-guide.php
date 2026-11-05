<?php
/**
 * Builds the user guide (assets/docs/wp-vegvesen-guide-{en,nb}.html), which tools/build-guide.sh
 * prints to PDF with Chrome. The field list and example values come from the plugin itself
 * (demo vehicle), so the guide follows the fields. Not part of the release zip.
 *
 * Usage: php tools/build-guide.php /path/to/wordpress
 */

if ( PHP_SAPI !== 'cli' ) {
	exit;
}
$bv_wp_path = rtrim( $argv[1] ?? '', '/\\' );
if ( ! $bv_wp_path || ! is_file( $bv_wp_path . '/wp-load.php' ) ) {
	fwrite( STDERR, "Usage: php tools/build-guide.php /path/to/wordpress\n" );
	exit( 1 );
}
$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
require $bv_wp_path . '/wp-load.php';

// Demo mode for the examples below.
add_filter( 'option_' . BV_Settings::OPTION, function ( $v ) {
	$v = (array) $v;
	$v['demo_mode'] = 1;
	return $v;
} );

$nb_messages = ( include BV_DIR . 'languages/bruktdeler-vegvesen-nb_NO.l10n.php' )['messages'] ?? array();
$out_dir     = BV_DIR . 'assets/docs/';
if ( ! is_dir( $out_dir ) ) {
	mkdir( $out_dir, 0755, true );
}

$h = function ( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
};
$c = function ( $s ) use ( $h ) {
	return '<code>' . $h( $s ) . '</code>';
};

foreach ( array( 'en', 'nb' ) as $lang ) {
	// The plugin's own translation and number style, so examples read as on a Norwegian site
	// (1 615 kg, 21.03.2027, hk, Godkjent). The site language does not matter.
	unload_textdomain( 'bruktdeler-vegvesen' );
	if ( 'nb' === $lang ) {
		load_textdomain( 'bruktdeler-vegvesen', BV_DIR . 'languages/bruktdeler-vegvesen-nb_NO.mo' );
		$GLOBALS['wp_locale']->number_format = array( 'thousands_sep' => "\xC2\xA0", 'decimal_point' => ',' );
	} else {
		$GLOBALS['wp_locale']->number_format = array( 'thousands_sep' => ',', 'decimal_point' => '.' );
	}
	$fields_cache = new ReflectionProperty( 'BV_Fields', 'fields' );
	$fields_cache->setAccessible( true );
	$fields_cache->setValue( null, null );
	// Example values from the bundled demo vehicle, never from a real number plate.
	$demo = BV_API::lookup( 'AB12345', array( 'rate_limit' => false ) );

	$t = guide_texts( $lang );
	$tr = function ( $s ) use ( $lang, $nb_messages ) {
		return 'nb' === $lang && isset( $nb_messages[ $s ] ) && '' !== $nb_messages[ $s ] ? $nb_messages[ $s ] : $s;
	};

	// Field reference, grouped as on the Variables page.
	$groups = array();
	foreach ( BV_Fields::all() as $key => $f ) {
		$groups[ $f['group'] ][ $key ] = $f;
	}
	$rows = '';
	foreach ( $groups as $group => $fields ) {
		$rows .= '<tr class="grp"><th colspan="3">' . $h( $tr( $group ) ) . '</th></tr>';
		foreach ( $fields as $key => $f ) {
			$example = 'merke_logo' === $key ? '(URL)' : BV_Render::value( $demo, $key, array( 'unit' => 1 ) );
			if ( 'status' === $key ) {
				$example = 'funnet';
			}
			$rows .= '<tr><td class="key">' . $c( $key ) . '</td><td><strong>' . $h( $tr( $f['label'] ) ) . '</strong><br><span class="muted">' . $h( $tr( $f['desc'] ?? '' ) ) . '</span></td><td>' . $h( $example ) . '</td></tr>';
		}
	}

	$html = strtr( guide_template(), array(
		'{{lang}}'      => $lang,
		'{{version}}'   => $h( BV_VERSION ),
		'{{fields}}'    => $rows,
	) );
	// Translated text: {{t:key}} (HTML allowed in the texts below, they are ours).
	$html = preg_replace_callback( '/\{\{t:([a-z0-9_]+)\}\}/', function ( $m ) use ( $t ) {
		return $t[ $m[1] ] ?? '[' . $m[1] . ']';
	}, $html );
	file_put_contents( $out_dir . 'wp-vegvesen-guide-' . $lang . '.html', $html );
	echo "assets/docs/wp-vegvesen-guide-$lang.html\n";
}

/** The page layout (A4, printed by Chrome). */
function guide_template() {
	return <<<'HTML'
<!doctype html>
<html lang="{{lang}}">
<head>
<meta charset="utf-8">
<title>{{t:doc_title}}</title>
<style>
@page { size: A4; margin: 18mm 16mm 20mm;
  @bottom-left { content: "WP Vegvesen · {{t:doc_short}}"; font: 8.5pt "Segoe UI", Arial, sans-serif; color: #7a8189; }
  @bottom-right { content: counter(page) " / " counter(pages); font: 8.5pt "Segoe UI", Arial, sans-serif; color: #7a8189; }
}
@page :first { @bottom-left { content: none; } @bottom-right { content: none; } }
:root { --accent: #0b6e3a; --ink: #16191c; --text: #3d4349; --muted: #6b7178; --line: #dfe3e6; --soft: #f3f6f4; }
* { box-sizing: border-box; }
html { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { margin: 0; font: 10pt/1.5 "Segoe UI", Arial, sans-serif; color: var(--text); }
h1, h2, h3 { color: var(--ink); line-height: 1.2; break-after: avoid; }
h2 { font-size: 16pt; margin: 0 0 4mm; padding-top: 2mm; }
h2 .n { color: var(--accent); margin-right: 6px; }
h3 { font-size: 11.5pt; margin: 6mm 0 2mm; }
p { margin: 0 0 3mm; }
section { margin-bottom: 8mm; }
.page { break-before: page; }
code { font: 9pt Consolas, "Courier New", monospace; background: #eef1f3; color: #0f3d24; padding: 0.5px 4px; border-radius: 3px; white-space: nowrap; }
pre { font: 9pt/1.5 Consolas, "Courier New", monospace; background: #f6f8f9; border: 1px solid var(--line); border-left: 3px solid var(--accent); border-radius: 4px; padding: 3mm 4mm; margin: 0 0 4mm; white-space: pre-wrap; break-inside: avoid; color: #0f3d24; }
table { width: 100%; border-collapse: collapse; margin: 0 0 4mm; font-size: 9.2pt; }
th, td { text-align: left; vertical-align: top; padding: 2mm 2.5mm; border-bottom: 1px solid var(--line); }
thead th { background: var(--ink); color: #fff; font-weight: 600; }
tr { break-inside: avoid; }
td code { white-space: normal; word-break: break-word; }
.grp th { background: var(--soft); color: var(--accent); font-size: 9.5pt; padding-top: 2.5mm; }
.key { width: 30%; }
.muted { color: var(--muted); font-size: 8.8pt; }
.cols { display: grid; grid-template-columns: 1fr 1fr; gap: 5mm; }
.box { border: 1px solid var(--line); border-radius: 6px; padding: 4mm 5mm; break-inside: avoid; }
.box h3 { margin-top: 0; }
.tip { background: var(--soft); border-radius: 6px; padding: 3mm 5mm; margin: 0 0 4mm; break-inside: avoid; }
.tip strong { color: var(--accent); }
.cover { height: 250mm; display: flex; flex-direction: column; justify-content: space-between; }
.cover .brand { font-size: 11pt; font-weight: 600; color: var(--accent); letter-spacing: .5px; text-transform: uppercase; }
.cover h1 { font-size: 34pt; margin: 40mm 0 4mm; }
.cover .lead { font-size: 14pt; color: var(--muted); max-width: 140mm; }
.cover .meta { border-top: 2px solid var(--accent); padding-top: 4mm; color: var(--muted); font-size: 9.5pt; }
.toc { list-style: none; padding: 0; margin: 10mm 0 0; columns: 2; column-gap: 10mm; font-size: 10.5pt; }
.toc li { margin-bottom: 2mm; }
.toc b { color: var(--accent); display: inline-block; width: 7mm; }
</style>
</head>
<body>

<div class="cover">
  <div>
    <div class="brand">WP Vegvesen</div>
    <h1>{{t:doc_title}}</h1>
    <p class="lead">{{t:lead}}</p>
    <ol class="toc">
      <li><b>1</b>{{t:s1}}</li><li><b>2</b>{{t:s2}}</li><li><b>3</b>{{t:s3}}</li><li><b>4</b>{{t:s4}}</li>
      <li><b>5</b>{{t:s5}}</li><li><b>6</b>{{t:s6}}</li><li><b>7</b>{{t:s7}}</li><li><b>8</b>{{t:s8}}</li><li><b>9</b>{{t:s9}}</li>
    </ol>
  </div>
  <div class="meta">{{t:meta}} {{version}} · Relevant AS · relevant.no</div>
</div>

<section class="page">
  <h2><span class="n">1</span>{{t:s1}}</h2>
  {{t:how}}
</section>

<section>
  <h2><span class="n">2</span>{{t:s2}}</h2>
  {{t:two_ways}}
</section>

<section>
  <h2><span class="n">3</span>{{t:s3}}</h2>
  {{t:shortcode}}
</section>

<section>
  <h2><span class="n">4</span>{{t:s4}}</h2>
  {{t:tokens}}
</section>

<section>
  <h2><span class="n">5</span>{{t:s5}}</h2>
  {{t:conditions}}
</section>

<section>
  <h2><span class="n">6</span>{{t:s6}}</h2>
  {{t:classes}}
</section>

<section>
  <h2><span class="n">7</span>{{t:s7}}</h2>
  {{t:blocks}}
</section>

<section>
  <h2><span class="n">8</span>{{t:s8}}</h2>
  {{t:recipes}}
  {{t:tips}}
</section>

<section class="page">
  <h2><span class="n">9</span>{{t:s9}}</h2>
  <p>{{t:fields_intro}}</p>
  <table>
    <thead><tr><th>{{t:col_key}}</th><th>{{t:col_field}}</th><th>{{t:col_example}}</th></tr></thead>
    <tbody>{{fields}}</tbody>
  </table>
</section>

</body>
</html>
HTML;
}

/** All texts of the guide, English and Norwegian bokmål. */
function guide_texts( $lang ) {
	$en = array(
		'doc_title'    => 'Guide to variables',
		'doc_short'    => 'Guide to variables',
		'lead'         => 'How to show vehicle data from Statens vegvesen on your pages: shortcodes, tokens, conditions, visibility classes and ready-made blocks.',
		'meta'         => 'Version',
		's1' => 'How it works', 's2' => 'Shortcode or token?', 's3' => 'Shortcode [bv]', 's4' => 'Tokens {bv:…}',
		's5' => 'Conditions [bv_if]', 's6' => 'Visibility classes', 's7' => 'Ready-made blocks', 's8' => 'Examples and tips', 's9' => 'All fields',
		'how' => '
<p>A <strong>variable</strong> is a placeholder in a page that the plugin replaces with data about one vehicle: the make, the weight, the date of the next EU inspection and so on.</p>
<p>Which vehicle? The one whose number is in the page address. After a search the visitor lands on the result page:</p>
<pre>https://your-site.no/vehicle/AB12345/
https://your-site.no/any-page/?regnr=AB12345</pre>
<p>Every variable on that page shows the vehicle <code>AB12345</code>. The plugin asks Vegvesen once per page and keeps the answer (24 hours by default), so many variables cost no extra requests.</p>
<div class="cols">
  <div class="box"><h3>The result page</h3><p>Chosen under <strong>WP Vegvesen → Settings → Result page</strong>. Build it in any page builder: put the search field at the top and the variables or ready-made blocks below.</p></div>
  <div class="box"><h3>Designing with real data</h3><p>Inside the page builder there is no number in the address. The plugin then shows the <strong>preview number for editors</strong> from Settings, so you see real values while you work. Visitors never see it.</p></div>
</div>
<div class="tip"><strong>Test first.</strong> <strong>WP Vegvesen → Variables</strong> lists every field with its value for a test number. Click a shortcode or token there to copy it.</div>',
		'two_ways' => '
<p>There are two ways to write the same variable. They show the same value; what differs is where they work.</p>
<table>
<thead><tr><th style="width:24%">Write</th><th>Use it in</th><th style="width:28%">Example</th></tr></thead>
<tbody>
<tr><td><code>[bv field="merke"]</code><br><span class="muted">shortcode</span></td><td>Large text fields with an editor: WPBakery Text Block, Info Box content, HTML Blocks, Gutenberg paragraphs.</td><td>Spare parts for [bv field="merke"]</td></tr>
<tr><td><code>{bv:merke}</code><br><span class="muted">token</span></td><td>One-line fields where a shortcode is not run: WoodMart Title, Info Box title, button text, list items, the page title. Tokens also work in text blocks.</td><td>Parts for {bv:merke_modell}</td></tr>
</tbody></table>
<div class="tip"><strong>Rule of thumb:</strong> if you typed a shortcode and see it on the site as plain text <code>[bv field="…"]</code>, that field does not run shortcodes. Use the token instead.</div>
<p>In the editor (admin) tokens stay as you wrote them; they are replaced on the site.</p>',
		'shortcode' => '
<p>The shortcode prints one value. <code>field</code> is the field key from the list in section 9.</p>
<pre>[bv field="egenvekt" unit="1"]                → 1,615 kg
[bv field="neste_eu" format="j M Y"]          → 21 Mar 2027
[bv field="merke" before="Make: "]            → Make: Volkswagen
[bv field="co2" suffix=" g/km" empty="—"]     → 118 g/km, or — when empty</pre>
<h3>Attributes</h3>
<table>
<thead><tr><th style="width:26%">Attribute</th><th>What it does</th></tr></thead>
<tbody>
<tr><td><code>field="merke"</code></td><td>Which field to show (required).</td></tr>
<tr><td><code>unit="1"</code></td><td>Adds the field\'s standard unit: kg, mm, kW, g/km, km…</td></tr>
<tr><td><code>format="d.m.Y"</code></td><td>Date format for date fields (see the table below). Without it: the site\'s default date format.</td></tr>
<tr><td><code>before="Make: "</code></td><td>Text in front of the value. Not shown when the value is empty.</td></tr>
<tr><td><code>suffix=" kg"</code> / <code>after</code></td><td>Text after the value. Not shown when the value is empty.</td></tr>
<tr><td><code>empty="—"</code></td><td>What to show when Vegvesen has no data for this field. Default: nothing.</td></tr>
<tr><td><code>number="0"</code></td><td>Number without thousands separator: 1615 instead of 1,615.</td></tr>
<tr><td><code>decimals="1"</code></td><td>Number of decimals for numbers (5.2 instead of 5).</td></tr>
<tr><td><code>regnr="AB12345"</code></td><td>Always show this vehicle instead of the one in the address.</td></tr>
<tr><td><code>el_class</code></td><td>CSS class; the value is then wrapped in <code>&lt;span class="bv-field …"&gt;</code>.</td></tr>
</tbody></table>
<h3>Date formats</h3>
<table>
<thead><tr><th style="width:26%">Format</th><th>Result (21 March 2027)</th></tr></thead>
<tbody>
<tr><td><code>d.m.Y</code></td><td>21.03.2027</td></tr>
<tr><td><code>d/m/Y</code></td><td>21/03/2027</td></tr>
<tr><td><code>j M Y</code></td><td>21 Mar 2027</td></tr>
<tr><td><code>j F Y</code></td><td>21 March 2027</td></tr>
<tr><td><code>Y</code></td><td>2027</td></tr>
</tbody></table>
<p class="muted">Letters: d = day with zero, j = day, m = month number, M = short month name, F = full month name, Y = year. Month names follow the site language.</p>
<p>A field\'s <strong>label</strong> (for your own tables) comes from <code>[bv_label field="egenvekt"]</code> → <em>Kerb weight</em>.</p>',
		'tokens' => '
<pre>{bv:merke}                → Volkswagen
{bv:merke_modell}         → Volkswagen Passat
{bv:egenvekt|unit}        → 1,615 kg
{bv:neste_eu|j M Y}       → 21 Mar 2027
{bv:label:egenvekt}       → Kerb weight</pre>
<table>
<thead><tr><th style="width:30%">Form</th><th>Meaning</th></tr></thead>
<tbody>
<tr><td><code>{bv:field}</code></td><td>The value, as <code>[bv field="field"]</code>.</td></tr>
<tr><td><code>{bv:field|unit}</code></td><td>The value with its unit.</td></tr>
<tr><td><code>{bv:field|j M Y}</code></td><td>Anything else after <code>|</code> is a date format.</td></tr>
<tr><td><code>{bv:label:field}</code></td><td>The field\'s label instead of its value.</td></tr>
<tr><td><code>{bv:status}</code></td><td>The search status: funnet, ikke_funnet, ugyldig, feil or ingen.</td></tr>
</tbody></table>
<div class="tip"><strong>Note:</strong> tokens have no <code>empty</code> or <code>before</code> option. If a field may be empty, put the line inside a condition (section 5) or use the shortcode.</div>',
		'conditions' => '
<p><code>[bv_if]</code> shows its content only when a condition holds. Everything inside it (text, shortcodes, tokens) is hidden otherwise.</p>
<table>
<thead><tr><th style="width:48%">Condition</th><th>Shows the content when…</th></tr></thead>
<tbody>
<tr><td><code>[bv_if field="co2"]…[/bv_if]</code></td><td>the field has a value</td></tr>
<tr><td><code>[bv_if field="co2" empty="1"]…[/bv_if]</code></td><td>the field is empty</td></tr>
<tr><td><code>[bv_if field="eu_status" is="utlopt,snart"]…[/bv_if]</code></td><td>the value is one of the list (comma separated)</td></tr>
<tr><td><code>[bv_if field="drivstoff" not="Elektrisk"]…[/bv_if]</code></td><td>the field has a value that is not in the list</td></tr>
<tr><td><code>[bv_if status="funnet"]…[/bv_if]</code></td><td>the search has this status</td></tr>
</tbody></table>
<p>Comparing with <code>is</code> and <code>not</code> ignores upper and lower case. Values such as fuel, colour or body type come from Vegvesen in Norwegian (Diesel, Elektrisk, Svart, Stasjonsvogn) – check the exact value on the Variables page.</p>
<h3>Search status</h3>
<table>
<thead><tr><th style="width:26%">Status</th><th>Meaning</th></tr></thead>
<tbody>
<tr><td><code>funnet</code></td><td>the vehicle was found</td></tr>
<tr><td><code>ikke_funnet</code></td><td>no vehicle with this number</td></tr>
<tr><td><code>ugyldig</code></td><td>not a valid registration number or VIN</td></tr>
<tr><td><code>feil</code></td><td>Vegvesen could not be reached or answered with an error</td></tr>
<tr><td><code>ingen</code></td><td>no number in the address yet (before a search)</td></tr>
</tbody></table>
<pre>[bv_if field="eu_status" is="utlopt"]
The EU inspection deadline has passed ([bv field="neste_eu"]).
[/bv_if]

[bv_if field="karosseri_kode" is="AC"]Parts for estate cars[/bv_if]</pre>',
		'classes' => '
<p>Whole rows, columns or elements can be shown or hidden without a shortcode: put a class in the element\'s <strong>Extra class name</strong> field (WPBakery) or <strong>Additional CSS class</strong> (Gutenberg).</p>
<table>
<thead><tr><th style="width:30%">Class</th><th>The element is visible…</th></tr></thead>
<tbody>
<tr><td><code>bv-if-found</code></td><td>only when a vehicle is found</td></tr>
<tr><td><code>bv-if-notfound</code></td><td>only when the search found nothing or failed</td></tr>
<tr><td><code>bv-if-empty</code></td><td>only before a search (no number in the address)</td></tr>
<tr><td><code>bv-if-searched</code></td><td>after any search, found or not</td></tr>
<tr><td><code>bv-scroll-target</code></td><td>not a visibility class: after a search the page scrolls to this element</td></tr>
<tr><td><code>bv-print-area</code></td><td>not a visibility class: printing (the Print button or Ctrl+P) prints only the elements with this class; without it, only the vehicle card</td></tr>
</tbody></table>
<div class="tip"><strong>In the page builder</strong> all these elements stay visible, so you can always edit them. The classes work on the site.</div>',
		'blocks' => '
<p>Ready-made blocks are complete pieces of layout. They follow the theme\'s colours and fonts. In WPBakery they are in the <strong>Vegvesen</strong> category of "Add element", with the same settings as the attributes below.</p>
<table>
<thead><tr><th style="width:36%">Shortcode</th><th>What it shows, main attributes</th></tr></thead>
<tbody>
<tr><td><code>[bv_search]</code></td><td>Search field. <code>layout="inline|hero|compact"</code>, <code>title</code>, <code>subtitle</code>, <code>image</code>, <code>placeholder</code>, <code>button</code>, <code>max_width</code>, <code>title_tag="h1"</code></td></tr>
<tr><td><code>[bv_card]</code></td><td>The whole vehicle card. <code>tiles</code>, <code>general</code>, <code>dates</code>, <code>miljo</code> (field lists), <code>print="0"</code>, <code>image="0"</code>, <code>logo="0"</code>, <code>notice="0"</code>, <code>title_tag="h2"</code>, <code>table_title_tag="h3"</code></td></tr>
<tr><td><code>[bv_tiles fields="…"]</code></td><td>Key figures with icons. <code>columns="4"</code></td></tr>
<tr><td><code>[bv_table fields="…" title="…"]</code></td><td>Table of fields. <code>icon</code>, <code>highlight="neste_eu"</code>, <code>labels="merke:Brand"</code>, <code>hide_empty="0"</code>, <code>title_tag="h3"</code>. The "Next EU inspection" row gets a status icon and, when the deadline is close or passed, a button; both are set in the element (WPBakery tab "EU inspection"), also on <code>[bv_card]</code>.</td></tr>
<tr><td><code>[bv_plate]</code></td><td>Number plate. <code>size="small|large"</code></td></tr>
<tr><td><code>[bv_status]</code></td><td>"Vehicle found" and the date. <code>found="your text"</code>, <code>updated="0"</code></td></tr>
<tr><td><code>[bv_image]</code></td><td>A photo from the media library titled "Make Model" (e.g. "Volkswagen Passat") or "Make"; otherwise a body type silhouette in the vehicle\'s colour.</td></tr>
<tr><td><code>[bv_logo size="64"]</code></td><td>Brand logo: a media library image titled "Make logo", otherwise the bundled logo.</td></tr>
<tr><td><code>[bv_print]</code></td><td>Print button. <code>label="your text"</code></td></tr>
<tr><td><code>[bv_eu_button]</code></td><td>The EU inspection button on its own, shown only when the deadline is close or passed. <code>eu_btn_title</code>, <code>eu_btn_link</code> (<code>{regnr}</code> = registration number), <code>eu_btn_class</code>, <code>eu_btn_custom_attributes</code>, <code>eu_show="snart,utlopt"</code>, <code>eu_icon="yes"</code>; with WoodMart also the Button style settings.</td></tr>
</tbody></table>
<h3>Field lists</h3>
<p><code>fields</code>, <code>tiles</code>, <code>general</code>, <code>dates</code> and <code>miljo</code> take field keys separated by commas, in the order you want them:</p>
<pre>[bv_tiles fields="registrert_aar,drivstoff,effekt,girkasse"]
[bv_table title="Size" icon="weight" fields="lengde,bredde,hoyde,egenvekt"]</pre>
<p>Empty fields are left out of tables and tiles automatically. The icon of a field in the tiles can be changed or removed on the <strong>Variables</strong> page: click the icon next to the field.</p>
<h3>Title tags (SEO)</h3>
<p><code>title_tag</code> changes only the HTML tag of a title (h1–h6, p, div, span); the look stays the same. A page should have one H1: if the vehicle name should be the H1, give the search field <code>title_tag="h2"</code> and the card <code>title_tag="h1"</code>.</p>',
		'recipes' => '
<div class="cols">
<div class="box"><h3>Page heading (WoodMart Title)</h3><pre>{bv:merke_modell} ({bv:registrert_aar})</pre><p class="muted">→ Volkswagen Passat (2018)</p></div>
<div class="box"><h3>Text block</h3><pre>We have used parts for [bv field="merke_modell"]
with a [bv field="slagvolum" unit="1"] engine.</pre></div>
<div class="box"><h3>Only for electric cars</h3><pre>[bv_if field="drivstoff" is="Elektrisk"]
Range: [bv field="rekkevidde" unit="1"]
[/bv_if]</pre></div>
<div class="box"><h3>Button text</h3><pre>See all parts for {bv:merke}</pre></div>
</div>',
		'tips' => '
<h3>When something does not show</h3>
<table>
<thead><tr><th style="width:36%">You see</th><th>What to do</th></tr></thead>
<tbody>
<tr><td><code>[bv field="…"]</code> as text on the site</td><td>The field does not run shortcodes – use the token <code>{bv:…}</code>.</td></tr>
<tr><td>Nothing where the value should be</td><td>Vegvesen has no data for this field for this vehicle (common for older cars). Use <code>empty="—"</code> or a condition. Check the key spelling on the Variables page.</td></tr>
<tr><td>Values from another car in the builder</td><td>That is the preview number for editors (Settings). Visitors see the vehicle they searched for.</td></tr>
<tr><td>"Demo mode: sample data" at the bottom</td><td>Add the API key and turn off demo mode under Settings.</td></tr>
<tr><td>Old data</td><td>Answers are kept for 24 hours (Settings → Cache, hours). On the Variables page, "skip cache" fetches fresh data.</td></tr>
</tbody></table>',
		'fields_intro' => 'Every field you can use as <code>[bv field="key"]</code> or <code>{bv:key}</code>. The examples are from the plugin\'s demo vehicle. Values such as fuel, colour and body type come from Vegvesen in Norwegian.',
		'col_key' => 'Key', 'col_field' => 'Field', 'col_example' => 'Example',
	);
	if ( 'en' === $lang ) {
		return $en;
	}

	return array_merge( $en, array(
		'doc_title'    => 'Veiledning for variabler',
		'doc_short'    => 'Veiledning for variabler',
		'lead'         => 'Slik viser du kjøretøydata fra Statens vegvesen på sidene dine: kortkoder, tokens, betingelser, synlighetsklasser og ferdige blokker.',
		'meta'         => 'Versjon',
		's1' => 'Slik fungerer det', 's2' => 'Kortkode eller token?', 's3' => 'Kortkoden [bv]', 's4' => 'Tokens {bv:…}',
		's5' => 'Betingelser [bv_if]', 's6' => 'Synlighetsklasser', 's7' => 'Ferdige blokker', 's8' => 'Eksempler og tips', 's9' => 'Alle felt',
		'how' => '
<p>En <strong>variabel</strong> er en plassholder på en side som utvidelsen erstatter med data om ett kjøretøy: merke, vekt, dato for neste EU-kontroll og så videre.</p>
<p>Hvilket kjøretøy? Det med nummeret i sideadressen. Etter et søk havner den besøkende på resultatsiden:</p>
<pre>https://ditt-nettsted.no/vehicle/AB12345/
https://ditt-nettsted.no/en-side/?regnr=AB12345</pre>
<p>Alle variabler på den siden viser kjøretøyet <code>AB12345</code>. Utvidelsen spør Vegvesen én gang per side og husker svaret (24 timer som standard), så mange variabler gir ingen ekstra forespørsler.</p>
<div class="cols">
  <div class="box"><h3>Resultatsiden</h3><p>Velges under <strong>WP Vegvesen → Innstillinger → Resultatside</strong>. Bygg den i hvilken som helst sidebygger: søkefeltet øverst, variabler eller ferdige blokker under.</p></div>
  <div class="box"><h3>Design med ekte data</h3><p>I sidebyggeren står det ikke noe nummer i adressen. Da viser utvidelsen <strong>forhåndsvisningsnummeret for redaktører</strong> fra innstillingene, så du ser ekte verdier mens du jobber. Besøkende ser det aldri.</p></div>
</div>
<div class="tip"><strong>Test først.</strong> <strong>WP Vegvesen → Variabler</strong> viser alle felt med verdien for et testnummer. Klikk på en kortkode eller et token der for å kopiere det.</div>',
		'two_ways' => '
<p>Samme variabel kan skrives på to måter. De viser samme verdi; forskjellen er hvor de virker.</p>
<table>
<thead><tr><th style="width:24%">Skriv</th><th>Bruk i</th><th style="width:28%">Eksempel</th></tr></thead>
<tbody>
<tr><td><code>[bv field="merke"]</code><br><span class="muted">kortkode</span></td><td>Store tekstfelt med redigeringsverktøy: WPBakery Text Block, innhold i Info Box, HTML-blokker, Gutenberg-avsnitt.</td><td>Deler til [bv field="merke"]</td></tr>
<tr><td><code>{bv:merke}</code><br><span class="muted">token</span></td><td>Énlinjefelt der kortkoder ikke kjøres: WoodMart Title, tittel i Info Box, knappetekst, listepunkter, sidetittelen. Tokens virker også i tekstblokker.</td><td>Deler til {bv:merke_modell}</td></tr>
</tbody></table>
<div class="tip"><strong>Tommelfingerregel:</strong> ser du kortkoden som ren tekst <code>[bv field="…"]</code> på nettstedet, kjører ikke det feltet kortkoder. Bruk token i stedet.</div>
<p>I redigeringsverktøyet (admin) står tokens slik du skrev dem; de erstattes på nettstedet.</p>',
		'shortcode' => '
<p>Kortkoden skriver ut én verdi. <code>field</code> er feltnøkkelen fra listen i del 9.</p>
<pre>[bv field="egenvekt" unit="1"]                → 1 615 kg
[bv field="neste_eu" format="d.m.Y"]          → 21.03.2027
[bv field="merke" before="Merke: "]           → Merke: Volkswagen
[bv field="co2" suffix=" g/km" empty="—"]     → 118 g/km, eller — når tomt</pre>
<h3>Attributter</h3>
<table>
<thead><tr><th style="width:26%">Attributt</th><th>Hva den gjør</th></tr></thead>
<tbody>
<tr><td><code>field="merke"</code></td><td>Hvilket felt som vises (påkrevd).</td></tr>
<tr><td><code>unit="1"</code></td><td>Legger til feltets standardenhet: kg, mm, kW, g/km, km …</td></tr>
<tr><td><code>format="d.m.Y"</code></td><td>Datoformat for datofelt (se tabellen under). Uten: nettstedets standard datoformat.</td></tr>
<tr><td><code>before="Merke: "</code></td><td>Tekst foran verdien. Vises ikke når verdien er tom.</td></tr>
<tr><td><code>suffix=" kg"</code> / <code>after</code></td><td>Tekst etter verdien. Vises ikke når verdien er tom.</td></tr>
<tr><td><code>empty="—"</code></td><td>Hva som vises når Vegvesen ikke har data for feltet. Standard: ingenting.</td></tr>
<tr><td><code>number="0"</code></td><td>Tall uten tusenskille: 1615 i stedet for 1 615.</td></tr>
<tr><td><code>decimals="1"</code></td><td>Antall desimaler (5,2 i stedet for 5).</td></tr>
<tr><td><code>regnr="AB12345"</code></td><td>Vis alltid dette kjøretøyet i stedet for det i adressen.</td></tr>
<tr><td><code>el_class</code></td><td>CSS-klasse; verdien pakkes da inn i <code>&lt;span class="bv-field …"&gt;</code>.</td></tr>
</tbody></table>
<h3>Datoformater</h3>
<table>
<thead><tr><th style="width:26%">Format</th><th>Resultat (21. mars 2027)</th></tr></thead>
<tbody>
<tr><td><code>d.m.Y</code></td><td>21.03.2027</td></tr>
<tr><td><code>d/m/Y</code></td><td>21/03/2027</td></tr>
<tr><td><code>j. M Y</code></td><td>21. mar. 2027</td></tr>
<tr><td><code>j. F Y</code></td><td>21. mars 2027</td></tr>
<tr><td><code>Y</code></td><td>2027</td></tr>
</tbody></table>
<p class="muted">Bokstaver: d = dag med null, j = dag, m = månedsnummer, M = kort månedsnavn, F = fullt månedsnavn, Y = år. Månedsnavn følger nettstedets språk.</p>
<p>Et felts <strong>etikett</strong> (til egne tabeller) får du med <code>[bv_label field="egenvekt"]</code> → <em>Egenvekt</em>.</p>',
		'tokens' => '
<pre>{bv:merke}                → Volkswagen
{bv:merke_modell}         → Volkswagen Passat
{bv:egenvekt|unit}        → 1 615 kg
{bv:neste_eu|d.m.Y}       → 21.03.2027
{bv:label:egenvekt}       → Egenvekt</pre>
<table>
<thead><tr><th style="width:30%">Form</th><th>Betydning</th></tr></thead>
<tbody>
<tr><td><code>{bv:felt}</code></td><td>Verdien, som <code>[bv field="felt"]</code>.</td></tr>
<tr><td><code>{bv:felt|unit}</code></td><td>Verdien med enhet.</td></tr>
<tr><td><code>{bv:felt|d.m.Y}</code></td><td>Alt annet etter <code>|</code> er et datoformat.</td></tr>
<tr><td><code>{bv:label:felt}</code></td><td>Feltets etikett i stedet for verdien.</td></tr>
<tr><td><code>{bv:status}</code></td><td>Søkestatus: funnet, ikke_funnet, ugyldig, feil eller ingen.</td></tr>
</tbody></table>
<div class="tip"><strong>Merk:</strong> tokens har ikke <code>empty</code> eller <code>before</code>. Kan et felt være tomt, legg linjen i en betingelse (del 5) eller bruk kortkoden.</div>',
		'conditions' => '
<p><code>[bv_if]</code> viser innholdet bare når en betingelse er oppfylt. Ellers skjules alt inni (tekst, kortkoder, tokens).</p>
<table>
<thead><tr><th style="width:48%">Betingelse</th><th>Viser innholdet når …</th></tr></thead>
<tbody>
<tr><td><code>[bv_if field="co2"]…[/bv_if]</code></td><td>feltet har en verdi</td></tr>
<tr><td><code>[bv_if field="co2" empty="1"]…[/bv_if]</code></td><td>feltet er tomt</td></tr>
<tr><td><code>[bv_if field="eu_status" is="utlopt,snart"]…[/bv_if]</code></td><td>verdien er en av listen (kommaseparert)</td></tr>
<tr><td><code>[bv_if field="drivstoff" not="Elektrisk"]…[/bv_if]</code></td><td>feltet har en verdi som ikke er i listen</td></tr>
<tr><td><code>[bv_if status="funnet"]…[/bv_if]</code></td><td>søket har denne statusen</td></tr>
</tbody></table>
<p>Sammenligning med <code>is</code> og <code>not</code> skiller ikke mellom store og små bokstaver. Verdier som drivstoff, farge og karosseri kommer fra Vegvesen på norsk (Diesel, Elektrisk, Svart, Stasjonsvogn) – sjekk den nøyaktige verdien på Variabler-siden.</p>
<h3>Søkestatus</h3>
<table>
<thead><tr><th style="width:26%">Status</th><th>Betydning</th></tr></thead>
<tbody>
<tr><td><code>funnet</code></td><td>kjøretøyet ble funnet</td></tr>
<tr><td><code>ikke_funnet</code></td><td>ingen kjøretøy med dette nummeret</td></tr>
<tr><td><code>ugyldig</code></td><td>ikke et gyldig registreringsnummer eller understellsnummer</td></tr>
<tr><td><code>feil</code></td><td>Vegvesen svarte ikke eller svarte med en feil</td></tr>
<tr><td><code>ingen</code></td><td>ingen nummer i adressen ennå (før et søk)</td></tr>
</tbody></table>
<pre>[bv_if field="eu_status" is="utlopt"]
Fristen for EU-kontroll er passert ([bv field="neste_eu"]).
[/bv_if]

[bv_if field="karosseri_kode" is="AC"]Deler til stasjonsvogner[/bv_if]</pre>',
		'classes' => '
<p>Hele rader, kolonner eller elementer kan vises eller skjules uten kortkode: skriv en klasse i elementets felt <strong>Extra class name</strong> (WPBakery) eller <strong>Ekstra CSS-klasse</strong> (Gutenberg).</p>
<table>
<thead><tr><th style="width:30%">Klasse</th><th>Elementet vises …</th></tr></thead>
<tbody>
<tr><td><code>bv-if-found</code></td><td>bare når et kjøretøy er funnet</td></tr>
<tr><td><code>bv-if-notfound</code></td><td>bare når søket ikke fant noe eller feilet</td></tr>
<tr><td><code>bv-if-empty</code></td><td>bare før et søk (ingen nummer i adressen)</td></tr>
<tr><td><code>bv-if-searched</code></td><td>etter ethvert søk, funnet eller ikke</td></tr>
<tr><td><code>bv-scroll-target</code></td><td>ikke en synlighetsklasse: etter et søk ruller siden hit</td></tr>
<tr><td><code>bv-print-area</code></td><td>ikke en synlighetsklasse: utskrift (Skriv ut-knappen eller Ctrl+P) skriver bare ut elementene med denne klassen; uten den bare kjøretøykortet</td></tr>
</tbody></table>
<div class="tip"><strong>I sidebyggeren</strong> er alle disse elementene synlige, så du alltid kan redigere dem. Klassene virker på nettstedet.</div>',
		'blocks' => '
<p>Ferdige blokker er komplette deler av oppsettet. De følger temaets farger og skrifter. I WPBakery ligger de i kategorien <strong>Vegvesen</strong> under «Add element», med de samme innstillingene som attributtene under.</p>
<table>
<thead><tr><th style="width:36%">Kortkode</th><th>Hva den viser, viktigste attributter</th></tr></thead>
<tbody>
<tr><td><code>[bv_search]</code></td><td>Søkefelt. <code>layout="inline|hero|compact"</code>, <code>title</code>, <code>subtitle</code>, <code>image</code>, <code>placeholder</code>, <code>button</code>, <code>max_width</code>, <code>title_tag="h1"</code></td></tr>
<tr><td><code>[bv_card]</code></td><td>Hele kjøretøykortet. <code>tiles</code>, <code>general</code>, <code>dates</code>, <code>miljo</code> (feltlister), <code>print="0"</code>, <code>image="0"</code>, <code>logo="0"</code>, <code>notice="0"</code>, <code>title_tag="h2"</code>, <code>table_title_tag="h3"</code></td></tr>
<tr><td><code>[bv_tiles fields="…"]</code></td><td>Nøkkeltall med ikoner. <code>columns="4"</code></td></tr>
<tr><td><code>[bv_table fields="…" title="…"]</code></td><td>Tabell med felt. <code>icon</code>, <code>highlight="neste_eu"</code>, <code>labels="merke:Bilmerke"</code>, <code>hide_empty="0"</code>, <code>title_tag="h3"</code>. Raden «Neste EU-kontroll» får statusikon og, når fristen nærmer seg eller er utløpt, en knapp; begge stilles inn i elementet (WPBakery-fanen «EU-kontroll»), også på <code>[bv_card]</code>.</td></tr>
<tr><td><code>[bv_plate]</code></td><td>Skilt. <code>size="small|large"</code></td></tr>
<tr><td><code>[bv_status]</code></td><td>«Kjøretøy funnet» og datoen. <code>found="din tekst"</code>, <code>updated="0"</code></td></tr>
<tr><td><code>[bv_image]</code></td><td>Et bilde i mediebiblioteket med tittelen «Merke Modell» (f.eks. «Volkswagen Passat») eller «Merke»; ellers en silhuett av karosseritypen i kjøretøyets farge.</td></tr>
<tr><td><code>[bv_logo size="64"]</code></td><td>Merkelogo: et bilde i mediebiblioteket med tittelen «Merke logo», ellers den medfølgende logoen.</td></tr>
<tr><td><code>[bv_print]</code></td><td>Utskriftsknapp. <code>label="din tekst"</code></td></tr>
<tr><td><code>[bv_eu_button]</code></td><td>Knappen for EU-kontroll alene, vises bare når fristen nærmer seg eller er utløpt. <code>eu_btn_title</code>, <code>eu_btn_link</code> (<code>{regnr}</code> = registreringsnummer), <code>eu_btn_class</code>, <code>eu_btn_custom_attributes</code>, <code>eu_show="snart,utlopt"</code>, <code>eu_icon="yes"</code>; med WoodMart også stilinnstillingene for Button.</td></tr>
</tbody></table>
<h3>Feltlister</h3>
<p><code>fields</code>, <code>tiles</code>, <code>general</code>, <code>dates</code> og <code>miljo</code> tar feltnøkler skilt med komma, i den rekkefølgen du vil:</p>
<pre>[bv_tiles fields="registrert_aar,drivstoff,effekt,girkasse"]
[bv_table title="Mål" icon="weight" fields="lengde,bredde,hoyde,egenvekt"]</pre>
<p>Tomme felt utelates automatisk fra tabeller og fliser. Ikonet til et felt i flisene kan endres eller fjernes på <strong>Variabler</strong>-siden: klikk på ikonet ved siden av feltet.</p>
<h3>Tittel-tagger (SEO)</h3>
<p><code>title_tag</code> endrer bare HTML-taggen til en tittel (h1–h6, p, div, span); utseendet er det samme. En side bør ha én H1: skal kjøretøynavnet være H1, gi søkefeltet <code>title_tag="h2"</code> og kortet <code>title_tag="h1"</code>.</p>',
		'recipes' => '
<div class="cols">
<div class="box"><h3>Sideoverskrift (WoodMart Title)</h3><pre>{bv:merke_modell} ({bv:registrert_aar})</pre><p class="muted">→ Volkswagen Passat (2018)</p></div>
<div class="box"><h3>Tekstblokk</h3><pre>Vi har brukte deler til [bv field="merke_modell"]
med [bv field="slagvolum" unit="1"] motor.</pre></div>
<div class="box"><h3>Bare for elbiler</h3><pre>[bv_if field="drivstoff" is="Elektrisk"]
Rekkevidde: [bv field="rekkevidde" unit="1"]
[/bv_if]</pre></div>
<div class="box"><h3>Knappetekst</h3><pre>Se alle deler til {bv:merke}</pre></div>
</div>',
		'tips' => '
<h3>Når noe ikke vises</h3>
<table>
<thead><tr><th style="width:36%">Du ser</th><th>Gjør slik</th></tr></thead>
<tbody>
<tr><td><code>[bv field="…"]</code> som tekst på nettstedet</td><td>Feltet kjører ikke kortkoder – bruk token <code>{bv:…}</code>.</td></tr>
<tr><td>Ingenting der verdien skulle stå</td><td>Vegvesen har ikke data for dette feltet for dette kjøretøyet (vanlig for eldre biler). Bruk <code>empty="—"</code> eller en betingelse. Sjekk stavingen av nøkkelen på Variabler-siden.</td></tr>
<tr><td>Verdier fra en annen bil i sidebyggeren</td><td>Det er forhåndsvisningsnummeret for redaktører (Innstillinger). Besøkende ser kjøretøyet de søkte etter.</td></tr>
<tr><td>«Demomodus: eksempeldata» nederst</td><td>Legg inn API-nøkkelen og slå av demomodus under Innstillinger.</td></tr>
<tr><td>Gamle data</td><td>Svar huskes i 24 timer (Innstillinger → Mellomlagring, timer). På Variabler-siden henter «uten mellomlagring» ferske data.</td></tr>
</tbody></table>',
		'fields_intro' => 'Alle felt du kan bruke som <code>[bv field="nøkkel"]</code> eller <code>{bv:nøkkel}</code>. Eksemplene er fra utvidelsens demokjøretøy. Verdier som drivstoff, farge og karosseri kommer fra Vegvesen på norsk.',
		'col_key' => 'Nøkkel', 'col_field' => 'Felt', 'col_example' => 'Eksempel',
	) );
}
