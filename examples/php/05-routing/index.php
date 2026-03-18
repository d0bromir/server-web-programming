<?php
declare(strict_types=1);
/**
 * Тема 5 – Routing и обработка на заявки
 *
 * Демонстрира:
 *  - Регистриране на routes по метод + path
 *  - Route параметри (/users/{id})
 *  - Middleware (преди и след handler-а)
 *  - Named routes и генериране на URL-и
 *  - 404 / 405 Method Not Allowed
 *
 * Стартиране: php -S localhost:8000
 * URL-и:
 *   GET  /              → начало
 *   GET  /users         → списък потребители
 *   GET  /users/42      → потребител с id=42
 *   POST /users         → създаване (тест с curl)
 *   GET  /admin         → защитена страница (изисква ?token=admin)
 *   GET  /about         → статична страница
 */

// ══════════════════════════════════════════════════════════════════════
//  ROUTER
// ══════════════════════════════════════════════════════════════════════

class Router
{
    /** @var array<string, array<array{pattern:string, handler:callable, middleware:callable[], name:?string}>> */
    private array $routes = [];

    /** @var callable[] */
    private array $globalMiddleware = [];

    /** @var array<string, string> */
    private array $namedRoutes = [];

    // ── Регистриране ──────────────────────────────────────────────────

    public function get(string $pattern, callable $handler): RouteBuilder
    {
        return $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): RouteBuilder
    {
        return $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): RouteBuilder
    {
        return $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): RouteBuilder
    {
        return $this->add('DELETE', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): RouteBuilder
    {
        $route = ['pattern' => $pattern, 'handler' => $handler, 'middleware' => [], 'name' => null];
        $this->routes[$method][] = &$route;
        return new RouteBuilder($route, $this);
    }

    public function middleware(callable $mw): void
    {
        $this->globalMiddleware[] = $mw;
    }

    public function registerNamed(string $name, string $pattern): void
    {
        $this->namedRoutes[$name] = $pattern;
    }

    /** Генерира URL по name + params */
    public function url(string $name, array $params = []): string
    {
        $pattern = $this->namedRoutes[$name] ?? throw new RuntimeException("Route '{$name}' не съществува");
        foreach ($params as $k => $v) {
            $pattern = str_replace("{{$k}}", (string) $v, $pattern);
        }
        return $pattern;
    }

    // ── Dispatch ─────────────────────────────────────────────────────

    public function dispatch(string $method, string $uri): void
    {
        $path = strtok($uri, '?');

        // Проверяваме дали path изобщо съществува за друг метод
        $pathMatchedOtherMethod = false;

        foreach ($this->routes as $routeMethod => $routeList) {
            foreach ($routeList as $route) {
                $params = $this->match($route['pattern'], $path);
                if ($params === null) continue;

                if ($routeMethod !== $method) {
                    $pathMatchedOtherMethod = true;
                    continue;
                }

                // Изграждаме middleware стек
                $handler    = $route['handler'];
                $middleware = array_merge($this->globalMiddleware, $route['middleware']);
                $stack      = array_reverse($middleware);
                $next       = fn(array $p) => $handler($p);

                foreach ($stack as $mw) {
                    $currentNext = $next;
                    $next = fn(array $p) => $mw($p, $currentNext);
                }

                $next($params);
                return;
            }
        }

        if ($pathMatchedOtherMethod) {
            http_response_code(405);
            echo "<h1>405 Method Not Allowed</h1><p>Методът '{$method}' не е разрешен за '{$path}'.</p>";
        } else {
            http_response_code(404);
            echo "<h1>404 Not Found</h1><p>Страницата '{$path}' не е намерена.</p>";
        }
    }

    /** Връща масив от параметри при съвпадение, null при несъвпадение */
    private function match(string $pattern, string $path): ?array
    {
        $regex = '@^' . preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern) . '$@';
        if (!preg_match($regex, $path, $m)) return null;
        // Връщаме само named captures (string keys)
        return array_filter($m, fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);
    }
}

class RouteBuilder
{
    public function __construct(private array &$route, private readonly Router $router) {}

    public function middleware(callable $mw): self
    {
        $this->route['middleware'][] = $mw;
        return $this;
    }

