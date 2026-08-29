<?php
class Tokenizer {

    private $input  = '';

    private $pos    = 0;

    private $len    = 0;

    private $tokens = array();

    private $stack  = array();

    private $rules  = array();

    private $inlineRules = array();

    private $blockRules  = array();

    private $multiRules  = array();

    private $listRules   = array();

    public function tokenize($input) {
        $this->input  = $input;
        $this->pos    = 0;
        $this->len    = strlen($input);
        $this->tokens = array();
        $this->stack  = array();

        $this->loadRules();
        $this->run();

        return $this->tokens;
    }

    private function loadRules() {
        $all = RuleRegistry::getRules();

        $this->rules       = $all;
        $this->inlineRules = array();
        $this->blockRules  = array();
        $this->multiRules  = array();
        $this->listRules   = array();

        foreach ($all as $rule) {
            switch ($rule['Тип']) {
                case 'Строка':
                    $this->inlineRules[] = $rule;
                    break;
                case 'Блок':
                    $this->blockRules[] = $rule;
                    break;
                case 'МногострочныйБлок':
                    $this->multiRules[] = $rule;
                    break;
                case 'Список':
                    $this->listRules[] = $rule;
                    break;
            }
        }
    }

    private function run() {
        while ($this->pos < $this->len) {
            if ($this->atLineStart()) {
                if ($this->tryMultilineClose()) continue;
                if ($this->tryMultilineOpen()) continue;
                if ($this->tryListRule())      continue;
                if ($this->tryBlockRule())     continue;
            }

            $ch = $this->input[$this->pos];

            if ($ch === "\n") {
                $this->addToken('newline', null, "\n");
                $this->pos++;

                if ($this->pos < $this->len && $this->input[$this->pos] === "\n") {
                    $this->addToken('blank_line', null, "\n");
                    $this->pos++;
                }
                continue;
            }

            if ($this->tryStackClose()) continue;

            if ($this->tryInlineRule()) continue;

            $this->appendText($ch);
            $this->pos++;
        }

        while (!empty($this->stack)) {
            $frame = array_pop($this->stack);
            $this->addToken('close', $frame['rule']);
        }
    }

    private function tryMultilineClose() {
        if (empty($this->stack)) return false;

        $top = end($this->stack);
        if ($top['rule']['Тип'] !== 'МногострочныйБлок') return false;

        $closing = $top['closing'];

        if ($top['rule']['ВнутреннииПробелы']) {
            $closing = ' ' . ltrim($closing);
        }

        if ($this->matchAt($this->pos, $closing)) {
            $this->pos += strlen($closing);
            $this->skipToLineEnd();
            array_pop($this->stack);
            $this->addToken('close', $top['rule']);
            return true;
        }

        return false;
    }
  
    private function tryMultilineOpen() {
        foreach ($this->multiRules as $rule) {
            $elem = $rule['Элемент'];

            if ($rule['ВнутреннииПробелы']) {
                if (!$this->matchAt($this->pos, $elem)) continue;
                $after = $this->pos + strlen($elem);
                if ($after < $this->len && $this->input[$after] !== "\n"
                    && !($this->input[$after] === ' ' && ($after + 1 >= $this->len || $this->input[$after + 1] === "\n"))
                ) continue;
            } else {
                if (!$this->matchAt($this->pos, $elem)) continue;
            }

            $this->pos += strlen($elem);
            $this->skipToLineEnd();

            $closing = $rule['КонечныйЭлемент'];
            $this->stack[] = array('rule' => $rule, 'closing' => $closing);
            $this->addToken('open', $rule);
            return true;
        }
        return false;
    }

    private function tryListRule() {
        $indent = 0;
        $p      = $this->pos;
        while ($p < $this->len && $this->input[$p] === ' ') {
            $indent++;
            $p++;
        }

        foreach ($this->listRules as $rule) {
            $elem = $rule['Элемент'];

            if (!$this->matchAt($p, $elem)) continue;

            $after = $p + strlen($elem);

            if ($rule['ВнутреннииПробелы']) {
                if ($after >= $this->len || $this->input[$after] !== ' ') continue;
                $after++;
            }

            $this->pos = $after;

            $content = $this->readToLineEnd();

            $this->addToken('list_item', $rule, $content, array('indent' => $indent));
            return true;
        }

        return false;
    }

