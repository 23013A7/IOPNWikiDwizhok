<?php
HookManager::register('page_view', function($data) {
    if (empty($data['file']) || !is_file($data['file'])) {
        return;
    }

    $fp = fopen($data['file'], 'c+');
    if (!$fp) return;

    if (flock($fp, LOCK_EX)) {
        $filesize = filesize($data['file']);
        $content  = $filesize > 0 ? fread($fp, $filesize) : '';

        $meta_pos = strpos($content, "\n");
        if ($meta_pos === false) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return;
        }

        $meta_json = substr($content, 0, $meta_pos);
        $body      = substr($content, $meta_pos + 1);
        $meta      = json_decode($meta_json, true);

        if (!is_array($meta)) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return;
        }

        $meta['views'] = (isset($meta['views']) ? (int)$meta['views'] : 0) + 1;

        $new_content = json_encode($meta, JSON_UNESCAPED_UNICODE) . "\n" . $body;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $new_content);
        fflush($fp);

        flock($fp, LOCK_UN);

        if (isset($data['meta_ref']) && is_array($data['meta_ref'])) {
            $data['meta_ref']['views'] = $meta['views'];
        }
    }

    fclose($fp);
}, 10);
