<?php
declare(strict_types=1);
/**
 * Тема 11 – Сигурност на уеб приложенията
 *
 * Демонстрира:
 *  1. XSS (Cross-Site Scripting) – превенция с htmlspecialchars()
 *  2. CSRF (Cross-Site Request Forgery) – защита с токен
 *  3. SQL Injection – защита с prepared statements
 *  4. Входна валидация и санитизация
 *  5. Сигурни HTTP заглавия (Security Headers)
 *  6. Replay Attack защита (nonce / timestamp)
 *
 * Стартиране: php -S localhost:8000
 */

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// ══════════════════════════════════════════════════════════════════════
//  1. SECURITY HEADERS
//     Задаваме ги преди всяка страница
// ══════════════════════════════════════════════════════════════════════

header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'");
header("X-Frame-Options: DENY");                    // Защита срещу Clickjacking
header("X-Content-Type-Options: nosniff");          // Предотвратява MIME sniffing
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), microphone=()");

// ══════════════════════════════════════════════════════════════════════
//  2. CSRF TOKEN
// ══════════════════════════════════════════════════════════════════════

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool
{
    // hash_equals е timing-safe сравнение (предотвратява timing attack)
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

// ══════════════════════════════════════════════════════════════════════
//  3. ВАЛИДАЦИЯ И САНИТИЗАЦИЯ
// ══════════════════════════════════════════════════════════════════════

class Validator
{
    private array $errors = [];

    public function required(string $field, mixed $value): self
    {
        if ($value === null || trim((string) $value) === '') {
            $this->errors[$field] = "Полето '{$field}' е задължително.";
        }
        return $this;
    }

    public function minLength(string $field, string $value, int $min): self
    {
        if (mb_strlen(trim($value)) < $min) {
            $this->errors[$field] = "Минимум {$min} символа.";
        }
        return $this;
    }

    public function maxLength(string $field, string $value, int $max): self
    {
        if (mb_strlen(trim($value)) > $max) {
            $this->errors[$field] = "Максимум {$max} символа.";
        }
        return $this;
    }

    public function email(string $field, string $value): self
    {
        // filter_var – вградена PHP функция за email валидация
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = "Невалиден email адрес.";
        }
        return $this;
    }

    public function numeric(string $field, mixed $value): self
    {
        if (!is_numeric($value)) {
            $this->errors[$field] = "Трябва да е число.";
        }
        return $this;
    }

    public function isValid(): bool { return $this->errors === []; }
    public function getErrors(): array { return $this->errors; }
}

// ══════════════════════════════════════════════════════════════════════
//  4. ОБРАБОТКА НА ФОРМА (с всички защити)
// ══════════════════════════════════════════════════════════════════════

$demo = [
    'xss_safe'  => '',
    'xss_raw'   => '',
    'csrf_ok'   => null,
    'form_ok'   => null,
    'errors'    => [],
    'submitted' => [],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── CSRF проверка ───────────────────────────────────────────────
    $csrfInput = $_POST['csrf_token'] ?? '';
    $demo['csrf_ok'] = verifyCsrfToken($csrfInput);

    if (!$demo['csrf_ok']) {
        $demo['errors']['csrf'] = 'CSRF Token невалиден – заявката е отхвърлена!';
    } else {
        $name    = $_POST['name']    ?? '';
        $email   = $_POST['email']   ?? '';
        $comment = $_POST['comment'] ?? '';

        // ── Санитизация ─────────────────────────────────────────────
        // НЕ пишем $_POST директно в изхода – винаги минаваме през htmlspecialchars
        $demo['xss_raw']  = $name;    // УЯЗВИМО – само за демо
        $demo['xss_safe'] = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');  // БЕЗОПАСНО

        // ── Валидация ────────────────────────────────────────────────
        $v = (new Validator())
            ->required('name', $name)
            ->minLength('name', $name, 2)
            ->maxLength('name', $name, 50)
            ->required('email', $email)
            ->email('email', $email);

        if ($v->isValid()) {
            $demo['form_ok']   = true;
            $demo['submitted'] = [
                'name'    => htmlspecialchars(trim($name), ENT_QUOTES, 'UTF-8'),
                'email'   => filter_var($email, FILTER_SANITIZE_EMAIL),
                'comment' => htmlspecialchars(trim($comment), ENT_QUOTES, 'UTF-8'),
            ];
        } else {
            $demo['form_ok'] = false;
            $demo['errors']  = $v->getErrors();
        }
    }
}

