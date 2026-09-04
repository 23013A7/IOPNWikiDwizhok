<?php
if (isset($editor_mode) && $editor_mode) {
    require __DIR__ . '/render_editor.php';
    return;
}

echo isset($error) ? $error : '';
echo $content_wiki;
?>
