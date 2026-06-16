<?php

declare(strict_types=1);

namespace PingPong\Database;

use PDO;
use PingPong\Support\Env;

/**
 * Conexión PDO única (lazy singleton) a MySQL / MariaDB.
 * Siempre con sentencias preparadas reales (EMULATE_PREPARES = false)
 * y excepciones activadas.
 */
final class Connection
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host    = Env::get('DB_HOST', '127.0.0.1');
        $port    = Env::get('DB_PORT', '3306');
        $name    = Env::get('DB_NAME', 'pingpong');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";

        self::$pdo = new PDO(
            $dsn,
            Env::get('DB_USER', 'root'),
            Env::get('DB_PASS', '') ?? '',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        return self::$pdo;
    }
}
