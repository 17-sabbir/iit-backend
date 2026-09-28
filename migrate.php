<?php
/**
 * Run backend database migrations.
 * Usage: php migrate.php
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Dhaka');

$host = getenv('DB_HOST') ?: 'localhost';
$database = getenv('DB_NAME') ?: 'iit_shelf';
$username = getenv('DB_USER') ?: 'iit_user';
$password = getenv('DB_PASSWORD') ?: 'iit_password';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
];

try {
    $server = new PDO("mysql:host={$host};charset=utf8mb4", $username, $password, $options);
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $db = new PDO("mysql:host={$host};dbname={$database};charset=utf8mb4", $username, $password, $options);
    $db->exec("SET time_zone = '+06:00'");
    $db->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (' .
        'version VARCHAR(255) PRIMARY KEY, ' .
        'checksum CHAR(64) NOT NULL, ' .
        'applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP' .
        ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $migrationDir = __DIR__ . DIRECTORY_SEPARATOR . 'migrations';
    $files = glob($migrationDir . DIRECTORY_SEPARATOR . '*.sql');
    sort($files, SORT_STRING);

    foreach ($files as $file) {
        $version = basename($file);
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Unable to read migration: {$version}");
        }

        $checksum = hash('sha256', $sql);
        $check = $db->prepare('SELECT checksum FROM schema_migrations WHERE version = :version');
        $check->execute([':version' => $version]);
        $applied = $check->fetchColumn();

        if ($applied !== false) {
            if (!hash_equals((string)$applied, $checksum)) {
                throw new RuntimeException("Migration checksum changed: {$version}");
            }
            echo "Skipped {$version}\n";
            continue;
        }

        echo "Applying {$version}...\n";
        $db->exec($sql);
        $insert = $db->prepare(
            'INSERT INTO schema_migrations (version, checksum) VALUES (:version, :checksum)'
        );
        $insert->execute([
            ':version' => $version,
            ':checksum' => $checksum,
        ]);
        echo "Applied {$version}\n";
    }

    echo "Database migrations completed successfully.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Migration failed: {$exception->getMessage()}\n");
    exit(1);
}
