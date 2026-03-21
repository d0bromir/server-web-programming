<?php
declare(strict_types=1);
/**
 * Тема 3 – MVC (Model-View-Controller)
 *
 * Прост MVC без external framework:
 *   Router   → разпределя URL към Controller
 *   Controller → взима/подготвя данни чрез Model
 *   Model      → данни (масив – без DB за простота)
 *   View       → генерира HTML, получава данни от Controller
 *
 * Стартиране: php -S localhost:8000
 * URL-и:
 *   /          → HomeController::index()
 *   /items     → ItemController::index()
 *   /items/1   → ItemController::show(1)
 *
 * curl заявки (ръчно тестване):
 *   # Начална страница
 *   curl http://localhost:8000/
 *
 *   # Списък с елементи
 *   curl http://localhost:8000/items
 *
 *   # Детайли за елемент
 *   curl http://localhost:8000/items/1
 *   curl http://localhost:8000/items/99  # 404 Not Found
 */

// ══════════════════════════════════════════════════════════════════════
//  MODEL
// ══════════════════════════════════════════════════════════════════════

class ItemModel
{
    /** @var array<int,array{id:int,name:string,description:string,price:float}> */
    private static array $items = [
        1 => ['id' => 1, 'name' => 'Кафе',     'description' => 'Еспресо кафе',       'price' => 2.50],
        2 => ['id' => 2, 'name' => 'Чай',      'description' => 'Зелен чай',          'price' => 1.80],
        3 => ['id' => 3, 'name' => 'Сок',      'description' => 'Портокалов сок',     'price' => 3.20],
        4 => ['id' => 4, 'name' => 'Бира',     'description' => 'Наливна бира 500мл', 'price' => 4.00],
    ];

    public function getAll(): array
    {
        return self::$items;
    }

    public function getById(int $id): ?array
    {
        return self::$items[$id] ?? null;
    }
}

// ══════════════════════════════════════════════════════════════════════
//  VIEW  (helper)
// ══════════════════════════════════════════════════════════════════════

/**
 * Рендерира view файл с подадени данни.
 * В реален проект view файловете са в отделна директория.
 */
function renderView(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);      // прави $var = $data['var']
    // В реален проект: include __DIR__ . "/views/{$view}.php";
    // Тук views са дефинирани като функции по-долу.
    if (function_exists($view)) {
        $view($data);
    }
}

// ══════════════════════════════════════════════════════════════════════
//  CONTROLLER
// ══════════════════════════════════════════════════════════════════════

class HomeController
{
    public function index(): void
    {
        renderView('view_home', [
            'title'   => 'Начало – MVC Demo',
            'message' => 'Добре дошли! Това е пример за MVC архитектура в PHP.',
        ]);
    }
}

class ItemController
{
    public function __construct(private readonly ItemModel $model) {}

    public function index(): void
    {
        $items = $this->model->getAll();
        renderView('view_items_list', [
            'title' => 'Списък от артикули',
            'items' => $items,
        ]);
    }

    public function show(int $id): void
    {
        $item = $this->model->getById($id);
        if ($item === null) {
            http_response_code(404);
            renderView('view_404', ['title' => '404 – Не е намерено']);
            return;
        }
        renderView('view_item_detail', [
            'title' => "Артикул: {$item['name']}",
            'item'  => $item,
        ]);
    }
}

// ══════════════════════════════════════════════════════════════════════
//  ROUTER
// ══════════════════════════════════════════════════════════════════════

