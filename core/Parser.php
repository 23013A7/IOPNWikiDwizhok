<?php
class Parser {

    private $tokenizer;
    private $treeParser;
    private $renderer;

    private $allowedTags = array(
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'p', 'br', 'hr',
        'strong', 'em', 'u', 'sub', 'sup', 's',
        'ul', 'ol', 'li',
        'dl', 'dt', 'dd',
        'a', 'img',
        'code', 'pre',
        'div', 'span', 'blockquote',
        'table', 'thead', 'tbody', 'tfoot',
        'tr', 'th', 'td',
        'caption', 'col', 'colgroup',
        'details', 'summary',
        'mark',
    );

    private $allowedAttrs = array(
        'a'        => array('href', 'title', 'target'),
        'img'      => array('src', 'alt', 'width', 'height'),
        'table'    => array('summary'),
        'th'       => array('colspan', 'rowspan', 'scope'),
        'td'       => array('colspan', 'rowspan'),
        'col'      => array('span'),
        'colgroup' => array('span'),
        'details'  => array('open'),
    );

    private $blockedProtocols = array('javascript', 'data', 'vbscript');

    public function __construct() {
        $this->tokenizer  = new Tokenizer();
        $this->treeParser = new TreeParser();
        $this->renderer   = new Renderer();

        $this->allowedTags  = HookManager::apply('parser_allowed_tags',  $this->allowedTags);
        $this->allowedAttrs = HookManager::apply('parser_allowed_attrs', $this->allowedAttrs);

        foreach ($this->allowedTags as $tag) {
            if (!isset($this->allowedAttrs[$tag])) {
                $this->allowedAttrs[$tag] = array();
            }
            if (!in_array('style', $this->allowedAttrs[$tag])) {
                $this->allowedAttrs[$tag][] = 'style';
            }
            if (!in_array('class', $this->allowedAttrs[$tag])) {
                $this->allowedAttrs[$tag][] = 'class';
            }
        }
    }

    public function parse($input) {
        if (empty($input)) return false;

        $input = str_replace("\r\n", "\n", $input);
        $input = str_replace("\r", "\n", $input);

        $input = HookManager::apply('parse_before', $input);

        $tokens = $this->tokenizer->tokenize($input);

        $tokens = HookManager::apply('parse_tokens', $tokens);

        $ast = $this->treeParser->build($tokens);

        $ast = HookManager::apply('parse_ast', $ast);

        $html = $this->renderer->render($ast);

        $html = HookManager::apply('parse_after', $html);

        $html = $this->sanitize($html);

        return $html;
    }

    public function debugAST($input) {
        $input  = str_replace(array("\r\n", "\r"), "\n", $input);
        $tokens = $this->tokenizer->tokenize($input);
        $ast    = $this->treeParser->build($tokens);
        return $ast->dump();
    }

    public function debugTokens($input) {
        $input = str_replace(array("\r\n", "\r"), "\n", $input);
        return $this->tokenizer->tokenize($input);
    }

    private function sanitize($html) {
        if (empty($html)) return $html;

        libxml_use_internal_errors(true);
        $dom     = new DOMDocument();
        $wrapped = '<?xml encoding="UTF-8"><div id="parser-root">' . $html . '</div>';
        @$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $root  = $dom->getElementById('parser-root');
        if (!$root) {
            return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
        }

        $elements = array();
        $all      = $xpath->query('.//*', $root);
        foreach ($all as $el) $elements[] = $el;
        $elements = array_reverse($elements);

        foreach ($elements as $el) {
            $tag = strtolower($el->nodeName);

            if (!in_array($tag, $this->allowedTags)) {
                $inner    = '';
                foreach ($el->childNodes as $child) $inner .= $dom->saveHTML($child);
                $fragment = $dom->createDocumentFragment();
                @$fragment->appendXML(htmlspecialchars($inner, ENT_QUOTES, 'UTF-8'));
                $el->parentNode->replaceChild($fragment, $el);
                continue;
            }

            if ($el->hasAttributes()) {
                $remove  = array();
                $allowed = isset($this->allowedAttrs[$tag]) ? $this->allowedAttrs[$tag] : array();

                foreach ($el->attributes as $attr) {
                    $name = strtolower($attr->name);
                    $val  = $attr->value;

                    if (!in_array($name, $allowed)) {
                        $remove[] = $name;
                        continue;
                    }

                    if (in_array($name, array('href', 'src', 'action'))) {
                        $clean = strtolower(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $val)));
                        foreach ($this->blockedProtocols as $proto) {
                            if (strpos($clean, $proto . ':') === 0) {
                                $remove[] = $name;
                                break;
                            }
                        }
                    }
                }

                foreach ($remove as $name) $el->removeAttribute($name);
            }
        }

        $result = '';
        foreach ($root->childNodes as $child) $result .= $dom->saveHTML($child);
        return $result;
    }
}
