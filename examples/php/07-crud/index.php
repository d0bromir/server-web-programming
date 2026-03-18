<?php
declare(strict_types=1);
/**
 * Тема 7 – CRUD операции и слоево разделение
 *
 * Демонстрира:
 *  - Пълен CRUD цикъл: Create, Read, Update, Delete
 *  - Слоево разделение: Controller → Service → Repository → Database
 *  - HTTP method override (DELETE / PUT чрез POST + hidden field)
 *  - Flash messages (еднократни съобщения)
 *  - Валидация на входните данни
 *
 * Стартиране: php -S localhost:8000
 */

session_start();

// ══════════════════════════════════════════════════════════════════════
//  СЛОЙ 1: DATABASE
// ══════════════════════════════════════════════════════════════════════

class Database
{
    private static ?PDO $pdo = null;

    public static function get(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = new PDO('sqlite:' . sys_get_temp_dir() . '/crud-demo.sqlite', options: [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec("
                CREATE TABLE IF NOT EXISTS tasks (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    title       TEXT    NOT NULL,
                    description TEXT,
                    status      TEXT    NOT NULL DEFAULT 'pending'
                                CHECK(status IN ('pending','in_progress','done')),
                    created_at  TEXT    DEFAULT (datetime('now'))
                )
            ");
        }
        return self::$pdo;
    }
}

// ══════════════════════════════════════════════════════════════════════
//  СЛОЙ 2: REPOSITORY  (само DB операции, без бизнес логика)
// ══════════════════════════════════════════════════════════════════════

class TaskRepository
{
    public function findAll(string $status = ''): array
    {
        if ($status !== '') {
            $stmt = Database::get()->prepare("SELECT * FROM tasks WHERE status = ? ORDER BY id DESC");
            $stmt->execute([$status]);
        } else {
            $stmt = Database::get()->query("SELECT * FROM tasks ORDER BY id DESC");
        }
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::get()->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $title, string $description, string $status): int
    {
        $stmt = Database::get()->prepare(
            "INSERT INTO tasks (title, description, status) VALUES (?, ?, ?)"
        );
        $stmt->execute([$title, $description, $status]);
        return (int) Database::get()->lastInsertId();
    }

    public function update(int $id, string $title, string $description, string $status): bool
    {
        $stmt = Database::get()->prepare(
            "UPDATE tasks SET title = ?, description = ?, status = ? WHERE id = ?"
        );
        return $stmt->execute([$title, $description, $status, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::get()->prepare("DELETE FROM tasks WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

// ══════════════════════════════════════════════════════════════════════
//  СЛОЙ 3: SERVICE  (бизнес логика + валидация)
// ══════════════════════════════════════════════════════════════════════

class TaskService
{
    private static array $validStatuses = ['pending', 'in_progress', 'done'];

    public function __construct(private readonly TaskRepository $repo) {}

    /** @return array{ok:bool, errors:string[], id?:int} */
    public function create(array $data): array
    {
        $errors = $this->validate($data);
        if ($errors !== []) return ['ok' => false, 'errors' => $errors];

        $id = $this->repo->create(
            trim($data['title']),
            trim($data['description'] ?? ''),
            $data['status']
        );
        return ['ok' => true, 'errors' => [], 'id' => $id];
    }

    /** @return array{ok:bool, errors:string[]} */
    public function update(int $id, array $data): array
    {
        $errors = $this->validate($data);
        if ($this->repo->findById($id) === null) $errors[] = 'Задачата не е намерена.';
        if ($errors !== []) return ['ok' => false, 'errors' => $errors];

        $this->repo->update($id, trim($data['title']), trim($data['description'] ?? ''), $data['status']);
        return ['ok' => true, 'errors' => []];
    }

    public function delete(int $id): void
    {
        $this->repo->delete($id);
    }

    public function getAll(string $status = ''): array
    {
        return $this->repo->findAll($status);
    }

    public function getById(int $id): ?array
    {
        return $this->repo->findById($id);
    }

    private function validate(array $d): array
    {
        $errors = [];
        if (empty(trim($d['title'] ?? '')))          $errors[] = 'Заглавието е задължително.';
        if (strlen(trim($d['title'] ?? '')) > 100)   $errors[] = 'Заглавието не трябва да е > 100 символа.';
        if (!in_array($d['status'] ?? '', self::$validStatuses, true))
            $errors[] = 'Невалиден статус.';
        return $errors;
    }
}

// ══════════════════════════════════════════════════════════════════════
//  FLASH MESSAGES
// ══════════════════════════════════════════════════════════════════════

function flash(string $msg = '', string $type = 'ok'): ?array
{
    if ($msg !== '') {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ══════════════════════════════════════════════════════════════════════
//  CONTROLLER  (обработка на заявки)
// ══════════════════════════════════════════════════════════════════════

$service = new TaskService(new TaskRepository());

$method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'];  // method override
$path   = strtok($_SERVER['REQUEST_URI'], '?');
$id     = null;

// Разпознаваме /tasks/42
if (preg_match('@^/tasks/(\d+)@', $path, $m)) {
    $id   = (int) $m[1];
    $path = preg_replace('@/tasks/\d+@', '/tasks/:id', $path);
}

match ([$method, $path]) {
    // CREATE
    ['POST', '/tasks']             => (function () use ($service) {
        $result = $service->create($_POST);
        if ($result['ok']) {
            flash('Задачата е създадена успешно.');
        } else {
            flash(implode('; ', $result['errors']), 'err');
        }
        header('Location: /');
        exit;
    })(),

    // UPDATE
    ['PUT', '/tasks/:id']          => (function () use ($service, $id) {
        $result = $service->update($id, $_POST);
        flash($result['ok'] ? 'Задачата е актуализирана.' : implode('; ', $result['errors']),
              $result['ok'] ? 'ok' : 'err');
        header('Location: /');
        exit;
    })(),

    // DELETE
    ['DELETE', '/tasks/:id']       => (function () use ($service, $id) {
        $service->delete($id);
        flash('Задачата е изтрита.');
        header('Location: /');
        exit;
    })(),

    // EDIT FORM
    ['GET', '/tasks/:id/edit']     => (function () use ($service, $id) {
        $task = $service->getById($id);
        if (!$task) { http_response_code(404); echo '404'; exit; }
        render_form($task);
        exit;
    })(),

    default                        => (function () use ($service) {
        $filter = $_GET['status'] ?? '';
        $tasks  = $service->getAll($filter);
        render_list($tasks, $filter);
    })(),
};

// ══════════════════════════════════════════════════════════════════════
//  VIEWS
// ══════════════════════════════════════════════════════════════════════

function layout_start(string $title): void { ?>
<!DOCTYPE html>
<html lang="bg"><head><meta charset="UTF-8"><title><?= htmlspecialchars($title) ?></title>
<style>
body{font-family:Arial,sans-serif;max-width:800px;margin:40px auto;padding:0 20px}
.ok{color:#27ae60;background:#eafaf1;padding:10px;border-radius:4px;margin:10px 0}
.err{color:#c0392b;background:#fadbd8;padding:10px;border-radius:4px;margin:10px 0}
table{border-collapse:collapse;width:100%}
td,th{padding:8px 12px;border:1px solid #ddd}
th{background:#f0f0f0}
input,select,textarea{padding:7px;font-size:1em;width:100%;box-sizing:border-box;margin-bottom:8px;border:1px solid #ccc;border-radius:4px}
button,a.btn{padding:7px 14px;font-size:.9em;border:none;border-radius:4px;cursor:pointer;text-decoration:none;display:inline-block}
.btn-blue{background:#2980b9;color:#fff}
.btn-green{background:#27ae60;color:#fff}
.btn-red{background:#c0392b;color:#fff}
form.inline{display:inline}
</style></head><body>
<?php }

function layout_end(): void { echo '</body></html>'; }

function flash_render(): void
{
    $f = flash();
    if ($f) {
        echo '<div class="' . htmlspecialchars($f['type']) . '">'
            . htmlspecialchars($f['msg']) . '</div>';
    }
}

function render_list(array $tasks, string $filter): void
{
    layout_start('07 – CRUD');
    flash_render();
    $statuses = [''=>'Всички','pending'=>'Чакащи','in_progress'=>'В процес','done'=>'Завършени'];
    echo '<h1>07 – CRUD: Задачи</h1>';
    echo '<p>';
    foreach ($statuses as $v => $l) {
        $active = $filter === $v ? ' style="font-weight:bold"' : '';
        echo "<a href='/?status={$v}'{$active}>{$l}</a> &nbsp;";
    }
    echo '</p>';

    // CREATE форма
    echo '<details><summary><strong>+ Нова задача</strong></summary><br>';
    echo '<form method="POST" action="/tasks">';
    echo '<input name="title" placeholder="Заглавие *" required>';
    echo '<textarea name="description" placeholder="Описание" rows="2"></textarea>';
    echo '<select name="status"><option value="pending">Чакаща</option>'
       . '<option value="in_progress">В процес</option>'
       . '<option value="done">Завършена</option></select>';
    echo '<button type="submit" class="btn btn-green">Създай</button>';
    echo '</form></details><br>';

    // Таблица
    echo '<table><tr><th>ID</th><th>Заглавие</th><th>Статус</th><th>Дата</th><th>Действия</th></tr>';
    $statusLabel = ['pending'=>'Чакаща','in_progress'=>'В процес','done'=>'✔ Завършена'];
    foreach ($tasks as $t):
    ?>
    <tr>
        <td><?= $t['id'] ?></td>
        <td><?= htmlspecialchars($t['title']) ?></td>
        <td><?= $statusLabel[$t['status']] ?? $t['status'] ?></td>
        <td><?= substr($t['created_at'], 0, 16) ?></td>
        <td>
            <a href="/tasks/<?= $t['id'] ?>/edit" class="btn btn-blue">Редактирай</a>
            <form method="POST" action="/tasks/<?= $t['id'] ?>" class="inline"
                  onsubmit="return confirm('Изтриване?')">
                <input type="hidden" name="_method" value="DELETE">
                <button class="btn btn-red">Изтрий</button>
            </form>
        </td>
    </tr>
    <?php endforeach;
    echo '</table>';
    if (empty($tasks)) echo '<p>Няма задачи.</p>';
    layout_end();
}

function render_form(array $task): void
{
    layout_start('Редактиране на задача');
    flash_render();
    $sl = ['pending'=>'Чакаща','in_progress'=>'В процес','done'=>'Завършена'];
    echo "<h1>Редактиране: " . htmlspecialchars($task['title']) . "</h1>";
    echo "<form method='POST' action='/tasks/{$task['id']}'>";
    echo "<input type='hidden' name='_method' value='PUT'>";
    echo "<input name='title' value='" . htmlspecialchars($task['title'], ENT_QUOTES) . "' required>";
    echo "<textarea name='description' rows='3'>" . htmlspecialchars($task['description'] ?? '') . "</textarea>";
    echo "<select name='status'>";
    foreach ($sl as $v => $l) {
        $sel = $task['status'] === $v ? ' selected' : '';
        echo "<option value='{$v}'{$sel}>{$l}</option>";
    }
    echo "</select>";
    echo "<button type='submit' class='btn btn-green'>Запази</button> ";
    echo "<a href='/' class='btn btn-blue'>Отказ</a>";
    echo "</form>";
    layout_end();
}
