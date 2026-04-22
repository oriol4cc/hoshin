<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_NAME') ?: 'hoshin_app';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $dbname);
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    ensureRuntimeSchema($pdo, $dbname);

    return $pdo;
}

function ensureRuntimeSchema(PDO $pdo, string $dbName): void
{
    static $checked = false;
    if ($checked) {
        return;
    }

    $requiredColumns = [
        'code' => 'ALTER TABLE cards ADD COLUMN code VARCHAR(50) NULL AFTER description',
        'linked_yearly_code' => 'ALTER TABLE cards ADD COLUMN linked_yearly_code VARCHAR(50) NULL AFTER code',
    ];

    foreach ($requiredColumns as $column => $sql) {
        $existsStmt = $pdo->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = :db AND table_name = :table AND column_name = :column LIMIT 1');
        $existsStmt->execute([
            'db' => $dbName,
            'table' => 'cards',
            'column' => $column,
        ]);

        if (!$existsStmt->fetchColumn()) {
            $pdo->exec($sql);
        }
    }

    $checked = true;
}
