<?php

declare(strict_types=1);

namespace Framework\Database;

use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Thin PDO wrapper. Every query goes through prepared statements, so user
 * input is always bound, never interpolated into SQL.
 */
final class Connection
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $driver = $config['driver'] ?? 'sqlite';

        if ($driver === 'sqlite') {
            $database = $config['database'] ?? ':memory:';

            if ($database !== ':memory:' && ! is_dir(dirname($database))) {
                mkdir(dirname($database), 0777, true);
            }

            $this->pdo = new PDO(
                'sqlite:' . $database,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            // SQLite ignores foreign keys unless they are switched on per connection.
            $this->pdo->exec('PRAGMA foreign_keys = ON');

            return;
        }

        if ($driver === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? '3306',
                $config['database'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            );

            $this->pdo = new PDO(
                $dsn,
                $config['username'] ?? 'root',
                $config['password'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            return;
        }

        throw new RuntimeException("Unsupported database driver [$driver].");
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function select(string $sql, array $bindings = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    public function statement(string $sql, array $bindings = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);

        return $statement;
    }

    /**
     * Run an insert/update/delete and return the affected row count.
     */
    public function execute(string $sql, array $bindings = []): int
    {
        return $this->statement($sql, $bindings)->rowCount();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Wrap a callback in a transaction; rolls back on any exception.
     */
    public function transaction(callable $callback)
    {
        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}
