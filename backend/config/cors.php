<?php
/**
 * Headers CORS para el frontend React.
 * - localhost / 127.0.0.1 (cualquier puerto): desarrollo.
 * - Origen http(s)://IPv4:puerto: despliegue con IP pública (misma máquina o red expuesta).
 * - Opcional: CORS_ALLOWED_ORIGINS (entorno o backend/.env), lista separada por comas de orígenes exactos.
 */

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$is_localhost = ($origin !== '' && preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin) === 1);
// Navegador en http://203.0.113.1:5173 llamando a API en http://203.0.113.1:8000
$is_public_ipv4_origin = ($origin !== '' && preg_match('#^https?://(?:\d{1,3}\.){3}\d{1,3}(:\d+)?$#', $origin) === 1);

$envList = getenv('CORS_ALLOWED_ORIGINS');
if ($envList === false || $envList === '') {
    $envList = $_ENV['CORS_ALLOWED_ORIGINS'] ?? $_SERVER['CORS_ALLOWED_ORIGINS'] ?? '';
}
if ($envList === '') {
    $envFile = dirname(__DIR__) . '/.env';
    if (is_readable($envFile)) {
        $lines = file($envFile, FILE_IGNORE_NEW_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_starts_with($line, 'CORS_ALLOWED_ORIGINS=')) {
                    continue;
                }
                $envList = trim(substr($line, strlen('CORS_ALLOWED_ORIGINS=')));
                $quoted = strlen($envList) >= 2
                    && (($envList[0] === '"' && str_ends_with($envList, '"'))
                        || ($envList[0] === "'" && str_ends_with($envList, "'")));
                if ($quoted) {
                    $envList = substr($envList, 1, -1);
                }
                break;
            }
        }
    }
}

$allowed_from_env = false;
if ($envList !== '' && $origin !== '') {
    foreach (array_map('trim', explode(',', $envList)) as $allowed) {
        if ($allowed !== '' && rtrim($origin, '/') === rtrim($allowed, '/')) {
            $allowed_from_env = true;
            break;
        }
    }
}

if ($is_localhost || $is_public_ipv4_origin || $allowed_from_env) {
    header("Access-Control-Allow-Origin: {$origin}");
}

header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
