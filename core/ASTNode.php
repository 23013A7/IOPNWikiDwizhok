<?php
class ASTNode {

    const TYPE_DOCUMENT  = 'Document';
    const TYPE_TEXT      = 'Text';
    const TYPE_RAW       = 'Raw';
    const TYPE_PARAGRAPH = 'Paragraph';

    public $type;

    public $children = array();

    public $parent = null;
  
    public $attrs = array();

    public $value = '';

    public function __construct($type, $value = '', $attrs = array()) {
        $this->type  = $type;
        $this->value = $value;
        $this->attrs = $attrs;
    }

    public function addChild(ASTNode $node) {
        $node->parent  = $this;
        $this->children[] = $node;
        return $this;
    }

    public function addText($text) {
        if ($text === '' || $text === null) return $this;
        return $this->addChild(new ASTNode(self::TYPE_TEXT, $text));
    }

    public function addRaw($html) {
        if ($html === '' || $html === null) return $this;
        return $this->addChild(new ASTNode(self::TYPE_RAW, $html));
    }

    public function hasChildren() {
        return !empty($this->children);
    }

    public function isLeaf() {
        return $this->type === self::TYPE_TEXT || $this->type === self::TYPE_RAW;
    }

    public function attr($key, $default = null) {
        return isset($this->attrs[$key]) ? $this->attrs[$key] : $default;
    }

    public function setAttr($key, $value) {
        $this->attrs[$key] = $value;
        return $this;
    }

    public function toPlainText() {
        if ($this->isLeaf()) {
            return $this->value;
        }
        $result = '';
        foreach ($this->children as $child) {
            $result .= $child->toPlainText();
        }
        return $result;
    }

    public function dump($indent = 0) {
        $pad = str_repeat('  ', $indent);
        if ($this->isLeaf()) {
            return $pad . '[' . $this->type . '] ' . json_encode($this->value, JSON_UNESCAPED_UNICODE) . "\n";
        }
        $out = $pad . '[' . $this->type . ']';
        if (!empty($this->attrs)) {
            $out .= ' ' . json_encode($this->attrs, JSON_UNESCAPED_UNICODE);
        }
        $out .= "\n";
        foreach ($this->children as $child) {
            $out .= $child->dump($indent + 1);
        }
        return $out;
    }
}