    private function tryBlockRule() {
        foreach ($this->blockRules as $rule) {
            $elem = $rule['Элемент'];

            if (!$this->matchAt($this->pos, $elem)) continue;

            $startPos = $this->pos;
            $this->pos += strlen($elem);

            if ($rule['РежимЛишьНачала']) {
                if ($rule['ВнутреннииПробелы']) {
                    if ($this->pos >= $this->len || $this->input[$this->pos] !== ' ') {
                        $this->pos = $startPos;
                        continue;
                    }
                    $this->pos++;
                }
                $content = $this->readToLineEnd();
                $this->addToken('block_line', $rule, $content);
                return true;
            }

            if ($rule['ВнутреннииПробелы']) {
                if ($this->pos >= $this->len || $this->input[$this->pos] !== ' ') {
                    $this->pos = $startPos;
                    continue;
                }
                $this->pos++;
            }

            $closing = $rule['КонечныйЭлемент'];
            if ($rule['ВнутреннииПробелы']) {
                $closing = ' ' . $closing;
            }

            $lineEnd = $this->findLineEnd($this->pos);
            $closePos = $this->findBefore($closing, $this->pos, $lineEnd);

            if ($closePos === false) {
                $this->pos = $startPos;
                continue;
            }

            $content = substr($this->input, $this->pos, $closePos - $this->pos);
            $this->pos = $closePos + strlen($closing);
            $this->skipToLineEnd();

            $this->addToken('open', $rule);
            $this->addToken('text', null, $content);
            $this->addToken('close', $rule);
            return true;
        }
        return false;
    }

    private function tryStackClose() {
        if (empty($this->stack)) return false;

        $top     = end($this->stack);
        $closing = $top['closing'];

        if ($top['rule']['ВнутреннииПробелы']) {
            $closing = ' ' . $closing;
        }

        if ($this->matchAt($this->pos, $closing)) {
            $this->pos += strlen($closing);
            array_pop($this->stack);
            $this->addToken('close', $top['rule']);
            return true;
        }

        return false;
    }

    private function tryInlineRule() {
        foreach ($this->inlineRules as $rule) {
            $variants = array_merge(array($rule['Элемент']), $rule['Алиасы']);

            foreach ($variants as $elem) {
                if (empty($elem)) continue;
                if (!$this->matchAt($this->pos, $elem)) continue;

                if ($rule['ВнутреннииПробелы']) {
                    $after = $this->pos + strlen($elem);
                    if ($after >= $this->len || $this->input[$after] !== ' ') continue;
                }

                $this->pos += strlen($elem);

                if ($rule['ВнутреннииПробелы']) {
                    $this->pos++;
                }

                if ($rule['РежимЛишьНачала']) {
                    $content = $this->readToLineEnd();
                    $this->addToken('open', $rule);
                    $this->addToken('text', null, $content);
                    $this->addToken('close', $rule);
                    return true;
                }

                $closing = $rule['КонечныйЭлемент'];
                if ($rule['ВнутреннииПробелы']) {
                    $closing = ' ' . $closing;
                }

                $this->stack[] = array(
                    'rule'    => $rule,
                    'closing' => $rule['КонечныйЭлемент'],
                );
                $this->addToken('open', $rule);
                return true;
            }
        }
        return false;
    }

    private function atLineStart() {
        if ($this->pos === 0) return true;
        return $this->pos > 0 && $this->input[$this->pos - 1] === "\n";
    }

    private function matchAt($pos, $needle) {
        $len = strlen($needle);
        if ($len === 0) return false;
        if ($pos + $len > $this->len) return false;
        return substr($this->input, $pos, $len) === $needle;
    }

    private function findBefore($needle, $from, $limit) {
        $nlen = strlen($needle);
        for ($i = $from; $i <= $limit - $nlen; $i++) {
            if (substr($this->input, $i, $nlen) === $needle) return $i;
        }
        return false;
    }

    private function findLineEnd($from) {
        $p = $from;
        while ($p < $this->len && $this->input[$p] !== "\n") $p++;
        return $p;
    }

    private function readToLineEnd() {
        $start = $this->pos;
        while ($this->pos < $this->len && $this->input[$this->pos] !== "\n") {
            $this->pos++;
        }
        return substr($this->input, $start, $this->pos - $start);
    }

    private function skipToLineEnd() {
        while ($this->pos < $this->len && $this->input[$this->pos] !== "\n") {
            $this->pos++;
        }
    }

    private function appendText($ch) {
        $last = end($this->tokens);
        if ($last && $last['type'] === 'text') {
            $this->tokens[count($this->tokens) - 1]['value'] .= $ch;
        } else {
            $this->addToken('text', null, $ch);
        }
    }

    private function addToken($type, $rule = null, $value = '', $extra = array()) {
        $token = array(
            'type'  => $type,
            'rule'  => $rule,
            'value' => $value,
        );
        if (!empty($extra)) {
            $token = array_merge($token, $extra);
        }
        $this->tokens[] = $token;
    }
}
