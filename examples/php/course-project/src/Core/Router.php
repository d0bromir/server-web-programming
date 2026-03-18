<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Router – HTTP маршрутизатор.
 *
 * Поддържа:
 *   - GET / POST / PUT / DELETE / PATCH
 *   - URL параметри  (/venues/{id})
 *   - Method override чрез hidden field _method (за HTML форми)
 */
class Router
{
    /** @var array<string, array<string, array{class: string, method: string}>> */
    private array $routes = [];

    // ── Регистриране на маршрути ──────────────────────────────────────

    public function get(string $path, array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, array $handler): void
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete(string $path, array $handler): void
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute(string $method, string $path, array $handler): void
    {
        [$class, $action] = $handler;
        $this->routes[$method][$path] = ['class' => $class, 'method' => $action];
    }

    // ── Dispatch ──────────────────────────────────────────────────────

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri    = '/' . ltrim(rawurldecode($uri), '/');

        // Method override: <input type="hidden" name="_method" value="DELETE">
        if ($method === 'POST') {
            $override = strtoupper($_POST['_method'] ?? '');
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        foreach ($this->routes[$method] ?? [] as $pattern => $handler) {
            $params = $this->matchRoute($pattern, $uri);
            if ($params !== null) {
                $controller = new $handler['class']();
                $controller->{$handler['method']}($params);
                return;
            }
        }

        $this->notFound();
    }

    /**
     * Съпоставя URI към шаблон с параметри.
     * Пример: /venues/{id} ↔ /venues/42  → ['id' => '42']
     *
     * @return array<string, string>|null  Масив с параметри, или null ако не съвпада.
     */
    private function matchRoute(string $pattern, string $uri): ?array
    {
        // Преобразуване: /venues/{id} → /venues/(?P<id>[^/]+)
        $regex = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Връщаме само именуваните captures
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo '<h1>404 – Страницата не е намерена</h1>';
        echo '<a href="/">← Начало</a>';
    }
}
