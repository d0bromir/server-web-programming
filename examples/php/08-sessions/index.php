<?php
declare(strict_types=1);
/**
 * Тема 8 – Управление на състояние: Сесии и Cookies
 *
 * Демонстрира:
 *  - PHP session lifecycle (start, read, write, destroy)
 *  - Flash messages чрез сесия
 *  - Cookies: setcookie(), $_COOKIE, изтичане, path, httponly
 *  - Session hijacking защита (regenerate_id)
 *  - "Запомни ме" (remember-me cookie)
 *
 * Стартиране: php -S localhost:8000
 *
 * curl заявки (ръчно тестване):
 *   # Запис на cookie jar за session cookie
 *   COOKIEJAR=$(mktemp /tmp/cookies-XXXX.txt)
 *
 *   # Начална страница (без активна сесия)
 *   curl -c "$COOKIEJAR" http://localhost:8000/
 *
 *   # Стартиране/актуализиране на сесия
 *   curl -b "$COOKIEJAR" -c "$COOKIEJAR" \
 *        -X POST "http://localhost:8000/?action=set_session" \
 *        -d "username=Иван"
 *
 *   # Начална страница (с активна сесия)
 *   curl -b "$COOKIEJAR" http://localhost:8000/
 *
 *   # Задаване на cookie
 *   curl -b "$COOKIEJAR" -c "$COOKIEJAR" \
 *        -X POST "http://localhost:8000/?action=set_cookie" \
 *        -d "name=theme&value=dark"
 *
 *   # Изтриване на cookie
 *   curl -b "$COOKIEJAR" -c "$COOKIEJAR" \
 *        -X POST "http://localhost:8000/?action=delete_cookie" \
 *        -d "name=theme"
 */

// ── Стартираме сесията с security настройки ───────────────────────────
session_set_cookie_params([
    'lifetime' => 0,           // Изтича при затваряне на браузъра
    'path'     => '/',
    'secure'   => false,       // true в production (HTTPS)
    'httponly' => true,        // JS нямА достъп - защита от XSS
    'samesite' => 'Lax',       // Защита от CSRF за form submissions
]);
session_start();

// ── Обработка на действия ──────────────────────────────────────────────

$action = $_POST['action'] ?? $_GET['action'] ?? '';

match ($action) {
    'set_session' => (function () {
        $_SESSION['user']    = htmlspecialchars(trim($_POST['username'] ?? 'Гост'), ENT_QUOTES, 'UTF-8');
        $_SESSION['counter'] = ($_SESSION['counter'] ?? 0) + 1;
        // Регенерираме ID при "login" – предотвратява session fixation
        session_regenerate_id(delete_old_session: true);
        $_SESSION['flash'] = ['msg' => "Сесията е актуализирана. User: {$_SESSION['user']}", 'type' => 'ok'];
        header('Location: /');
        exit;
    })(),

    'destroy_session' => (function () {
        $_SESSION = [];
        session_destroy();
        $_SESSION['flash'] = ['msg' => 'Сесията е унищожена.', 'type' => 'ok'];
        header('Location: /');
        exit;
    })(),

    'set_cookie' => (function () {
        $value    = htmlspecialchars(trim($_POST['cvalue'] ?? 'демо'), ENT_QUOTES, 'UTF-8');
        $expires  = (int) ($_POST['expires'] ?? 3600);
        setcookie(
            name:             'demo_cookie',
            value:            $value,
            expires_or_options: $expires > 0 ? time() + $expires : 0,
            path:             '/',
            secure:           false,   // true в production
            httponly:         true,    // не е достъпна от JavaScript
        );
        // Favorites biscuit - обикновена cookie
        setcookie('theme', 'dark', time() + 86400 * 30, '/');  // 30 дни
        header('Location: /');
        exit;
    })(),

    'delete_cookie' => (function () {
        // Изтриваме cookie като зададем expires в миналото
        setcookie('demo_cookie', '', time() - 3600, '/');
        header('Location: /');
        exit;
    })(),

    default => null,
};

