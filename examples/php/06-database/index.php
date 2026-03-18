<?php
declare(strict_types=1);
/**
 * Тема 6 – Работа с бази данни и ORM подход
 *
 * Демонстрира:
 *  - PDO конекция (SQLite – без допълнителни настройки)
 *  - Prepared statements (защита от SQL injection)
 *  - Repository Pattern – отделна класа за DB операции
 *  - Active Record–подобен Model
 *  - Транзакции
 *  - Pagination query
 *
 * Стартиране: php -S localhost:8000
 */

// ══════════════════════════════════════════════════════════════════════
//  DATABASE  (PDO Singleton + Helper методи)
// ══════════════════════════════════════════════════════════════════════

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            // SQLite файл в temp директорията – не изисква конфигурация
            $dsn = 'sqlite:' . sys_get_temp_dir() . '/php-db-demo.sqlite';
            self::$instance = new PDO($dsn, options: [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$instance;
    }

    /** Изпълнява SELECT и връща всички редове */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Изпълнява SELECT и връща един ред */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /** Изпълнява INSERT / UPDATE / DELETE, връща брой засегнати редове */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Връща последния INSERT id */
    public static function lastInsertId(): int
    {
        return (int) self::getInstance()->lastInsertId();
    }
}

// ══════════════════════════════════════════════════════════════════════
//  SCHEMA (migrate при нужда)
// ══════════════════════════════════════════════════════════════════════

function migrate(): void
{
    $pdo = Database::getInstance();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS books (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            title       TEXT    NOT NULL,
            author      TEXT    NOT NULL,
            year        INTEGER,
            genre       TEXT,
            created_at  TEXT    DEFAULT (datetime('now'))
        )
    ");

    // Seed данни само ако таблицата е празна
    $count = (int) Database::fetchOne("SELECT COUNT(*) AS cnt FROM books")['cnt'];
    if ($count === 0) {
        $books = [
            ['Под игото',             'Иван Вазов',      1888, 'Роман'],
            ['Тютюн',                 'Димитър Димов',   1951, 'Роман'],
            ['Хайтарин',              'Йордан Йовков',   1934, 'Разкази'],
            ['Старият човек и морето','Ърнест Хемингуей', 1952, 'Роман'],
            ['1984',                  'Джордж Оруел',    1949, 'Дистопия'],
            ['Критиката на чистия разум','Имануел Кант',  1781, 'Философия'],
        ];
        foreach ($books as [$title, $author, $year, $genre]) {
            Database::execute(
                "INSERT INTO books (title, author, year, genre) VALUES (?, ?, ?, ?)",
                [$title, $author, $year, $genre]
            );
        }
    }
}

// ══════════════════════════════════════════════════════════════════════
//  REPOSITORY  (Data Access Layer)
// ══════════════════════════════════════════════════════════════════════

class BookRepository
{
    public function findAll(int $page = 1, int $perPage = 4): array
    {
        $offset = ($page - 1) * $perPage;
        return Database::fetchAll(
            "SELECT * FROM books ORDER BY title LIMIT ? OFFSET ?",
            [$perPage, $offset]          // Prepared statement – стойностите са параметри
        );
    }

    public function countAll(): int
    {
        return (int) Database::fetchOne("SELECT COUNT(*) AS cnt FROM books")['cnt'];
    }

    public function findById(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT * FROM books WHERE id = ?",
            [$id]
        );
    }

    public function findByGenre(string $genre): array
    {
        return Database::fetchAll(
            "SELECT * FROM books WHERE genre = ? ORDER BY year",
            [$genre]
        );
    }

    public function search(string $query): array
    {
        $like = "%{$query}%";
        return Database::fetchAll(
            "SELECT * FROM books WHERE title LIKE ? OR author LIKE ?",
            [$like, $like]
        );
    }

    public function create(string $title, string $author, int $year, string $genre): int
    {
        Database::execute(
            "INSERT INTO books (title, author, year, genre) VALUES (?, ?, ?, ?)",
            [$title, $author, $year, $genre]
        );
        return Database::lastInsertId();
    }

    public function delete(int $id): bool
    {
        return Database::execute("DELETE FROM books WHERE id = ?", [$id]) > 0;
    }
}

// ══════════════════════════════════════════════════════════════════════
//  ТРАНЗАКЦИЯ – пример
// ══════════════════════════════════════════════════════════════════════

