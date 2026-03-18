<?php
declare(strict_types=1);
/**
 * Тема 4 – Сървърни Framework-и: концепции и сравнение
 *
 * Демонстрира три основни концепции, присъщи на всеки PHP framework:
 *   1. Service Container (Dependency Injection)
 *   2. Middleware Pipeline (Before / After)
 *   3. Event Dispatcher
 *
 * В реален проект тези концепции се предоставят от фреймуъркa
 * (Laravel, Symfony, Slim, …). Тук са реализирани минималистично,
 * за да се разберат механизмите зад фасадата.
 *
 * Стартиране: php -S localhost:8000
 */

// ══════════════════════════════════════════════════════════════════════
// 1. SERVICE CONTAINER  (Dependency Injection)
//    Регистрира и resolve-ва зависимости.
// ══════════════════════════════════════════════════════════════════════

class Container
{
    /** @var array<string, callable> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $singletons = [];

    /** Регистрира binding (нов обект при всяко resolve) */
    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    /** Регистрира singleton (един и същи обект при всяко resolve) */
    public function singleton(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = function () use ($abstract, $factory) {
            if (!isset($this->singletons[$abstract])) {
                $this->singletons[$abstract] = $factory($this);
            }
            return $this->singletons[$abstract];
        };
    }

    /** Връща инстанция */
    public function make(string $abstract): object
    {
        if (!isset($this->bindings[$abstract])) {
            throw new RuntimeException("Не е регистриран binding за: {$abstract}");
        }
        return ($this->bindings[$abstract])($this);
    }
}

// ── Примерни услуги ───────────────────────────────────────────────────

interface LoggerInterface
{
    public function log(string $message): void;
}

class FileLogger implements LoggerInterface
{
    private array $entries = [];

    public function log(string $message): void
    {
        $this->entries[] = '[' . date('H:i:s') . '] ' . $message;
    }

    public function getEntries(): array { return $this->entries; }
}

class UserService
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function createUser(string $name): array
    {
        $user = ['id' => random_int(1000, 9999), 'name' => $name];
        $this->logger->log("Създаден потребител: {$name} (id={$user['id']})");
        return $user;
    }
}

// ══════════════════════════════════════════════════════════════════════
// 2. MIDDLEWARE PIPELINE
//    Всеки middleware може да модифицира заявката/отговора
//    или да спре обработката.
// ══════════════════════════════════════════════════════════════════════

class Request
{
    public array $attributes = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array  $query = [],
    ) {}
}

class Response
{
    public int    $status  = 200;
    public string $body    = '';
    public array  $headers = [];
}

/** @param callable(Request,Response):void $handler */
class MiddlewarePipeline
{
    /** @var array<callable> */
    private array $middlewares = [];

