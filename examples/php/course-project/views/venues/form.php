<?php
declare(strict_types=1);

use App\Core\Request;
use App\Model\Venue;

// $venue  – масив с данни (нов: [], редакция: съществуващ запис)
// $errors – масив с грешки от валидацията

$isEdit = !empty($venue['id']);
$title  = $isEdit ? 'Редакция на заведение' : 'Ново заведение';

function e(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE); }

$action = $isEdit
    ? '/venues/' . (int)$venue['id']
    : '/venues';

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3"><?= e($title) ?></h1>
            <a href="/venues" class="btn btn-outline-secondary btn-sm">← Назад</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <form method="POST" action="<?= e($action) ?>" novalidate>
                    <?= Request::csrfField() ?>
                    <?php if ($isEdit): ?>
                        <input type="hidden" name="_method" value="PUT">
                    <?php endif; ?>

                    <div class="row g-3">

                        <!-- Наименование -->
                        <div class="col-md-8">
                            <label for="name" class="form-label fw-semibold">
                                Наименование <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="name" name="name"
                                   class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>"
                                   value="<?= e($venue['name'] ?? '') ?>"
                                   maxlength="200" required>
                            <?php if (!empty($errors['name'])): ?>
                                <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Категория -->
                        <div class="col-md-4">
                            <label for="category" class="form-label fw-semibold">Категория</label>
                            <select id="category" name="category" class="form-select">
                                <?php foreach (Venue::CATEGORIES as $cat): ?>
                                <option value="<?= e($cat) ?>"
                                        <?= ($venue['category'] ?? 'other') === $cat ? 'selected' : '' ?>>
                                    <?= e(ucfirst($cat)) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Град -->
                        <div class="col-md-6">
                            <label for="city" class="form-label fw-semibold">
                                Град <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="city" name="city"
                                   class="form-control <?= !empty($errors['city']) ? 'is-invalid' : '' ?>"
                                   value="<?= e($venue['city'] ?? '') ?>" required>
                            <?php if (!empty($errors['city'])): ?>
                                <div class="invalid-feedback"><?= e($errors['city']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Адрес -->
                        <div class="col-md-6">
                            <label for="address" class="form-label fw-semibold">Адрес</label>
                            <input type="text" id="address" name="address" class="form-control"
                                   value="<?= e($venue['address'] ?? '') ?>" maxlength="300">
                        </div>

                        <!-- Описание -->
                        <div class="col-12">
                            <label for="description" class="form-label fw-semibold">Описание</label>
                            <textarea id="description" name="description"
                                      class="form-control" rows="3"><?= e($venue['description'] ?? '') ?></textarea>
                        </div>

                        <!-- Оценка -->
                        <div class="col-md-4">
                            <label for="rating" class="form-label fw-semibold">Оценка (1–5)</label>
                            <select id="rating" name="rating"
                                    class="form-select <?= !empty($errors['rating']) ? 'is-invalid' : '' ?>">
                                <option value="">– без оценка –</option>
                                <?php for ($r = 1; $r <= 5; $r++): ?>
                                <option value="<?= $r ?>"
                                        <?= (string)($venue['rating'] ?? '') === (string)$r ? 'selected' : '' ?>>
                                    <?= str_repeat('★', $r) . str_repeat('☆', 5-$r) ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                            <?php if (!empty($errors['rating'])): ?>
                                <div class="invalid-feedback"><?= e($errors['rating']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Уебсайт -->
                        <div class="col-md-8">
                            <label for="website" class="form-label fw-semibold">Уебсайт</label>
                            <input type="url" id="website" name="website"
                                   class="form-control <?= !empty($errors['website']) ? 'is-invalid' : '' ?>"
                                   value="<?= e($venue['website'] ?? '') ?>"
                                   placeholder="https://...">
                            <?php if (!empty($errors['website'])): ?>
                                <div class="invalid-feedback"><?= e($errors['website']) ?></div>
                            <?php endif; ?>
                        </div>

                        <!-- Публично -->
                        <div class="col-12">
                            <div class="form-check">
                                <input type="checkbox" id="is_public" name="is_public"
                                       value="1" class="form-check-input"
                                       <?= ($venue['is_public'] ?? true) ? 'checked' : '' ?>>
                                <label for="is_public" class="form-check-label">
                                    Видимо за всички (публично)
                                </label>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="col-12 d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <?= $isEdit ? 'Запиши промените' : 'Добави заведение' ?>
                            </button>
                            <a href="/venues" class="btn btn-outline-secondary">Отказ</a>
                        </div>

                    </div><!-- /row -->
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
