<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Database – PDO singleton за PostgreSQL.
 *
 * Употреба:
 *   $db = Database::getInstance();
 *   $db->fetchAll('SELECT * FROM venues WHERE city = :city', [':city' => 'Sofia']);
 */
class Database
{
    private static ?self $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $config = require dirname(__DIR__, 2) . '/config/database.php';

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            $config['host'],
            $config['port'],
            $config['dbname']
        );

        try {
            $this->pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $this->pdo->exec("SET client_encoding TO 'UTF8'");
        } catch (PDOException $e) {
            // В продукция: логвай грешката, не я показвай на потребителя
            http_response_code(500);
            exit('Грешка при свързване с базата данни.');
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Query helpers ─────────────────────────────────────────────────

    /** Връща масив от редове. */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Връща един ред или null. */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** Изпълнява INSERT/UPDATE/DELETE, връща брой засегнати редове. */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Последно вмъкнато ID (само след INSERT). */
    public function lastInsertId(string $sequence = ''): string
    {
        return $this->pdo->lastInsertId($sequence ?: null);
    }

    /** За сложни заявки, изискващи директен PDO достъп. */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    // Предотвратяване на clone / unserialize
    private function __clone() {}
    public function __wakeup(): void { throw new \RuntimeException('Cannot unserialize singleton'); }
}
