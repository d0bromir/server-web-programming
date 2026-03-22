<?php
declare(strict_types=1);
/**
 * Тема 10 – RESTful уеб услуги
 *
 * ══════════════════════════════════════════════════════════════════
 * КАКВО Е REST?
 * ══════════════════════════════════════════════════════════════════
 * REST (Representational State Transfer) е архитектурен стил за
 * изграждане на уеб услуги, дефиниран от Roy Fielding (2000).
 * "RESTful API" означава HTTP API, което следва REST принципите.
 *
 * ══════════════════════════════════════════════════════════════════
 * 6-ТЕ REST ПРИНЦИПА
 * ══════════════════════════════════════════════════════════════════
 * 1. Client–Server  – клиентът и сървърът са независими;
 *                     клиентът не знае как се съхраняват данните.
 *
 * 2. Stateless      – всяка заявка съдържа ЦЯЛАТА необходима
 *                     информация; сървърът не пази клиентско
 *                     състояние между заявките.
 *                     → не се използват сесии; токенът се изпраща
 *                       с всяка заявка.
 *
 * 3. Cacheable      – отговорите могат да се кешират (Cache-Control,
 *                     ETag). GET заявките са кешируеми по дефиниция.
 *
 * 4. Uniform Interface – единен интерфейс чрез:
 *                     а) Ресурси с URL идентификатор (/api/items/1)
 *                     б) Манипулация чрез представяния (JSON/XML)
 *                     в) Self-descriptive messages (Content-Type)
 *                     г) HATEOAS (линкове в отговора – вж. по-долу)
 *
 * 5. Layered System – клиентът не знае дали говори директно
 *                     със сървъра или с proxy/load balancer.
 *
 * 6. Code on Demand – (незадължително) сървърът може да изпраща
 *                     изпълним код (напр. JavaScript).
 *
 * ══════════════════════════════════════════════════════════════════
 * РЕСУРСИ
 * ══════════════════════════════════════════════════════════════════
 * Ресурсът е всяка именувана концепция, която може да бъде
 * идентифицирана, адресирана и манипулирана – обект от реалния свят
 * представен в системата (потребител, продукт, поръчка и т.н.).
 *
 * Характеристики:
 *   Идентификатор – уникален URI:  /api/items/42
 *   Представяне   – конкретен формат на данните: JSON, XML, HTML
 *                   (един ресурс може да има много представяния)
 *   Действия      – манипулира се чрез HTTP методи (GET, POST…)
 *
 * Именуване на URI-та (конвенции):
 *   /api/items          – колекция (множествено число, съществителни)
 *   /api/items/42       – единичен ресурс
 *   /api/items/42/tags  – вложен ресурс (под-колекция)
 *   НЕ: /api/getItems, /api/deleteItem/42  ← глаголи са грешка
 *
 * ══════════════════════════════════════════════════════════════════
 * HTTP МЕТОДИ И ТЯХНАТА СЕМАНТИКА
 * ══════════════════════════════════════════════════════════════════
 * GET    /api/items      – списък с ресурси       (read)
 * GET    /api/items/{id} – единичен ресурс         (read)
 * POST   /api/items      – създаване на нов ресурс (create)
 * PUT    /api/items/{id} – ПЪЛНА замяна на ресурс  (replace)
 * PATCH  /api/items/{id} – ЧАСТИЧНА промяна        (update)
 * DELETE /api/items/{id} – изтриване               (delete)
 *
 * Idempotentност (многократно извикване = същия резултат):
 *   GET, PUT, DELETE са идемпотентни.
 *   POST НЕ е идемпотентен (всеки път създава нов запис).
 *   PATCH може да не е идемпотентен (зависи от логиката).
 *
 * ══════════════════════════════════════════════════════════════════
 * HTTP СТАТУС КОДОВЕ
 * ══════════════════════════════════════════════════════════════════
 * 2xx – Успех
 *   200 OK           – стандартен успешен отговор (GET, PUT, PATCH)
 *   201 Created      – ресурсът е създаден (POST); Location хедър
 *   204 No Content   – успех без тяло (DELETE)
 *
 * 3xx – Пренасочване
 *   301 Moved Permanently – ресурсът е преместен постоянно
 *   304 Not Modified      – кешираното съдържание е актуално
 *
 * 4xx – Грешка на клиента
 *   400 Bad Request       – невалидни данни / грешен JSON
 *   401 Unauthorized      – липсва или невалиден токен
 *   403 Forbidden         – автентициран, но без право
 *   404 Not Found         – ресурсът не съществува
 *   405 Method Not Allowed– HTTP методът не е поддържан
 *   409 Conflict          – конфликт (напр. дублиран запис)
 *   422 Unprocessable     – данните са валиден JSON, но семантично грешни
 *   429 Too Many Requests – rate limiting
 *
 * 5xx – Грешка на сървъра
 *   500 Internal Server Error – неочаквана грешка
 *   503 Service Unavailable   – сървърът временно недостъпен
 *
 * ══════════════════════════════════════════════════════════════════
 * АВТЕНТИКАЦИЯ В REST API
 * ══════════════════════════════════════════════════════════════════
 * REST е stateless → не се използват сесии/cookies.
 * Токенът се изпраща с всяка заявка в Authorization хедъра:
 *
 *   Authorization: Bearer <token>
 *
 * Видове токени:
 *   API Key   – прост статичен ключ (подходящ за server-to-server)
 *   JWT       – JSON Web Token; съдържа claims (user_id, role, exp);
 *               подписан с тайна или RSA ключ; не изисква БД заявка
 *               при верификация.
 *   OAuth 2.0 – стандарт за делегиран достъп ("Login with Google");
 *               сложен, но най-сигурен за публични API-та.
 *
 * В тази демонстрация: прост Bearer token (demo-token-12345),
 * в реална система → JWT или OAuth 2.0.
 *
 * ══════════════════════════════════════════════════════════════════
 * HATEOAS
 * ══════════════════════════════════════════════════════════════════
 * Hypermedia As The Engine Of Application State.
 * Всеки отговор включва линкове към свързани действия:
 *   {
 *     "id": 1, "name": "...",
 *     "_links": {
 *       "self":   { "href": "/api/items/1" },
 *       "update": { "href": "/api/items/1", "method": "PUT" },
 *       "delete": { "href": "/api/items/1", "method": "DELETE" }
 *     }
 *   }
 * Клиентът не трябва да конструира URL-и сам – открива ги от отговора.
 *
 * ══════════════════════════════════════════════════════════════════
 * ВЕРСИОНИРАНЕ НА API
 * ══════════════════════════════════════════════════════════════════
 * При промяна на контракта се въвежда нова версия:
 *   URL:    /api/v1/items  →  /api/v2/items   (най-разпространено)
 *   Header: Accept: application/vnd.myapp.v2+json
 *
 * ══════════════════════════════════════════════════════════════════
 * Демонстрира:
 *  - REST принципи: ресурси, методи, stateless
 *  - JSON API с правилни HTTP статус кодове
 *  - Bearer Token автентикация
 *  - GET / POST / PUT / PATCH / DELETE
 *  - Content-Type validation
 *  - HATEOAS линкове в отговора
 *
 * Стартиране: php -S localhost:8000
 *
 * Тестване с curl:
 *   # Токен за автентикация: demo-token-12345
 *   TOKEN="demo-token-12345"
 *
 *   # Публичен списък (без токен)
 *   curl http://localhost:8000/api/items
 *
 *   # Единичен ресурс (без токен → 401 ако е protected)
 *   curl http://localhost:8000/api/items/1
 *
 *   # Вземане с токен
 *   curl -H "Authorization: Bearer $TOKEN" \
 *        http://localhost:8000/api/items
 *
 *   # Създаване (POST)
 *   curl -X POST http://localhost:8000/api/items \
 *        -H "Content-Type: application/json" \
 *        -H "Authorization: Bearer $TOKEN" \
 *        -d '{"name":"Нов запис","category":"тест","price":9.99}'
 *
 *   # Пълна замяна (PUT)
 *   curl -X PUT http://localhost:8000/api/items/1 \
 *        -H "Content-Type: application/json" \
 *        -H "Authorization: Bearer $TOKEN" \
 *        -d '{"name":"Обновен запис","category":"тест","price":19.99}'
 *
 *   # Частична промяна (PATCH)
 *   curl -X PATCH http://localhost:8000/api/items/1 \
 *        -H "Content-Type: application/json" \
 *        -H "Authorization: Bearer $TOKEN" \
 *        -d '{"price":14.99}'
 *
 *   # Изтриване (DELETE)
 *   curl -X DELETE http://localhost:8000/api/items/1 \
 *        -H "Authorization: Bearer $TOKEN"
 */

