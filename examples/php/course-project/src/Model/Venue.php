<?php
declare(strict_types=1);

namespace App\Model;

use App\Core\Database;

/**
 * Venue – модел за заведения.
 *
 * Всички заявки използват prepared statements.
 */
class Venue
{
    public const CATEGORIES = ['restaurant', 'cafe', 'bar', 'club', 'bakery', 'other'];
    public const PER_PAGE    = 6;

    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ── Четене ───────────────────────────────────────────────────────

    public function findById(int $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT v.*, u.name AS owner_name
             FROM venues v
             JOIN users u ON u.id = v.user_id
             WHERE v.id = :id',
            [':id' => $id]
        );
    }

    /**
     * Пагиниран списък с незадължителен search и sort.
     *
     * @return array{items: array<int, array<string,mixed>>, total: int, pages: int}
     */
    public function paginate(
        int    $page     = 1,
        string $search   = '',
        string $category = '',
        string $sort     = 'created_at',
        string $dir      = 'DESC',
        bool   $publicOnly = true,
        ?int   $userId   = null
    ): array {
        // Защита от SQL injection при sort/dir параметри
        $allowedSort = ['name', 'city', 'rating', 'created_at'];
        $sort = in_array($sort, $allowedSort, true) ? $sort : 'created_at';
        $dir  = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $where  = [];
        $params = [];

        if ($publicOnly) {
            $where[] = 'v.is_public = TRUE';
        }
        if ($userId !== null) {
            $where[]         = 'v.user_id = :user_id';
            $params[':user_id'] = $userId;
        }
        if ($search !== '') {
            $where[]          = "(v.name ILIKE :search OR v.city ILIKE :search OR v.description ILIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        if ($category !== '' && in_array($category, self::CATEGORIES, true)) {
            $where[]             = 'v.category = :category';
            $params[':category'] = $category;
        }

        $whereClause = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Брой на всички резултати
        $total = (int) $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM venues v $whereClause",
            $params
        )['cnt'];

        $pages  = max(1, (int) ceil($total / self::PER_PAGE));
        $page   = max(1, min($page, $pages));
        $offset = ($page - 1) * self::PER_PAGE;

        $items = $this->db->fetchAll(
            "SELECT v.*, u.name AS owner_name
             FROM venues v
             JOIN users u ON u.id = v.user_id
             $whereClause
             ORDER BY v.$sort $dir
             LIMIT :limit OFFSET :offset",
            array_merge($params, [':limit' => self::PER_PAGE, ':offset' => $offset])
        );

        return ['items' => $items, 'total' => $total, 'pages' => $pages];
    }

    /** Всички заведения на един потребител (за My Venues). */
    public function findByUser(int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM venues WHERE user_id = :uid ORDER BY created_at DESC',
            [':uid' => $userId]
        );
    }

    // ── Запис ────────────────────────────────────────────────────────

    /** @param array<string,mixed> $data */
    public function create(int $userId, array $data): int
    {
        $this->db->execute(
            'INSERT INTO venues (user_id, name, city, address, category, description, rating, website, is_public)
             VALUES (:uid, :name, :city, :addr, :cat, :desc, :rat, :web, :pub)',
            [
                ':uid'  => $userId,
                ':name' => $data['name'],
                ':city' => $data['city'],
                ':addr' => $data['address'] ?? null,
                ':cat'  => $this->safeCategory($data['category'] ?? 'other'),
                ':desc' => $data['description'] ?? null,
                ':rat'  => $this->safeRating($data['rating'] ?? null),
                ':web'  => $data['website'] ?? null,
                ':pub'  => isset($data['is_public']) ? (bool) $data['is_public'] : true,
            ]
        );
        return (int) $this->db->lastInsertId('venues_id_seq');
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE venues
             SET name=:name, city=:city, address=:addr, category=:cat,
                 description=:desc, rating=:rat, website=:web, is_public=:pub,
                 updated_at=NOW()
             WHERE id=:id',
            [
                ':id'   => $id,
                ':name' => $data['name'],
                ':city' => $data['city'],
                ':addr' => $data['address'] ?? null,
                ':cat'  => $this->safeCategory($data['category'] ?? 'other'),
                ':desc' => $data['description'] ?? null,
                ':rat'  => $this->safeRating($data['rating'] ?? null),
                ':web'  => $data['website'] ?? null,
                ':pub'  => isset($data['is_public']) ? (bool) $data['is_public'] : true,
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM venues WHERE id = :id', [':id' => $id]);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function safeCategory(string $cat): string
    {
        return in_array($cat, self::CATEGORIES, true) ? $cat : 'other';
    }

    private function safeRating(mixed $rating): ?int
    {
        if ($rating === null || $rating === '') {
            return null;
        }
        $r = (int) $rating;
        return ($r >= 1 && $r <= 5) ? $r : null;
    }

    /** Валидира данни за заведение. Връща масив с грешки. */
    public function validate(array $data): array
    {
        $errors = [];

        if (empty(trim($data['name'] ?? ''))) {
            $errors['name'] = 'Името е задължително.';
        } elseif (mb_strlen(trim($data['name'])) > 200) {
            $errors['name'] = 'Името не може да е по-дълго от 200 символа.';
        }

        if (empty(trim($data['city'] ?? ''))) {
            $errors['city'] = 'Градът е задължителен.';
        }

        if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
            $errors['website'] = 'Невалиден URL адрес.';
        }

        if (!empty($data['rating']) && ((int)$data['rating'] < 1 || (int)$data['rating'] > 5)) {
            $errors['rating'] = 'Оценката трябва да е между 1 и 5.';
        }

        return $errors;
    }
}
