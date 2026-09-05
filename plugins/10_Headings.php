<?php
function plugin_manifest_10_Headings() {
    return [
        'name'        => 'Заголовки',
        'description' => 'Добавляет заголовки = ... = вплоть до шестого уровня',
        'version'     => '1.0.0',
        'author'      => 'ИОПН',
        'priority'    => 10,
    ];
}

RuleRegistry::add('Заголовок6', [
    'Тип'               => 'Блок',
    'Элемент'           => '======',
    'Результат'         => '<h6>',
    'Приоритет'         => 10,
    'TrimInnerSpaces'   => true,
]);

RuleRegistry::add('Заголовок5', [
    'Тип'               => 'Блок',
    'Элемент'           => '=====',
    'Результат'         => '<h5>',
    'Приоритет'         => 10,
    'TrimInnerSpaces'   => true,
]);

RuleRegistry::add('Заголовок4', [
    'Тип'               => 'Блок',
    'Элемент'           => '====',
    'Результат'         => '<h4>',
    'Приоритет'         => 10,
    'TrimInnerSpaces'   => true,
]);

RuleRegistry::add('Заголовок3', [
    'Тип'               => 'Блок',
    'Элемент'           => '===',
    'Результат'         => '<h3>',
    'Приоритет'         => 10,
    'TrimInnerSpaces'   => true,
]);

RuleRegistry::add('Заголовок2', [
    'Тип'               => 'Блок',
    'Элемент'           => '==',
    'Результат'         => '<h2>',
    'Приоритет'         => 10,
    'TrimInnerSpaces'   => true,
]);

RuleRegistry::add('Заголовок1', [
    'Тип'               => 'Блок',
    'Элемент'           => '=',
    'Результат'         => '<h1>',
    'Приоритет'         => 10,
    'TrimInnerSpaces'   => true,
]);

HookManager::register('editor_buttons', function($buttons, $context) {
    $buttons[] = array('id' => 'heading2', 'label' => 'H2', 'title' => 'Вставить заголовок 2 уровня', 'insert' => "== {{selection}} ==");
    $buttons[] = array('id' => 'heading3', 'label' => 'H3', 'title' => 'Вставить заголовок 3 уровня', 'insert' => "=== {{selection}} ===");
    return $buttons;
}, 10);
