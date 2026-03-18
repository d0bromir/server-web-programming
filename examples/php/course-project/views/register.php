<?php
declare(strict_types=1);

use App\Core\Request;

$title = 'Регистрация';

function e(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE); }

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="card-title text-center mb-4">Регистрация</h2>

                <form method="POST" action="/register" novalidate>
                    <?= Request::csrfField() ?>

                    <!-- Име -->
                    <div class="mb-3">
                        <label for="name" class="form-label">Имe</label>
                        <input type="text" id="name" name="name"
                               class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>"
                               value="<?= e($name ?? '') ?>"
                               autocomplete="name" required>
                        <?php if (!empty($errors['name'])): ?>
                            <div class="invalid-feedback"><?= e($errors['name']) ?></div>
                        <?php endif; ?>
                    </div>

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
                               autocomplete="new-password" required>
                        <div class="form-text">Поне 8 символа, главна буква и цифра.</div>
                        <?php if (!empty($errors['password'])): ?>
                            <div class="invalid-feedback"><?= e($errors['password']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Повторение на парола -->
                    <div class="mb-3">
                        <label for="password_confirm" class="form-label">Повторете паролата</label>
                        <input type="password" id="password_confirm" name="password_confirm"
                               class="form-control <?= !empty($errors['password_confirm']) ? 'is-invalid' : '' ?>"
                               autocomplete="new-password" required>
                        <?php if (!empty($errors['password_confirm'])): ?>
                            <div class="invalid-feedback"><?= e($errors['password_confirm']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-success">Регистрирай се</button>
                    </div>
                </form>

                <hr>
                <p class="text-center mb-0 small">
                    Вече имате акаунт? <a href="/login">Влезте</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../views/layout.php';
