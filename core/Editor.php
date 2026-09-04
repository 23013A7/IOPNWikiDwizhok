<?php
class Editor {

    public static function readPage($file) {
        if (!$file || !is_file($file)) {
            return array('body' => '', 'meta' => array());
        }

        $fp = fopen($file, 'r');
        if (!$fp) throw new Exception('Не удалось открыть страницу.');
        if (!flock($fp, LOCK_SH)) {
            fclose($fp);
            throw new Exception('Не удалось получить блокировку страницы.');
        }

        $size = filesize($file);
        $content = $size > 0 ? fread($fp, $size) : '';
        flock($fp, LOCK_UN);
        fclose($fp);

        $metaPos = strpos($content, "\n");
        $metaJson = $metaPos !== false ? substr($content, 0, $metaPos) : '';
        $body = $metaPos !== false ? substr($content, $metaPos + 1) : $content;
        $meta = json_decode($metaJson, true);
        if (!is_array($meta)) $meta = array();

        return array('body' => $body, 'meta' => $meta);
    }

    public static function savePage($file, $body, array $meta) {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
            throw new Exception('Не удалось создать каталог страницы.');
        }

        $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new Exception('Не удалось сериализовать метаданные.');

        $content = $json . "\n" . $body;
        $tmp = $file . '.tmp.' . uniqid('', true);

        $fp = fopen($tmp, 'wb');
        if (!$fp) throw new Exception('Не удалось создать временный файл.');
        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            @unlink($tmp);
            throw new Exception('Не удалось заблокировать временный файл.');
        }

        $written = fwrite($fp, $content);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($written === false || $written < strlen($content)) {
            @unlink($tmp);
            throw new Exception('Не удалось записать страницы.');
        }

        if (!rename($tmp, $file)) {
            @unlink($tmp);
            throw new Exception('Не удалось заменить файл страницы.');
        }
    }

    public static function getButtons($context = array()) {
        $buttons = array();
        $buttons = HookManager::apply('editor_buttons', $buttons, $context);
        return is_array($buttons) ? $buttons : array();
    }

    public static function filterMeta($meta, $context = array()) {
        $result = HookManager::apply('editor_metadata', $meta, $context);
        return is_array($result) ? $result : $meta;
    }

    public static function beforeSave($data) {
        return HookManager::apply('editor_before_save', $data, $data);
    }

    public static function afterSave($data) {
        HookManager::fire('editor_after_save', $data);
    }

    private static function csrfSecret() {
        $file = dirname(__DIR__) . '/Pages/.iopnwiki_editor_secret';

        if (is_file($file)) {
            $secret = trim((string)@file_get_contents($file));
            if ($secret !== '') return $secret;
        }

        if (function_exists('random_bytes')) {
            $secret = bin2hex(random_bytes(32));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $secret = bin2hex(openssl_random_pseudo_bytes(32));
        } else {
            $secret = sha1(uniqid(mt_rand(), true) . microtime(true) . __FILE__);
        }

        if (@file_put_contents($file, $secret, LOCK_EX) !== false) {
            @chmod($file, 0600);
            return $secret;
        }

        return $secret;
    }

    public static function csrfToken() {
        $nonce = '';
        if (function_exists('random_bytes')) {
            $nonce = bin2hex(random_bytes(32));
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $nonce = bin2hex(openssl_random_pseudo_bytes(32));
        } else {
            $nonce = sha1(uniqid(mt_rand(), true) . microtime(true));
        }

        $secret = self::csrfSecret();
        $signature = hash_hmac('sha256', $nonce, $secret);
        $token = $nonce . '.' . $signature;

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['iopnwiki_editor_csrf'] = $token;
        }

        return $token;
    }

    public static function checkCsrf($token) {
        if (!is_string($token) || $token === '') return false;

        $parts = explode('.', $token, 2);
        if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
            $expected = hash_hmac('sha256', $parts[0], self::csrfSecret());
            if (strlen($expected) === strlen($parts[1])) {
                if (function_exists('hash_equals')) {
                    if (hash_equals($expected, $parts[1])) return true;
                } elseif ($expected === $parts[1]) {
                    return true;
                }
            }
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $sessionToken = (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['iopnwiki_editor_csrf']))
            ? (string)$_SESSION['iopnwiki_editor_csrf']
            : '';
        if ($sessionToken === '' || strlen($sessionToken) !== strlen($token)) return false;
        return function_exists('hash_equals') ? hash_equals($sessionToken, $token) : ($sessionToken === $token);
    }

    public static function h($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function metaBool($value) {
        if ($value === true || $value === 1 || $value === '1') return true;
        if (!is_string($value)) return false;
        $value = strtolower(trim($value));
        return in_array($value, array('true', 'yes', 'да', 'on'), true);
    }

    public static function renderToolbar($buttons) {
        if (empty($buttons)) return '';
        $html = '<div class="iopn-editor-toolbar" role="toolbar">';
        foreach ($buttons as $button) {
            if (!is_array($button) || empty($button['id']) || empty($button['label'])) continue;
            $id = self::h($button['id']);
            $label = self::h($button['label']);
            $title = self::h(isset($button['title']) ? $button['title'] : $button['label']);
            $insert = isset($button['insert']) ? $button['insert'] : '';
            $insertJson = htmlspecialchars(json_encode($insert, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
            $cursor = isset($button['cursor']) ? $button['cursor'] : 'end';
            $cursor = self::h($cursor);
            $html .= '<button type="button" class="iopn-editor-button" id="editor-button-' . $id . '" title="' . $title . '" data-editor-insert="' . $insertJson . '" data-editor-cursor="' . $cursor . '">' . $label . '</button>';
        }
        $html .= '</div>';
        return $html;
    }
}