class Router
{
    /** @var array<array{pattern:string, callback:callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $callback): void
    {
        $this->routes[] = ['pattern' => $pattern, 'callback' => $callback];
    }

    public function dispatch(string $uri): void
    {
        $path = strtok($uri, '?');
        foreach ($this->routes as $route) {
            // Конвертираме {id} → regex capture group (\d+)
            $regex = '@^' . preg_replace('/\{(\w+)\}/', '(\d+)', $route['pattern']) . '$@';
            if (preg_match($regex, $path, $matches)) {
                array_shift($matches);   // махаме целия match
                ($route['callback'])(...$matches);
                return;
            }
        }
        // Нищо не се съвпадна → 404
        http_response_code(404);
        renderView('view_404', ['title' => '404 – Не е намерено']);
    }
}

// ══════════════════════════════════════════════════════════════════════
//  BOOTSTRAP
// ══════════════════════════════════════════════════════════════════════

$router = new Router();
$items  = new ItemController(new ItemModel());

$router->get('/',          fn()      => (new HomeController())->index());
$router->get('/items',     fn()      => $items->index());
$router->get('/items/{id}',fn($id)   => $items->show((int) $id));

$router->dispatch($_SERVER['REQUEST_URI']);

// ══════════════════════════════════════════════════════════════════════
//  VIEWS  (дефинирани като функции за простота на примера)
// ══════════════════════════════════════════════════════════════════════

function view_layout_start(string $title): void { ?>
<!DOCTYPE html>
<html lang="bg">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($title) ?></title>
<style>
body{font-family:Arial,sans-serif;max-width:800px;margin:40px auto;padding:0 20px}
nav a{margin-right:15px;text-decoration:none;color:#2980b9}
.card{border:1px solid #ddd;border-radius:8px;padding:18px;margin:16px 0}
table{border-collapse:collapse;width:100%}
td,th{padding:8px 12px;border:1px solid #ddd;text-align:left}
th{background:#f0f0f0}
</style>
</head>
<body>
<nav><a href="/">Начало</a><a href="/items">Артикули</a></nav>
<hr>
<?php } // end layout_start

function view_layout_end(): void { ?>
</body></html>
<?php }

function view_home(array $data): void
{
    view_layout_start($data['title']);
    echo "<h1>{$data['title']}</h1><p>{$data['message']}</p>";
    echo '<div class="card">
    <h2>MVC – как работи?</h2>
    <ol>
      <li><strong>Router</strong> – приема заявката и избира правилния Controller</li>
      <li><strong>Controller</strong> – бизнес логика; взима данни от Model</li>
      <li><strong>Model</strong> – данните (DB, файл, API …)</li>
      <li><strong>View</strong> – генерира HTML от данните</li>
    </ol>
    <p><a href="/items">Вижте примера с артикули →</a></p>
    </div>';
    view_layout_end();
}

function view_items_list(array $data): void
{
    view_layout_start($data['title']);
    echo "<h1>{$data['title']}</h1>";
    echo '<table><tr><th>ID</th><th>Ime</th><th>Описание</th><th>Цена</th><th></th></tr>';
    foreach ($data['items'] as $item) {
        printf(
            '<tr><td>%d</td><td>%s</td><td>%s</td><td>%.2f €</td><td><a href="/items/%d">Детайли</a></td></tr>',
            $item['id'],
            htmlspecialchars($item['name']),
            htmlspecialchars($item['description']),
            $item['price'],
            $item['id']
        );
    }
    echo '</table>';
    view_layout_end();
}

function view_item_detail(array $data): void
{
    $item = $data['item'];
    view_layout_start($data['title']);
    echo "<h1>{$data['title']}</h1>";
    echo '<div class="card">';
    echo '<p><strong>ID:</strong> ' . $item['id'] . '</p>';
    echo '<p><strong>Название:</strong> ' . htmlspecialchars($item['name']) . '</p>';
    echo '<p><strong>Описание:</strong> ' . htmlspecialchars($item['description']) . '</p>';
    echo '<p><strong>Цена:</strong> ' . number_format($item['price'], 2) . ' €</p>';
    echo '</div>';
    echo '<a href="/items">← Обратно към списъка</a>';
    view_layout_end();
}

function view_404(array $data): void
{
    view_layout_start($data['title']);
    echo '<h1>404 – Страницата не е намерена</h1><a href="/">Начало</a>';
    view_layout_end();
}
