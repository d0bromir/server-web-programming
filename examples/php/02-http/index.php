<?php
declare(strict_types=1);
/**
 * Тема 2: Клиент–сървър архитектура и HTTP протокол
 *
 * Демонстрира:
 *  - HTTP методи: GET, POST, PUT, DELETE
 *  - HTTP статус кодове (200, 201, 301, 400, 404, 500 …)
 *  - Request headers и Response headers
 *  - Query string и тяло на заявката
 *  - HTTP редиректи
 *
 * Стартиране: php -S localhost:8000
 *
 * curl заявки (ръчно тестване):
 *   # Начална страница
 *   curl http://localhost:8000/
 *
 *   # HTTP статус кодове
 *   curl -s -o /dev/null -w "%{http_code}" "http://localhost:8000/status?code=200"
 *   curl -s -o /dev/null -w "%{http_code}" "http://localhost:8000/status?code=404"
 *   curl -s -o /dev/null -w "%{http_code}" "http://localhost:8000/status?code=500"
 *
 *   # POST – echo на заявката
 *   curl -X POST http://localhost:8000/echo-request \
 *        -H "Content-Type: application/json" \
 *        -d '{"key":"value","num":42}'
 *
 *   # Редирект (следва автоматично с -L)
 *   curl -L -v http://localhost:8000/redirect
 */

// ── Routing по URL + метод ────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];
$path   = strtok($_SERVER['REQUEST_URI'], '?');   // без query string

// ── /redirect  →  301 Moved Permanently ──────────────────────────────────
if ($path === '/redirect') {
    header('Location: /?redirected=1', true, 301);
    exit;
}

