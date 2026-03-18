<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Auth;
use App\Core\Request;
use App\Model\Venue;

/**
 * VenueController – CRUD за заведения (изисква вход).
 */
class VenueController
{
    // ── Списък с MОИ заведения ────────────────────────────────────────

    public function index(array $params = []): void
    {
        Auth::requireAuth();

        $model  = new Venue();
        $venues = $model->findByUser(Auth::userId());

        require dirname(__DIR__, 2) . '/views/venues/index.php';
    }

    // ── Форма за създаване ───────────────────────────────────────────

    public function createForm(array $params = []): void
    {
        Auth::requireAuth();

        $venue  = [];          // Празен обект за view
        $errors = [];

        require dirname(__DIR__, 2) . '/views/venues/form.php';
    }

    // ── Запис на ново заведение ───────────────────────────────────────

    public function store(array $params = []): void
    {
        Auth::requireAuth();
        Request::verifyCsrf();

        $data     = $this->collectPostData();
        $model    = new Venue();
        $errors   = $model->validate($data);

        if (empty($errors)) {
            $model->create(Auth::userId(), $data);
            Request::flash('success', 'Заведението беше добавено успешно.');
            Request::redirect('/venues');
        }

        $venue = $data;    // Попълваме формата с въведените данни
        require dirname(__DIR__, 2) . '/views/venues/form.php';
    }

    // ── Форма за редакция ────────────────────────────────────────────

    public function editForm(array $params): void
    {
        Auth::requireAuth();

        $venue = $this->findOwned((int) ($params['id'] ?? 0));
        $errors = [];

        require dirname(__DIR__, 2) . '/views/venues/form.php';
    }

    // ── Запис на редакция ────────────────────────────────────────────

    public function update(array $params): void
    {
        Auth::requireAuth();
        Request::verifyCsrf();

        $id    = (int) ($params['id'] ?? 0);
        $this->findOwned($id);          // Проверка за собственост

        $data   = $this->collectPostData();
        $model  = new Venue();
        $errors = $model->validate($data);

        if (empty($errors)) {
            $model->update($id, $data);
            Request::flash('success', 'Заведението беше обновено успешно.');
            Request::redirect('/venues');
        }

        $venue = array_merge($data, ['id' => $id]);
        require dirname(__DIR__, 2) . '/views/venues/form.php';
    }

    // ── Изтриване ────────────────────────────────────────────────────

    public function destroy(array $params): void
    {
        Auth::requireAuth();
        Request::verifyCsrf();

        $id = (int) ($params['id'] ?? 0);
        $this->findOwned($id);

        (new Venue())->delete($id);
        Request::flash('success', 'Заведението беше изтрито.');
        Request::redirect('/venues');
    }

    // ── Private helpers ───────────────────────────────────────────────

    /** Събира POST данните в масив. */
    private function collectPostData(): array
    {
        return [
            'name'        => Request::post('name'),
            'city'        => Request::post('city'),
            'address'     => Request::post('address'),
            'category'    => Request::post('category', 'other'),
            'description' => Request::post('description'),
            'rating'      => Request::post('rating'),
            'website'     => Request::post('website'),
            'is_public'   => Request::post('is_public') === '1',
        ];
    }

    /**
     * Намери заведение, което принадлежи на текущия потребител.
     * При несъответствие → 403.
     */
    private function findOwned(int $id): array
    {
        if ($id <= 0) {
            http_response_code(404);
            exit('Заведението не е намерено.');
        }

        $model = new Venue();
        $venue = $model->findById($id);

        if ($venue === null) {
            http_response_code(404);
            exit('Заведението не е намерено.');
        }

        // Admin може да редактира всичко
        if ((int) $venue['user_id'] !== Auth::userId() && !Auth::isAdmin()) {
            http_response_code(403);
            exit('Нямате права за тази операция.');
        }

        return $venue;
    }
}
