<?php
/**
 * Headers CORS para el frontend React.
 * - localhost / 127.0.0.1 (cualquier puerto): desarrollo.
 * - Origen http(s)://IPv4:puerto: despliegue con IP pública (misma máquina o red expuesta).
 * - Opcional: variable de entorno CORS_ALLOWED_ORIGINS (lista separada por comas de orígenes exactos).
 */

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$is_localhost = ($origin !== '' && preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin) === 1);
// Navegador en http://203.0.113.1:5173 llamando a API en http://203.0.113.1:8000
$is_public_ipv4_origin = ($origin !== '' && preg_match('#^https?://(?:\d{1,3}\.){3}\d{1,3}(:\d+)?$#', $origin) === 1);

$allowed_from_env = false;
$envList = getenv('CORS_ALLOWED_ORIGINS');
if ($envList !== false && $envList !== '' && $origin !== '') {
    foreach (array_map('trim', explode(',', $envList)) as $allowed) {
        if ($allowed !== '' && $origin === $allowed) {
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
