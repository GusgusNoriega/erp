<?php

declare(strict_types=1);

$configPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

if (!file_exists($configPath)) {
    fwrite(STDERR, "No se encontro config/database.php\n");
    exit(1);
}

/** @var array<string, scalar> $config */
$config = require $configPath;

$host = (string) ($config['host'] ?? '127.0.0.1');
$port = (int) ($config['port'] ?? 3306);
$database = (string) ($config['database'] ?? 'erp');
$username = (string) ($config['username'] ?? 'root');
$password = (string) ($config['password'] ?? '');
$charset = (string) ($config['charset'] ?? 'utf8mb4');
$collation = (string) ($config['collation'] ?? 'utf8mb4_unicode_ci');
$safeDatabase = str_replace('`', '``', $database);

$dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);

try {
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("Error de conexion MySQL: %s\n", $exception->getMessage()));
    exit(1);
}

$pdo->exec(sprintf(
    'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
    $safeDatabase,
    $charset,
    $collation
));
$pdo->exec(sprintf('USE `%s`', $safeDatabase));
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(255) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$migrationFiles = glob(__DIR__ . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '*.sql') ?: [];
sort($migrationFiles);

$appliedRows = $pdo->query('SELECT migration FROM schema_migrations')->fetchAll();
$applied = array_flip(array_map(static fn (array $row): string => (string) $row['migration'], $appliedRows));

if ($migrationFiles === []) {
    fwrite(STDOUT, "No hay migraciones disponibles.\n");
    exit(0);
}

foreach ($migrationFiles as $file) {
    $migrationName = basename($file);

    if (isset($applied[$migrationName])) {
        fwrite(STDOUT, sprintf("Ya aplicada: %s\n", $migrationName));
        continue;
    }

    $sql = file_get_contents($file);

    if ($sql === false) {
        throw new RuntimeException(sprintf('No se pudo leer la migracion %s', $migrationName));
    }

    $statements = array_values(array_filter(array_map('trim', explode(';', $sql))));

    if ($statements === []) {
        fwrite(STDOUT, sprintf("Migracion vacia: %s\n", $migrationName));
        continue;
    }

    try {
        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        $insert = $pdo->prepare(
            'INSERT INTO schema_migrations (migration, applied_at) VALUES (:migration, NOW())'
        );
        $insert->execute(['migration' => $migrationName]);
        fwrite(STDOUT, sprintf("Aplicada: %s\n", $migrationName));
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("Error aplicando %s: %s\n", $migrationName, $exception->getMessage()));
        exit(1);
    }
}

fwrite(STDOUT, "Migraciones completadas.\n");
