<?php
class RuleRegistry {

    private static $rules          = array();
    private static $compiled       = false;
    private static $compiled_rules = array();

    private static $aliases = array(
        'Type'                  => 'Тип',
        'Inline'                => 'Строка',
        'Block'                 => 'Блок',
        'MultilineBlock'        => 'МногострочныйБлок',
        'List'                  => 'Список',

        'Token'                 => 'Элемент',
        'Element'               => 'Элемент',
        'CloseToken'            => 'КонечныйЭлемент',
        'CloseElement'          => 'КонечныйЭлемент',
        'Output'                => 'Результат',
        'Result'                => 'Результат',
        'CloseOutput'           => 'КонечныйРезультат',
        'CloseResult'           => 'КонечныйРезультат',
        'Wrapper'               => 'Обёртка',
        'CloseWrapper'          => 'КонечнаяОбёртка',
        'Priority'              => 'Приоритет',
        'Aliases'               => 'Алиасы',
        'Handler'               => 'Обработчик',

        'OnlyStart'             => 'РежимЛишьНачала',
        'StartOnly'             => 'РежимЛишьНачала',
        'InnerSpaces'           => 'ВнутреннииПробелы',
        'RequireSpaces'         => 'ВнутреннииПробелы',
        'TrimInnerSpaces'       => 'УбиратьВнутренниеПробелы',
        'StopProcessing'        => 'ПрекратитьОбработку',
        'NoChildParsing'        => 'ПрекратитьОбработку',
        'MarkdownLists'         => 'РежимМаркдавэнСписков',
        'NoMerge'               => 'НеСоединятьСДругими',
        'NoMergeWithOthers'     => 'НеСоединятьСДругими',

        'Args'                  => 'Аргументы',
        'Arguments'             => 'Аргументы',
        'ArgSeparator'          => 'РазделительАргументов',
        'Separator'             => 'РазделительАргументов',
        'ArgCount'              => 'ЧислоАргументов',
        'MaxArgs'               => 'ЧислоАргументов',
        'NoParseArgs'           => 'НепарситьАргументы',
        'RawArgs'               => 'НепарситьАргументы',
        'ArgDefaults'           => 'ЗначенияАргументов',
        'DefaultArgs'           => 'ЗначенияАргументов',
    );

    // Алиасы значений типа (английское → русское)
    private static $typeAliases = array(
        'Inline'         => 'Строка',
        'String'         => 'Строка',
        'Block'          => 'Блок',
        'MultilineBlock' => 'МногострочныйБлок',
        'Multiline'      => 'МногострочныйБлок',
        'List'           => 'Список',
    );
    public static function add($name, array $rule) {
        $rule = self::translateKeys($rule);
        self::$rules[$name] = self::normalize($name, $rule);
        self::$compiled = false;
    }

    public static function getRules() {
        if (!self::$compiled) self::compile();
        return self::$compiled_rules;
    }

    public static function getRulesByType($type) {
        $type = isset(self::$typeAliases[$type]) ? self::$typeAliases[$type] : $type;
        return array_filter(self::getRules(), function($r) use ($type) {
            return $r['Тип'] === $type;
        });
    }

    public static function has($name)    { return isset(self::$rules[$name]); }
    public static function getNames()    { return array_keys(self::$rules); }
    public static function reset() {
        self::$rules = self::$compiled_rules = array();
        self::$compiled = false;
    }

    private static function translateKeys(array $rule) {
        $out = array();
        foreach ($rule as $key => $val) {
            $canonical = isset(self::$aliases[$key]) ? self::$aliases[$key] : $key;
            $out[$canonical] = $val;
        }
        if (isset($out['Тип']) && isset(self::$typeAliases[$out['Тип']])) {
            $out['Тип'] = self::$typeAliases[$out['Тип']];
        }
        return $out;
    }