// ══════════════════════════════════════════════════════════════════════
//  API HELPER функции
// ══════════════════════════════════════════════════════════════════════

function jsonResponse(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function errorResponse(string $message, int $status, array $extra = []): never
{
    jsonResponse(['error' => $message, 'status' => $status, ...$extra], $status);
}

function parseJsonBody(): array
{
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (!str_contains($ct, 'application/json')) {
        errorResponse('Content-Type трябва да е application/json', 415);
    }
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        errorResponse('Невалиден JSON', 400);
    }
    return $body;
}

// ══════════════════════════════════════════════════════════════════════
//  AUTH MIDDLEWARE
// ══════════════════════════════════════════════════════════════════════

// Токенът в реален проект – JWT или random string в DB
const API_TOKENS = ['demo-token-12345', 'another-token-abc'];

function requireToken(): void
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer (.+)$/', $auth, $m) || !in_array($m[1], API_TOKENS, true)) {
        errorResponse('Unauthorized – invalid or missing token', 401);
    }
}

// ══════════════════════════════════════════════════════════════════════
//  "БАЗА ДАННИ" (JSON файл)
//
//  php -S стартира нов процес за всяка заявка → in-memory масивите
//  се нулират след всеки отговор. Затова използваме JSON файл като
//  прост persistent store (в реален проект: PDO + таблица).
// ══════════════════════════════════════════════════════════════════════

