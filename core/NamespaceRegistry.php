<?php
class NamespaceRegistry {
    private static $namespaces = array();
    private static $aliases = array();
    private static $initialized = false;

    public static function init($settings = array()) {
        if (self::$initialized) return;

        self::$namespaces = array(
            '' => array('name' => '', 'aliases' => array()),
            'Служебная' => array('name' => 'Служебная', 'aliases' => array('Special', 'Спец')),
        );

        if (isset($settings['Namespaces']) && is_array($settings['Namespaces'])) {
            foreach ($settings['Namespaces'] as $name => $data) {
                if (is_int($name)) {
                    $name = (string)$data;
                    $data = array();
                }
                self::register($name, $data);
            }
        }

        self::$initialized = true;
        self::rebuildAliases();
    }

    public static function register($name, $data = array()) {
        $name = self::sanitize((string)$name);
        if ($name === '') {
            $name = '';
        }

        if (!is_array($data)) $data = array();
        $canonical = isset($data['name']) ? self::sanitize((string)$data['name']) : $name;
        if ($canonical === '' && $name !== '') $canonical = $name;

        $aliases = isset($data['aliases']) && is_array($data['aliases']) ? $data['aliases'] : array();
        $cleanAliases = array();
        foreach ($aliases as $alias) {
            $alias = self::sanitize((string)$alias);
            if ($alias !== '' && $alias !== $canonical && !in_array($alias, $cleanAliases, true)) {
                $cleanAliases[] = $alias;
            }
        }

        self::$namespaces[$canonical] = array(
            'name' => $canonical,
            'aliases' => $cleanAliases,
        );
        self::rebuildAliases();
        return $canonical;
    }

    public static function resolve($name) {
        self::ensureInit();
        $name = self::sanitize((string)$name);
        if ($name === '') return '';
        if (isset(self::$namespaces[$name])) return $name;
        if (isset(self::$aliases[$name])) return self::$aliases[$name];
        return $name;
    }

    public static function get($name) {
        self::ensureInit();
        $canonical = self::resolve($name);
        return isset(self::$namespaces[$canonical]) ? self::$namespaces[$canonical] : null;
    }

    public static function all() {
        self::ensureInit();
        return self::$namespaces;
    }

    public static function refresh($pagesDir = null) {
        self::ensureInit();
        if ($pagesDir === null) $pagesDir = dirname(__DIR__) . '/Pages';
        if (!is_dir($pagesDir)) return self::$namespaces;

        $dirs = glob(rtrim($pagesDir, '/\\') . '/*', GLOB_ONLYDIR);
        if (is_array($dirs)) {
            foreach ($dirs as $dir) {
                $base = basename($dir);
                if ($base === '' || $base[0] === '.') continue;
                $canonical = self::sanitize($base);
                if ($canonical === '') continue;
                if (!isset(self::$namespaces[$canonical])) {
                    self::$namespaces[$canonical] = array('name' => $canonical, 'aliases' => array());
                }
            }
        }

        self::rebuildAliases();
        return self::$namespaces;
    }

    public static function sanitize($name) {
        $name = trim((string)$name);
        return preg_replace('/[^a-zA-Zа-яёА-ЯЁ0-9_\- \p{Greek}]/u', '', $name);
    }

    private static function ensureInit() {
        if (!self::$initialized) self::init(array());
    }

    private static function rebuildAliases() {
        self::$aliases = array();
        foreach (self::$namespaces as $canonical => $data) {
            if (!isset($data['aliases']) || !is_array($data['aliases'])) continue;
            foreach ($data['aliases'] as $alias) {
                self::$aliases[$alias] = $canonical;
            }
        }
    }
}
