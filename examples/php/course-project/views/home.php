<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Request;
use App\Model\Venue;

// Променливите са зададени от HomeController
// $result   = ['items'=>[], 'total'=>0, 'pages'=>1]
// $page, $search, $category, $sort, $dir
// $favorites = [1, 3, 7, ...]

$title   = 'Начало';

/** Помощна ф-я за XSS-безопасен изход */
function e(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE); }

/** URL за пагинация / сортиране без промяна на другите параметри */
function queryWith(array $overrides): string {
    $base = array_filter([
        'page'     => $_GET['page'] ?? 1,
        'search'   => $_GET['search'] ?? '',
        'category' => $_GET['category'] ?? '',
        'sort'     => $_GET['sort'] ?? 'created_at',
        'dir'      => $_GET['dir'] ?? 'DESC',
    ]);
    return '?' . http_build_query(array_merge($base, $overrides));
}

/** Звездичен рейтинг */
function stars(?int $rating): string {
    if (!$rating) return '<span class="text-muted">–</span>';
    return '<span class="rating-stars">' . str_repeat('★', $rating) . str_repeat('☆', 5 - $rating) . '</span>';
}

ob_start();
?>

<!-- Заглавие и форма за търсене -->
<div class="row align-items-center mb-3">
    <div class="col-md-6">
        <h1 class="h3 mb-0">Заведения</h1>
        <small class="text-muted">
            Намерени: <?= e($result['total']) ?> заведения
        </small>
    </div>
    <div class="col-md-6 text-md-end">
        <?php if (Auth::isLoggedIn()): ?>
        <a href="/venues/create" class="btn btn-primary btn-sm">+ Добави заведение</a>
        <?php endif; ?>
    </div>
</div>

<!-- Филтри -->
<form method="GET" action="/" class="row g-2 mb-4">
    <div class="col-md-4">
        <input type="search" name="search" class="form-control"
               placeholder="Търси по име, град..." value="<?= e($search) ?>">
    </div>
    <div class="col-md-3">
        <select name="category" class="form-select">
            <option value="">Всички категории</option>
            <?php foreach (Venue::CATEGORIES as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                <?= e(ucfirst($cat)) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-3">
        <select name="sort" class="form-select">
            <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Най-нови</option>
            <option value="name"       <?= $sort === 'name'       ? 'selected' : '' ?>>По азбука</option>
            <option value="rating"     <?= $sort === 'rating'     ? 'selected' : '' ?>>По оценка</option>
            <option value="city"       <?= $sort === 'city'       ? 'selected' : '' ?>>По град</option>
        </select>
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-secondary w-100">Филтрирай</button>
    </div>
</form>

<!-- Карти на заведения -->
<?php if (empty($result['items'])): ?>
    <div class="alert alert-info">Няма намерени заведения.</div>
<?php else: ?>
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
    <?php foreach ($result['items'] as $v): ?>
    <?php
        $isFav = in_array((int)$v['id'], $favorites, true);
        $favAction = $isFav ? 'remove' : 'add';
        $favUrl    = '/?' . http_build_query(array_filter([
            'favorite' => $favAction,
            'venue_id' => $v['id'],
            'page'     => $page,
            'search'   => $search,
            'category' => $category,
        ]));
    ?>
    <div class="col">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <h5 class="card-title mb-1">
                        <?= e($v['name']) ?>
                    </h5>
                    <a href="<?= $favUrl ?>"
                       title="<?= $isFav ? 'Премахни от любими' : 'Добави в любими' ?>"
                       class="fs-4 text-decoration-none">
                        <?= $isFav ? '❤️' : '🤍' ?>
                    </a>
                </div>

                <div class="mb-2">
                    <span class="badge bg-secondary badge-category">
                        <?= e($v['category']) ?>
                    </span>
                    <small class="text-muted ms-2">📍 <?= e($v['city']) ?></small>
                </div>

                <?php if ($v['rating']): ?>
                    <div class="mb-2"><?= stars((int)$v['rating']) ?></div>
                <?php endif; ?>

                <?php if ($v['description']): ?>
                    <p class="card-text text-muted small">
                        <?= e(mb_strimwidth($v['description'], 0, 120, '…')) ?>
                    </p>
                <?php endif; ?>
            </div>

            <?php if ($v['address'] || $v['website']): ?>
            <div class="card-footer bg-transparent small text-muted">
                <?php if ($v['address']): ?>
                    <div>📮 <?= e($v['address']) ?></div>
                <?php endif; ?>
                <?php if ($v['website']): ?>
                    <div>🌐 <a href="<?= e($v['website']) ?>" target="_blank" rel="noopener noreferrer">
                        <?= e($v['website']) ?>
                    </a></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Пагинация -->
<?php if ($result['pages'] > 1): ?>
<nav aria-label="Пагинация">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= queryWith(['page' => $page - 1]) ?>">«</a>
        </li>
        <?php for ($p = 1; $p <= $result['pages']; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= queryWith(['page' => $p]) ?>"><?= $p ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $result['pages'] ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= queryWith(['page' => $page + 1]) ?>">»</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
