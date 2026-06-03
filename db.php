<?php
function get_pdo(): PDO {
    // Try DATABASE_PUBLIC_URL first, then DATABASE_URL, then individual vars
    $url = getenv('DATABASE_PUBLIC_URL') ?: getenv('DATABASE_URL');

    if ($url) {
        $parts = parse_url($url);
        $host   = $parts['host'];
        $port   = $parts['port'] ?? 5432;
        $dbname = ltrim($parts['path'], '/');
        $user   = $parts['user'];
        $pass   = $parts['pass'];
    } else {
        $host   = getenv('DB_HOST')     ?: 'localhost';
        $port   = getenv('DB_PORT')     ?: '5432';
        $dbname = getenv('DB_NAME')     ?: 'railway';
        $user   = getenv('DB_USER')     ?: 'postgres';
        $pass   = getenv('DB_PASSWORD') ?: '';
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

try {
    $pdo = get_pdo();
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}