const DB_FILE = __DIR__ . '/items_db.json';

$defaultItems = [
    1 => ['id' => 1, 'name' => 'Кафе',   'price' => 2.50, 'category' => 'напитки'],
    2 => ['id' => 2, 'name' => 'Чай',    'price' => 1.80, 'category' => 'напитки'],
    3 => ['id' => 3, 'name' => 'Баница', 'price' => 1.20, 'category' => 'храна'],
];

function loadDb(): array
{
    if (!file_exists(DB_FILE)) {
        global $defaultItems;
        saveDb($defaultItems, 4);
    }
    return json_decode(file_get_contents(DB_FILE), true);
}

function saveDb(array $items, int $nextId): void
{
    file_put_contents(DB_FILE, json_encode(
        ['items' => $items, 'next_id' => $nextId],
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    ));
}

$db     = loadDb();
$items  = $db['items'];
$nextId = $db['next_id'];

// ══════════════════════════════════════════════════════════════════════
//  URL структура:
//  GET    /api/items          → списък
//  POST   /api/items          → създаване (изисква токен)
//  GET    /api/items/{id}     → детайли (изисква токен)
//  PUT    /api/items/{id}     → пълна замяна (изисква токен)
//  PATCH  /api/items/{id}     → частична промяна (изисква токен)
//  DELETE /api/items/{id}     → изтриване (изисква токен)
// ══════════════════════════════════════════════════════════════════════

$method = $_SERVER['REQUEST_METHOD'];
$path   = strtok($_SERVER['REQUEST_URI'], '?');
$itemId = null;

if (preg_match('@^/api/items/(\d+)$@', $path, $m)) {
    $itemId = (int) $m[1];
    $path   = '/api/items/:id';
}

