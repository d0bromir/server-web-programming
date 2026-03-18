<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Model\User;
use App\Model\Venue;

/**
 * ApiController – REST JSON API за заведения.
 *
 * Автентикация: Bearer token (Authorization: Bearer <token>)
 *
 * GET    /api/venues         → публичен списък с пагинация
 * GET    /api/venues/{id}    → едно заведение
 * POST   /api/venues         → ново заведение (изисква токен)
 * PUT    /api/venues/{id}    → пълна замяна (изисква токен)
 * DELETE /api/venues/{id}    → изтриване (изисква токен)
 */
class ApiController
{
    // ── GET /api/venues ───────────────────────────────────────────────

    public function index(array $params = []): void
    {
        $page   = Request::queryInt('page', 1);
        $search = Request::query('search');
        $cat    = Request::query('category');

        $model  = new Venue();
        $result = $model->paginate(page: $page, search: $search, category: $cat, publicOnly: true);

        $this->json([
            'data'  => $result['items'],
            'meta'  => [
                'total'   => $result['total'],
                'pages'   => $result['pages'],
                'page'    => $page,
                'perPage' => Venue::PER_PAGE,
            ],
            '_links' => [
                'self' => "/api/venues?page=$page",
                'next' => $page < $result['pages'] ? "/api/venues?page=" . ($page + 1) : null,
            ],
        ]);
    }

    // ── GET /api/venues/{id} ──────────────────────────────────────────

    public function show(array $params): void
    {
        $venue = (new Venue())->findById((int) ($params['id'] ?? 0));

        if ($venue === null) {
            $this->json(['error' => 'Не е намерено.'], 404);
            return;
        }

        $this->json(['data' => $venue]);
    }

    // ── POST /api/venues ──────────────────────────────────────────────

    public function store(array $params = []): void
    {
        $user = $this->requireToken();
        $data = Request::json();

        $model  = new Venue();
        $errors = $model->validate($data);

        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        $id    = $model->create((int) $user['id'], $data);
        $venue = $model->findById($id);

        $this->json(['data' => $venue], 201);
    }

    // ── PUT /api/venues/{id} ──────────────────────────────────────────

    public function update(array $params): void
    {
        $user  = $this->requireToken();
        $id    = (int) ($params['id'] ?? 0);
        $data  = Request::json();
        $model = new Venue();

        $existing = $model->findById($id);
        if ($existing === null) {
            $this->json(['error' => 'Не е намерено.'], 404);
            return;
        }

        if ((int) $existing['user_id'] !== (int) $user['id']) {
            $this->json(['error' => 'Нямате права.'], 403);
            return;
        }

        $errors = $model->validate($data);
        if (!empty($errors)) {
            $this->json(['errors' => $errors], 422);
            return;
        }

        $model->update($id, $data);
        $this->json(['data' => $model->findById($id)]);
    }

    // ── DELETE /api/venues/{id} ───────────────────────────────────────

    public function destroy(array $params): void
    {
        $user  = $this->requireToken();
        $id    = (int) ($params['id'] ?? 0);
        $model = new Venue();

        $existing = $model->findById($id);
        if ($existing === null) {
            $this->json(['error' => 'Не е намерено.'], 404);
            return;
        }

        if ((int) $existing['user_id'] !== (int) $user['id']) {
            $this->json(['error' => 'Нямате права.'], 403);
            return;
        }

        $model->delete($id);
        $this->json(null, 204);
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** Проверява Bearer токена и връща потребителя. При грешка → 401. */
    private function requireToken(): array
    {
        $token = Request::bearerToken();

        if ($token === null) {
            $this->json(['error' => 'Липсва Authorization хедър.'], 401);
            exit;
        }

        $user = (new User())->findByToken($token);

        if ($user === null) {
            $this->json(['error' => 'Невалиден токен.'], 401);
            exit;
        }

        return $user;
    }

    private function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');

        if ($data !== null) {
            echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        exit;
    }
}
