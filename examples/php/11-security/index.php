<?php
declare(strict_types=1);
/**
 * Тема 11 – Сигурност на уеб приложенията
 *
 * Демонстрира:
 *
 *  1. XSS (Cross-Site Scripting)
 *     Нападателят инжектира JavaScript в страницата, която след
 *     това се изпълнява в браузъра на жертвата.
 *     Пример: <input value="<script>document.cookie</script>">
 *     Превенция: htmlspecialchars($val, ENT_QUOTES, 'UTF-8') преди
 *     всеки изход в HTML; Content-Security-Policy хедър.
 *
 *  2. CSRF (Cross-Site Request Forgery)
 *     Злонамерен сайт кара браузъра на влязъл потребител да изпрати
 *     заявка към целевия сайт (напр. банков превод) без негово знание.
 *     Браузърът автоматично включва cookies → заявката изглежда легитимна.
 *     Превенция: скрит токен в HTML формата, проверяван на сървъра с
 *     hash_equals(); SameSite=Lax/Strict cookie атрибут.
 *
 *  3. SQL Injection
 *     Нападателят добавя SQL код към входни данни, с което манипулира
 *     заявката към БД.
 *     Пример: username = ' OR '1'='1  →  SELECT * FROM users WHERE username='' OR '1'='1'
 *     Превенция: PDO prepared statements – стойностите никога не се
 *     конкатенират директно в SQL низа.
 *
 *  4. Входна валидация и санитизация
 *     Валидация  = проверка дали данните отговарят на очакван формат
 *                  (задължително поле, email, мин/макс дължина).
 *     Санитизация = почистване на данните преди запис/изход
 *                  (trim, htmlspecialchars, filter_var).
 *     Правило: валидирайте на сървъра ВИНАГИ – клиентската валидация
 *     може да бъде заобиколена.
 *
 *  5. Сигурни HTTP заглавия (Security Headers)
 *     Content-Security-Policy   – ограничава откъде може да се зарежда съдържание;
 *                                  основна защита срещу XSS.
 *     X-Frame-Options: DENY     – забранява вграждането в <iframe>;
 *                                  защита срещу Clickjacking.
 *     X-Content-Type-Options    – браузърът не "познава" MIME типа;
 *                                  защита срещу MIME sniffing атаки.
 *     Referrer-Policy           – контролира какво се изпраща в Referer хедъра.
 *     Permissions-Policy        – изключва ненужни браузърни API-та (камера, GPS).
 *
 *  6. Replay Attack защита (nonce / timestamp)
 *     Нападателят прихваща валидна заявка и я изпраща повторно по-късно.
 *     Превенция: към всяка заявка се добавя:
 *       nonce     – случаен еднократен токен, запазван в сесията;
 *                   след употреба се изтрива (не може да се ползва пак).
 *       timestamp – сървърът отхвърля заявки извън допустим прозорец
 *                   (напр. ±5 минути).
 *
 * Стартиране: php -S localhost:8000
 *
 * curl заявки (ръчно тестване):
 *   # Начална страница – вижте security headers в отговора
 *   curl -I http://localhost:8000/
 *
 *   # Пълен отговор
 *   curl http://localhost:8000/
 *
 *   # XSS опит (htmlspecialchars го неутрализира)
 *   curl "http://localhost:8000/?name=<script>alert(1)</script>"
 *
 *   # SQL injection опит (prepared statement го блокира)
 *   curl "http://localhost:8000/?search=' OR '1'='1"
 */

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// ══════════════════════════════════════════════════════════════════════
//  CSP TOGGLE  (?action=toggle-csp)
//  Обработва се ПРЕДИ headers, за да може redirect-ът да е чист.
//  Съхранява се в сесията – по подразбиране CSP е ВКЛЮЧЕН.
// ══════════════════════════════════════════════════════════════════════

if (($_GET['action'] ?? '') === 'toggle-csp' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['csp_enabled'] = !(($_SESSION['csp_enabled'] ?? true));
    header('Location: /');
    exit;
}

