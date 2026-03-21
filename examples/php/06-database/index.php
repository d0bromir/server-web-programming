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
 *
 * curl заявки (ръчно тестване):
 *   # Начална страница (първа страница книги)
 *   curl http://localhost:8000/
 *
 *   # Пагинация
 *   curl "http://localhost:8000/?page=2"
 *
 *   # Търсене по ключова дума
 *   curl "http://localhost:8000/?search=Вазов"
 *
 *   # Филтриране по жанр
 *   curl "http://localhost:8000/?genre=Роман"
 *
 *   # Демонстрация на транзакция
 *   curl "http://localhost:8000/?tx=1"
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

    // Таблица за ревюта – свързана с books (FK)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reviews (
            id       INTEGER PRIMARY KEY AUTOINCREMENT,
            book_id  INTEGER NOT NULL REFERENCES books(id),
            reviewer TEXT    NOT NULL,
            rating   INTEGER NOT NULL,
            comment  TEXT
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
        // Named placeholder :q – използва се два пъти, подава се само веднъж
        $like = "%{$query}%";
        return Database::fetchAll(
            "SELECT * FROM books WHERE title LIKE :q OR author LIKE :q",
            [':q' => $like]
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

/**
 * Демонстрира транзакция с три свързани SQL заявки.
 *
 * $mode = 'commit'   – всичко минава успешно, после почистваме теста
 * $mode = 'rollback' – втората заявка е умишлено грешна → rollBack откатва и двете
 */
function demoTransaction(string $mode): array
{
    $pdo = Database::getInstance();
    $log = [];          // Дневник на стъпките за показване в браузъра
    try {
        $pdo->beginTransaction();
        $log[] = '① beginTransaction()';

        // Заявка 1: вмъкваме нова книга
        Database::execute(
            "INSERT INTO books (title, author, year, genre) VALUES (:title, :author, :year, :genre)",
            [':title' => 'Демо книга', ':author' => 'Демо автор', ':year' => 2024, ':genre' => 'Тест']
        );
        $bookId = Database::lastInsertId();
        $log[] = "② INSERT INTO books → id={$bookId}";

        // Заявка 2: вмъкваме ревю за книгата
        if ($mode === 'rollback') {
            // Умишлена грешка – невалиден рейтинг ще мине на ниво SQL,
            // затова симулираме грешка ръчно
            throw new RuntimeException('Симулирана грешка след INSERT на книга!');
        }
        Database::execute(
            "INSERT INTO reviews (book_id, reviewer, rating, comment) VALUES (?, ?, ?, ?)",
            [$bookId, 'Тест Рецензент', 5, 'Отличен пример!']
        );
        $reviewId = Database::lastInsertId();
        $log[] = "③ INSERT INTO reviews → id={$reviewId}";

        // Заявка 3: отбелязваме книгата с жанр 'Тест-OK'
        Database::execute(
            "UPDATE books SET genre = ? WHERE id = ?",
            ['Тест-OK', $bookId]
        );
        $log[] = "④ UPDATE books SET genre='Тест-OK'";

        $pdo->commit();
        $log[] = '⑤ commit() – всички промени записани';

        // Почистваме теста от базата
        Database::execute("DELETE FROM reviews WHERE id = ?", [$reviewId]);
        Database::execute("DELETE FROM books WHERE id = ?", [$bookId]);
        $log[] = '⑥ Тест данните изтрити (book + review)';

        return ['success' => true, 'log' => $log];
    } catch (Throwable $e) {
        $pdo->rollBack();
        $log[] = '✘ rollBack() – НИТО ЕДНА промяна не е записана';
        $log[] = 'Причина: ' . $e->getMessage();
        return ['success' => false, 'log' => $log];
    }
}

// ══════════════════════════════════════════════════════════════════════
//  BOOTSTRAP
// ══════════════════════════════════════════════════════════════════════

migrate();
$repo = new BookRepository();

$page       = max(1, (int) ($_GET['page'] ?? 1));
$search     = trim($_GET['search'] ?? '');
$txMode     = $_GET['tx'] ?? null;   // 'commit' | 'rollback' | null
$txResult   = $txMode !== null ? demoTransaction($txMode) : null;

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

<?php if ($txResult !== null): ?>
<div class="card" style="border-color:<?= $txResult['success'] ? '#27ae60' : '#c0392b' ?>">
  <strong><?= $txResult['success'] ? '✔ Транзакция успешна (commit)' : '✘ Транзакция откатена (rollBack)' ?></strong>
  <ol style="margin:8px 0 0">
    <?php foreach ($txResult['log'] as $step): ?>
    <li><?= htmlspecialchars($step) ?></li>
    <?php endforeach; ?>
  </ol>
</div>
<?php endif; ?>

<!-- ── 1. Prepared statements ─────────────────────────────────────── -->
<div class="card">
  <h2>Prepared Statements – защо?</h2>
  <p>
    Когато стойности от потребителя се слагат директно в SQL низ, атакуващият може да
    <strong>промени смисъла на заявката</strong> (SQL Injection). Prepared statements
    изпращат SQL структурата и данните <em>поотделно</em> – базата данни никога не
    интерпретира данните като код.
  </p>
  <pre>// ✘ УЯЗВИМО – SQL Injection
// Ако $_GET['title'] = "' OR '1'='1" ще върне всички редове!
$pdo->query("SELECT * FROM books WHERE title = '{$_GET['title']}'");</pre>

  <h3 style="margin-top:14px">Стил 1 – Positional placeholders (<code>?</code>)</h3>
  <p>
    Параметрите се подават като индексиран масив. Редът на стойностите трябва да съвпада
    точно с реда на <code>?</code> знаците в SQL. Удобен при кратки заявки с малко параметри.
  </p>
  <pre>// Всеки ? се замества поред с елементите на масива
$stmt = $pdo->prepare(
    "INSERT INTO books (title, author, year, genre) VALUES (?, ?, ?, ?)"
);
$stmt->execute([$title, $author, $year, $genre]);</pre>

  <h3 style="margin-top:14px">Стил 2 – Named placeholders (<code>:name</code>)</h3>
  <p>
    Параметрите се именуват с <code>:name</code> и се подават като асоциативен масив
    (с или без водещо <code>:</code> в ключа). Редът няма значение – PDO съпоставя
    по <em>имена</em>. Препоръчва се при заявки с много параметри или когато дadе стойност
    се използва повече от веднъж.
  </p>
  <pre>// Named placeholders – по-четим при много параметри
$stmt = $pdo->prepare(
    "INSERT INTO books (title, author, year, genre)
     VALUES (:title, :author, :year, :genre)"
);
$stmt->execute([
    ':title'  => $title,
    ':author' => $author,
    ':year'   => $year,
    ':genre'  => $genre,
]);

// Същата стойност може да се ползва два пъти без повтаряне
$stmt = $pdo->prepare(
    "SELECT * FROM books WHERE title LIKE :q OR author LIKE :q"
);
$stmt->execute([':q' => "%{$search}%"]);</pre>

  <table style="margin-top:10px">
    <tr><th></th><th>Positional <code>?</code></th><th>Named <code>:name</code></th></tr>
    <tr><td>Подаване на параметри</td><td>Индексиран масив</td><td>Асоциативен масив</td></tr>
    <tr><td>Ред на параметрите</td><td>Важен – трябва да съвпада</td><td>Без значение</td></tr>
    <tr><td>Повторна употреба на стойност</td><td>Трябва да се повтори</td><td>Само един ключ</td></tr>
    <tr><td>Четимост при много параметри</td><td>По-трудна</td><td>По-лесна</td></tr>
  </table>
</div>

<!-- ── 1б. PDO конекция ───────────────────────────────────────────── -->
<div class="card">
  <h2>PDO конекция и DSN</h2>
  <p>
    <strong>PDO (PHP Data Objects)</strong> е единен интерфейс за работа с различни бази данни.
    Конекцията се описва с <strong>DSN (Data Source Name)</strong> низ:
  </p>
  <pre>// SQLite (файл – без сървър, идеален за примери)
$dsn = 'sqlite:/tmp/myapp.sqlite';

// MySQL / MariaDB
$dsn = 'mysql:host=localhost;dbname=myapp;charset=utf8mb4';
$pdo = new PDO($dsn, 'user', 'password');

// PostgreSQL
$dsn = 'pgsql:host=localhost;dbname=myapp';</pre>
  <p>
    В примера се използва <strong>SQLite</strong> – базата е един файл, не изисква инсталиран сървър.
    Файлът се създава автоматично при първо стартиране в <code><?= sys_get_temp_dir() ?></code>.
  </p>
  <p>Важни опции при създаване на PDO обект:</p>
  <table>
    <tr><th>Опция</th><th>Стойност в примера</th><th>Значение</th></tr>
    <tr><td><code>ATTR_ERRMODE</code></td><td><code>ERRMODE_EXCEPTION</code></td><td>Хвърля изключение при грешка (препоръчително)</td></tr>
    <tr><td><code>ATTR_DEFAULT_FETCH_MODE</code></td><td><code>FETCH_ASSOC</code></td><td>Резултатите се връщат като асоциативни масиви</td></tr>
    <tr><td><code>ATTR_EMULATE_PREPARES</code></td><td><code>false</code></td><td>Истински prepared statements в базата (по-сигурно)</td></tr>
  </table>
</div>

<!-- ── 2. Repository ──────────────────────────────────────────────── -->
<div class="card">
  <h2>Repository Pattern – списък с книги</h2>
  <p>
    <strong>Repository Pattern</strong> изолира логиката за достъп до база данни в отделен клас.
    Контролерът (или скриптът) работи с методи като <code>findAll()</code>, <code>search()</code>,
    <code>create()</code> – без да знае как точно се изпълняват SQL заявките.
    Ако смените базата данни (SQLite → MySQL), редактирате само Repository класа.
  </p>
  <p>Методи на <code>BookRepository</code> в примера:</p>
  <table>
    <tr><th>Метод</th><th>Описание</th></tr>
    <tr><td><code>findAll($page)</code></td><td>Страница с книги (LIMIT + OFFSET)</td></tr>
    <tr><td><code>findById($id)</code></td><td>Една книга по ID</td></tr>
    <tr><td><code>findByGenre($genre)</code></td><td>Филтриране по жанр</td></tr>
    <tr><td><code>search($query)</code></td><td>Търсене в заглавие и автор (LIKE)</td></tr>
    <tr><td><code>create(...)</code></td><td>Вмъква нов ред, връща новото ID</td></tr>
    <tr><td><code>delete($id)</code></td><td>Изтрива ред по ID</td></tr>
  </table>
  <form method="GET" style="margin:14px 0">
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

<!-- ── 3. Active Record ───────────────────────────────────────────── -->
<div class="card">
  <h2>Active Record – подобен модел</h2>
  <p>
    При <strong>Active Record</strong> шаблона всеки обект знае <em>сам как да се запише, зареди
    и изтрие</em> от базата данни – методите за достъп до DB живеят <em>вътре в модела</em>,
    а не в отделен Repository клас.
  </p>
  <pre>// Active Record – моделът "знае" за базата
class Book
{
    public int    $id;
    public string $title;
    public string $author;
    public int    $year;

    // Запазва текущия обект (INSERT или UPDATE)
    public function save(): void
    {
        if (isset($this->id)) {
            Database::execute(
                "UPDATE books SET title=?, author=?, year=? WHERE id=?",
                [$this->title, $this->author, $this->year, $this->id]
            );
        } else {
            Database::execute(
                "INSERT INTO books (title, author, year) VALUES (?, ?, ?)",
                [$this->title, $this->author, $this->year]
            );
            $this->id = Database::lastInsertId();
        }
    }

    // Статичен метод – зарежда обект по ID
    public static function find(int $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM books WHERE id = ?", [$id]);
        if ($row === null) return null;
        $book = new self();
        foreach ($row as $k => $v) $book->$k = $v;
        return $book;
    }

    public function delete(): void
    {
        Database::execute("DELETE FROM books WHERE id = ?", [$this->id]);
    }
}

// Употреба
$book = Book::find(1);
$book->title = 'Ново заглавие';
$book->save();   // UPDATE</pre>
  <p>
    <strong>Repository vs Active Record:</strong>
    Repository изолира SQL в отделен клас (по-добро разделение на отговорностите);
    Active Record е по-компактен, но смесва бизнес логика с persistence логика.
    ORM библиотеки като <em>Eloquent (Laravel)</em> и <em>Doctrine (Symfony)</em> реализират
    тези шаблони – Active Record и Data Mapper съответно.
  </p>
</div>

<!-- ── 4. Pagination ──────────────────────────────────────────────── -->
<div class="card">
  <h2>Pagination (LIMIT + OFFSET)</h2>
  <p>
    При голям брой записи не изтегляме всичко наведнъж. SQL <code>LIMIT</code> ограничава
    броя на върнатите редове, а <code>OFFSET</code> прескача вече показаните:
  </p>
  <pre>// Страница 1 – записи 1‥4 (OFFSET = 0)
SELECT * FROM books ORDER BY title LIMIT 4 OFFSET 0;

// Страница 2 – записи 5‥8 (OFFSET = 4)
SELECT * FROM books ORDER BY title LIMIT 4 OFFSET 4;

// Страница 3 – записи 9‥12 (OFFSET = 8)
SELECT * FROM books ORDER BY title LIMIT 4 OFFSET 8;</pre>
  <p>Формулата:</p>
  <pre>$perPage = 4;
$offset  = ($page - 1) * $perPage;   // page=1 → 0, page=2 → 4, page=3 → 8

$books      = Database::fetchAll(
    "SELECT * FROM books ORDER BY title LIMIT ? OFFSET ?",
    [$perPage, $offset]
);

// Общ брой за изчисляване на последната страница
$total      = (int) Database::fetchOne("SELECT COUNT(*) AS cnt FROM books")['cnt'];
$totalPages = (int) ceil($total / $perPage);</pre>
  <p>
    Важno: преброителната заявка (<code>COUNT(*)</code>) и заявката с данни се изпълняват
    поотделно. При много голям набор от данни вместо <code>OFFSET</code> се предпочита
    <strong>keyset pagination</strong> (<code>WHERE id &gt; :last_seen_id</code>),
    защото пропускането на много редове с <code>OFFSET</code> е бавно.
  </p>
  <p>Текуща страница: <strong><?= $page ?></strong> от <strong><?= $totalPages ?></strong>
  &nbsp;(<?= $total ?> книги общо)</p>
</div>

<!-- ── 5. Транзакция ──────────────────────────────────────────────── -->
<div class="card">
  <h2>Транзакции</h2>
  <p>
    Транзакцията гарантира <strong>атомарност</strong> – или <em>всички</em> операции се
    записват успешно (<code>commit</code>), или <em>нито една</em> не се запазва (<code>rollBack</code>).
    Това е критично при свързани операции: в примера вмъкваме книга <strong>и</strong> ревю за нея
    едновременно – ако ревюто не се запише, не искаме и книгата да остане.
  </p>
  <pre>// Три заявки в една транзакция – или всички, или нито една
$pdo->beginTransaction();
try {
    // 1. INSERT books
    Database::execute("INSERT INTO books ...", [...]);
    $bookId = Database::lastInsertId();

    // 2. INSERT reviews (свързан запис)
    Database::execute("INSERT INTO reviews (book_id, ...) VALUES (?, ...)", [$bookId, ...]);

    // 3. UPDATE books (актуализира жанра)
    Database::execute("UPDATE books SET genre = ? WHERE id = ?", ['Тест-OK', $bookId]);

    $pdo->commit();       // Всички три заявки се записват заедно
} catch (Throwable $e) {
    $pdo->rollBack();     // Нито една от трите не се записва
}</pre>
  <p>
    Транзакциите спазват <strong>ACID</strong> принципите:
    <em>Atomicity</em> (атомарност),
    <em>Consistency</em> (консистентност),
    <em>Isolation</em> (изолация),
    <em>Durability</em> (трайност).
  </p>
  <p>
    Натиснете бутоните, за да видите стъпка по стъпка какво се случва:
  </p>
  <a href="?tx=commit" class="btn" style="background:#27ae60">Демо – commit (успех)</a>
  &nbsp;
  <a href="?tx=rollback" class="btn" style="background:#c0392b">Демо – rollBack (грешка)</a>
</div>

</body>
</html>
