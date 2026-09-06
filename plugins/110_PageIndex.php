<?php
function plugin_manifest_110_PageIndex() {
    return array(
        'name'        => 'Индекс страниц',
        'description' => 'Синхронизирует Pages/index.json и предоставляет операции обновления индекса и пространств имён',
        'version'     => '1.0.0',
        'author'      => 'ИОПН',
        'priority'    => 110,
    );
}

function iopn_page_index_update($page, $metadata) {
    return PageIndex::updateFromPage($page, $metadata);
}

function iopn_page_index_rebuild() {
    return PageIndex::rebuild();
}

function iopn_namespace_refresh() {
    return PageIndex::refreshNamespaces();
}

function iopn_namespace_register($name, $aliases = array()) {
    $result = NamespaceRegistry::register($name, array('aliases' => $aliases));
    PageIndex::refreshNamespaces();
    return $result;
}

HookManager::register('editor_after_save', function($data) {
    if (!is_array($data) || !isset($data['page']) || !isset($data['meta'])) return;
    PageIndex::updateFromPage($data['page'], $data['meta']);
}, 100);

HookManager::register('wiki_init', function($data) {
    if (!is_file(PageIndex::getFile())) {
        PageIndex::rebuild();
    }
}, 100);
