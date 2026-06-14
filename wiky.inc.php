<?php
/* Wiky.php - крошечная PHP-библиотека для преобразования вики-разметки в HTML
 * Автор: Тони Ляхдекорпи <toni@lygon.net>
 *
 * Использование кода разрешено на условиях любой из следующих лицензий:
 * Apache License 2.0, http://www.apache.org/licenses/LICENSE-2.0
 * Mozilla Public License 1.1, http://www.mozilla.org/MPL/1.1/
 * GNU Lesser General Public License 3.0, http://www.gnu.org/licenses/lgpl-3.0.html
 * GNU General Public License 2.0, http://www.gnu.org/licenses/gpl-2.0.html
 * Creative Commons Attribution 3.0 Unported License, http://creativecommons.org/licenses/by/3.0/
 */

class wiky {
	private $patterns, $replacements;
	
	//Разрешёные теги HTML
	private $allowedTags = [
		'h1', 'h2', 'h3', 'h4', 'p', 'br', 'hr', 'strong', 'em', 'u', 'sub', 'sup', 'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'a', 'img', 'code', 'pre', 'div', 'style', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'col', 'colgroup'
	];
	//Разрешёные атрибуты
	private $allowedAttrs = [
		'a' => ['href', 'title'],
		'img' => ['src', 'alt', 'width', 'height'],
		'table' => ['summary'],
    	'th' => ['colspan', 'rowspan', 'scope'],
    	'td' => ['colspan', 'rowspan'],
    	'col' => ['span'],
    	'colgroup' => ['span']
	];
	
	private $blockedProtocols = ['javascript', 'data', 'vbscript'];
	
	private $formulaPlaceholders = [];
	private $formulaCounter = 0;

