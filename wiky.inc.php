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
	
	// Хранилище для временно спрятанных формул
	private $formulaPlaceholders = [];
	private $formulaCounter = 0;

	public function __construct($analyze=false) {
		$this->patterns=array(
			"/\r\n/",
			//Фильтрация математических формул
			//"/\$\$ (.+?) \$\$/s",
			//"/\$ (.+?) \$/s",

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
			"/\[\[(file|img):((ht|f)tp(s?):\/\/(.+?))( (.+))*\]\]/i",	// (file|img):(http|https|ftp) — изображение	
			"/\[((news|(ht|f)tp(s?)|irc):\/\/(.+?))( (.+))\]/i",		// Другие URL с текстом
			"/\[((news|(ht|f)tp(s?)|irc):\/\/(.+?))\]/i",				  // Другие URL без текста
			"/\[\[([^|\]]+)\|([^\]]+)\]\]/",			                        // Внутренние ссылки с текстом
			"/\[\[([^\]]+)\]\]/",			                                         // Внутренние ссылки без текста
			"/\[([^\]]+)\]/",			                                         // Внутренние ссылки без текста c одинарными кавычками
	
			// Отступы
			"/[\n\r]: *.+([\n\r]:+.+)*/",					// Отступы, первый проход
			"/^:(?!:) *(.+)$/m",						// Отступы, второй проход
			"/([\n\r]:: *.+)+/",						// Вложенные отступы, первый проход
			"/^:: *(.+)$/m",						// Вложенные отступы, второй проход
	
			// Нумерованный список
			"/[\n\r]?#.+([\n|\r]#.+)+/",					// Первый проход, поиск всех блоков
			"/[\n\r]#(?!#) *(.+)(([\n\r]#{2,}.+)+)/",			// Элемент списка с подэлементами уровня 2 и более
			"/[\n\r]#{2}(?!#) *(.+)(([\n\r]#{3,}.+)+)/",			// Элемент списка с подэлементами уровня 3 и более
			"/[\n\r]#{3}(?!#) *(.+)(([\n\r]#{4,}.+)+)/",			// Элемент списка с подэлементами уровня 4 и более
	
			// Ненумерованный список
			"/[\n\r]?\*.+([\n|\r]\*.+)+/",					// Первый проход, поиск всех блоков
			"/[\n\r]\*(?!\*) *(.+)(([\n\r]\*{2,}.+)+)/",			// Элемент списка с подэлементами уровня 2 и более
			"/[\n\r]\*{2}(?!\*) *(.+)(([\n\r]\*{3,}.+)+)/",		// Элемент списка с подэлементами уровня 3 и более
			"/[\n\r]\*{3}(?!\*) *(.+)(([\n\r]\*{4,}.+)+)/",		// Элемент списка с подэлементами уровня 4 и более
	
			// Элементы списков
			"/^[#\*]+ *(.+)$/m",						// Оборачивает все элементы списка в <li/>

			// Паттерны для переноса строк <br/> удалены, так как теперь используется логика абзацев
		);
		$this->replacements=array(
			"\n",

			// Заголовки
			"<h4>$1</h4>",
			"<h3>$1</h3>",
			"<h2>$1</h2>",
			"<h1>$1</h1>",
	
			// Форматирование
			"<strong><em>$1</em></strong>",
			"<strong>$1</strong>",
			"<em>$1</em>",
	
			// Специальные элементы
			"<hr/>",
			"<img src=\"$2\" alt=\"$6\"/>",
			"<a href=\"$1\">$7</a>",
			"<a href=\"$1\">$1</a>",
			"<a href=\"?Page=$1\">$2</a>",
			"<a href=\"?Page=$1\">$1</a>",
			"<a href=\"?Page=$1\">$1</a>",
	
			// Отступы
			"\n<dl>$0\n</dl>", // Перевод строки здесь нужен для упрощения второго прохода
			"<dd>$1</dd>",
			"\n<dd><dl>$0\n</dl></dd>",
			"<dd>$1</dd>",
	
			// Нумерованный список
			"\n<ol>\n$0\n</ol>",
			"\n<li>$1\n<ol>$2\n</ol>\n</li>",
			"\n<li>$1\n<ol>$2\n</ol>\n</li>",
			"\n<li>$1\n<ol>$2\n</ol>\n</li>",
	
			// Ненумерованный список
			"\n<ul>\n$0\n</ul>",
			"\n<li>$1\n<ul>$2\n</ul>\n</li>",
			"\n<li>$1\n<ul>$2\n</ul>\n</li>",
			"\n<li>$1\n<ul>$2\n</ul>\n</li>",
	
			// Элементы списков
			"<li>$1</li>",
		);
		if($analyze) {
			foreach($this->patterns as $k=>$v) {
				$this->patterns[$k].="S";
			}
		}
		//добавления атрибуотов по умолчанию ко всем тегам вообще
		foreach ($this->allowedTags as $tag) {
    		if (!isset($this->allowedAttrs[$tag])) {
    		    $this->allowedAttrs[$tag] = [];
    		}
    		$toAdd = [];
    		if (!in_array('style', $this->allowedAttrs[$tag])) $toAdd[] = 'style';
    		if (!in_array('class', $this->allowedAttrs[$tag])) $toAdd[] = 'class';
    		if (!empty($toAdd)) {
    		    $this->allowedAttrs[$tag] = array_merge($this->allowedAttrs[$tag], $toAdd);
    		}
		}
	}
	
	//Обработка HTML в тексте
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
		
		//Обработка
		$elements = [];
		$all = $xpath->query('.//*', $root);
		foreach ($all as $el) {
			$elements[] = $el;
		}
		$elements = array_reverse($elements);
		
		foreach ($elements as $el) {
			$tagName = strtolower($el->nodeName);
			
			//Экранирования
			if (!in_array($tagName, $this->allowedTags)) {
				$text = '';
				
				foreach ($el->childNodes as $child) {
					$text .= $dom->saveHTML($child);
				}
				//Экранирования ТУТ
				$escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
				$fragment = $dom->createDocumentFragment();
				$fragment->appendXML($escaped);
				$el->parentNode->replaceChild($fragment, $el);
				continue;
			}
			
			//Фильтрация атрибутов тега
			if ($el->hasAttributes()) {
				$attrsToRemove = [];
				$allowedForTag = isset($this->allowedAttrs[$tagName]) ? $this->allowedAttrs[$tagName] : [];
				
				foreach ($el->attributes as $attr) {
					$attrName = strtolower($attr->name);
					$attrValue = $attr->value;
					
					//Удаления
					if (!in_array($attrName, $allowedForTag)) {
						$attrsToRemove[] = $attrName;
						continue;
					}
					
					//Проверка протоколов
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
	

	private function escapeMathBrackets($text) {
    	// Блочные формулы $$...$$
    	$text = preg_replace_callback('/\$\$(\s*.+?\s*)\$\$/s', function($m) {
    	    $content = $m[1];
    	    // Проверка на присутсвия квадратов
    	    if (strpos($content, '[') === false && strpos($content, ']') === false) {
    	        return $m[0];
    	    }
    	    $escaped = str_replace(['[', ']'], ['&#91;', '&#93;'], $content);
    	    return '$$' . $escaped . '$$';
    	}, $text);
    
    	// В текстовые-формулы $...$
    	$text = preg_replace_callback('/(?<!\\\)\$(\s*.+?\s*)(?<!\\\)\$/s', function($m) {
    	    $content = $m[1];
    	    if (strpos($content, '[') === false && strpos($content, ']') === false) {
    	        return $m[0];
    	    }
    	    $escaped = str_replace(['[', ']'], ['&#91;', '&#93;'], $content);
    	    return '$' . $escaped . '$';
    	}, $text);
    
    	return $text;
	}

	//Группировка текста в абзацы <p>
	private function groupParagraphs($text) {
		// Идёт разбивка текста на пустые строки
		$blocks = preg_split('/\n\s*\n/u', $text, -1, PREG_SPLIT_NO_EMPTY);
		
		$result = [];
		foreach ($blocks as $block) {
			$block = trim($block);
			if ($block === '') continue;
			
			// ПроверкаЖ содержит ли блок блочную вики разметку
			$lines = explode("\n", $block);
			$hasBlockMarkup = false;
			
			foreach ($lines as $line) {
				$trimmed = trim($line);
				if ($trimmed === '') continue;
				
				// Маркеры блочных элементов вики-разметки:
				// = Заголовки, ---- Горизонтальная линия, #/* Списки, : Отступы
				// \s* перед [#*:]+ позволяет находить списки с отступом
				if (preg_match('/^(=+|-{4,}|\s*[#*:]+)/', $trimmed)) {
					$hasBlockMarkup = true;
					break;
				}
				// Если блок уже содержит HTML-теги блочного уровня, не оборачиваем в <p>
				if (preg_match('/^<(h[1-4]|ul|ol|dl|hr|table|pre|div)/i', $trimmed)) {
					$hasBlockMarkup = true;
					break;
				}
			}
			
			if (!$hasBlockMarkup) {
				// Обычный текст: заменяем переносы строк на пробелы и оборачиваем в <p>
				$block = preg_replace('/\s*\n\s*/u', ' ', $block);
				$block = '<p>' . trim($block) . '</p>';
			}
			// Если есть блочная разметка, оставляем как есть для обработки основными паттернами
			
			$result[] = $block;
		}
		
		return implode("\n", $result);
	}
	
	// Метод парсинга
	public function parse($input, $sanitize = true) {
	    if(!empty($input)) {
	        $input = $this->escapeMathBrackets($input); // Экранирование скобок в формулах
	        
	        // Группируем абзацы ДО применения основных паттернов
	        $input = $this->groupParagraphs($input);
	        
	        $output = preg_replace($this->patterns, $this->replacements, $input); // Вики парсер
	    } else {
	        $output = false;
	    }
	
	    if ($sanitize && $output) {
	        $output = $this->sanitize($output);
	    }
	
	    return $output;
	}
}