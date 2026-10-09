<?php

/** Generate editable animation layers without altering the reference artwork. */
$document = new DOMDocument;
$document->load(__DIR__.'/../public/plummo/base.svg');
$xpath = new DOMXPath($document);
$xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');
$elements = fn (DOMNode $node) => array_values(array_filter(iterator_to_array($node->childNodes), fn ($child) => $child instanceof DOMElement));
$outer = $xpath->query('/svg:svg/svg:g')->item(0);
$children = $elements($outer);
$parts = [];
$add = function (string $name, DOMNode|string $node, array $pivot, string $parent) use (&$parts, $document): void {
    $parts[$name] = ['svg' => is_string($node) ? $node : $document->saveXML($node), 'pivot' => $pivot, 'parent' => $parent];
};
foreach ($elements($children[0]) as $i => $node) {
    $add('foot-'.['left', 'right'][$i], $node, [[197, 453], [305, 460]][$i], 'pose');
}
foreach ($elements($children[1]) as $i => $node) {
    $add(['plume-large', 'plume-pink', 'plume-side', 'plume-large-reflection', 'plume-side-reflection'][$i], $node, [244, 165], 'pose');
}
$hands = $elements($children[2]);
foreach (['left', 'right'] as $i => $side) {
    $reflection = clone $hands[2];
    $reflection->setAttribute('d', $i === 0 ? 'M55 305 Q75 333 107 327' : 'M419 319 Q440 324 456 308');
    $add('hand-'.$side.'-reflection', $reflection, $i === 0 ? [116, 319] : [391, 324], 'arm-'.$side);
    $add('hand-'.$side, $document->saveXML($hands[$i]), $i === 0 ? [116, 319] : [391, 324], 'arm-'.$side);
}
$add('hands-reflection', $hands[2], [256, 320], 'pose');
$add('body', $children[3], [256, 420], 'pose');
$add('shadow', $children[4], [256, 420], 'pose');
$names = ['brow-left', 'brow-right', 'eye-white-left', 'eye-white-right', 'iris-left', 'iris-right', 'highlight-left', 'highlight-right', 'cheek-left', 'cheek-right', 'mouth-opening', 'tongue', 'tongue-reflection'];
foreach ($elements($children[5]) as $i => $node) {
    $name = $names[$i];
    $pivot = str_ends_with($name, 'left') ? [195, 263] : (str_ends_with($name, 'right') ? [314, 273] : [250, 313]);
    $parent = preg_match('/^(eye-white|iris|highlight)-(left|right)$/', $name, $match) ? 'eye-'.$match[2] : (in_array($name, ['mouth-opening', 'tongue', 'tongue-reflection']) ? 'mouth' : 'face');
    $add($name, $node, $pivot, $parent);
}
$attributes = '';
foreach ($outer->attributes as $attribute) {
    $attributes .= ' '.$attribute->name.'="'.$attribute->value.'"';
}
$rig = ['version' => 1, 'sourceHash' => hash_file('sha256', __DIR__.'/../public/plummo/base.svg'), 'defs' => $document->saveXML($xpath->query('/svg:svg/svg:defs')->item(0)), 'attributes' => $attributes, 'parts' => $parts];
file_put_contents(__DIR__.'/../public/plummo/rig.json', json_encode($rig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
