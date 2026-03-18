<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Request;

$title = 'Моите заведения';

function e(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE); }

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Моите заведения</h1>
    <a href="/venues/create" class="btn btn-primary">+ Добави</a>
</div>

<?php if (empty($venues)): ?>
    <div class="alert alert-info">Все още нямате добавени заведения.</div>
<?php else: ?>
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-dark">
            <tr>
                <th>Заведение</th>
                <th>Категория</th>
                <th>Град</th>
                <th>Оценка</th>
                <th>Видимост</th>
                <th>Дата</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($venues as $v): ?>
        <tr>
            <td>
                <strong><?= e($v['name']) ?></strong>
                <?php if ($v['address']): ?>
                    <br><small class="text-muted"><?= e($v['address']) ?></small>
                <?php endif; ?>
            </td>
            <td><span class="badge bg-secondary"><?= e($v['category']) ?></span></td>
            <td><?= e($v['city']) ?></td>
            <td>
                <?php if ($v['rating']): ?>
                    <span class="rating-stars">
                        <?= str_repeat('★', (int)$v['rating']) . str_repeat('☆', 5 - (int)$v['rating']) ?>
                    </span>
                <?php else: ?>
                    <span class="text-muted">–</span>
                <?php endif; ?>
            </td>
            <td>
                <?= $v['is_public']
                    ? '<span class="badge bg-success">Публично</span>'
                    : '<span class="badge bg-warning text-dark">Скрито</span>' ?>
            </td>
            <td>
                <small><?= e(substr($v['created_at'], 0, 10)) ?></small>
            </td>
            <td>
                <a href="/venues/<?= (int)$v['id'] ?>/edit"
                   class="btn btn-sm btn-outline-primary">Редакция</a>

                <!-- Изтриване с CSRF -->
                <form method="POST"
                      action="/venues/<?= (int)$v['id'] ?>/delete"
                      class="d-inline"
                      onsubmit="return confirm('Сигурни ли сте?')">
                    <?= Request::csrfField() ?>
                    <input type="hidden"  name="_method" value="DELETE">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Изтрий</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
