<?php
declare(strict_types=1);
/**
 * Тема 9 – Автентикация и авторизация
 *
 * Демонстрира:
 *  - Регистрация и хеширане на парола (password_hash / password_verify)
 *  - Login / Logout цикъл
 *  - Session-based автентикация
 *  - Роли (role-based authorization): admin / user
 *  - Auth guard (middleware)
 *
 * Стартиране: php -S localhost:8000
 * Абонати по подразбиране:
 *   admin / admin123  (роля: admin)
 *   user  / user123   (роля: user)
 *
 * curl заявки (ръчно тестване):
 *   # Запис на cookie jar за session cookie
 *   COOKIEJAR=$(mktemp /tmp/cookies-XXXX.txt)
 *
 *   # Login form
 *   curl http://localhost:8000/login
 *
 *   # Вход като admin
 *   curl -c "$COOKIEJAR" -X POST http://localhost:8000/login \
 *        -d "username=admin&password=admin123"
 *
 *   # Dashboard (изисква автентикация)
 *   curl -b "$COOKIEJAR" http://localhost:8000/dashboard
 *
 *   # Admin страница (изисква роля admin)
 *   curl -b "$COOKIEJAR" http://localhost:8000/admin
 *
 *   # Изход
 *   curl -b "$COOKIEJAR" -c "$COOKIEJAR" \
 *        -X POST http://localhost:8000/logout
 *
 *   # Вход като обикновен user
 *   curl -c "$COOKIEJAR" -X POST http://localhost:8000/login \
 *        -d "username=user&password=user123"
 *
 *   # Admin страница – отказан достъп (потребителят е с роля user)
 *   curl -b "$COOKIEJAR" http://localhost:8000/admin
 */

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// ══════════════════════════════════════════════════════════════════════
//  "БАЗА ДАННИ" (in-memory за простота)
//
//  ХЕШИРАНЕ НА ПАРОЛИ
//  ─────────────────────
//  Никога не съхранявайте пароли в plaintext! Ако БД бъде изтече,
//  атакуващият ще получи директно всички пароли.
//
//  password_hash($plaintext, PASSWORD_DEFAULT)
//    – PHP избира алгоритъма автоматично (днес = bcrypt)
//    – Всеки път връща РАЗЛИЧЕН hash (включва случаен salt)
//    – Формат: $2y$10$<22-символа salt><31-символа hash>
//    – пример: '$2y$10$abcdefghijklmnopqrstuuXXXXXXXXXXXXXXXXXXXXXXXXXXXXX'
//
//  password_verify($input, $hash)
//    – Извлича salt-а от hash-а, хешира input-а и сравнява
//    – Използва сравнение в постоянно време (timing-safe) за защита
//      срещу timing attacks
//
//  В реален проект:
//    CREATE TABLE users (id INTEGER PK, username TEXT UNIQUE NOT NULL,
//                        password_hash TEXT NOT NULL, role TEXT NOT NULL);
//    -- При регистрация:
//    INSERT INTO users (username, password_hash, role)
//    VALUES (?, password_hash(?), 'user');
//    -- При login:
//    $row = SELECT * FROM users WHERE username = ?;
//    if (password_verify($input, $row['password_hash'])) { ... }
// ══════════════════════════════════════════════════════════════════════

// В реален проект: PDO + users таблица
// password_hash() използва bcrypt по подразбиране (BCRYPT_DEFAULT)
$users = [
    'admin' => [
        'id'       => 1,
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'role'     => 'admin',
        'name'     => 'Администратор',
    ],
    'user'  => [
        'id'       => 2,
        'username' => 'user',
        'password' => password_hash('user123', PASSWORD_DEFAULT),
        'role'     => 'user',
        'name'     => 'Обикновен потребител',
    ],
];