    private static function normalize($name, array $rule) {
        $тип     = isset($rule['Тип'])      ? $rule['Тип']      : 'Строка';
        $элемент = isset($rule['Элемент'])  ? $rule['Элемент']  : '';

        $результат          = isset($rule['Результат'])         ? $rule['Результат']        : '';
        $конечный_результат = isset($rule['КонечныйРезультат']) ? $rule['КонечныйРезультат'] : self::inferClosingTag($результат);

        $обёртка         = isset($rule['Обёртка'])        ? $rule['Обёртка']        : '';
        $конечная_обёртка = isset($rule['КонечнаяОбёртка']) ? $rule['КонечнаяОбёртка'] : self::inferClosingTag($обёртка);

        return array(
            'Имя'                        => $name,
            'Тип'                        => $тип,
            'Элемент'                    => $элемент,
            'КонечныйЭлемент'            => isset($rule['КонечныйЭлемент'])        ? $rule['КонечныйЭлемент']       : $элемент,
            'Результат'                  => $результат,
            'КонечныйРезультат'          => $конечный_результат,
            'Обёртка'                    => $обёртка,
            'КонечнаяОбёртка'            => $конечная_обёртка,
            'Приоритет'                  => isset($rule['Приоритет'])               ? (int)$rule['Приоритет']        : 50,
            'Алиасы'                     => isset($rule['Алиасы'])                  ? (array)$rule['Алиасы']         : array(),
            'Обработчик'                 => isset($rule['Обработчик']) && is_callable($rule['Обработчик']) ? $rule['Обработчик'] : null,

            'РежимЛишьНачала'            => !empty($rule['РежимЛишьНачала']),
            'ВнутреннииПробелы'          => !empty($rule['ВнутреннииПробелы']),
            'УбиратьВнутренниеПробелы'   => !empty($rule['УбиратьВнутренниеПробелы']),
            'РежимМаркдавэнСписков'      => !empty($rule['РежимМаркдавэнСписков']),
            'ПрекратитьОбработку'        => !empty($rule['ПрекратитьОбработку']),
            'НеСоединятьСДругими'        => !empty($rule['НеСоединятьСДругими']),

            'Аргументы'                  => !empty($rule['Аргументы']),
            'РазделительАргументов'      => isset($rule['РазделительАргументов'])   ? $rule['РазделительАргументов'] : '|',
            'ЧислоАргументов'            => isset($rule['ЧислоАргументов'])          ? (int)$rule['ЧислоАргументов']  : -1,
            'НепарситьАргументы'         => isset($rule['НепарситьАргументы'])       ? (array)$rule['НепарситьАргументы'] : array(),
            'ЗначенияАргументов'         => isset($rule['ЗначенияАргументов'])       ? (array)$rule['ЗначенияАргументов'] : array(),
        );
    }

    private static function inferClosingTag($openTag) {
        if (empty($openTag)) return '';
      
        $len  = strlen($openTag);
        $i    = 0;
        while ($i < $len && $openTag[$i] !== '<') $i++;
        if ($i >= $len) return '';

        $trimmed = rtrim($openTag);
        $tlen    = strlen($trimmed);
        if ($tlen >= 2 && $trimmed[$tlen - 2] === '/' && $trimmed[$tlen - 1] === '>') {
            return '';
        }

        $i++;
        $tagName = '';
        while ($i < $len) {
            $ch = $openTag[$i];
            if ($ch === ' ' || $ch === '>' || $ch === '/') break;
            $tagName .= $ch;
            $i++;
        }

        return $tagName !== '' ? '</' . $tagName . '>' : '';
    }

    private static function compile() {
        $rules = array_values(self::$rules);

        usort($rules, function($a, $b) {
            if ($a['Приоритет'] !== $b['Приоритет']) {
                return $a['Приоритет'] - $b['Приоритет'];
            }
            return strlen($b['Элемент']) - strlen($a['Элемент']);
        });

        self::$compiled_rules = $rules;
        self::$compiled       = true;
    }
}
