<?php
HookManager::register('parse_text', function($text) {

    // Нумерованный список
    $text = preg_replace('/[\n\r]?#.+([\n|r]#.+)+/', "\n<ol>\n$0\n</ol>", $text);
    $text = preg_replace('/[\n\r]#(?!#) *(.+)(([\n\r]#{2,}.+)+)/', "\n<li>$1\n<ol>$2\n</ol>\n</li>", $text);
    $text = preg_replace('/[\n\r]#{2}(?!#) *(.+)(([\n\r]#{3,}.+)+)/', "\n<li>$1\n<ol>$2\n</ol>\n</li>", $text);
    $text = preg_replace('/[\n\r]#{3}(?!#) *(.+)(([\n\r]#{4,}.+)+)/', "\n<li>$1\n<ol>$2\n</ol>\n</li>", $text);

    // Ненумерованный список
    $text = preg_replace('/[\n\r]?\*.+([\n|\r]\*.+)+/', "\n<ul>\n$0\n</ul>", $text);
    $text = preg_replace('/[\n\r]\*(?!\*) *(.+)(([\n\r]\*{2,}.+)+)/', "\n<li>$1\n<ul>$2\n</ul>\n</li>", $text);
    $text = preg_replace('/[\n\r]\*{2}(?!\*) *(.+)(([\n\r]\*{3,}.+)+)/', "\n<li>$1\n<ul>$2\n</ul>\n</li>", $text);
    $text = preg_replace('/[\n\r]\*{3}(?!\*) *(.+)(([\n\r]\*{4,}.+)+)/', "\n<li>$1\n<ul>$2\n</ul>\n</li>", $text);

    // Остальные элементы списка
    $text = preg_replace('/^[#\*]+ *(.+)$/m', '<li>$1</li>', $text);

    // Отступы
    $text = preg_replace('/[\n\r]: *.+([\n\r]:+.+)*/', "\n<dl>$0\n</dl>", $text);
    $text = preg_replace('/^:(?!:) *(.+)$/m',          '<dd>$1</dd>',     $text);
    $text = preg_replace('/([\n\r]:: *.+)+/',          "\n<dd><dl>$0\n</dl></dd>", $text);
    $text = preg_replace('/^:: *(.+)$/m',              '<dd>$1</dd>',     $text);

    return $text;
}, 50);
