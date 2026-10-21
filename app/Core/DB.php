<?php
declare(strict_types=1);
namespace Core;
use PDO;
final class DB
{
    private static ?PDO $pdo = null;
    public static function connection(): PDO
    {
        if (!self::$pdo) {
            self::$pdo = new PDO('mysql:host=' . env('DB_HOST', '127.0.0.1') . ';port=' . env('DB_PORT', '3306') . ';dbname=' . env('DB_NAME', 'senn_aix') . ';charset=utf8mb4', env('DB_USER'), env('DB_PASS'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // Toda data é persistida na zona operacional configurada no PHP.
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        }
        return self::$pdo;
    }
    public static function run(string $sql, array $params = []): \PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);
        return $statement;
    }
    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }
    public static function one(string $sql, array $params = []): ?array
    {
        return self::run($sql, $params)->fetch() ?: null;
    }
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            throw $e;
        }
    }
}
