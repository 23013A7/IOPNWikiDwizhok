<?php
function plugin_manifest_105_PageMetadata() {
    return [
        'name'        => 'Метаданные страницы',
        'description' => 'Обновляет версию страницы, дату изменения и дату назначения статуса',
        'version'     => '1.0.0',
        'author'      => 'ИОПН',
        'priority'    => 5,
    ];
}

HookManager::register('editor_before_save', function($data) {
    if (!is_array($data) || !isset($data['meta']) || !is_array($data['meta'])) {
        return $data;
    }

    $old = isset($data['old_meta']) && is_array($data['old_meta'])
        ? $data['old_meta']
        : array();

    $data['meta']['version'] = isset($old['version'])
        ? ((int)$old['version'] + 1)
        : 1;

    if (isset($old['data_create'])) {
        $data['meta']['data_create'] = $old['data_create'];
    } elseif (!isset($data['meta']['data_create']) || trim((string)$data['meta']['data_create']) === '') {
        $data['meta']['data_create'] = date('Y.m.d H:i:s');
    }

    $data['meta']['data_update'] = date('Y.m.d H:i:s');

    $oldStatusExists = array_key_exists('status', $old);
    $newStatusExists = array_key_exists('status', $data['meta']);
    $oldStatus = $oldStatusExists ? $old['status'] : null;
    $newStatus = $newStatusExists ? $data['meta']['status'] : null;

    if ($oldStatus !== $newStatus) {
        $data['meta']['data_status'] = date('Y.m.d H:i:s');
    } elseif (array_key_exists('data_status', $old)) {
        $data['meta']['data_status'] = $old['data_status'];
    }

    return $data;
}, 5);
