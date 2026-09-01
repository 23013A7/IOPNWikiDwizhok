<?php
class TreeParser {

    private $root;
    private $current;
    private $nodeStack = array();
    private $paragraph = null;

    public function build(array $tokens) {
        $this->root      = new ASTNode(ASTNode::TYPE_DOCUMENT);
        $this->current   = $this->root;
        $this->nodeStack = array();
        $this->paragraph = null;

        foreach ($tokens as $token) {
            switch ($token['type']) {
                case 'open':        $this->handleOpen($token);       break;
                case 'close':       $this->handleClose($token);      break;
                case 'block_line':  $this->handleBlockLine($token);  break;
                case 'list_item':   $this->handleListItem($token);   break;
                case 'text':        $this->handleText($token);       break;
                case 'newline':     $this->handleNewline();          break;
                case 'blank_line':  $this->handleBlankLine();        break;
                case 'raw_handler': $this->handleRawHandler($token); break;
            }
        }

        $this->flushParagraph();
        return $this->root;
    }

    private function handleOpen(array $token) {
        $rule    = $token['rule'];
        $isBlock = $rule['Тип'] === 'Блок' || $rule['Тип'] === 'МногострочныйБлок';

        if ($isBlock) {
            $this->flushParagraph();
        }

        $node = new ASTNode($rule['Имя'], '', array('rule' => $rule));

        if ($isBlock) {
            $this->current->addChild($node);
        } else {
            $this->ensureParagraph();
            $this->paragraph->addChild($node);
        }

        $this->nodeStack[] = array(
            'node'      => $this->current,
            'paragraph' => $this->paragraph,
            'isBlock'   => $isBlock,
        );

        $this->current   = $node;
        $this->paragraph = null;
    }

    private function handleClose(array $token) {
        if (empty($this->nodeStack)) return;

        $frame           = array_pop($this->nodeStack);
        $this->current   = $frame['node'];
        $this->paragraph = $frame['paragraph'];
    }

    private function handleRawHandler(array $token) {
        $rule = $token['rule'];
        $node = new ASTNode($rule['Имя'], $token['value'], array('rule' => $rule));

        if ($this->current !== $this->root) {
            $this->current->addChild($node);
            return;
        }

        $isBlock = false;
        if ($rule['Обработчик'] !== null) {
            $parts   = _li_split($token['value'], '|', -1);
            $first   = isset($parts[0]) ? trim($parts[0]) : '';
            $imgPrefixes = ['File:', 'file:', 'Файл:', 'файл:', 'img:', 'Img:', 'IMAGE:', 'image:'];
            $isImg = false;
            foreach ($imgPrefixes as $pfx) {
                if (strncasecmp($first, $pfx, strlen($pfx)) === 0) { $isImg = true; break; }
            }
            if ($isImg) {
                $rest = array_slice($parts, 1);
                $blockWords = ['left','right','center','слева','справа','центр','thumb','мини','mini'];
                foreach ($rest as $param) {
                    if (in_array(strtolower(trim($param)), $blockWords)) { $isBlock = true; break; }
                }
            }
        }

        if ($isBlock) {
            $this->flushParagraph();
            $this->current->addChild($node);
        } else {
            $this->ensureParagraph();
            $this->paragraph->addChild($node);
        }
    }

    private function handleBlockLine(array $token) {
        $this->flushParagraph();
        $rule = $token['rule'];
        $node = new ASTNode($rule['Имя'], '', array('rule' => $rule));
        $node->addText($token['value']);
        $this->current->addChild($node);
    }

    private function handleListItem(array $token) {
        $this->flushParagraph();
        $rule   = $token['rule'];
        $indent = isset($token['indent']) ? $token['indent'] : 0;

        $listNode = null;
        $children = $this->current->children;
        $count    = count($children);

        if ($count > 0) {
            $last     = $children[$count - 1];
            $isList   = substr($last->type, -5) === '_List';
            $sameRule = $last->attr('rule_name') === $rule['Имя'];
            $canMerge = !$rule['НеСоединятьСДругими'] || $sameRule;

            if ($isList && $sameRule && $canMerge) {
                $listNode = $last;
            }
        }

        if ($listNode === null) {
            $listNode = new ASTNode($rule['Имя'] . '_List', '', array(
                'rule'      => $rule,
                'rule_name' => $rule['Имя'],
            ));
            $this->current->addChild($listNode);
        }

        $item = new ASTNode($rule['Имя'] . '_Item', $token['value'], array(
            'rule'   => $rule,
            'indent' => $indent,
        ));
        $listNode->addChild($item);
    }

    private function handleText(array $token) {
        if ($token['value'] === '') return;

        if ($this->current !== $this->root) {
            $this->current->addText($token['value']);
        } else {
            $this->ensureParagraph();
            $this->paragraph->addText($token['value']);
        }
    }

    private function handleNewline() {
        if ($this->current !== $this->root) {
            $this->current->addText(' ');
        } elseif ($this->paragraph !== null && $this->paragraph->hasChildren()) {
            $this->paragraph->addText(' ');
        }
    }

    private function handleBlankLine() {
        $this->flushParagraph();
    }

    private function ensureParagraph() {
        if ($this->paragraph === null) {
            $this->paragraph = new ASTNode(ASTNode::TYPE_PARAGRAPH);
        }
    }

    private function flushParagraph() {
        if ($this->paragraph === null) return;
        if ($this->paragraph->hasChildren()) {
            $this->current->addChild($this->paragraph);
        }
        $this->paragraph = null;
    }
}