	public function __construct($analyze=false) {
		$this->patterns=array(
			"/\r\n/",
			// Заголовки
			"/^==== (.+?) ====$/m",						// Подподзаголовок
			"/^=== (.+?) ===$/m",						// Подзаголовок
			"/^== (.+?) ==$/m",							// Заголовок
			"/^= (.+?) =$/m",							// Название
	
			// Форматирование
			"/\'\'\'\'\'(.+?)\'\'\'\'\'/s",					// Жирный курсив
			"/\'\'\'(.+?)\'\'\'/s",							// Жирный
			"/\'\'(.+?)\'\'/s",							// Курсив
	
			// Специальные элементы
			"/^----+(\s*)$/m",						// Горизонтальная линия
			// Паттерн изображений удалён - обрабатывается отдельно через посимвольный парсер
			"/\[((news|(ht|f)tp(s?)|irc):\/\/(.+?))( (.+))\]/i",		// URL с текстом
			"/\[((news|(ht|f)tp(s?)|irc):\/\/(.+?))\]/i",				  // URL без текста
			"/\[\[([^|\]]+)\|([^\]]+)\]\]/",			                        // Внутренние ссылки с текстом
			"/\[\[([^\]]+)\]\]/",			                                         // Внутренние ссылки без текста
	
			// Отступы
			"/[\n\r]: *.+([\n\r]:+.+)*/",					// Отступы, первый проход
			"/^:(?!:) *(.+)$/m",						// Отступы, второй проход
			"/([\n\r]:: *.+)+/",						// Вложенные отступы, первый проход
			"/^:: *(.+)$/m",						// Вложенные отступы, второй проход
	
			// Нумерованный список
			"/[\n\r]?#.+([\n|\r]#.+)+/",					// Первый проход, поиск всех блоков
			"/[\n\r]#(?!#) *(.+)(([\n\r]#{2,}.+)+)/",			// Элемент списка с подэлементами уровня 2+
			"/[\n\r]#{2}(?!#) *(.+)(([\n\r]#{3,}.+)+)/",			// Элемент списка с подэлементами уровня 3+
			"/[\n\r]#{3}(?!#) *(.+)(([\n\r]#{4,}.+)+)/",			// Элемент списка с подэлементами уровня 4+
	
			// Ненумерованный список
			"/[\n\r]?\*.+([\n|\r]\*.+)+/",					// Первый проход, поиск всех блоков
			"/[\n\r]\*(?!\*) *(.+)(([\n\r]\*{2,}.+)+)/",			// Элемент списка с подэлементами уровня 2+
			"/[\n\r]\*{2}(?!\*) *(.+)(([\n\r]\*{3,}.+)+)/",		// Элемент списка с подэлементами уровня 3+
			"/[\n\r]\*{3}(?!\*) *(.+)(([\n\r]\*{4,}.+)+)/",		// Элемент списка с подэлементами уровня 4+
	
			// Элементы списков
			"/^[#\*]+ *(.+)$/m",						// Оборачивает все элементы списка в <li/>
		);
		$this->replacements=array(
			"\n",
			// Заголовки
			"<h4>$1</h4>", "<h3>$1</h3>", "<h2>$1</h2>", "<h1>$1</h1>",
			// Форматирование
			"<strong><em>$1</em></strong>", "<strong>$1</strong>", "<em>$1</em>",
			// Специальные элементы
			"<hr/>",
			"<a href=\"$1\">$7</a>", "<a href=\"$1\">$1</a>",
			"<a href=\"?Page=$1\">$2</a>", "<a href=\"?Page=$1\">$1</a>",
			// Отступы
			"\n<dl>$0\n</dl>", "<dd>$1</dd>", "\n<dd><dl>$0\n</dl></dd>", "<dd>$1</dd>",
			// Нумерованный список
			"\n<ol>\n$0\n</ol>", "\n<li>$1\n<ol>$2\n</ol>\n</li>", "\n<li>$1\n<ol>$2\n</ol>\n</li>", "\n<li>$1\n<ol>$2\n</ol>\n</li>",
			// Ненумерованный список
			"\n<ul>\n$0\n</ul>", "\n<li>$1\n<ul>$2\n</ul>\n</li>", "\n<li>$1\n<ul>$2\n</ul>\n</li>", "\n<li>$1\n<ul>$2\n</ul>\n</li>",
			// Элементы списков
			"<li>$1</li>",
		);
		if($analyze) {
			foreach($this->patterns as $k=>$v) {
				$this->patterns[$k].="S";
			}
		}
		// Добавление style и class ко всем разрешённым тегам
		foreach ($this->allowedTags as $tag) {
    		if (!isset($this->allowedAttrs[$tag])) $this->allowedAttrs[$tag] = [];
    		$toAdd = [];
    		if (!in_array('style', $this->allowedAttrs[$tag])) $toAdd[] = 'style';
    		if (!in_array('class', $this->allowedAttrs[$tag])) $toAdd[] = 'class';
    		if (!empty($toAdd)) $this->allowedAttrs[$tag] = array_merge($this->allowedAttrs[$tag], $toAdd);
		}
	}
	
