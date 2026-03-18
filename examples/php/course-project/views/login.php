<?php
declare(strict_types=1);

use App\Core\Request;

$title = 'Вход';

function e(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE); }

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4">Вход</h2>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                <?php endif; ?>

                <form method="POST" action="/login" novalidate>
                    <?= Request::csrfField() ?>

                    <!-- Имейл -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Имейл</label>
                        <input type="email" id="email" name="email"
                               class="form-control <?= !empty($errors['email']) ? 'is-invalid' : '' ?>"
                               value="<?= e($email ?? '') ?>"
                               autocomplete="email" required>
                        <?php if (!empty($errors['email'])): ?>
                            <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Парола -->
                    <div class="mb-3">
                        <label for="password" class="form-label">Парола</label>
                        <input type="password" id="password" name="password"
                               class="form-control <?= !empty($errors['password']) ? 'is-invalid' : '' ?>"
                               autocomplete="current-password" required>
                        <?php if (!empty($errors['password'])): ?>
                            <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary">Влез</button>
                    </div>
                </form>

                <hr>
                <p class="text-center mb-0 small">
                    Нямате акаунт? <a href="/register">Регистрирайте се</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