// Само GET /api/items не изисква токен (публичен endpoint)
match ([$method, $path]) {

    // ── GET /api/items ──────────────────────────────────────────────
    ['GET', '/api/items'] => (function () use ($items) {
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 10)));
        $all     = array_values($items);
        $slice   = array_slice($all, ($page - 1) * $perPage, $perPage);

        jsonResponse([
            'data'  => array_map('addLinks', $slice),
            'meta'  => ['total' => count($all), 'page' => $page, 'per_page' => $perPage],
            'links' => [
                'self' => "/api/items?page={$page}",
                'next' => $page * $perPage < count($all) ? '/api/items?page=' . ($page + 1) : null,
            ],
        ]);
    })(),

    // ── POST /api/items ─────────────────────────────────────────────
    ['POST', '/api/items'] => (function () use (&$items, &$nextId) {
        requireToken();
        $body = parseJsonBody();

        // Валидация
        $errors = [];
        if (empty(trim($body['name'] ?? '')))  $errors['name']  = 'Задължително поле';
        if (!is_numeric($body['price'] ?? '')) $errors['price'] = 'Трябва да е число';
        if ($errors !== []) errorResponse('Грешка при валидация', 422, ['errors' => $errors]);

        $newItem = [
            'id'       => $nextId,
            'name'     => htmlspecialchars(trim($body['name']), ENT_QUOTES, 'UTF-8'),
            'price'    => round((float) $body['price'], 2),
            'category' => htmlspecialchars(trim($body['category'] ?? ''), ENT_QUOTES, 'UTF-8'),
        ];
        $items[$nextId++] = $newItem;
        saveDb($items, $nextId);

        // 201 Created + Location header
        header('Location: /api/items/' . $newItem['id']);
        jsonResponse(['data' => addLinks($newItem)], 201);
    })(),

    // ── GET /api/items/:id ──────────────────────────────────────────
    ['GET', '/api/items/:id'] => (function () use ($items, $itemId) {
        requireToken();
        $item = $items[$itemId] ?? null;
        if (!$item) errorResponse('Записът не е намерен', 404);
        jsonResponse(['data' => addLinks($item)]);
    })(),

    // ── PUT /api/items/:id ─────────────────────────────────────────
    ['PUT', '/api/items/:id'] => (function () use (&$items, $itemId) {
        requireToken();
        if (!isset($items[$itemId])) errorResponse('Записът не е намерен', 404);
        $body = parseJsonBody();

        $errors = [];
        if (empty(trim($body['name'] ?? '')))  $errors['name']  = 'Задължително';
        if (!is_numeric($body['price'] ?? '')) $errors['price'] = 'Трябва да е число';
        if ($errors !== []) errorResponse('Грешка при валидация', 422, ['errors' => $errors]);

        $items[$itemId] = [
            'id'       => $itemId,
            'name'     => htmlspecialchars(trim($body['name']), ENT_QUOTES, 'UTF-8'),
            'price'    => round((float) $body['price'], 2),
            'category' => htmlspecialchars(trim($body['category'] ?? ''), ENT_QUOTES, 'UTF-8'),
        ];
        saveDb($items, $GLOBALS['nextId']);
        jsonResponse(['data' => addLinks($items[$itemId])]);
    })(),

    // ── PATCH /api/items/:id ────────────────────────────────────────
    ['PATCH', '/api/items/:id'] => (function () use (&$items, $itemId) {
        requireToken();
        if (!isset($items[$itemId])) errorResponse('Записът не е намерен', 404);
        $body = parseJsonBody();

        // Само подадените полета се обновяват
        if (isset($body['name']))     $items[$itemId]['name']     = htmlspecialchars(trim($body['name']), ENT_QUOTES, 'UTF-8');
        if (isset($body['price']))    $items[$itemId]['price']    = round((float) $body['price'], 2);
        if (isset($body['category'])) $items[$itemId]['category'] = htmlspecialchars(trim($body['category']), ENT_QUOTES, 'UTF-8');
        saveDb($items, $GLOBALS['nextId']);

        jsonResponse(['data' => addLinks($items[$itemId])]);
    })(),

    // ── DELETE /api/items/:id ───────────────────────────────────────
    ['DELETE', '/api/items/:id'] => (function () use (&$items, $itemId) {
        requireToken();
        if (!isset($items[$itemId])) errorResponse('Записът не е намерен', 404);
        unset($items[$itemId]);
        saveDb($items, $GLOBALS['nextId']);
        http_response_code(204);   // No Content
        header('Content-Type: application/json');
        exit;
    })(),

    // ── POST /api/reset (само за демо) ─────────────────────────────
    ['POST', '/api/reset'] => (function () {
        global $defaultItems;
        saveDb($defaultItems, 4);
        jsonResponse(['message' => 'Данните са нулирани към началните стойности.']);
    })(),

    // ── Документация (не-API заявки) ────────────────────────────────
    default => (function () {
        if (str_starts_with($_SERVER['REQUEST_URI'], '/api/')) {
            errorResponse('Endpoint не съществува', 404);
        }
        // Показва HTML документация
        showDocs();
    })(),
};