	// Обработка HTML в тексте (санитизация)
	private function sanitize($html) {
		if (empty($html)) return $html;
		libxml_use_internal_errors(true);
		$dom = new DOMDocument();
		$wrapped = '<?xml encoding="UTF-8"><div id="wiky-root">' . $html . '</div>';
		@$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
		libxml_clear_errors();
		
		$xpath = new DOMXPath($dom);
		$root = $dom->getElementById('wiky-root');
		if (!$root) return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
		
		$elements = [];
		$all = $xpath->query('.//*', $root);
		foreach ($all as $el) $elements[] = $el;
		$elements = array_reverse($elements);
		
		foreach ($elements as $el) {
			$tagName = strtolower($el->nodeName);
			if (!in_array($tagName, $this->allowedTags)) {
				$text = '';
				foreach ($el->childNodes as $child) $text .= $dom->saveHTML($child);
				$escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
				$fragment = $dom->createDocumentFragment();
				$fragment->appendXML($escaped);
				$el->parentNode->replaceChild($fragment, $el);
				continue;
			}
			if ($el->hasAttributes()) {
				$attrsToRemove = [];
				$allowedForTag = isset($this->allowedAttrs[$tagName]) ? $this->allowedAttrs[$tagName] : [];
				foreach ($el->attributes as $attr) {
					$attrName = strtolower($attr->name);
					$attrValue = $attr->value;
					if (!in_array($attrName, $allowedForTag)) {
						$attrsToRemove[] = $attrName;
						continue;
					}
					if (in_array($attrName, ['href', 'src', 'action', 'formaction'])) {
						$cleanValue = strtolower(trim(preg_replace('/[\x00-\x1F\x7F]/u', '', $attrValue)));
						foreach ($this->blockedProtocols as $proto) {
							if (preg_match('/^\s*' . preg_quote($proto, '/') . '\s*:/i', $cleanValue)) {
								$attrsToRemove[] = $attrName;
								break;
							}
						}
					}
				}
				foreach ($attrsToRemove as $attrName) $el->removeAttribute($attrName);
			}
		}
		$result = '';
		foreach ($root->childNodes as $child) $result .= $dom->saveHTML($child);
		return $result;
	}
	
	private function escapeMathBrackets($text) {
    	$text = preg_replace_callback('/\$\$(\s*.+?\s*)\$\$/s', function($m) {
    	    $content = $m[1];
    	    if (strpos($content, '[') === false && strpos($content, ']') === false) return $m[0];
    	    return '$$' . str_replace(['[', ']'], ['&#91;', '&#93;'], $content) . '$$';
    	}, $text);
    	$text = preg_replace_callback('/(?<!\\\)\$(\s*.+?\s*)(?<!\\\)\$/s', function($m) {
    	    $content = $m[1];
    	    if (strpos($content, '[') === false && strpos($content, ']') === false) return $m[0];
    	    return '$' . str_replace(['[', ']'], ['&#91;', '&#93;'], $content) . '$';
    	}, $text);
    	return $text;
	}

	private function groupParagraphs($text) {
		$blocks = preg_split('/\n\s*\n/u', $text, -1, PREG_SPLIT_NO_EMPTY);
		$result = [];
		foreach ($blocks as $block) {
			$block = trim($block);
			if ($block === '') continue;
			$lines = explode("\n", $block);
			$hasBlockMarkup = false;
			foreach ($lines as $line) {
				$trimmed = trim($line);
				if ($trimmed === '') continue;
				if (preg_match('/^(=+|-{4,}|\s*[#*:]+)/', $trimmed) || preg_match('/^<(h[1-4]|ul|ol|dl|hr|table|pre|div)/i', $trimmed)) {
					$hasBlockMarkup = true;
					break;
				}
			}
			if (!$hasBlockMarkup) {
				$block = preg_replace('/\s*\n\s*/u', ' ', $block);
				$block = '<p>' . trim($block) . '</p>';
			}
			$result[] = $block;
		}
		return implode("\n", $result);
	}
	
