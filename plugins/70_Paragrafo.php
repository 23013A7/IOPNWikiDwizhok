<?php
HookManager::register('parse_after', function($text) {
    // Теги игнорирования
    $blockTags = 'h[1-4]|ul|ol|dl|hr|table|pre|div|blockquote';

    $blocks = preg_split('/\n\s*\n/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $result = array();

    foreach ($blocks as $block) {
        $block   = trim($block);
        if ($block === '') continue;

        $lines          = explode("\n", $block);
        $hasBlockMarkup = false;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') continue;

            // Тут заголовки, списки и тд
            if (preg_match('/^(=+|-{4,}|\s*[#*:]+)/', $trimmed)) {
                $hasBlockMarkup = true;
                break;
            }
            // Строка начинается с хтмл игнор
            if (preg_match('/^<(' . $blockTags . ')[\s>\/]/i', $trimmed)) {
                $hasBlockMarkup = true;
                break;
            }
        }

        if (!$hasBlockMarkup) {
            $block = preg_replace('/\s*\n\s*/u', ' ', $block);
            $block = '<p>' . trim($block) . '</p>';
        }

        $result[] = $block;
    }

    return implode("\n", $result);
}, 20);
