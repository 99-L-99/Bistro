<?php
declare(strict_types=1);
// Local: localhost / bistro_devops / root / senha vazia (padrão XAMPP).
// InfinityFree: substitua pelos dados exatos exibidos no painel da hospedagem.
$dbHost = 'localhost';
$dbName = 'bistro';
$dbUser = 'root';
$dbPass = '';
try {
    $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Erro de conexão: '.$e->getMessage());
    http_response_code(500);
    exit('Não foi possível conectar ao banco. Confira config/database.php e importe database.sql.');
}