// Генерираме токен за формата
$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<title>11 – Сигурност</title>
<style>
body{font-family:Arial,sans-serif;max-width:860px;margin:40px auto;padding:0 20px}
h2{border-bottom:1px solid #eee;padding-bottom:6px}
code{background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:.88em}
pre{background:#f7f7f7;padding:14px;border-radius:6px;overflow-x:auto;font-size:.83em}
.card{border:1px solid #ddd;border-radius:8px;padding:18px;margin:18px 0}
.ok{color:#27ae60;background:#eafaf1;padding:8px 12px;border-radius:4px;margin:8px 0}
.err{color:#c0392b;background:#fadbd8;padding:8px 12px;border-radius:4px;margin:8px 0}
.vuln{color:#c0392b;border:2px solid #c0392b;padding:4px 8px}
.safe{color:#27ae60;border:2px solid #27ae60;padding:4px 8px}
input,textarea{padding:8px;width:100%;box-sizing:border-box;border:1px solid #ccc;border-radius:4px;font-size:.95em;margin-bottom:10px}
button{padding:8px 16px;background:#2980b9;color:#fff;border:none;border-radius:4px;cursor:pointer}
</style>
</head>
<body>

<h1>11 – Сигурност на уеб приложенията</h1>

<!-- ── 1. XSS ─────────────────────────────────────────────────────── -->
<div class="card">
    <h2>1. XSS – Cross-Site Scripting</h2>
    <form method="POST">
        <?= csrfField() ?>
        <p>Въведете <code>&lt;script&gt;alert('XSS!')&lt;/script&gt;</code> за тест:</p>
        <input name="name" placeholder="Вашето ime" value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <input name="email" placeholder="Email" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <textarea name="comment" rows="2" placeholder="Коментар"><?= htmlspecialchars($_POST['comment'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        <button type="submit">Изпрати</button>
    </form>

    <?php if ($demo['xss_raw'] !== ''): ?>
    <p><span class="vuln">✘ УЯЗВИМО (raw output):</span><br>
       <!-- НЕ правете това в истинско приложение! -->
       <?= $demo['xss_raw'] ?></p>
    <p><span class="safe">✔ БЕЗОПАСНО (htmlspecialchars):</span><br>
       <?= $demo['xss_safe'] ?></p>
    <?php endif; ?>

    <pre>// ✘ Уязвимо:
echo $_POST['name'];

// ✔ Безопасно:
echo htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8');</pre>
</div>

<!-- ── 2. CSRF ────────────────────────────────────────────────────── -->
<div class="card">
    <h2>2. CSRF – Cross-Site Request Forgery</h2>

    <?php if ($demo['csrf_ok'] === false): ?>
    <div class="err">✘ CSRF токенът е невалиден – заявката е отхвърлена!</div>
    <?php elseif ($demo['csrf_ok'] === true): ?>
    <div class="ok">✔ CSRF токенът е валиден – заявката е приета.</div>
    <?php endif; ?>

    <p>Текущ CSRF токен: <code><?= substr($csrfToken, 0, 16) ?>...</code></p>
    <pre>// Генериране:
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// В HTML формата:
echo '&lt;input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '"&gt;';

// Проверка (timing-safe):
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403); die('CSRF Protection');
}</pre>
</div>

<!-- ── 3. Валидация и резултат ────────────────────────────────────── -->
<?php if ($demo['form_ok'] === true): ?>
<div class="card">
    <h2>✔ Формата е приета</h2>
    <pre><?= htmlspecialchars(json_encode($demo['submitted'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') ?></pre>
</div>
<?php elseif ($demo['form_ok'] === false): ?>
<div class="card">
    <h2>✘ Грешки при валидация</h2>
    <?php foreach ($demo['errors'] as $e): ?>
    <p class="err"><?= htmlspecialchars($e) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── 4. Security Headers ────────────────────────────────────────── -->
<div class="card">
    <h2>4. Сигурни HTTP Заглавия</h2>
    <pre>header("Content-Security-Policy: default-src 'self'");
header("X-Frame-Options: DENY");               // Clickjacking защита
header("X-Content-Type-Options: nosniff");     // MIME sniffing защита
header("Referrer-Policy: strict-origin-when-cross-origin");</pre>
    <p>Текущи заглавия на страницата:
       Проверете с <code>F12 → Network → Response Headers</code>.</p>
</div>

<!-- ── 5. SQL Injection ───────────────────────────────────────────── -->
<div class="card">
    <h2>5. SQL Injection – Prepared Statements</h2>
    <pre>// ✘ УЯЗВИМО:
$name = $_GET['name'];
$pdo->query("SELECT * FROM users WHERE username = '{$name}'");
// Атака: name = ' OR '1'='1

// ✔ БЕЗОПАСНО:
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$_GET['name']]);</pre>
</div>

</body>
</html>
