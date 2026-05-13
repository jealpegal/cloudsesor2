<?php
/**
 * Router para el servidor built-in de PHP.
 * Local:  php -S localhost:8000 -t backend router.php
 * Red / IP pública:  php -S 0.0.0.0:8000 -t backend router.php  (y NAT en el router)
 *
 * Redirige /api/* a api/index.php y pasa el path como GET.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/api(?|$|/(.*)$)#', $uri, $m)) {
    $_GET['path'] = isset($m[1]) ? $m[1] : '';
    include __DIR__ . '/api/index.php';
    return true;
}

// Si no es /api, devolver 404 o servir archivos estáticos
return false;