function demoTransaction(): string
{
    $pdo = Database::getInstance();
    try {
        $pdo->beginTransaction();

        Database::execute(
            "INSERT INTO books (title, author, year, genre) VALUES (?, ?, ?, ?)",
            ['Тест Книга', 'Тест Автор', 2024, 'Тест']
        );
        $id = Database::lastInsertId();

        // Ако искаме rollback: throw new RuntimeException('Rollback!');

        $pdo->commit();
        // Изтриваме теста
        Database::execute("DELETE FROM books WHERE id = ?", [$id]);
        return "✔ Транзакция успешна (вмъкнато и изтрито id={$id})";
    } catch (Throwable $e) {
        $pdo->rollBack();
        return "✘ Транзакция отменена: {$e->getMessage()}";
    }
}

// ══════════════════════════════════════════════════════════════════════
//  BOOTSTRAP
// ══════════════════════════════════════════════════════════════════════

migrate();
$repo = new BookRepository();

$page       = max(1, (int) ($_GET['page'] ?? 1));
$search     = trim($_GET['search'] ?? '');
$txResult   = isset($_GET['tx']) ? demoTransaction() : '';

$books      = $search !== ''
    ? $repo->search($search)
    : $repo->findAll($page);
$total      = $repo->countAll();
$totalPages = (int) ceil($total / 4);

?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<title>06 – Работа с бази данни</title>
<style>
body{font-family:Arial,sans-serif;max-width:860px;margin:40px auto;padding:0 20px}
h2{border-bottom:1px solid #eee;padding-bottom:6px}
code{background:#f0f0f0;padding:2px 5px;border-radius:3px;font-size:.88em}
pre{background:#f7f7f7;padding:14px;border-radius:6px;overflow-x:auto;font-size:.83em}
.card{border:1px solid #ddd;border-radius:8px;padding:18px;margin:18px 0}
table{border-collapse:collapse;width:100%}
td,th{padding:8px 12px;border:1px solid #ddd}
th{background:#f0f0f0}
.ok{color:#27ae60}.err{color:#c0392b}
a.btn{display:inline-block;padding:5px 12px;background:#2980b9;color:#fff;border-radius:4px;text-decoration:none;font-size:.9em}
form input{padding:7px;font-size:1em}
</style>
</head>
<body>

<h1>06 – Бази данни: PDO + Repository Pattern</h1>

<?php if ($txResult): ?>
<p class="ok"><?= htmlspecialchars($txResult) ?></p>
<?php endif; ?>

<!-- ── 1. Prepared statements ─────────────────────────────────────── -->
<div class="card">
  <h2>Prepared Statements – защо?</h2>
  <pre>// ✘ УЯЗВИМО – SQL Injection
$pdo->query("SELECT * FROM books WHERE title = '{$_GET['title']}'");

// ✔ БЕЗОПАСНО – Prepared Statement
$stmt = $pdo->prepare("SELECT * FROM books WHERE title = ?");
$stmt->execute([$_GET['title']]);</pre>
  <p>PDO автоматично <strong>escapes</strong> параметрите – потребителят не може да инжектира SQL.</p>
</div>

<!-- ── 2. Repository ──────────────────────────────────────────────── -->
<div class="card">
  <h2>Repository Pattern – списък с книги</h2>
  <form method="GET" style="margin-bottom:12px">
      <input name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Търси по заглавие / автор">
      <button type="submit">Търси</button>
      <?php if ($search): ?><a href="/">Изчисти</a><?php endif; ?>
  </form>
  <table>
      <tr><th>ID</th><th>Заглавие</th><th>Автор</th><th>Год.</th><th>Жанр</th></tr>
      <?php foreach ($books as $b): ?>
      <tr>
          <td><?= $b['id'] ?></td>
          <td><?= htmlspecialchars($b['title']) ?></td>
          <td><?= htmlspecialchars($b['author']) ?></td>
          <td><?= $b['year'] ?></td>
          <td><?= htmlspecialchars($b['genre'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
  </table>

  <?php if ($search === ''): ?>
  <p>Страница <?= $page ?> от <?= $totalPages ?> &nbsp;|&nbsp; Общо: <?= $total ?>
     &nbsp;
     <?php if ($page > 1): ?><a href="?page=<?= $page - 1 ?>">← Предишна</a>&nbsp;<?php endif; ?>
     <?php if ($page < $totalPages): ?><a href="?page=<?= $page + 1 ?>">Следваща →</a><?php endif; ?>
  </p>
  <?php endif; ?>
</div>

<!-- ── 3. Транзакция ──────────────────────────────────────────────── -->
<div class="card">
  <h2>Транзакции</h2>
  <pre>$pdo->beginTransaction();
try {
    // ... INSERT / UPDATE / DELETE ...
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();   // Отменя всички промени
}</pre>
  <a href="?tx=1" class="btn">Демо транзакция</a>
</div>

</body>
</html>
