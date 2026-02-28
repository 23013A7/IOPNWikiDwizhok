<?php

// Настройки
$directories = ['Page/Pages', 'Page/Save'];
$extension = 'iopnwiki';
$outputFile = 'Page/index.json';
$result = [];

//значения по умолчанию
$defaultData = [
    'views' => false,
    'data_create' => false,
    'data_update' => false,
    'author' => false,
    'status' => false,
    'data_status' => false,
    'version' => false
];

//фунцкия получения метаданных из статьи
function getFirstLine($filePath) {
    $handle = fopen($filePath, "r");
    if ($handle) {
        $line = fgets($handle);
        fclose($handle);
        
        //забыл что тут
        if (substr($line, 0, 3) == "\xEF\xBB\xBF") {
            $line = substr($line, 3);
        }
        
        return trim($line);
    }
    return "";
}

//функция для json
function parseJsonLine($line) {
    //проверка на то что строка вообще json
    if ($line[0] !== '{') {
        return null;
    }
    
    $decoded = json_decode($line, true);
    
    //проверка что это не сломаный json и это ассоциативный массив
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return $decoded;
    }
    
    return null;
}

//проход по деректориям
foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        echo "Папка {$directory} не найдена, пропускаем...\n";
        continue; 
    }

    //пепеход по папкам для получения из них жэтих
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        //проверка на то что это имено файл иопнвики
        if ($file->isFile() && $file->getExtension() === $extension) {
            
            $filePath = $file->getPathname();
            $fileName = $file->getBasename('.' . $extension); //имя без расширения
            
            $firstLine = getFirstLine($filePath);
            $jsonData = parseJsonLine($firstLine);
            
            if ($jsonData !== null) {
                $articleData = array_merge($defaultData, $jsonData);
            } else {
                $articleData = $defaultData;
            }
            
            $result[$fileName] = $articleData;
        }
    }
}

//кодирование в json
$jsonContent = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

//сахранения в файл
if (file_put_contents($outputFile, $jsonContent) !== false) {
    echo "Записано в: {$outputFile}\n";
    echo "Найдено статей: " . count($result) . "\n";
} else {
    echo "Ошибка: не удалось записать файл {$outputFile}\n";
}

?>