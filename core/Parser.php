<?php
class Parser {

    //Разрешённые теги
    private $allowedTags = array(
        'h1', 'h2', 'h3', 'h4',
        'p', 'br', 'hr',
        'strong', 'em', 'u', 'sub', 'sup',
        'ul', 'ol', 'li',
        'dl', 'dt', 'dd',
        'a', 'img',
        'code', 'pre',
        'div', 'span', 'blockquote',
        'style',
        'table', 'thead', 'tbody', 'tfoot',
        'tr', 'th', 'td',
        'caption', 'col', 'colgroup'
    );

    //Разрешённые атрибуты тегов
    private $allowedAttrs = array(
        'a'        => array('href', 'title'),
        'img'      => array('src', 'alt', 'width', 'height'),
        'table'    => array('summary'),
        'th'       => array('colspan', 'rowspan', 'scope'),
        'td'       => array('colspan', 'rowspan'),
        'col'      => array('span'),
        'colgroup' => array('span'),
    );

    private $blockedProtocols = array('javascript', 'data', 'vbscript');

    public function __construct() {
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
        if (empty($input)) {
            return false;
        }
        $text = HookManager::apply('parse_before', $input);
        $text = HookManager::apply('parse_text', $text);
        $text = HookManager::apply('parse_after', $text);
        $text = $this->sanitize($text);
        return $text;
    }
  
    private function sanitize($html) {
        if (empty($html)) return $html;

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $wrapped = '<?xml encoding="UTF-8"><div id="parser-root">' . $html . '</div>';
        @$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $root  = $dom->getElementById('parser-root');
        if (!$root) {
            return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
        }

        $elements = array();
        $all = $xpath->query('.//*', $root);
        foreach ($all as $el) {
            $elements[] = $el;
        }
        $elements = array_reverse($elements);

        foreach ($elements as $el) {
            $tagName = strtolower($el->nodeName);

            if (!in_array($tagName, $this->allowedTags)) {
                $text = '';
                foreach ($el->childNodes as $child) {
                    $text .= $dom->saveHTML($child);
                }
                $escaped  = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
                $fragment = $dom->createDocumentFragment();
                $fragment->appendXML($escaped);
                $el->parentNode->replaceChild($fragment, $el);
                continue;
            }

            if ($el->hasAttributes()) {
                $attrsToRemove  = array();
                $allowedForTag  = isset($this->allowedAttrs[$tagName])
                    ? $this->allowedAttrs[$tagName]
                    : array();

                foreach ($el->attributes as $attr) {
                    $attrName  = strtolower($attr->name);
                    $attrValue = $attr->value;

                    if (!in_array($attrName, $allowedForTag)) {
                        $attrsToRemove[] = $attrName;
                        continue;
                    }

                    if (in_array($attrName, array('href', 'src', 'action', 'formaction'))) {
                        $clean = strtolower(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $attrValue)));
                        foreach ($this->blockedProtocols as $proto) {
                            if (preg_match('/^\s*' . preg_quote($proto, '/') . '\s*:/i', $clean)) {
                                $attrsToRemove[] = $attrName;
                                break;
                            }
                        }
                    }
                }

                foreach ($attrsToRemove as $attrName) {
                    $el->removeAttribute($attrName);
                }
            }
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }
        return $result;
    }
}
