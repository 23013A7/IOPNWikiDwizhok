<?php
class Renderer {

    public function render(ASTNode $node) {
        switch ($node->type) {
            case ASTNode::TYPE_DOCUMENT:
                return $this->renderChildren($node);

            case ASTNode::TYPE_TEXT:
                return htmlspecialchars($node->value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            case ASTNode::TYPE_RAW:
                return $node->value;

            case ASTNode::TYPE_PARAGRAPH:
                $inner = trim($this->renderChildren($node));
                if ($inner === '') return '';
                return '<p>' . $inner . '</p>' . "\n";

            default:
                return $this->renderNode($node);
        }
    }

    private function renderNode(ASTNode $node) {
        $rule = $node->attr('rule');

        if ($rule && substr($node->type, -5) === '_List') {
            return $this->renderList($node, $rule);
        }

        if (!$rule) {
            return $this->renderChildren($node);
        }

        if ($rule['Обработчик'] !== null) {
            return $this->renderWithHandler($node, $rule);
        }

        if ($rule['Аргументы']) {
            return $this->renderWithArgs($node, $rule);
        }

        return $this->renderSimple($node, $rule);
    }

    private function renderSimple(ASTNode $node, array $rule) {
        if ($rule['ПрекратитьОбработку']) {
            $inner = htmlspecialchars($node->toPlainText(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        } else {
            $inner = $this->renderChildren($node);
        }

        if ($rule['УбиратьВнутренниеПробелы']) {
            $inner = trim($inner);
        }

        return $rule['Результат'] . $inner . $rule['КонечныйРезультат'];
    }

    private function renderWithHandler(ASTNode $node, array $rule) {
        $rawText = $node->toPlainText();
        $args    = $rule['Аргументы'] ? $this->splitArgs($rawText, $rule) : array();
        $args    = $this->applyArgDefaults($args, $rule);
        $result  = call_user_func($rule['Обработчик'], $args, $rawText, $rule);
        return is_string($result) ? $result : '';
    }

    private function renderWithArgs(ASTNode $node, array $rule) {
        $rawText  = $node->toPlainText();
        $args     = $this->splitArgs($rawText, $rule);
        $args     = $this->applyArgDefaults($args, $rule);
        $rendered = array();

        foreach ($args as $i => $val) {
            if (in_array($i, $rule['НепарситьАргументы'])) {
                $rendered[$i] = htmlspecialchars(trim($val), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            } else {
                $rendered[$i] = $this->parseInline(trim($val));
            }
        }

        $result = $rule['Результат'];
        foreach ($rendered as $i => $val) {
            $result = str_replace('%' . $i, $val, $result);
        }

        $result = $this->cleanUnusedPlaceholders($result);
        return $result . $rule['КонечныйРезультат'];
    }

    private function splitArgs($rawText, array $rule) {
        $sep     = $rule['РазделительАргументов'];
        $maxArgs = $rule['ЧислоАргументов'];
        $parts   = $maxArgs === -1
            ? explode($sep, $rawText)
            : explode($sep, $rawText, $maxArgs + 1);
        $args = array();
        foreach ($parts as $i => $part) $args[$i] = $part;
        return $args;
    }

    private function applyArgDefaults(array $args, array $rule) {
        foreach ($rule['ЗначенияАргументов'] as $i => $default) {
            if (!isset($args[$i]) || trim($args[$i]) === '') {
                if (strlen($default) >= 2 && $default[0] === '%' && is_numeric(substr($default, 1))) {
                    $ref = (int)substr($default, 1);
                    $args[$i] = isset($args[$ref]) ? $args[$ref] : '';
                } else {
                    $args[$i] = $default;
                }
            }
        }
        return $args;
    }

    private function cleanUnusedPlaceholders($str) {
        $result = '';
        $len    = strlen($str);
        $i      = 0;
        while ($i < $len) {
            if ($str[$i] === '%' && $i + 1 < $len && $str[$i + 1] >= '0' && $str[$i + 1] <= '9') {
                $i += 2;
                while ($i < $len && $str[$i] >= '0' && $str[$i] <= '9') $i++;
            } else {
                $result .= $str[$i++];
            }
        }
        return $result;
    }

    private function parseInline($text) {
        if (empty($text)) return '';
        static $inlineParser = null;
        if ($inlineParser === null && class_exists('Parser')) {
            $inlineParser = new Parser();
        }
        if ($inlineParser !== null) {
            $html = $inlineParser->parse($text);
            $html = trim($html);
            if (strncmp($html, '<p>', 3) === 0 && substr($html, -4) === '</p>') {
                $html = substr($html, 3, strlen($html) - 7);
            }
            return $html;
        }
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function renderList(ASTNode $node, array $rule) {
        if ($rule['РежимМаркдавэнСписков']) {
            return $this->renderMarkdownList($node, $rule, 0);
        }

        $html = $rule['Обёртка'] . "\n";
        foreach ($node->children as $item) {
            $inner = $this->parseInline($item->value);
            if ($rule['УбиратьВнутренниеПробелы']) $inner = trim($inner);
            $html .= $rule['Результат'] . $inner . $rule['КонечныйРезультат'] . "\n";
        }
        $html .= $rule['КонечнаяОбёртка'] . "\n";
        return $html;
    }

    private function renderMarkdownList(ASTNode $node, array $rule, $level) {
        $items = $node->children;
        $count = count($items);
        $html  = $rule['Обёртка'] . "\n";
        $i     = 0;

        while ($i < $count) {
            $item       = $items[$i];
            $indent     = $item->attr('indent', 0);
            $inner      = $this->parseInline($item->value);
            $nextIndent = ($i + 1 < $count) ? $items[$i + 1]->attr('indent', 0) : -1;

            if ($nextIndent > $indent) {
                $html .= $rule['Результат'] . $inner . "\n";
                $subNode = new ASTNode($node->type, '', $node->attrs);
                $i++;
                while ($i < $count && $items[$i]->attr('indent', 0) > $indent) {
                    $subNode->addChild($items[$i]);
                    $i++;
                }
                $html .= $this->renderMarkdownList($subNode, $rule, $level + 1);
                $html .= $rule['КонечныйРезультат'] . "\n";
            } else {
                $html .= $rule['Результат'] . $inner . $rule['КонечныйРезультат'] . "\n";
                $i++;
            }
        }

        $html .= $rule['КонечнаяОбёртка'] . "\n";
        return $html;
    }

    private function renderChildren(ASTNode $node) {
        $html = '';
        foreach ($node->children as $child) $html .= $this->render($child);
        return $html;
    }
}