// ══════════════════════════════════════════════════════════════════════
//  AUTH HELPERS
//
//  SESSION-BASED АВТЕНТИКАЦИЯ
//  ─────────────────────────
//  Когато потребителят влезе успешно, PHP:
//    1. Създава сесия (file на сървъра или Redis/Memcached)
//    2. Изпраща session ID чрез Set-Cookie: PHPSESSID=<hash>; HttpOnly
//    3. Браузърът връща PHPSESSID при всяка последваща заявка
//    4. PHP зарежда $_SESSION от файла по session ID
//
//  session_regenerate_id(true) – ВАЖНО! Пренамерва session ID след login,
//  за да предотврати Session Fixation атака:
//    атакуващият знае session ID (напр. от URL) преди вход
//    → след входа има автентицирана сесия със знаетия session ID
//    → решение: след проверка = смяна на ID (старият се изтрива)
//
//  session_set_cookie_params(['httponly'=>true, 'samesite'=>'Lax'])
//    httponly: бразърът блокира JavaScript достъпа до cookie
//              → защита срещу XSS
//    samesite: браузърът не изпраща cookie при cross-site заявки
//              → защита срещу CSRF
//
//  РОЛИ (АВТОРИЗАЦИЯ)
//  ─────────────────
//  Автентикация = потвърждаване на самоличността ("кой си?")
//  Авторизация  = проверка на права ("имаш ли право?")
//
//  requireAuth()     – пренасочва към /login ако няма активна сесия
//  requireRole('x')  – ако ролята не съвпада → 403 Forbidden
//
//  intended_url: запазваме URL-а, към който потребителят искаше да
//  отиде, за да го пренасочим там след успешен login.
//
//  ГРЕШКО СЪОБЩЕНИЕ = фиксирано:
//  "Грешно потребителско или парола." – не (зло) кой от двата
//  е грешен, защото това би помогнало на атакуващ да
//  прецени дали username-ът съществува.
// ══════════════════════════════════════════════════════════════════════

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function currentUser(): ?array
{
    return $_SESSION['auth_user'] ?? null;
}

function hasRole(string $role): bool
{
    return (currentUser()['role'] ?? '') === $role;
}

/** Redirect ако не е влязъл */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        $_SESSION['flash']       = ['msg' => 'Необходима е автентикация.', 'type' => 'err'];
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'];
        header('Location: /login');
        exit;
    }
}

/** Redirect ако нямa нужната роля */
function requireRole(string $role): void
{
    requireAuth();
    if (!hasRole($role)) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>Нямате права за тази страница.</p><a href="/">Начало</a>');
    }
}

// ══════════════════════════════════════════════════════════════════════
//  ROUTING
// ══════════════════════════════════════════════════════════════════════

