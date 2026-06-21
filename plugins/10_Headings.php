<?php
HookManager::register('parse_text', function($text) {
    $text = preg_replace('/^====== (.+?) ======\r?$/m', '<h6>$1</h6>', $text);
    $text = preg_replace('/^===== (.+?) =====\r?$/m',  '<h5>$1</h5>', $text);
    $text = preg_replace('/^==== (.+?) ====\r?$/m',    '<h4>$1</h4>', $text);
    $text = preg_replace('/^=== (.+?) ===\r?$/m',      '<h3>$1</h3>', $text);
    $text = preg_replace('/^== (.+?) ==\r?$/m',        '<h2>$1</h2>', $text);
    $text = preg_replace('/^= (.+?) =\r?$/m',          '<h1>$1</h1>', $text);
    return $text;
}, 10);