// ── HATEOAS link helper ─────────────────────────────────────────────────────
function addLinks(array $item): array
{
    $item['_links'] = [
        'self'       => "/api/items/{$item['id']}",
        'collection' => '/api/items',
    ];
    return $item;
}

// ── HTML документация ──────────────────────────────────────────────────────
function showDocs(): never
{
    ?><!DOCTYPE html>
<html lang="bg"><head><meta charset="UTF-8"><title>10 – REST API</title>
<style>body{font-family:Arial,sans-serif;max-width:860px;margin:40px auto;padding:0 20px}
code{background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:.88em}
pre{background:#f7f7f7;padding:14px;border-radius:6px;overflow-x:auto;font-size:.83em}
.card{border:1px solid #ddd;border-radius:8px;padding:18px;margin:16px 0}
table{border-collapse:collapse;width:100%}td,th{padding:8px 12px;border:1px solid #ddd}
th{background:#f0f0f0}.get{color:#27ae60}.post{color:#2980b9}
.put{color:#e67e22}.del{color:#c0392b}.patch{color:#8e44ad}</style>
</head><body>
<h1>10 – REST API</h1>
<div class="card">
<h2>Endpoints</h2>
<table>
<tr><th>Метод</th><th>URL</th><th>Описание</th><th>Auth?</th></tr>
<tr><td class="get">GET</td><td><code>/api/items</code></td><td>Списък (публичен)</td><td>Не</td></tr>
<tr><td class="post">POST</td><td><code>/api/items</code></td><td>Новo</td><td>✔</td></tr>
<tr><td class="get">GET</td><td><code>/api/items/{id}</code></td><td>Детайли</td><td>✔</td></tr>
<tr><td class="put">PUT</td><td><code>/api/items/{id}</code></td><td>Пълна замяна</td><td>✔</td></tr>
<tr><td class="patch">PATCH</td><td><code>/api/items/{id}</code></td><td>Частична промяна</td><td>✔</td></tr>
<tr><td class="del">DELETE</td><td><code>/api/items/{id}</code></td><td>Изтриване</td><td>✔</td></tr>
</table>
<p>Token: <code>demo-token-12345</code> &nbsp;|&nbsp; Header: <code>Authorization: Bearer demo-token-12345</code></p>
</div>
<div class="card">
<h2>curl примери</h2>
<pre>
# Публичен endpoint
curl http://localhost:8000/api/items

# С токен
curl http://localhost:8000/api/items/1 \
     -H "Authorization: Bearer demo-token-12345"

# Създаване
curl -X POST http://localhost:8000/api/items \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer demo-token-12345" \
     -d '{"name":"Банички","price":1.20,"category":"храна"}'

# Изтриване
curl -X DELETE http://localhost:8000/api/items/2 \
     -H "Authorization: Bearer demo-token-12345"
</pre>
</div>
<div class="card">
<h2>Тест директно</h2>
<p style="color:#888;font-size:.9em">Токен: <code>demo-token-12345</code> — попълнен автоматично
&nbsp;|&nbsp;
<button onclick="reset()" style="background:#e74c3c;color:#fff;border:none;padding:4px 10px;border-radius:4px;cursor:pointer">↺ Нулирай данните</button>
</p>

<table style="width:100%;border:none">
<tr><td style="border:none;padding:4px 0">
  <b class="get">GET</b> <code>/api/items</code>
  &nbsp;<button onclick="req('GET','/api/items')">▶ Изпълни</button>
</td></tr>
<tr><td style="border:none;padding:4px 0">
  <b class="get">GET</b> <code>/api/items/</code><input id="get-id" type="number" value="1" style="width:50px">
  &nbsp;<button onclick="req('GET','/api/items/'+document.getElementById('get-id').value,true)">▶ Изпълни</button>
</td></tr>
<tr><td style="border:none;padding:8px 0">
  <b class="post">POST</b> <code>/api/items</code><br>
  <textarea id="post-body" rows="3" style="width:100%;font-family:monospace;font-size:.85em">{"name":"Нов запис","category":"тест","price":9.99}</textarea>
  <button onclick="req('POST','/api/items',true,document.getElementById('post-body').value)">▶ Изпълни</button>
</td></tr>
<tr><td style="border:none;padding:8px 0">
  <b class="put">PUT</b> <code>/api/items/</code><input id="put-id" type="number" value="1" style="width:50px"><br>
  <textarea id="put-body" rows="3" style="width:100%;font-family:monospace;font-size:.85em">{"name":"Пълна замяна","category":"тест","price":19.99}</textarea>
  <button onclick="req('PUT','/api/items/'+document.getElementById('put-id').value,true,document.getElementById('put-body').value)">▶ Изпълни</button>
</td></tr>
<tr><td style="border:none;padding:8px 0">
  <b class="patch">PATCH</b> <code>/api/items/</code><input id="patch-id" type="number" value="1" style="width:50px"><br>
  <textarea id="patch-body" rows="2" style="width:100%;font-family:monospace;font-size:.85em">{"price":14.99}</textarea>
  <button onclick="req('PATCH','/api/items/'+document.getElementById('patch-id').value,true,document.getElementById('patch-body').value)">▶ Изпълни</button>
</td></tr>
<tr><td style="border:none;padding:4px 0">
  <b class="del">DELETE</b> <code>/api/items/</code><input id="del-id" type="number" value="1" style="width:50px">
  &nbsp;<button onclick="req('DELETE','/api/items/'+document.getElementById('del-id').value,true)">▶ Изпълни</button>
</td></tr>
</table>

<div style="margin-top:12px">
  <b>Отговор:</b>
  <span id="out-status" style="margin-left:8px;font-weight:bold"></span>
  <pre id="out" style="min-height:60px">← резултат</pre>
</div>

<script>
const TOKEN = 'Bearer demo-token-12345';
async function reset(){
    if(!confirm('Нулиране на данните?')) return;
    await req('POST','/api/reset',false);
  }
async function req(method, url, auth=false, body=null) {
  const headers = {};
  if (auth) headers['Authorization'] = TOKEN;
  if (body) headers['Content-Type'] = 'application/json';
  try {
    const r = await fetch(url, {method, headers, body});
    const statusEl = document.getElementById('out-status');
    statusEl.textContent = r.status + ' ' + r.statusText;
    statusEl.style.color = r.ok ? '#27ae60' : '#c0392b';
    const text = await r.text();
    try { document.getElementById('out').textContent = JSON.stringify(JSON.parse(text), null, 2); }
    catch { document.getElementById('out').textContent = text || '(празен отговор)'; }
  } catch(e) {
    document.getElementById('out').textContent = 'Грешка: ' + e.message;
  }
}
</script>
</div>
</body></html><?php
    exit;
}
