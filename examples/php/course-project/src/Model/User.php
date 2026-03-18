<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Database;

/**
 * User – модел за потребители.
 */
class User
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Четене ───────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, name, email, role, api_token, created_at FROM users WHERE id = :id',
            [':id' => $id]
        );
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM users WHERE email = :email',
            [':email' => strtolower($email)]
        );
    }

    public function findByToken(string $token): ?array
    {
        return $this->db->fetchOne(
            'SELECT id, name, email, role FROM users WHERE api_token = :token',
            [':token' => $token]
        );
    }

    // ── Запис ─────────────────────────────────────────────────────────

    /**
     * Създава нов потребител.
     * Паролата се хешира тук – извикващият изпраща plain-text.
     */
    public function create(string $name, string $email, string $password): int
    {
        $hash  = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $token = bin2hex(random_bytes(32));

        $this->db->execute(
            'INSERT INTO users (name, email, password, role, api_token)
             VALUES (:name, :email, :password, :role, :token)',
            [
                ':name'     => trim($name),
                ':email'    => strtolower(trim($email)),
                ':password' => $hash,
                ':role'     => 'user',
                ':token'    => $token,
            ]
        );

        return (int) $this->db->lastInsertId('users_id_seq');
    }

    /**
     * Проверява парола за потребител.
     * Връща данните при успех, null при грешна парола.
     */
    public function authenticate(string $email, string $password): ?array
    {
        $user = $this->findByEmail($email);
        if ($user === null) {
            // Изпълняваме dummy hash за да избегнем timing attack
            password_verify('dummy', '$2y$12$invalidhashpadding.................................');
            return null;
        }

        if (!password_verify($password, $user['password'])) {
            return null;
        }

        // Ако хеш алгоритъмът е остарял, обнови го
        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $this->db->execute(
                'UPDATE users SET password = :p WHERE id = :id',
                [':p' => $newHash, ':id' => $user['id']]
            );
        }

        return $user;
    }

    // ── Валидация ─────────────────────────────────────────────────────

    public function emailExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }
}
