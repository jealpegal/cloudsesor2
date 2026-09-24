<?php
/**
 * Conexión PDO.
 *
 * Local: si existe backend/.env.local o config/db.local.php, se usa MySQL.
 * Producción: si no hay archivos locales y DATABASE_URL es postgresql://, se usa PostgreSQL.
 *
 * Uso: require_once __DIR__ . '/../config/db.php';
 * La variable $pdo queda disponible.
 */

db_load_env_file(dirname(__DIR__) . '/.env', false);
db_load_env_file(dirname(__DIR__) . '/.env.local', true);

$localFile = __DIR__ . '/db.local.php';
$useLocalMysql = is_readable(dirname(__DIR__) . '/.env.local') || is_readable($localFile);
$databaseUrl = db_env('DATABASE_URL');
$isPostgresUrl = (bool) preg_match('#^postgres(ql)?://#i', $databaseUrl);

if (!$useLocalMysql && $isPostgresUrl) {
    $pg = db_parse_database_url($databaseUrl);
    $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $pg['host'], $pg['port'], $pg['name']);
    if ($pg['sslmode'] !== '') {
        $dsn .= ';sslmode=' . $pg['sslmode'];
    }
    if ($pg['channel_binding'] !== '') {
        $dsn .= ';channel_binding=' . $pg['channel_binding'];
    }
    $db_user = $pg['user'];
    $db_pass = $pg['pass'];
} else {
    $db_host = db_env('DB_HOST') !== '' ? db_env('DB_HOST') : 'localhost';
    $db_name = db_env('DB_NAME') !== '' ? db_env('DB_NAME') : 'cloudsensor';
    $db_user = db_env('DB_USER') !== '' ? db_env('DB_USER') : 'root';
    $db_pass = db_env('DB_PASS');
    $db_port = db_env('DB_PORT') !== '' ? db_env('DB_PORT') : '3306';
    if (is_readable($localFile)) {
        require $localFile;
    }
    $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
}

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

function db_env(string $key): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? '';
    }
    return is_string($value) ? $value : '';
}

function db_load_env_file(string $path, bool $override): void
{
    if (!is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $value = trim(substr($line, $eq + 1));
        $quoted = strlen($value) >= 2
            && (($value[0] === '"' && str_ends_with($value, '"'))
                || ($value[0] === "'" && str_ends_with($value, "'")));
        if ($quoted) {
            $value = substr($value, 1, -1);
        }
        if ($key === '' || (!$override && db_env($key) !== '')) {
            continue;
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

function db_parse_database_url(string $url): array
{
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        throw new RuntimeException('DATABASE_URL inválida');
    }
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
    }
    $driver = str_starts_with(strtolower($parts['scheme'] ?? ''), 'postgres') ? 'pgsql' : 'mysql';
    return [
        'user' => isset($parts['user']) ? rawurldecode($parts['user']) : '',
        'pass' => isset($parts['pass']) ? rawurldecode($parts['pass']) : '',
        'host' => $parts['host'],
        'port' => isset($parts['port']) ? (int) $parts['port'] : ($driver === 'pgsql' ? 5432 : 3306),
        'name' => isset($parts['path']) ? rawurldecode(ltrim($parts['path'], '/')) : '',
        'sslmode' => isset($query['sslmode']) ? (string) $query['sslmode'] : '',
        'channel_binding' => isset($query['channel_binding']) ? (string) $query['channel_binding'] : '',
    ];
}

function db_json_array(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || $value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function db_last_insert_id(PDO $pdo, string $table): int
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
        return (int) $pdo->lastInsertId($table . '_id_seq');
    }
    return (int) $pdo->lastInsertId();
}
