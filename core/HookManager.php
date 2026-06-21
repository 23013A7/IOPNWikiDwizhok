<?php
/**
 * HookManager — система хуков для плагинов
 *
 * Два вида хуков:
 *   apply($hook, $value, $data) — хук-фильтр, возвращает изменённое значение
 *                                 используется для parse_text, render_content и т.д.
 *   fire($hook, $data)          — хук-событие, ничего не возвращает
 *                                 используется для page_view, page_save и т.д.
 */
class HookManager {

    // Все зарегистрированные колбеки: ['имя_хука' => [приоритет => [колбеки]]]
    private static $hooks = array();

    // Лог ошибок сломанных плагинов за текущий запрос
    private static $errors = array();

    /**
     * Регистрация колбека на хук.
     *
     * @param string   $hook_name  Имя хука, например 'parse_text' или 'page_view'
     * @param callable $callback   Функция или замыкание
     * @param int      $priority   Порядок вызова — меньше число, раньше вызов (по умолчанию 10)
     */
    public static function register($hook_name, $callback, $priority = 10) {
        if (!is_callable($callback)) {
            self::$errors[] = "HookManager: попытка зарегистрировать не-callable на хук '$hook_name'";
            return;
        }
        self::$hooks[$hook_name][$priority][] = $callback;
    }

    /**
     * Хук-фильтр: передаёт значение по цепочке плагинов, каждый может его изменить.
     * Если плагин сломался — пропускаем его и показываем ошибку, значение не теряется.
     *
     * @param string $hook_name  Имя хука
     * @param mixed  $value      Значение которое фильтруем (текст, массив и т.д.)
     * @param array  $data       Дополнительные данные контекста (не изменяются)
     * @return mixed             Отфильтрованное значение
     */
    public static function apply($hook_name, $value, $data = array()) {
        if (empty(self::$hooks[$hook_name])) {
            return $value;
        }

        ksort(self::$hooks[$hook_name]);

        foreach (self::$hooks[$hook_name] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    $result = call_user_func($callback, $value, $data);
                    // Если колбек вернул null — скорее всего забыл return, не ломаем значение
                    if ($result !== null) {
                        $value = $result;
                    }
                } catch (Exception $e) {
                    self::$errors[] = "Хук '$hook_name' (приоритет $priority): " . $e->getMessage();
                } catch (Error $e) {
                    // Error — для PHP 7+, но оставим на будущее
                    self::$errors[] = "Хук '$hook_name' (приоритет $priority): " . $e->getMessage();
                }
            }
        }

        return $value;
    }

    /**
     * Хук-событие: оповещает плагины о событии, передавая данные.
     * Возвращаемое значение колбеков игнорируется.
     *
     * @param string $hook_name  Имя хука
     * @param array  $data       Данные события, например ['page_id' => ..., 'meta' => ...]
     */
    public static function fire($hook_name, $data = array()) {
        if (empty(self::$hooks[$hook_name])) {
            return;
        }

        ksort(self::$hooks[$hook_name]);

        foreach (self::$hooks[$hook_name] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                try {
                    call_user_func($callback, $data);
                } catch (Exception $e) {
                    self::$errors[] = "Хук '$hook_name' (приоритет $priority): " . $e->getMessage();
                } catch (Error $e) {
                    self::$errors[] = "Хук '$hook_name' (приоритет $priority): " . $e->getMessage();
                }
            }
        }
    }

    /**
     * Проверить, есть ли хоть один плагин на данный хук.
     * Полезно если нужно пропустить тяжёлую подготовку данных когда слушателей нет.
     *
     * @param string $hook_name
     * @return bool
     */
    public static function has($hook_name) {
        return !empty(self::$hooks[$hook_name]);
    }

    /**
     * Получить все ошибки плагинов за текущий запрос.
     *
     * @return array
     */
    public static function getErrors() {
        return self::$errors;
    }

    /**
     * Есть ли ошибки плагинов.
     *
     * @return bool
     */
    public static function hasErrors() {
        return !empty(self::$errors);
    }
}
