<?php
declare(strict_types=1);
/**
 * Тема 10 – RESTful уеб услуги
 *
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
 *   curl http://localhost:8000/api/items
 *   curl http://localhost:8000/api/items/1 \
 *        -H "Authorization: Bearer demo-token-12345"
 *   curl -X POST http://localhost:8000/api/items \
 *        -H "Content-Type: application/json" \
 *        -H "Authorization: Bearer demo-token-12345" \
 *        -d '{"name":"Нов запис","price":9.99}'
 *   curl -X DELETE http://localhost:8000/api/items/1 \
 *        -H "Authorization: Bearer demo-token-12345"
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
//  "БАЗА ДАННИ" (in-memory)
// ══════════════════════════════════════════════════════════════════════

// В реален проект – PDO + таблица
$items = [
    1 => ['id' => 1, 'name' => 'Кафе',    'price' => 2.50, 'category' => 'напитки'],
    2 => ['id' => 2, 'name' => 'Чай',     'price' => 1.80, 'category' => 'напитки'],
    3 => ['id' => 3, 'name' => 'Баница',  'price' => 1.20, 'category' => 'храна'],
];
$nextId = 4;

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
        $perPage = 2;
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

        jsonResponse(['data' => addLinks($items[$itemId])]);
    })(),

    // ── DELETE /api/items/:id ───────────────────────────────────────
    ['DELETE', '/api/items/:id'] => (function () use (&$items, $itemId) {
        requireToken();
        if (!isset($items[$itemId])) errorResponse('Записът не е намерен', 404);
        unset($items[$itemId]);
        http_response_code(204);   // No Content
        header('Content-Type: application/json');
        exit;
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
<button onclick="testGet()">GET /api/items</button>
<button onclick="testCreate()">POST /api/items</button>
<pre id="out">← резултат</pre>
<script>
const token = 'Bearer demo-token-12345';
async function testGet(){
  const r=await fetch('/api/items');
  document.getElementById('out').textContent=JSON.stringify(await r.json(),null,2);
}
async function testCreate(){
  const r=await fetch('/api/items',{method:'POST',
    headers:{'Content-Type':'application/json','Authorization':token},
    body:JSON.stringify({name:'Тест '+Date.now(),price:3.99,category:'demo'})});
  document.getElementById('out').textContent=JSON.stringify(await r.json(),null,2);
}
</script>
</div>
</body></html><?php
    exit;
}
