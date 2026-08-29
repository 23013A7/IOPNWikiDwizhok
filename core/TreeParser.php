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
                case 'open':       $this->handleOpen($token);      break;
                case 'close':      $this->handleClose($token);     break;
                case 'block_line': $this->handleBlockLine($token); break;
                case 'list_item':  $this->handleListItem($token);  break;
                case 'text':       $this->handleText($token);      break;
                case 'newline':    $this->handleNewline();         break;
                case 'blank_line': $this->handleBlankLine();       break;
            }
        }

        $this->flushParagraph();
        return $this->root;
    }

    private function handleOpen(array $token) {
        $rule = $token['rule'];
        $isBlock = $rule['Тип'] === 'Блок' || $rule['Тип'] === 'МногострочныйБлок';

        if ($isBlock) {
            $this->flushParagraph();
        }

        $node = new ASTNode($rule['Имя'], '', array('rule' => $rule));

        if (!$isBlock && $this->paragraph !== null) {
            $this->paragraph->addChild($node);
        } else {
            $this->current->addChild($node);
        }

        $this->nodeStack[] = array(
            'node'      => $this->current,
            'paragraph' => $this->paragraph,
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
            $last = $children[$count - 1];
            $isList = substr($last->type, -5) === '_List';
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

        $item = new ASTNode($rule['Имя'] . '_Item', '', array(
            'rule'   => $rule,
            'indent' => $indent,
        ));
        $item->addText($token['value']);
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
        $text = trim($this->paragraph->toPlainText());
        if ($text !== '') {
            $this->current->addChild($this->paragraph);
        }
        $this->paragraph = null;
    }
}
