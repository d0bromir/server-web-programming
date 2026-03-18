<?php
declare(strict_types=1);
/**
 * Тема 1: Въведение в сървърното уеб програмиране
 *
 * Демонстрира:
 *  - Как PHP обработва HTTP заявката и генерира HTML отговор
 *  - Суперглобали: $_GET, $_POST, $_SERVER
 *  - Вградения уеб сървър (php -S localhost:8000)
 *
 * Стартиране: php -S localhost:8000
 * Отворете:   http://localhost:8000
 */

// ── Обработка на заявката ─────────────────────────────────────────────────

$name   = htmlspecialchars(trim($_GET['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'];

// Ако е изпратена POST форма – обработваме данните
$message = '';
if ($method === 'POST' && isset($_POST['greeting'])) {
    $greeting = htmlspecialchars(trim($_POST['greeting']), ENT_QUOTES, 'UTF-8');
    $message  = "Изпратихте: „{$greeting}"";
}

$display = $name !== '' ? $name : 'Свят';
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>01 – Въведение в сървърното уеб програмиране</title>
    <style>
        body  { font-family: Arial, sans-serif; max-width: 820px; margin: 40px auto; padding: 0 20px; }
        h1    { color: #2c3e50; }
        h2    { color: #34495e; border-bottom: 1px solid #eee; padding-bottom: 6px; }
        code  { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-size: .9em; }
        pre   { background: #f7f7f7; padding: 15px; border-radius: 6px; overflow-x: auto; font-size: .85em; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0; }
        .ok   { color: #27ae60; font-weight: bold; }
        input, button { padding: 8px 14px; font-size: 1em; }
        button { background: #2980b9; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>

<h1>Здравей, <?= $display ?>!</h1>

<?php if ($message !== ''): ?>
    <p class="ok">✔ <?= $message ?></p>
<?php endif; ?>

<!-- ── 1. Как работи PHP ───────────────────────────────────────────────── -->
<div class="card">
    <h2>Как работи PHP?</h2>
    <ol>
        <li>Браузърът изпраща <strong>HTTP заявка</strong> към сървъра</li>
        <li>PHP <strong>обработва файла</strong> – изпълнява кода</li>
        <li>Генерира се <strong>HTML отговор</strong>, изпратен обратно</li>
        <li>Браузърът <strong>визуализира</strong> HTML-а</li>
    </ol>
    <p>При вградения сървър <code>php -S localhost:8000</code> PHP изпълнява тази роля локално.</p>
</div>

<!-- ── 2. Информация за сървъра ───────────────────────────────────────── -->
<div class="card">
    <h2>Информация за сървъра</h2>
    <p><strong>PHP версия:</strong> <code><?= PHP_VERSION ?></code></p>
    <p><strong>HTTP метод:</strong> <code><?= htmlspecialchars($method, ENT_QUOTES, 'UTF-8') ?></code></p>
    <p><strong>URL:</strong> <code><?= htmlspecialchars($uri, ENT_QUOTES, 'UTF-8') ?></code></p>
    <p><strong>Скрипт:</strong> <code><?= htmlspecialchars($_SERVER['SCRIPT_FILENAME'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></p>
</div>

<!-- ── 3. GET параметри ───────────────────────────────────────────────── -->
<div class="card">
    <h2>GET параметри</h2>
    <p>Добавете <code>?name=Иван</code> в URL-а:</p>
    <a href="?name=Иван">?name=Иван</a> &nbsp;|&nbsp;
    <a href="?name=Мария">?name=Мария</a> &nbsp;|&nbsp;
    <a href="?">(без параметри)</a>
    <pre><?= htmlspecialchars(var_export($_GET, true), ENT_QUOTES, 'UTF-8') ?></pre>
</div>

<!-- ── 4. POST форма ─────────────────────────────────────────────────── -->
<div class="card">
    <h2>POST форма</h2>
    <form method="POST">
        <input type="text" name="greeting" placeholder="Въведете приветствие" required>
        <button type="submit">Изпрати</button>
    </form>
    <?php if (!empty($_POST)): ?>
        <pre><?= htmlspecialchars(var_export($_POST, true), ENT_QUOTES, 'UTF-8') ?></pre>
    <?php endif; ?>
</div>

<!-- ── 5. Суперглобали ────────────────────────────────────────────────── -->
<div class="card">
    <h2>Суперглобали <code>$_SERVER</code> (извадка)</h2>
    <pre><?php
    $keys = ['SERVER_SOFTWARE','HTTP_HOST','HTTP_USER_AGENT','REMOTE_ADDR','REQUEST_TIME'];
    foreach ($keys as $k) {
        if (isset($_SERVER[$k])) {
            echo '$_SERVER[' . var_export($k, true) . '] = '
                . var_export($_SERVER[$k], true) . "\n";
        }
    }
    ?></pre>
</div>

</body>
</html>
