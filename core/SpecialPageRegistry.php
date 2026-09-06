<?php
class SpecialPageRegistry {
    private static $pages = array();

    public static function register($name, $callback, $options = array()) {
        $name = trim((string)$name);
        if ($name === '' || !is_callable($callback)) {
            throw new Exception('Некорректная специальная страница.');
        }

        $defaults = array(
            'name'        => $name,
            'description' => '',
            'aliases'     => array(),
            'priority'    => 50,
        );
        $options = array_merge($defaults, is_array($options) ? $options : array());
        $options['name'] = $name;
        $options['callback'] = $callback;

        self::$pages[$name] = $options;
        return $options;
    }

    public static function get($name) {
        $name = trim((string)$name);
        foreach (self::$pages as $canonical => $page) {
            if ($canonical === $name) return $page;
            if (!empty($page['aliases']) && is_array($page['aliases'])) {
                foreach ($page['aliases'] as $alias) {
                    if ((string)$alias === $name) return $page;
                }
            }
        }
        return null;
    }

    public static function all() {
        $pages = self::$pages;
        uasort($pages, function($a, $b) {
            $pa = isset($a['priority']) ? (int)$a['priority'] : 50;
            $pb = isset($b['priority']) ? (int)$b['priority'] : 50;
            if ($pa === $pb) return strcmp($a['name'], $b['name']);
            return $pa < $pb ? -1 : 1;
        });
        return $pages;
    }

    public static function render($name, $context = array()) {
        $page = self::get($name);
        if ($page === null) return null;
        $result = call_user_func($page['callback'], $context);
        return array(
            'name'    => $page['name'],
            'html'    => is_string($result) ? $result : '',
            'meta'    => $page,
        );
    }
}
