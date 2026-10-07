<?php

// The source assets remain individual SVGs. This packs them for an offline preview.
$root = dirname(__DIR__);
$directory = $root.'/public/plummo';
$catalog = json_decode(file_get_contents($directory.'/catalog.json'), true, flags: JSON_THROW_ON_ERROR);
$messages = [];
foreach (['fr', 'en'] as $locale) {
    $messages[$locale] = require $root.'/lang/'.$locale.'/plummo.php';
}
$symbols = [];
$files = ['base' => 'base.svg'];
foreach ($catalog['accessories'] as $item) {
    $files[$item['id']] = $item['front'];
    if (isset($item['back'])) {
        $files[$item['id'].'-back'] = $item['back'];
    }
}
foreach ($files as $id => $file) {
    $source = file_get_contents($directory.'/'.$file);
    $body = preg_replace('/^<svg[^>]*>|<\/svg>\s*$/', '', $source);
    $body = preg_replace('/<title>.*?<\/title>/', '', $body);
    $symbols[$id] = $body;
}
$encode = static fn ($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR);
$template = file_get_contents($root.'/resources/plummo-preview.html');
$page = str_replace(['__SYMBOLS__', '__CATALOG__', '__MESSAGES__'], [$encode($symbols), $encode($catalog), $encode($messages)], $template);
file_put_contents($directory.'/index.html', $page);
echo 'Preview built from '.count($files)." SVG layers.\n";
