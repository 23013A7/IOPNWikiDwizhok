<?php
HookManager::register('parse_text', function($text) {
    // Внешние ссылки с текстом
    $text = preg_replace(
        '/\[((news|(ht|f)tp(s?)|irc):\/\/([^\]\s]+))\s+([^\]]+)\]/i',
        '<a href="$1">$6</a>',
        $text
    );

    // Внешние ссылки без текста
    $text = preg_replace(
        '/\[((news|(ht|f)tp(s?)|irc):\/\/([^\]]+))\]/i',
        '<a href="$1">$1</a>',
        $text
    );

    // Внутренние ссылки с текстом
    $text = preg_replace(
        '/\[\[([^|\]]+)\|([^\]]+)\]\]/',
        '<a href="?Page=$1">$2</a>',
        $text
    );

    // Внутренние ссылки без текста
    $text = preg_replace(
        '/\[\[([^\]]+)\]\]/',
        '<a href="?Page=$1">$1</a>',
        $text
    );

    return $text;
}, 30);
