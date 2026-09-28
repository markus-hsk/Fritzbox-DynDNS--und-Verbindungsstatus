<?php
declare(strict_types=1);

final class Database
{
    public static function connect(array $config): PDO
    {
        $driver = $config['db']['driver'];

        if ($driver === 'sqlite') {
            $path = $config['db']['sqlite_path'];
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $path);
        } elseif ($driver === 'mysql') {
            $m = $config['db']['mysql'];
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $m['host'],
                $m['port'],
                $m['dbname'],
                $m['charset']
            );
            $pdo = new PDO($dsn, $m['user'], $m['pass']);
        } else {
            throw new RuntimeException("Unbekannter DB-Treiber: {$driver}");
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        self::initSchema($pdo, $driver);

        return $pdo;
    }

    private static function initSchema(PDO $pdo, string $driver): void
    {
        $autoIncrement = $driver === 'sqlite'
            ? 'INTEGER PRIMARY KEY AUTOINCREMENT'
            : 'INT PRIMARY KEY AUTO_INCREMENT';
        $datetime = $driver === 'sqlite' ? 'TEXT' : 'DATETIME';

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ip_history (
                id {$autoIncrement},
                ip_address VARCHAR(45) NOT NULL,
                ip_version INT NOT NULL,
                started_at {$datetime} NOT NULL,
                last_confirmed_at {$datetime} NOT NULL,
                ended_at {$datetime} NULL,
                source VARCHAR(50) NOT NULL DEFAULT 'fritzbox'
            )
        ");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS connectivity_log (
                id {$autoIncrement},
                status VARCHAR(10) NOT NULL,
                checked_ip VARCHAR(45) NULL,
                started_at {$datetime} NOT NULL,
                last_seen_at {$datetime} NOT NULL,
                ended_at {$datetime} NULL
            )
        ");
    }
}
