<?php
class PageIndex {
    private static $file = null;
    private static $pagesDir = null;

    public static function init($pagesDir = null) {
        if ($pagesDir === null) $pagesDir = dirname(__DIR__) . '/Pages';
        self::$pagesDir = rtrim($pagesDir, '/\\');
        self::$file = self::$pagesDir . '/index.json';
        if (!is_dir(self::$pagesDir)) @mkdir(self::$pagesDir, 0775, true);
    }

    public static function getFile() {
        self::ensureInit();
        return self::$file;
    }

    public static function get($page) {
        $index = self::read();
        $page = self::normalizePage($page);
        return isset($index['pages'][$page]) ? $index['pages'][$page] : null;
    }

    public static function set($page, $data) {
        $index = self::read();
        $page = self::normalizePage($page);
        if ($page === '') throw new Exception('Нельзя добавить в индекс страницу без имени.');
        if (!is_array($data)) $data = array();
        $index['pages'][$page] = $data;
        self::write($index);
        return $index['pages'][$page];
    }

    public static function remove($page) {
        $index = self::read();
        $page = self::normalizePage($page);
        if (isset($index['pages'][$page])) {
            unset($index['pages'][$page]);
            self::write($index);
            return true;
        }
        return false;
    }

    public static function all() {
        $index = self::read();
        return $index['pages'];
    }

    public static function find($criteria = array()) {
        $pages = self::all();
        if (!is_array($criteria) || empty($criteria)) return $pages;

        $result = array();
        foreach ($pages as $page => $data) {
            $match = true;
            foreach ($criteria as $key => $expected) {
                if ($key === 'page') {
                    if ($page !== self::normalizePage($expected)) { $match = false; break; }
                } elseif ($key === 'namespace' || $key === 'name') {
                    $actual = isset($data[$key]) ? $data[$key] : '';
                    if ($actual !== $expected) { $match = false; break; }
                } elseif (strpos($key, 'meta.') === 0) {
                    $metaKey = substr($key, 5);
                    $actual = isset($data['meta']) && is_array($data['meta']) && array_key_exists($metaKey, $data['meta'])
                        ? $data['meta'][$metaKey] : null;
                    if ($actual !== $expected) { $match = false; break; }
                } elseif (!array_key_exists($key, $data) || $data[$key] !== $expected) {
                    $match = false; break;
                }
            }
            if ($match) $result[$page] = $data;
        }
        return $result;
    }

    public static function updateFromPage($page, $metadata) {
        self::ensureInit();
        $parts = self::splitPage($page);
        $data = array(
            'name' => $parts['name'],
            'namespace' => $parts['namespace'],
            'meta' => is_array($metadata) ? $metadata : array(),
        );
        return self::set($parts['full'], $data);
    }

    public static function rebuild() {
        self::ensureInit();
        NamespaceRegistry::refresh(self::$pagesDir);

        $pages = array();
        self::scanDirectory(self::$pagesDir, '', $pages);

        $index = array(
            'version' => 1,
            'updated' => date('Y.m.d H:i:s'),
            'namespaces' => NamespaceRegistry::all(),
            'pages' => $pages,
        );
        self::write($index);
        return $index;
    }

    public static function refreshNamespaces() {
        self::ensureInit();
        NamespaceRegistry::refresh(self::$pagesDir);
        $index = self::read();
        $index['namespaces'] = NamespaceRegistry::all();
        self::write($index);
        return $index['namespaces'];
    }

    public static function normalizePage($page) {
        $parts = self::splitPage($page);
        return $parts['full'];
    }

    public static function splitPage($page) {
        $page = trim((string)$page);
        $namespace = '';
        $name = $page;
        if (strpos($page, ':') !== false) {
            list($namespace, $name) = explode(':', $page, 2);
            $namespace = NamespaceRegistry::resolve($namespace);
        }
        $namespace = NamespaceRegistry::sanitize($namespace);
        $name = preg_replace('/[^a-zA-Zа-яёА-ЯЁ0-9_\- —№.,() \p{Greek}]/u', '', trim($name));
        if ($name === '') return array('namespace' => '', 'name' => '', 'full' => '');
        $full = $namespace !== '' ? $namespace . ':' . $name : $name;
        return array('namespace' => $namespace, 'name' => $name, 'full' => $full);
    }

    private static function scanDirectory($dir, $namespace, &$pages) {
        $files = glob(rtrim($dir, '/\\') . '/*.iopnwiki');
        if (is_array($files)) {
            foreach ($files as $file) {
                $base = basename($file, '.iopnwiki');
                $parts = self::splitPage($namespace !== '' ? $namespace . ':' . $base : $base);
                if ($parts['full'] === '') continue;
                $meta = self::readMetadata($file);
                $pages[$parts['full']] = array(
                    'name' => $parts['name'],
                    'namespace' => $parts['namespace'],
                    'meta' => $meta,
                );
            }
        }

        $dirs = glob(rtrim($dir, '/\\') . '/*', GLOB_ONLYDIR);
        if (!is_array($dirs)) return;
        foreach ($dirs as $child) {
            $base = basename($child);
            if ($base === '' || $base[0] === '.') continue;
            $childNamespace = $namespace === '' ? $base : $namespace . '/' . $base;
            if ($namespace !== '') continue;
            self::scanDirectory($child, $base, $pages);
        }
    }

    private static function readMetadata($file) {
        $fp = @fopen($file, 'rb');
        if (!$fp) return array();
        $line = fgets($fp);
        fclose($fp);
        if ($line === false) return array();
        $meta = json_decode(trim($line), true);
        return is_array($meta) ? $meta : array();
    }

    private static function read() {
        self::ensureInit();
        if (!is_file(self::$file)) {
            return array('version' => 1, 'updated' => '', 'namespaces' => NamespaceRegistry::all(), 'pages' => array());
        }

        $fp = @fopen(self::$file, 'rb');
        if (!$fp) throw new Exception('Не удалось открыть Pages/index.json.');
        if (!flock($fp, LOCK_SH)) { fclose($fp); throw new Exception('Не удалось заблокировать Pages/index.json.'); }
        $json = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $data = json_decode($json, true);
        if (!is_array($data)) throw new Exception('Повреждён Pages/index.json.');
        if (!isset($data['pages']) || !is_array($data['pages'])) $data['pages'] = array();
        if (!isset($data['namespaces']) || !is_array($data['namespaces'])) $data['namespaces'] = NamespaceRegistry::all();
        if (!isset($data['version'])) $data['version'] = 1;
        return $data;
    }

    private static function write($data) {
        self::ensureInit();
        $data['version'] = isset($data['version']) ? $data['version'] : 1;
        $data['updated'] = date('Y.m.d H:i:s');
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) throw new Exception('Не удалось сериализовать Pages/index.json.');

        $tmp = self::$file . '.tmp.' . uniqid('', true);
        $fp = @fopen($tmp, 'wb');
        if (!$fp) throw new Exception('Не удалось создать временный индекс.');
        if (!flock($fp, LOCK_EX)) { fclose($fp); @unlink($tmp); throw new Exception('Не удалось заблокировать индекс.'); }
        $written = fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        if ($written === false || $written < strlen($json)) { @unlink($tmp); throw new Exception('Не удалось записать индекс.'); }
        if (!rename($tmp, self::$file)) { @unlink($tmp); throw new Exception('Не удалось заменить Pages/index.json.'); }
    }

    private static function ensureInit() {
        if (self::$file === null) self::init();
        NamespaceRegistry::init(array());
    }
}