// ── Flash message ──────────────────────────────────────────────────────
$flash = null;
if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<title>08 – Сесии и Cookies</title>
<style>
body{font-family:Arial,sans-serif;max-width:860px;margin:40px auto;padding:0 20px}
h2{border-bottom:1px solid #eee;padding-bottom:6px}
code{background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:.88em}
pre{background:#f7f7f7;padding:14px;border-radius:6px;overflow-x:auto;font-size:.83em}
.card{border:1px solid #ddd;border-radius:8px;padding:18px;margin:18px 0}
.ok{color:#27ae60;background:#eafaf1;padding:10px;border-radius:4px}
input,select{padding:7px;font-size:.95em;border:1px solid #ccc;border-radius:4px}
button{padding:7px 14px;background:#2980b9;color:#fff;border:none;border-radius:4px;cursor:pointer}
button.red{background:#c0392b}
</style>
</head>
<body>

<h1>08 – Управление на сесии и cookies</h1>

<?php if ($flash): ?>
<p class="<?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></p>
<?php endif; ?>

<!-- ── 1. Сесия ──────────────────────────────────────────────────── -->
<div class="card">
    <h2>Сесия (<code>$_SESSION</code>)</h2>

    <p><strong>Session ID:</strong> <code><?= htmlspecialchars(session_id()) ?></code></p>
    <p><strong>$_SESSION съдържание:</strong></p>
    <pre><?= htmlspecialchars(var_export($_SESSION, true), ENT_QUOTES, 'UTF-8') ?></pre>

    <form method="POST">
        <input type="hidden" name="action" value="set_session">
        <input name="username" placeholder="Потребителско име" value="<?= htmlspecialchars($_SESSION['user'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit">Запиши в сесията</button>
    </form>
    <br>
    <form method="POST">
        <input type="hidden" name="action" value="destroy_session">
        <button type="submit" class="red">Унищожи сесията</button>
    </form>

    <pre style="margin-top:12px">// PHP код:
session_start();               // Стартира / наследява сесия
$_SESSION['key'] = 'value';    // Записва
$value = $_SESSION['key'];     // Чете
session_regenerate_id(true);   // Ново ID (при login)
session_destroy();             // Унищожава сесията</pre>
</div>

<!-- ── 2. Cookies ────────────────────────────────────────────────── -->
<div class="card">
    <h2>Cookies (<code>setcookie()</code>)</h2>

    <p><strong>Текущи cookies:</strong></p>
    <pre><?php
    if (empty($_COOKIE)) {
        echo "(няма)";
    } else {
        foreach ($_COOKIE as $k => $v) {
            echo htmlspecialchars($k) . " = " . htmlspecialchars($v) . "\n";
        }
    }
    ?></pre>

    <form method="POST">
        <input type="hidden" name="action" value="set_cookie">
        <input name="cvalue" placeholder="Стойност на cookie" value="демо-стойност">
        <select name="expires">
            <option value="3600">1 час</option>
            <option value="86400">1 ден</option>
            <option value="0">Сесийна (без expires)</option>
        </select>
        <button type="submit">Задай cookie</button>
    </form>
    <br>
    <form method="POST">
        <input type="hidden" name="action" value="delete_cookie">
        <button type="submit" class="red">Изтрий demo_cookie</button>
    </form>

    <pre style="margin-top:12px">// PHP код:
setcookie(
    name: 'favorite',
    value: 'pizza',
    expires: time() + 86400 * 30, // 30 дни
    path: '/',
    secure: true,      // HTTPS only
    httponly: true,    // Не е достъпна от JavaScript
);

$value = $_COOKIE['favorite'] ?? null; // Чтене</pre>
</div>

<!-- ── 3. Сравнение ──────────────────────────────────────────────── -->
<div class="card">
    <h2>Сесия vs Cookie – кога кое да използваме</h2>
    <table style="width:100%;border-collapse:collapse">
        <tr><th style="text-align:left;padding:8px;border:1px solid #ddd"></th>
            <th style="padding:8px;border:1px solid #ddd">Сесия</th>
            <th style="padding:8px;border:1px solid #ddd">Cookie</th></tr>
        <tr><td style="padding:8px;border:1px solid #ddd">Съхранение</td>
            <td style="padding:8px;border:1px solid #ddd">Сървър</td>
            <td style="padding:8px;border:1px solid #ddd">Клиент</td></tr>
        <tr><td style="padding:8px;border:1px solid #ddd">Размер</td>
            <td style="padding:8px;border:1px solid #ddd">Неограничен</td>
            <td style="padding:8px;border:1px solid #ddd">~4 KB</td></tr>
        <tr><td style="padding:8px;border:1px solid #ddd">Сигурност</td>
            <td style="padding:8px;border:1px solid #ddd">По-сигурна</td>
            <td style="padding:8px;border:1px solid #ddd">Достъпна в браузъра</td></tr>
        <tr><td style="padding:8px;border:1px solid #ddd">Издържливост</td>
            <td style="padding:8px;border:1px solid #ddd">До затваряне / таймаут</td>
            <td style="padding:8px;border:1px solid #ddd">Задава се с expires</td></tr>
        <tr><td style="padding:8px;border:1px solid #ddd">Използва се за</td>
            <td style="padding:8px;border:1px solid #ddd">Login данни, кошница</td>
            <td style="padding:8px;border:1px solid #ddd">Теми, предпочитания, "Запомни ме"</td></tr>
    </table>
</div>

</body>
</html>
