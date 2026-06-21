<?php
HookManager::register('parse_text', function($text) {
    $text = preg_replace("/'''''(.+?)'''''/s", '<strong><em>$1</em></strong>', $text);
    $text = preg_replace("/'''(.+?)'''/s",     '<strong>$1</strong>',          $text);
    $text = preg_replace("/''(.+?)''/s",       '<em>$1</em>',                 $text);

    $text = preg_replace('/^----+(\s*)$/m', '<hr/>', $text);

    return $text;
}, 20);