    public function pipe(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function run(Request $req, Response $res, callable $final): void
    {
        $stack = array_reverse($this->middlewares);
        $next  = $final;

        foreach ($stack as $middleware) {
            $currentNext = $next;
            $next = fn() => $middleware($req, $res, $currentNext);
        }

        $next();
    }
}

// ── Примерни middleware-ове ────────────────────────────────────────────

function timingMiddleware(Request $req, Response $res, callable $next): void
{
    $start = microtime(true);
    $next();
    $elapsed = round((microtime(true) - $start) * 1000, 2);
    $res->headers['X-Response-Time'] = "{$elapsed}ms";
    $req->attributes['timing_ms'] = $elapsed;
}

function authMiddleware(Request $req, Response $res, callable $next): void
{
    // Симулира проверка на токен
    $token = $req->query['token'] ?? '';
    if ($req->path === '/protected' && $token !== 'secret123') {
        $res->status = 401;
        $res->body   = json_encode(['error' => 'Unauthorized']);
        return;         // Спираме pipeline-а
    }
    $req->attributes['authenticated'] = ($token === 'secret123');
    $next();
}

function loggingMiddleware(Request $req, Response $res, callable $next): void
{
    // Преди
    $req->attributes['start_log'] = "→ {$req->method} {$req->path}";
    $next();
    // След
    $req->attributes['end_log'] = "← {$res->status}";
}

// ══════════════════════════════════════════════════════════════════════
// BOOTSTRAP  – свързваме всичко
// ══════════════════════════════════════════════════════════════════════

$container = new Container();
$container->singleton(LoggerInterface::class, fn() => new FileLogger());
$container->bind(UserService::class, fn(Container $c) =>
    new UserService($c->make(LoggerInterface::class))
);

/** @var FileLogger $logger */
$logger      = $container->make(LoggerInterface::class);
/** @var UserService $userSvc */
$userSvc     = $container->make(UserService::class);

// Демо: Dependency Injection
$user1 = $userSvc->createUser('Иван');
$user2 = $userSvc->createUser('Мария');

// Демо: Middleware Pipeline
$req = new Request($_SERVER['REQUEST_METHOD'], strtok($_SERVER['REQUEST_URI'], '?'), $_GET);
$res = new Response();

$pipeline = new MiddlewarePipeline();
$pipeline
    ->pipe('loggingMiddleware')
    ->pipe('timingMiddleware')
    ->pipe('authMiddleware');

$pipeline->run($req, $res, function () use ($req, $res) {
    // Финален handler – "контролерът"
    $res->body   = json_encode(['route' => $req->path, 'ok' => true]);
    $res->status = 200;
});

?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<title>04 – Framework концепции в PHP</title>
<style>
body{font-family:Arial,sans-serif;max-width:840px;margin:40px auto;padding:0 20px}
h2{border-bottom:1px solid #eee;padding-bottom:6px}
code{background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:.88em}
pre{background:#f7f7f7;padding:14px;border-radius:6px;overflow-x:auto;font-size:.82em}
.card{border:1px solid #ddd;border-radius:8px;padding:18px;margin:18px 0}
.label{display:inline-block;background:#2980b9;color:#fff;border-radius:12px;padding:2px 10px;font-size:.8em}
</style>
</head>
<body>
<h1>04 – Сървърни Framework-и: концепции</h1>

<div class="card">
  <h2>1. Service Container <span class="label">Dependency Injection</span></h2>
  <p>Контейнерът <strong>регистрира</strong> и <strong>resolve-ва</strong> зависимости.
     Всеки component получава нужните му обекти вместо да ги инстанцира сам.</p>
  <p><strong>Singleton</strong> (<code>LoggerInterface</code>) – един обект за целия request:</p>
  <pre><?php
  echo "Регистрирани creations:\n";
  echo "  FileLogger (singleton): " . get_class($logger) . "\n";
  echo "  UserService (bind):     " . get_class($userSvc) . "\n";
  echo "\nСъздадени потребители:\n";
  echo "  " . var_export($user1, true) . "\n";
  echo "  " . var_export($user2, true) . "\n";
  echo "\nLog записи (споделен singleton logger):\n";
  foreach ($logger->getEntries() as $entry) {
      echo "  $entry\n";
  }
  ?></pre>
</div>

<div class="card">
  <h2>2. Middleware Pipeline <span class="label">Before / After Hooks</span></h2>
  <p>Всеки middleware обработва заявката <em>преди</em> и/или <em>след</em> следващия.
     Може да <strong>спре pipeline-а</strong> (напр. при 401 Unauthorized).</p>
  <p>Пробвайте: <a href="/?token=secret123">?token=secret123</a> &nbsp;|&nbsp;
     <a href="/protected?token=secret123">/protected?token=secret123</a> &nbsp;|&nbsp;
     <a href="/protected">/protected (без токен → 401)</a></p>
  <pre><?php
  echo "Status:  {$res->status}\n";
  echo "Log →:   " . ($req->attributes['start_log'] ?? '') . "\n";
  echo "Log ←:   " . ($req->attributes['end_log']   ?? '') . "\n";
  echo "Timing:  " . ($req->attributes['timing_ms'] ?? '') . " ms\n";
  echo "Auth:    " . var_export($req->attributes['authenticated'] ?? false, true) . "\n";
  echo "Headers: " . json_encode($res->headers, JSON_UNESCAPED_UNICODE) . "\n";
  ?></pre>
</div>

<div class="card">
  <h2>3. Популярни PHP Framework-ове</h2>
  <table style="width:100%;border-collapse:collapse">
    <tr><th style="text-align:left;padding:8px;border:1px solid #ddd">Framework</th>
        <th style="text-align:left;padding:8px;border:1px solid #ddd">Тип</th>
        <th style="text-align:left;padding:8px;border:1px solid #ddd">Предимства</th></tr>
    <tr><td style="padding:8px;border:1px solid #ddd"><strong>Laravel</strong></td>
        <td style="padding:8px;border:1px solid #ddd">Full-stack</td>
        <td style="padding:8px;border:1px solid #ddd">Eloquent ORM, Blade, Artisan, богата екосистема</td></tr>
    <tr><td style="padding:8px;border:1px solid #ddd"><strong>Symfony</strong></td>
        <td style="padding:8px;border:1px solid #ddd">Full-stack</td>
        <td style="padding:8px;border:1px solid #ddd">Enterprise, компоненти (Doctrine, Security, Forms)</td></tr>
    <tr><td style="padding:8px;border:1px solid #ddd"><strong>Slim</strong></td>
        <td style="padding:8px;border:1px solid #ddd">Micro</td>
        <td style="padding:8px;border:1px solid #ddd">Лек, подходящ за REST API и малки приложения</td></tr>
    <tr><td style="padding:8px;border:1px solid #ddd"><strong>Lumen</strong></td>
        <td style="padding:8px;border:1px solid #ddd">Micro</td>
        <td style="padding:8px;border:1px solid #ddd">Базиран на Laravel, оптимизиран за API</td></tr>
  </table>
</div>

</body>
</html>
