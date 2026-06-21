<?php
$_math_placeholders = array();
$_math_counter      = 0;

HookManager::register('parse_before', function($text) {
    global $_math_placeholders, $_math_counter;

    $text = preg_replace_callback('/\$\$(.+?)\$\$/s', function($m) {
        global $_math_placeholders, $_math_counter;
        $key = '@@MATH_BLOCK_' . $_math_counter . '@@';
        $_math_placeholders[$key] = '$$' . $m[1] . '$$';
        $_math_counter++;
        return $key;
    }, $text);

    $text = preg_replace_callback('/(?<!\\\\)\$([^\$\n]+?)(?<!\\\\)\$/s', function($m) {
        global $_math_placeholders, $_math_counter;

        if (preg_match('/^\d/', $m[1])) return $m[0];
        $key = '@@MATH_INLINE_' . $_math_counter . '@@';
        $_math_placeholders[$key] = '$' . $m[1] . '$';
        $_math_counter++;
        return $key;
    }, $text);

    return $text;
}, 10);

HookManager::register('parse_after', function($text) {
    global $_math_placeholders;

    foreach ($_math_placeholders as $key => $formula) {
        $text = str_replace($key, $formula, $text);
    }

    $_math_placeholders = array();

    return $text;
}, 10);
