<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Model\Venue;

/**
 * HomeController – публична начална страница.
 *
 * Покрива: пагинация, търсене, сортиране, cookie за любими.
 */
class HomeController
{
    public function index(array $params = []): void
    {
        $model    = new Venue();
        $page     = Request::queryInt('page', 1);
        $search   = Request::query('search');
        $category = Request::query('category');
        $sort     = Request::query('sort', 'created_at');
        $dir      = Request::query('dir', 'DESC');

        $result = $model->paginate(
            page:      $page,
            search:    $search,
            category:  $category,
            sort:      $sort,
            dir:       $dir,
            publicOnly: true
        );

        // ── Cookie: любими заведения ──────────────────────────────────
        // Потребителят може да "харесва" заведение без вход
        $action   = Request::query('favorite');
        $venueId  = Request::queryInt('venue_id');

        if ($action !== '' && $venueId > 0) {
            $favorites = self::getFavorites();
            if ($action === 'add' && !in_array($venueId, $favorites, true)) {
                $favorites[] = $venueId;
            } elseif ($action === 'remove') {
                $favorites = array_values(array_filter($favorites, fn($f) => $f !== $venueId));
            }
            // Cookie живее 30 дни, httponly
            setcookie(
                'favorites',
                implode(',', $favorites),
                time() + 30 * 86400,
                '/',
                '',
                false,   // secure = true в продукция с HTTPS
                true     // httponly
            );
            Request::redirect('/?' . http_build_query($_GET));
        }

        $favorites = self::getFavorites();

        require dirname(__DIR__, 2) . '/views/home.php';
    }

    private static function getFavorites(): array
    {
        $raw = $_COOKIE['favorites'] ?? '';
        if ($raw === '') {
            return [];
        }
        // Само числа
        return array_values(array_filter(
            array_map('intval', explode(',', $raw)),
            fn($id) => $id > 0
        ));
    }
}