    public function name(string $name): self
    {
        $this->route['name'] = $name;
        $this->router->registerNamed($name, $this->route['pattern']);
        return $this;
    }
}

// ══════════════════════════════════════════════════════════════════════
//  MIDDLEWARE-ОВЕ
// ══════════════════════════════════════════════════════════════════════

$authMiddleware = function (array $params, callable $next): void {
    $token = $_GET['token'] ?? '';
    if ($token !== 'admin') {
        http_response_code(401);
        echo '<h1>401 Unauthorized</h1><p>Изисква се <code>?token=admin</code></p>';
        return;
    }
    $next($params);
};

$logMiddleware = function (array $params, callable $next): void {
    // Симулирано логване
    error_log(date('H:i:s') . ' ' . $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI']);
    $next($params);
};

// ══════════════════════════════════════════════════════════════════════
//  ДЕФИНИРАНЕ НА ROUTES
// ══════════════════════════════════════════════════════════════════════

$router = new Router();
$router->middleware($logMiddleware);   // Глобален middleware

// Статични routes
$router->get('/', function (array $p) use ($router): void {
    page_layout('Начало – Routing Demo', function () use ($router) { ?>
    <h1>05 – Routing Demo</h1>
    <ul>
        <li><a href="<?= $router->url('users.index') ?>">GET /users</a> – списък</li>
        <li><a href="<?= $router->url('users.show', ['id' => 7]) ?>">GET /users/7</a> – детайли</li>
        <li><a href="<?= $router->url('about') ?>">GET /about</a></li>
        <li><a href="/admin?token=admin">GET /admin?token=admin</a> – защитена</li>
        <li><a href="/admin">GET /admin (без токен → 401)</a></li>
    </ul>
    <p><strong>Named route генериране:</strong>
       <code>$router->url('users.show', ['id' => 7])</code>
       → <code><?= $router->url('users.show', ['id' => 7]) ?></code></p>
    <?php });
})->name('home');

$router->get('/about', function (array $p): void {
    page_layout('За нас', fn() => print('<h1>За нас</h1><p>Статична страница.</p>'));
})->name('about');

// Route с параметър
$router->get('/users', function (array $p): void {
    $users = [
        ['id' => 1, 'name' => 'Иван Иванов'],
        ['id' => 2, 'name' => 'Мария Петрова'],
        ['id' => 7, 'name' => 'Стефан Георгиев'],
    ];
    page_layout('Потребители', function () use ($users) {
        echo '<h1>Потребители</h1><ul>';
        foreach ($users as $u) {
            echo "<li><a href='/users/{$u['id']}'>{$u['name']}</a></li>";
        }
        echo '</ul>';
    });
})->name('users.index');

$router->get('/users/{id}', function (array $p): void {
    $id = (int) $p['id'];
    page_layout("Потребител #{$id}", fn() =>
        printf('<h1>Потребител #%d</h1><p>Route параметър: <code>$params["id"] = %d</code></p><a href="/users">← Назад</a>', $id, $id)
    );
})->name('users.show');

$router->post('/users', function (array $p): void {
    http_response_code(201);
    header('Content-Type: application/json');
    echo json_encode(['created' => true, 'data' => $_POST], JSON_UNESCAPED_UNICODE);
});

// Route с middleware
$router->get('/admin', function (array $p): void {
    page_layout('Admin', fn() => print('<h1>Администраторски панел</h1><p>Достъпен само с токен.</p>'));
})->middleware($authMiddleware)->name('admin');

// ── Dispatch ──────────────────────────────────────────────────────────
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);

// ── Layout helper ─────────────────────────────────────────────────────
function page_layout(string $title, callable $body): void
{
    ?><!DOCTYPE html>
<html lang="bg"><head><meta charset="UTF-8"><title><?= htmlspecialchars($title) ?></title>
<style>body{font-family:Arial,sans-serif;max-width:800px;margin:40px auto;padding:0 20px}
code{background:#f0f0f0;padding:2px 5px;border-radius:3px}
nav a{margin-right:12px;color:#2980b9;text-decoration:none}</style>
</head><body>
<nav><a href="/">Начало</a><a href="/users">Потребители</a><a href="/about">За нас</a></nav><hr>
<?php $body(); ?>
</body></html><?php
}