$cspEnabled = $_SESSION['csp_enabled'] ?? true;

// ══════════════════════════════════════════════════════════════════════
//  1. SECURITY HEADERS
//     Задаваме ги преди всяка страница
// ══════════════════════════════════════════════════════════════════════

// CSP се изпраща само когато е включен (може да се изключи за демо на XSS)
if ($cspEnabled) {
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'");
}
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
//  4. AJAX ENDPOINT ROUTING (за интерактивните тестове)
//     Всички заявки от JavaScript тест-компонентите минават оттук
//     и връщат JSON. Достъпват се чрез ?action=<name>.
// ══════════════════════════════════════════════════════════════════════

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── ?action=csrf-test (POST) ──────────────────────────────────────────
if ($action === 'csrf-test' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'CSRF Token невалиден – заявката е отхвърлена!'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['ok' => true, 'message' => 'CSRF Token е валиден – заявката е приета.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ── ?action=new-nonce (GET) ───────────────────────────────────────────
if ($action === 'new-nonce') {
    header('Content-Type: application/json; charset=utf-8');
    $nonce = bin2hex(random_bytes(16));
    $_SESSION['replay_nonce']      = $nonce;
    $_SESSION['replay_nonce_used'] = false;
    echo json_encode(['nonce' => $nonce, 'timestamp' => time()]);
    exit;
}

// ── ?action=replay-test (POST) ────────────────────────────────────────
if ($action === 'replay-test' && $method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $nonce = $_POST['nonce'] ?? '';
    $ts    = (int)($_POST['timestamp'] ?? 0);
    $now   = time();

    if (abs($now - $ts) > 300) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Timestamp извън прозореца ±5 мин. Разлика: ' . abs($now - $ts) . ' сек.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (empty($nonce) || $nonce !== ($_SESSION['replay_nonce'] ?? '')) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Невалиден nonce – вземете нов.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($_SESSION['replay_nonce_used'] === true) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => '⚠ Replay Attack! Nonce е вече използван. Заявката е отхвърлена.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $_SESSION['replay_nonce_used'] = true;
    echo json_encode(['ok' => true, 'message' => '✔ Заявката е приета. Nonce е изразходен (повторна употреба е невъзможна).'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── ?action=sql-demo (GET) ────────────────────────────────────────────
if ($action === 'sql-demo') {
    header('Content-Type: application/json; charset=utf-8');
    $input = $_GET['input'] ?? '';

    if (!class_exists('PDO') || !in_array('sqlite', PDO::getAvailableDrivers())) {
        http_response_code(501);
        echo json_encode(['ok' => false, 'error' => 'PDO SQLite не е наличен в тази PHP инсталация.']);
        exit;
    }

    $db = new PDO('sqlite::memory:');
    $db->exec("CREATE TABLE users (id INTEGER, name TEXT, email TEXT)");
    $db->exec("INSERT INTO users VALUES (1,'Alice','alice@example.com')");
    $db->exec("INSERT INTO users VALUES (2,'Bob','bob@example.com')");
    $db->exec("INSERT INTO users VALUES (3,'Admin','admin@example.com')");

    // Уязвима заявка (директна конкатенация на вход)
    $vulnQuery = "SELECT id,name,email FROM users WHERE name='{$input}'";
    try {
        $vulnRows  = $db->query($vulnQuery)->fetchAll(PDO::FETCH_ASSOC);
        $vulnError = null;
    } catch (\Exception $e) {
        $vulnRows  = [];
        $vulnError = 'SQL ERROR: ' . $e->getMessage();
    }

    // Безопасна заявка (prepared statement)
    $stmt = $db->prepare("SELECT id,name,email FROM users WHERE name=?");
    $stmt->execute([$input]);
    $safeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok'         => true,
        'input'      => $input,
        'vuln_query' => $vulnQuery,
        'vuln_rows'  => $vulnRows,
        'vuln_error' => $vulnError,
        'safe_query' => 'SELECT id,name,email FROM users WHERE name=?',
        'safe_rows'  => $safeRows,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ══════════════════════════════════════════════════════════════════════
//  5. ОБРАБОТКА НА ФОРМА (с всички защити)
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
button{padding:8px 16px;background:#2980b9;color:#fff;border:none;border-radius:4px;cursor:pointer;margin:3px 3px 3px 0}
button:hover{opacity:.85}
button.danger{background:#c0392b}
button.secondary{background:#7f8c8d}
button:disabled{opacity:.45;cursor:default}
.test-out{min-height:40px;margin-top:8px;white-space:pre-wrap;word-break:break-all;background:#f7f7f7;padding:12px;border-radius:6px;font-size:.83em;font-family:monospace}
.test-out.ok{background:#eafaf1;color:#1a7a42}
.test-out.err{background:#fadbd8;color:#922b21}
</style>
</head>
<body>

<!-- ── CSP режим – лента + превключвател ─────────────────────────── -->
<?php
$cspBg     = $cspEnabled ? '#eafaf1' : '#fadbd8';
$cspBorder = $cspEnabled ? '#27ae60' : '#c0392b';
$cspLabel  = $cspEnabled
    ? '<span style="color:#27ae60"><strong>ВКЛ</strong> – защитен режим</span>'
    : '<span style="color:#c0392b"><strong>ИЗКЛ</strong> – XSS payload-ите се изпълняват!</span>';
$cspBtnBg  = $cspEnabled ? '#c0392b' : '#27ae60';
$cspBtnTxt = $cspEnabled ? 'Изключи CSP (демо на реален XSS)' : 'Включи CSP (защитен режим)';
?>
<div style="background:<?= $cspBg ?>;border:2px solid <?= $cspBorder ?>;border-radius:8px;padding:10px 18px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <span>Content-Security-Policy: <?= $cspLabel ?></span>
    <a href="/?action=toggle-csp"
       style="padding:6px 14px;border-radius:4px;background:<?= $cspBtnBg ?>;color:#fff;text-decoration:none;font-size:.9em;white-space:nowrap">
        <?= $cspBtnTxt ?>
    </a>
</div>

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
        <div style="margin:8px 0">
            <strong>Бързо попълване (XSS payloads):</strong>
            <button type="button" data-xss-fill="<?= htmlspecialchars("<script>alert('XSS!')</script>", ENT_QUOTES, 'UTF-8') ?>">&lt;script&gt;</button>
            <button type="button" data-xss-fill="<?= htmlspecialchars('<img src=x onerror=alert(1)>', ENT_QUOTES, 'UTF-8') ?>">&lt;img onerror&gt;</button>
            <button type="button" data-xss-fill="<?= htmlspecialchars('<svg onload=alert(1)>', ENT_QUOTES, 'UTF-8') ?>">&lt;svg onload&gt;</button>
            <button type="button" data-xss-fill="<?= htmlspecialchars("<a href=\"javascript:alert(1)\">Кликни</a>", ENT_QUOTES, 'UTF-8') ?>">&lt;a href=javascript:&gt;</button>
        </div>
    </form>

    <?php if ($demo['xss_raw'] !== ''): ?>
    <?php if ($cspEnabled): ?>
    <p><span class="vuln">✘ УЯЗВИМО – raw output (само htmlspecialchars липсва):</span></p>
    <p style="font-size:.82em;color:#7f8c8d">HTML, който би бил вмъкнат директно в страницата:</p>
    <pre><?= htmlspecialchars($demo['xss_raw'], ENT_QUOTES, 'UTF-8') ?></pre>
    <p style="font-size:.82em;background:#fef9e7;padding:8px;border-radius:4px">
       ℹ <strong>CSP е включен</strong> – инлайн скриптове и event handler-и са блокирани.
       Изключете CSP от лентата горе за да видите реалното изпълнение на payload-а.
    </p>
    <?php else: ?>
    <div class="err" style="margin:8px 0">⚠ CSP е ИЗКЛЮЧЕН – payload-ът по-долу се изпълнява реално!</div>
    <p><span class="vuln">✘ УЯЗВИМО – raw output (изпълнява се от браузъра):</span></p>
    <!-- НЕ правете това в истинско приложение! Само за демонстрация. -->
    <?= $demo['xss_raw'] ?>
    <p style="font-size:.82em;background:#fadbd8;padding:8px;border-radius:4px;margin-top:8px">
       ☝ <code>&lt;script&gt;alert()&lt;/script&gt;</code> се изпълни защото липсва CSP.
       <code>&lt;img onerror&gt;</code> / <code>&lt;svg onload&gt;</code> също работят.
    </p>
    <?php endif; ?>
    <p><span class="safe">✔ БЕЗОПАСНО – след htmlspecialchars:</span><br>
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
    <hr>
    <h3>Тест: изпрати заявка с/без CSRF токен</h3>
    <button id="csrf-valid-btn">✔ С валиден токен (легитимна заявка)</button>
    <button id="csrf-invalid-btn" class="danger">✘ БЕЗ токен (симулира CSRF атака)</button>
    <div id="csrf-test-out" class="test-out"></div>
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
    <button id="headers-check-btn">Провери заглавията на тази страница</button>
    <div id="headers-out" class="test-out"></div>
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
    <hr>
    <h3>Интерактивен тест</h3>
    <p>Таблица: <code>users (id, name, email)</code> с три реда – Alice, Bob, Admin.</p>
    <input id="sql-input" placeholder="Въведете стойност: Alice  или  ' OR '1'='1  или  Alice'; DROP TABLE users --" style="max-width:100%;margin-bottom:6px">
    <div>
        <button id="sql-btn-alice">Alice (нормален)</button>
        <button id="sql-btn-inject" class="danger">' OR '1'='1</button>
        <button id="sql-btn-drop" class="danger">Alice'; DROP TABLE users --</button>
        <button id="sql-btn-run" class="secondary">▶ Изпълни</button>
    </div>
    <div id="sql-out" class="test-out"></div>
</div>

<!-- ── 6. Replay Attack ──────────────────────────────────────────── -->
<div class="card">
    <h2>6. Replay Attack – Nonce + Timestamp защита</h2>
    <p>Нападателят прихваща валидна заявка и я изпраща повторно.
       Защитата: еднократен <strong>nonce</strong> (изтрива се след употреба)
       + <strong>timestamp</strong> (±5 мин прозорец).</p>
    <ol>
        <li>Вземете nonce от сървъра.</li>
        <li>Изпратете заявката → <em>приета</em>, nonce се маркира като използван.</li>
        <li>Повторете <em>същата</em> заявка → <em>отхвърлена</em>.</li>
    </ol>
    <div style="margin:10px 0">
        <button id="replay-get-btn">1. Вземете нов nonce</button>
        <span id="replay-nonce-display" style="margin-left:12px;font-family:monospace;font-size:.85em;color:#555"></span>
    </div>
    <div style="margin:6px 0">
        <button id="replay-send-btn" disabled>2. Изпрати (1-ви път)</button>
        <button id="replay-resend-btn" class="danger" disabled>3. Повтори! (Replay Attack)</button>
    </div>
    <div id="replay-out" class="test-out"></div>
    <pre>// PHP защита:
$nonce = $_POST['nonce'];
if ($nonce !== ($_SESSION['replay_nonce'] ?? ''))   { die('Невалиден nonce');      }
if ($_SESSION['replay_nonce_used'] === true)         { die('Replay! Nonce е изтрит'); }
if (abs(time() - $_POST['timestamp']) > 300)         { die('Timestamp изтекъл');    }
$_SESSION['replay_nonce_used'] = true; // Маркираме – не може повторно</pre>
</div>

<script src="security-ui.js"></script>
</body>
</html>
