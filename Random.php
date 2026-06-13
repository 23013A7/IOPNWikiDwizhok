<?php
// Максимально быстрое перенаправление
$data = json_decode(file_get_contents('Page/index.json'), true);
$keys = array_keys($data);
$randomPage = $keys[array_rand($keys)];

// HTTP-редирект (быстрее meta refresh в 10-50 раз)
header('Location: .?Page=' . urlencode($randomPage));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
exit;
?>