	// Обработка изображений с гибкими параметрами
	private function buildImageTag($filename, $paramString = '') {
	    $src = htmlspecialchars(trim($filename), ENT_QUOTES, 'UTF-8');
	    $params = $paramString ? array_map('trim', explode('|', $paramString)) : [];
	    $alignment = ''; $sizeClass = ''; $width = null; $caption = '';
	    
	    foreach ($params as $param) {
	        $paramLower = function_exists('mb_strtolower') ? mb_strtolower($param, 'UTF-8') : strtolower($param);
	        if (in_array($paramLower, ['слева', 'left'])) $alignment = 'left';
	        elseif (in_array($paramLower, ['справа', 'right'])) $alignment = 'right';
	        elseif (in_array($paramLower, ['центр', 'center'])) $alignment = 'center';
	        elseif (in_array($paramLower, ['мини', 'миниатюра', 'thumb', 'thumbnail', 'mini'])) $sizeClass = 'image-mini';
	        elseif (preg_match('/^(\d+)px$/i', $param, $m)) $width = (int)$m[1];
	        else $caption = htmlspecialchars($param, ENT_QUOTES, 'UTF-8');
	    }
	    
	    $classes = ['image'];
	    if ($alignment) $classes[] = "image-$alignment";
	    if ($sizeClass) $classes[] = $sizeClass;
	    $classStr = implode(' ', $classes);
	    
	    $imgAttrs = 'src="' . $src . '"';
	    if ($width) $imgAttrs .= ' width="' . $width . '"';
	    if ($sizeClass) $imgAttrs .= ' class="' . $sizeClass . '"';
	    
	    $html = '<div class="' . $classStr . '">';
	    $html .= '<img ' . $imgAttrs . '/>';
	    if ($caption !== '') $html .= '<p class="image-description">' . $caption . '</p>';
	    $html .= '</div>';
	    return $html;
	}
	
	// Посимвольный парсер изображений с поддержкой вложенных [[...]]
	private function parseImagesWithNestedBrackets($text) {
	    $result = '';
	    $pos = 0;
	    $len = strlen($text);
	    
	    while ($pos < $len) {
	        $start = strpos($text, '[[', $pos);
	        if ($start === false) {
	            $result .= substr($text, $pos);
	            break;
	        }
	        
	        $result .= substr($text, $pos, $start - $pos);
	        $remaining = substr($text, $start + 2);
	        
	        // Проверяем, начинается ли с file:, img: или файл:
	        if (preg_match('/^(file|img|файл):/iu', $remaining)) {
	            $end = $this->findClosingBrackets($text, $start + 2);
	            if ($end !== false) {
	                // Извлекаем всё между [[ и ]]
	                $content = substr($text, $start + 2, $end - $start - 2);
	                
	                // !!! ИСПРАВЛЕНИЕ ЗДЕСЬ: Удаляем префикс "File:", "img:" или "файл:" !!!
	                $content = preg_replace('/^(file|img|файл):/iu', '', $content);
	                
	                $firstPipe = strpos($content, '|');
	                if ($firstPipe === false) {
	                    $result .= $this->buildImageTag($content, '');
	                } else {
	                    $filename = substr($content, 0, $firstPipe);
	                    $paramString = substr($content, $firstPipe + 1);
	                    $result .= $this->buildImageTag($filename, $paramString);
	                }
	                $pos = $end + 2;
	            } else {
	                $result .= '[[';
	                $pos = $start + 2;
	            }
	        } else {
	            $result .= '[[';
	            $pos = $start + 2;
	        }
	    }
	    return $result;
	}
	
	// Поиск закрывающих ]] с учётом вложенности
	private function findClosingBrackets($text, $startPos) {
	    $depth = 1;
	    $pos = $startPos;
	    $len = strlen($text);
	    
	    while ($pos < $len - 1) {
	        if ($text[$pos] === '[' && $text[$pos + 1] === '[') {
	            $depth++;
	            $pos += 2;
	        } elseif ($text[$pos] === ']' && $text[$pos + 1] === ']') {
	            $depth--;
	            if ($depth === 0) return $pos;
	            $pos += 2;
	        } else {
	            $pos++;
	        }
	    }
	    return false;
	}
	
	// Главный метод парсинга
	public function parse($input, $sanitize = true) {
	    if(!empty($input)) {
	        $input = $this->escapeMathBrackets($input);
	        $input = $this->groupParagraphs($input);
	        
	        // Новый надёжный парсер изображений
	        $output = $this->parseImagesWithNestedBrackets($input);
	        
	        // Остальная вики-разметка
	        $output = preg_replace($this->patterns, $this->replacements, $output);
	    } else {
	        $output = false;
	    }
	
	    if ($sanitize && $output) {
	        $output = $this->sanitize($output);
	    }
	
	    return $output;
	}
}
