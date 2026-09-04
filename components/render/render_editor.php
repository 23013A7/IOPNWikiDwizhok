<?php
$editor_buttons = Editor::getButtons(array(
    'page' => $FullPageName,
    'body' => $editor_data['body'],
    'meta' => $editor_data['meta'],
));
?>
<div class="iopn-editor">
    <?php if ($editor_saved): ?>
        <div class="iopn-editor-message iopn-editor-success">Страница сохранена.</div>
    <?php endif; ?>
    <?php if ($editor_error !== ''): ?>
        <div class="iopn-editor-message iopn-editor-error"><?= Editor::h($editor_error) ?></div>
    <?php endif; ?>

    <?php if ($editor_can_edit): ?>
    <form method="post" action="?Page=<?= rawurlencode($FullPageName) ?>&amp;machen=edit">
        <input type="hidden" name="csrf_token" value="<?= Editor::h(Editor::csrfToken()) ?>">

        <?= Editor::renderToolbar($editor_buttons) ?>

        <textarea id="iopn-editor-text" name="wiki_text" class="iopn-editor-text" spellcheck="false"><?= Editor::h($editor_data['body']) ?></textarea>

        <fieldset class="iopn-editor-meta">
            <legend>Метаданные страницы</legend>
            <p class="iopn-editor-help">Метаданные хранятся в JSON первой строкой файла. Их можно изменять, добавлять и удалять. Версия страницы увеличивается автоматически при сохранении.</p>
            <div id="iopn-meta-rows">
                <?php $meta_index = 0; foreach ($editor_data['meta'] as $key => $value): ?>
                    <div class="iopn-meta-row">
                        <input type="text" name="meta[<?= $meta_index ?>][key]" value="<?= Editor::h($key) ?>" aria-label="Имя метаданных">
                        <input type="text" name="meta[<?= $meta_index ?>][value]" value="<?= Editor::h(is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>" aria-label="Значение метаданных">
                        <label><input type="checkbox" name="meta[<?= $meta_index ?>][delete]" value="1"> удалить</label>
                    </div>
                    <?php $meta_index++; endforeach; ?>
            </div>
            <button type="button" id="iopn-add-meta" class="iopn-editor-secondary">Добавить поле</button>
        </fieldset>

        <div class="iopn-editor-actions">
            <button type="submit" class="iopn-editor-primary">Сохранить</button>
            <a href="?Page=<?= rawurlencode($FullPageName) ?>" class="iopn-editor-secondary">Отмена</a>
            <?php if (!empty($settings['AllowSource']) && !Editor::metaBool(isset($editor_data['meta']['source_protected']) ? $editor_data['meta']['source_protected'] : false) && is_file($target_file)): ?>
                <a href="?Page=<?= rawurlencode($FullPageName) ?>&amp;machen=source" class="iopn-editor-secondary">Исходный код</a>
            <?php endif; ?>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
(function () {
    var textarea = document.getElementById('iopn-editor-text');
    if (!textarea) return;

    function insertText(text) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var selected = textarea.value.substring(start, end);
        var value = String(text).replace(/\{\{selection\}\}/g, selected);
        textarea.value = textarea.value.substring(0, start) + value + textarea.value.substring(end);
        var cursor = start + value.length;
        textarea.focus();
        textarea.selectionStart = cursor;
        textarea.selectionEnd = cursor;
    }

    var buttons = document.querySelectorAll('[data-editor-insert]');
    for (var i = 0; i < buttons.length; i++) {
        buttons[i].addEventListener('click', function () {
            var raw = this.getAttribute('data-editor-insert');
            var insert = '';
            try { insert = JSON.parse(raw); } catch (e) { insert = raw || ''; }
            insertText(insert);
        });
    }

    var metaContainer = document.getElementById('iopn-meta-rows');
    var addMeta = document.getElementById('iopn-add-meta');
    if (metaContainer && addMeta) {
        var metaCounter = 100000;
        addMeta.addEventListener('click', function () {
            var row = document.createElement('div');
            var idx = metaCounter++;
            row.className = 'iopn-meta-row';
            row.innerHTML = '<input type="text" name="meta[' + idx + '][key]" placeholder="имя" aria-label="Имя метаданных">' +
                '<input type="text" name="meta[' + idx + '][value]" placeholder="значение" aria-label="Значение метаданных">' +
                '<label><input type="checkbox" name="meta[' + idx + '][delete]" value="1"> удалить</label>';
            metaContainer.appendChild(row);
        });
    }
})();
</script>
