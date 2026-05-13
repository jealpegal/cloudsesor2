<?php
/**
 * Configuración de conexión a la base de datos MySQL mediante PDO.
 * Uso: require_once __DIR__ . '/../config/db.php';
 * La variable $pdo queda disponible.
 *
 * Credenciales: se leen de config/db.local.php si existe (no se sube a git),
 * o de variables de entorno DB_HOST, DB_NAME, DB_USER, DB_PASS.
 */

$db_host = getenv('DB_HOST') ?: 'localhost';
$db_name = getenv('DB_NAME') ?: 'cloudsensor';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') ?: '';

$localFile = __DIR__ . '/db.local.php';
if (file_exists($localFile)) {
    require $localFile;
}

$dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'message' => $e->getMessage()
    ]);
    exit;
}
