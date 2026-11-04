<?php
// Turns downloaded Lucide SVGs into a PHP array of safe inner markup (shapes + geometry attributes only).
$dir = $argv[1]; $out = $argv[2];
$skip = array('car', 'fuel', 'gauge', 'calendar', 'info', 'leaf', 'weight'); // the plugin's own icons keep these names
$allowed = array('path' => array('d'), 'circle' => array('cx','cy','r'), 'ellipse' => array('cx','cy','rx','ry'), 'rect' => array('x','y','width','height','rx','ry'), 'line' => array('x1','y1','x2','y2'), 'polyline' => array('points'), 'polygon' => array('points'));
$icons = array();
foreach (glob($dir . '/*.svg') as $file) {
  $name = basename($file, '.svg');
  if (in_array($name, $skip, true)) continue;
  $d = new DOMDocument(); if (!@$d->loadXML(preg_replace('/<!--.*?-->/s', '', file_get_contents($file)))) { fwrite(STDERR, "bad $name\n"); continue; }
  $parts = array();
  foreach ($d->documentElement->childNodes as $n) {
    if ($n->nodeType !== XML_ELEMENT_NODE) continue;
    if (!isset($allowed[$n->nodeName])) { fwrite(STDERR, "skip tag {$n->nodeName} in $name\n"); continue; }
    $a = '';
    foreach ($allowed[$n->nodeName] as $attr) {
      if ($n->hasAttribute($attr)) {
        $v = $n->getAttribute($attr);
        if (!preg_match('/^[0-9a-zA-Z .,\-]*$/', $v)) { fwrite(STDERR, "odd value in $name\n"); continue 2; }
        $a .= ' ' . $attr . '="' . $v . '"';
      }
    }
    $parts[] = '<' . $n->nodeName . $a . '/>';
  }
  $icons[$name] = implode('', $parts);
}
ksort($icons);
$php = "<?php\n/**\n * Extra icons from Lucide (https://lucide.dev), v0.460.0. Shapes only, drawn with the plugin's own\n * stroke settings in BV_Icons::get().\n *\n * ISC License\n * Copyright (c) for portions of Lucide are held by Cole Bemis 2013-2022 as part of Feather (MIT).\n * All other copyright (c) for Lucide are held by Lucide Contributors 2022.\n *\n * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is\n * hereby granted, provided that the above copyright notice and this permission notice appear in all copies.\n *\n * THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE\n * INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR\n * ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER RESULTING FROM LOSS OF USE,\n * DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF OR IN\n * CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.\n *\n * @package WP_Vegvesen\n */\n\ndefined( 'ABSPATH' ) || exit;\n\nreturn array(\n";
foreach ($icons as $k => $v) $php .= "\t'" . $k . "' => '" . $v . "',\n";
$php .= ");\n";
file_put_contents($out, $php);
echo count($icons), " icons\n";