$path   = strtok($_SERVER['REQUEST_URI'], '?');
$method = $_SERVER['REQUEST_METHOD'];
$flash  = null;
if (isset($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

// ── POST /login ───────────────────────────────────────────────────────
if ($path === '/login' && $method === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = $users[$username] ?? null;

    // ВАЖНО: password_verify() – сравнява plaintext с hash
    // Никога не съхранявайте пароли в plaintext!
    if ($user && password_verify($password, $user['password'])) {
        // Регенерираме session ID при login – предотвратява session fixation
        session_regenerate_id(delete_old_session: true);

        $_SESSION['user_id']   = $user['id'];
        $_SESSION['auth_user'] = ['id' => $user['id'], 'username' => $user['username'],
                                   'role' => $user['role'], 'name' => $user['name']];

        $redirect = $_SESSION['intended_url'] ?? '/dashboard';
        unset($_SESSION['intended_url']);
        header("Location: {$redirect}");
    } else {
        // Фиксирано съобщение – не разкриваме дали username или password е грешен
        $_SESSION['flash'] = ['msg' => 'Грешно потребителско или парола.', 'type' => 'err'];
        header('Location: /login');
    }
    exit;
}

// ── POST /logout ──────────────────────────────────────────────────────
if ($path === '/logout' && $method === 'POST') {
    $_SESSION = [];
    session_destroy();
    header('Location: /login');
    exit;
}

// ── Pages ─────────────────────────────────────────────────────────────

match ($path) {
    '/login'     => render_login($flash),
    '/dashboard' => (function () use ($flash) { requireAuth(); render_dashboard($flash); })(),
    '/admin'     => (function () use ($flash) { requireRole('admin'); render_admin($flash); })(),
    default      => (isLoggedIn() ? header('Location: /dashboard') : header('Location: /login')) && exit,
};
exit;

// ══════════════════════════════════════════════════════════════════════
//  VIEWS
// ══════════════════════════════════════════════════════════════════════

function layout(string $title, string $body): void { ?>
<!DOCTYPE html>
<html lang="bg"><head><meta charset="UTF-8"><title><?= htmlspecialchars($title) ?></title>
<style>
body{font-family:Arial,sans-serif;max-width:700px;margin:60px auto;padding:0 20px}
.card{border:1px solid #ddd;border-radius:8px;padding:24px;max-width:400px;margin:0 auto}
.ok{color:#27ae60;background:#eafaf1;padding:10px;border-radius:4px}
.err{color:#c0392b;background:#fadbd8;padding:10px;border-radius:4px}
input{padding:9px;width:100%;box-sizing:border-box;border:1px solid #ccc;border-radius:4px;font-size:1em;margin-bottom:12px}
button,a.btn{padding:9px 18px;font-size:1em;border:none;border-radius:4px;cursor:pointer;text-decoration:none;display:inline-block;margin-top:6px}
.btn-blue{background:#2980b9;color:#fff} .btn-red{background:#c0392b;color:#fff}
.role-admin{background:#8e44ad;color:#fff;padding:3px 10px;border-radius:12px;font-size:.85em}
.role-user{background:#2980b9;color:#fff;padding:3px 10px;border-radius:12px;font-size:.85em}
</style></head><body><?= $body ?></body></html>
<?php }

function render_login(?array $flash): void
{
    $f = $flash ? '<p class="' . $flash['type'] . '">' . htmlspecialchars($flash['msg']) . '</p>' : '';
    layout('Login', "
    <h2 style='text-align:center'>Вход в системата</h2>
    {$f}
    <div class='card'>
        <form method='POST' action='/login'>
            <input name='username' placeholder='Потребителско ime' required autocomplete='username'>
            <input name='password' type='password' placeholder='Парола' required autocomplete='current-password'>
            <button type='submit' class='btn-blue' style='width:100%'>Вход</button>
        </form>
        <p style='text-align:center;color:#888;font-size:.85em;margin-top:16px'>
            admin / admin123 &nbsp;|&nbsp; user / user123
        </p>
    </div>");
}

function render_dashboard(?array $flash): void
{
    $u = currentUser();
    $f = $flash ? '<p class="' . $flash['type'] . '">' . htmlspecialchars($flash['msg']) . '</p>' : '';
    $roleClass = 'role-' . $u['role'];
    $adminLink = hasRole('admin')
        ? '<p><a href="/admin" class="btn btn-blue">Администраторски панел</a></p>'
        : '<p style="color:#888">Нямате достъп до администраторския панел.</p>';
    layout('Dashboard', "
    {$f}
    <h1>Добре дошли, " . htmlspecialchars($u['name']) . "!</h1>
    <p>Потребител: <strong>" . htmlspecialchars($u['username']) . "</strong>
       &nbsp; Роля: <span class='{$roleClass}'>{$u['role']}</span></p>
    <p>Session ID: <code>" . htmlspecialchars(session_id()) . "</code></p>
    {$adminLink}
    <form method='POST' action='/logout'>
        <button type='submit' class='btn-red'>Изход</button>
    </form>
    <hr>
    <h2>Как работи автентикацията?</h2>
    <pre style='background:#f7f7f7;padding:14px;border-radius:6px;font-size:.83em'>
// Хеширане при регистрация:
\$hash = password_hash(\$plaintext, PASSWORD_DEFAULT);
// PASSWORD_DEFAULT = bcrypt (адаптивно хеширане)

// Проверка при login:
if (password_verify(\$input, \$hash)) {
    // Паролата е правилна
    session_regenerate_id(true); // Ново session ID
    \$_SESSION['user_id'] = \$user['id'];
}

// Auth guard:
if (!isset(\$_SESSION['user_id'])) {
    header('Location: /login'); exit;
}</pre>");
}

function render_admin(?array $flash): void
{
    layout('Admin Panel', "
    <h1>Администраторски панел</h1>
    <p>✔ Имате роля <span class='role-admin'>admin</span> и достъп до тази страница.</p>
    <a href='/dashboard' class='btn btn-blue'>← Назад</a>");
}