// ── /status  →  показва различни статус кодове ───────────────────────────
if ($path === '/status') {
    $code = (int) ($_GET['code'] ?? 200);
    $allowed = [200, 201, 204, 301, 302, 400, 401, 403, 404, 422, 500];
    if (!in_array($code, $allowed, true)) {
        $code = 400;
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => $code,
        'message' => match($code) {
            200 => 'OK – заявката е успешна',
            201 => 'Created – ресурсът е създаден',
            204 => 'No Content – успех без тяло',
            301 => 'Moved Permanently – постоянен редирект',
            302 => 'Found – временен редирект',
            400 => 'Bad Request – невалидна заявка',
            401 => 'Unauthorized – необходима е автентикация',
            403 => 'Forbidden – нямате достъп',
            404 => 'Not Found – ресурсът не е намерен',
            422 => 'Unprocessable Entity – грешка при валидация',
            500 => 'Internal Server Error – сървърна грешка',
        },
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── /echo-request  →  отразява POST тяло ─────────────────────────────────
if ($path === '/echo-request' && $method === 'POST') {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    $body = file_get_contents('php://input');
    echo json_encode([
        'method'       => $method,
        'content_type' => $_SERVER['CONTENT_TYPE'] ?? '',
        'body_raw'     => $body,
        'body_decoded' => json_decode($body, true),
        'post_fields'  => $_POST,
        'headers'      => getallheaders(),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── Главна страница ───────────────────────────────────────────────────────

// Извличаме ВСИЧКИ headers за показване
$requestHeaders = getallheaders();
$redirected     = isset($_GET['redirected']);

?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <title>02 – HTTP протокол</title>
    <style>
        body  { font-family: Arial, sans-serif; max-width: 860px; margin: 40px auto; padding: 0 20px; }
        h2    { border-bottom: 1px solid #eee; padding-bottom: 6px; }
        code  { background: #f0f0f0; padding: 2px 5px; border-radius: 3px; }
        pre   { background: #f7f7f7; padding: 14px; border-radius: 6px; overflow-x: auto; font-size: .84em; }
        .card { border: 1px solid #ddd; border-radius: 8px; padding: 18px; margin: 18px 0; }
        table { border-collapse: collapse; width: 100%; }
        td, th { padding: 7px 12px; border: 1px solid #ddd; text-align: left; }
        th    { background: #f0f0f0; }
        .badge { display:inline-block; padding:2px 8px; border-radius:12px; font-size:.8em; color:#fff; }
        .b2xx { background:#27ae60; } .b3xx { background:#2980b9; }
        .b4xx { background:#e67e22; } .b5xx { background:#c0392b; }
    </style>
</head>
<body>

<h1>02 – HTTP протокол</h1>

<?php if ($redirected): ?>
    <p style="color:#27ae60">✔ Прехвърлени сте чрез 301 редирект!</p>
<?php endif; ?>

<!-- ── HTTP Методи ────────────────────────────────────────────────────── -->
<div class="card">
    <h2>HTTP Методи</h2>
    <table>
        <tr><th>Метод</th><th>Предназначение</th><th>Идемпотентен?</th><th>Тяло?</th></tr>
        <tr><td><code>GET</code></td><td>Извличане на ресурс</td><td>Да</td><td>Не</td></tr>
        <tr><td><code>POST</code></td><td>Създаване на ресурс</td><td>Не</td><td>Да</td></tr>
        <tr><td><code>PUT</code></td><td>Пълна замяна на ресурс</td><td>Да</td><td>Да</td></tr>
        <tr><td><code>PATCH</code></td><td>Частична промяна</td><td>Не</td><td>Да</td></tr>
        <tr><td><code>DELETE</code></td><td>Изтриване на ресурс</td><td>Да</td><td>Не</td></tr>
        <tr><td><code>HEAD</code></td><td>Само хедъри (без тяло)</td><td>Да</td><td>Не</td></tr>
        <tr><td><code>OPTIONS</code></td><td>Налични методи</td><td>Да</td><td>Не</td></tr>
    </table>
</div>

<!-- ── HTTP Статус кодове ──────────────────────────────────────────────── -->
<div class="card">
    <h2>HTTP Статус кодове</h2>
    <table>
        <tr><th>Код</th><th>Значение</th><th>Клас</th></tr>
        <tr><td>200</td><td>OK</td><td><span class="badge b2xx">2xx Успех</span></td></tr>
        <tr><td>201</td><td>Created</td><td><span class="badge b2xx">2xx Успех</span></td></tr>
        <tr><td>204</td><td>No Content</td><td><span class="badge b2xx">2xx Успех</span></td></tr>
        <tr><td>301</td><td>Moved Permanently</td><td><span class="badge b3xx">3xx Пренасочване</span></td></tr>
        <tr><td>302</td><td>Found</td><td><span class="badge b3xx">3xx Пренасочване</span></td></tr>
        <tr><td>400</td><td>Bad Request</td><td><span class="badge b4xx">4xx Грешка на клиента</span></td></tr>
        <tr><td>401</td><td>Unauthorized</td><td><span class="badge b4xx">4xx Грешка на клиента</span></td></tr>
        <tr><td>403</td><td>Forbidden</td><td><span class="badge b4xx">4xx Грешка на клиента</span></td></tr>
        <tr><td>404</td><td>Not Found</td><td><span class="badge b4xx">4xx Грешка на клиента</span></td></tr>
        <tr><td>422</td><td>Unprocessable Entity</td><td><span class="badge b4xx">4xx Грешка на клиента</span></td></tr>
        <tr><td>500</td><td>Internal Server Error</td><td><span class="badge b5xx">5xx Сървърна грешка</span></td></tr>
    </table>
    <p>Пробвайте в браузъра: <a href="/status?code=404">/status?code=404</a> &nbsp;|&nbsp;
       <a href="/status?code=201">/status?code=201</a></p>
</div>

<!-- ── Request Headers ────────────────────────────────────────────────── -->
<div class="card">
    <h2>Текуща заявка – Headers</h2>
    <p><strong>Метод:</strong> <code><?= htmlspecialchars($method, ENT_QUOTES, 'UTF-8') ?></code>
       &nbsp; <strong>URL:</strong> <code><?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, 'UTF-8') ?></code></p>
    <table>
        <tr><th>Header</th><th>Стойност</th></tr>
        <?php foreach ($requestHeaders as $name => $value): ?>
        <tr>
            <td><code><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></code></td>
            <td><?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- ── Редирект ───────────────────────────────────────────────────────── -->
<div class="card">
    <h2>HTTP Редирект (301)</h2>
    <p>PHP код: <code>header('Location: /', true, 301); exit;</code></p>
    <a href="/redirect">Щракнете за 301 редирект</a>
</div>

<!-- ── HTTP заявка като байтове ───────────────────────────────────────── -->
<div class="card">
    <h2>HTTP/1.1 GET заявка – байтово ниво</h2>
    <p>
        Всяка HTTP/1.1 заявка е поредица от <strong>ASCII байтове</strong>.
        Редовете се разделят с <code>CR LF</code> (<code>0D 0A</code>).
        Празен ред (<code>0D 0A 0D 0A</code>) бележи края на хедърите.
    </p>

    <?php
    /* ── примерна заявка ── */
    $sample  = "GET /en/docs/web/http/overview.html HTTP/1.1\r\n";
    $sample .= "Host: example.com\r\n";
    $sample .= "Accept: text/html,application/xhtml+xml\r\n";
    $sample .= "Accept-Language: bg\r\n";
    $sample .= "Connection: keep-alive\r\n";
    $sample .= "\r\n";

    /* ── текстово представяне с маркирани \r\n ── */
    $visual = htmlspecialchars($sample, ENT_QUOTES, 'UTF-8');
    $visual = str_replace("\r\n", '<span style="color:#c0392b">\\r\\n</span>' . "\n", $visual);
    echo '<p><strong>Текстово (human-readable):</strong></p>';
    echo '<pre style="line-height:1.7">' . $visual . '</pre>';

    /* ── шестнадесетичен дъмп 16 байта на ред ── */
    $bytes   = str_split($sample);
    $rows    = array_chunk($bytes, 16);
    $hexDump = "OFFSET  БАЙТОВЕ (HEX)                                     ASCII\n";
    $hexDump .= str_repeat('─', 72) . "\n";
    $addr    = 0;
    foreach ($rows as $row) {
        $hexPart = implode(' ', array_map(fn($b) => strtoupper(bin2hex($b)), $row));
        $ascPart = implode('', array_map(fn($b) => match(true) {
            $b === "\r" => '·',
            $b === "\n" => '↵',
            ord($b) < 32 || ord($b) > 126 => '.',
            default => $b
        }, $row));
        $hexDump .= sprintf("%04X    %-48s  %s\n", $addr, $hexPart, $ascPart);
        $addr += count($row);
    }
    $hexDump .= str_repeat('─', 72) . "\n";
    $hexDump .= sprintf("Общо: %d байта\n", strlen($sample));
    ?>
    <p><strong>Hexdump:</strong></p>
    <pre><?= htmlspecialchars($hexDump, ENT_QUOTES, 'UTF-8') ?></pre>

    <p style="margin-top:12px">
        <strong>Забележка за HTTP/2:</strong> При HTTP/2 заявката вече <em>не е</em> четим текст –
        тя се разбива на бинарни <strong>рамки (frames)</strong>
        (<code>HEADERS frame</code> + евентуален <code>DATA frame</code>).
        Хедърите се компресират с <strong>HPACK</strong>, което значително намалява размера им.
    </p>
</div>

<!-- ── Версии на HTTP ─────────────────────────────────────────────────── -->
<div class="card">
    <h2>Версии на HTTP протокола</h2>
    <table>
        <tr>
            <th>Характеристика</th>
            <th>HTTP/1.0<br><small>RFC 1945 · 1996</small></th>
            <th>HTTP/1.1<br><small>RFC 9112 · 1997/2022</small></th>
            <th>HTTP/2<br><small>RFC 9113 · 2015/2022</small></th>
            <th>HTTP/3<br><small>RFC 9114 · 2022</small></th>
        </tr>
        <tr>
            <td><strong>Транспорт</strong></td>
            <td>TCP</td>
            <td>TCP</td>
            <td>TCP + TLS</td>
            <td><strong>QUIC</strong> (върху UDP)</td>
        </tr>
        <tr>
            <td><strong>Формат</strong></td>
            <td>Текст</td>
            <td>Текст</td>
            <td>Бинарни рамки</td>
            <td>Бинарни рамки</td>
        </tr>
        <tr>
            <td><strong>Keep-Alive</strong></td>
            <td>Не (1 заявка/TCP)</td>
            <td>Да (по подразбиране)</td>
            <td>Да</td>
            <td>Да</td>
        </tr>
        <tr>
            <td><strong>Мултиплексиране</strong></td>
            <td>Не</td>
            <td>Pipelining (на практика рядко)</td>
            <td><strong>Да</strong> – много потоци в 1 TCP</td>
            <td><strong>Да</strong> – много потоци в 1 QUIC</td>
        </tr>
        <tr>
            <td><strong>Head-of-line блокиране</strong></td>
            <td>Да</td>
            <td>Да (на ниво приложение)</td>
            <td>Да (на ниво TCP)</td>
            <td><strong>Не</strong> – QUIC обработва потоците независимо</td>
        </tr>
        <tr>
            <td><strong>Компресия на хедъри</strong></td>
            <td>Не</td>
            <td>Не</td>
            <td><strong>HPACK</strong></td>
            <td><strong>QPACK</strong></td>
        </tr>
        <tr>
            <td><strong>Server Push</strong></td>
            <td>Не</td>
            <td>Не</td>
            <td>Да (RFC; браузърите го депрекираха)</td>
            <td>Ограничено</td>
        </tr>
        <tr>
            <td><strong>TLS задължителен?</strong></td>
            <td>Не</td>
            <td>Не</td>
            <td>De-facto да (браузърите изискват)</td>
            <td><strong>Да</strong> – вграден в QUIC</td>
        </tr>
        <tr>
            <td><strong>Типично използване днес</strong></td>
            <td>Легаси / curl скриптове</td>
            <td>Широко (~35 % трафик)</td>
            <td>Мнозинство сайтове (~65 %)</td>
            <td>Нараства (~30 % и повече)</td>
        </tr>
    </table>

    <h3 style="margin-top:20px">Ключови разлики накратко</h3>
    <ul>
        <li><strong>1.0 → 1.1:</strong> Keep-Alive, chunked transfer, виртуален хостинг (<code>Host</code> хедър задължителен), кеш контрол с <code>ETag</code>/<code>Cache-Control</code>.</li>
        <li><strong>1.1 → 2:</strong> Бинарен протокол, мултиплексиране (много заявки в 1 TCP връзка без чакане), HPACK компресия на хедъри, приоритизация на потоци.</li>
        <li><strong>2 → 3:</strong> Смяна на транспорта от TCP на <strong>QUIC</strong> (UDP-базиран) – решава TCP head-of-line блокирането, по-бързо установяване на връзката (0-RTT/1-RTT), вградена TLS 1.3.</li>
    </ul>

    <p>
        <strong>Проверете версията в браузъра:</strong> DevTools → Network → протокол колоната показва
        <code>h2</code> (HTTP/2) или <code>h3</code> (HTTP/3) за съвременни сайтове.
    </p>
</div>

<!-- ── Fetch примери ──────────────────────────────────────────────────── -->
<div class="card">
    <h2>Изпращане на POST заявки (Fetch API)</h2>
    <button onclick="sendPost()">Изпрати POST /echo-request</button>
    <pre id="result">← резултатът ще се появи тук</pre>
    <script>
    async function sendPost() {
        const res = await fetch('/echo-request', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ hello: 'world', number: 42 })
        });
        const data = await res.json();
        document.getElementById('result').textContent = JSON.stringify(data, null, 2);
    }
    </script>
</div>

</body>
</html>
