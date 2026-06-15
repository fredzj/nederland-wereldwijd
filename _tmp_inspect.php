<?php
spl_autoload_register(function ($class) {
    $p = 'NederlandWereldwijd\\';
    if (str_starts_with($class, $p)) {
        $f = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($p))) . '.php';
        if (is_file($f)) require $f;
    }
});
$c = require __DIR__ . '/config/config.php';
$d = $c['db'];
$pdo = new PDO("mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset={$d['charset']}", $d['user'], $d['password']);
$r = $pdo->query('SELECT files FROM travel_advice WHERE files IS NOT NULL AND files <> "" LIMIT 1')->fetchColumn();
$arr = json_decode($r, true);
echo json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
