<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Request;

// $title, $content трябва да са дефинирани от изгледа, извикващ layout
$title   ??= 'Любими заведения';
$content ??= '';

$flash_success = Request::getFlash('success');
$flash_error   = Request::getFlash('error');
?>
<!DOCTYPE html>
<html lang="bg">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> – Любими заведения</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; }
        .rating-stars { color: #ffc107; }
        .badge-category { font-size: .75em; }
        .card { transition: transform .15s; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
    </style>
</head>
<body>

<!-- Навигация -->
<nav class="navbar navbar-expand-md navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="/">🍽️ Любими заведения</a>
        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/">Начало</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/brewery">Пивоварни (API)</a>
                </li>
                <?php if (Auth::isLoggedIn()): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/venues">Моите заведения</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/venues/create">+ Добави</a>
                </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto">
                <?php if (Auth::isLoggedIn()): ?>
                    <li class="nav-item">
                        <span class="nav-link text-light">
                            👤 <?= htmlspecialchars(Auth::user()['name']) ?>
                        </span>
                    </li>
                    <li class="nav-item">
                        <form method="POST" action="/logout" class="d-inline">
                            <?= Request::csrfField() ?>
                            <button class="btn btn-sm btn-outline-light my-1">Изход</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/login">Вход</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-sm btn-outline-light my-1 ms-1" href="/register">Регистрация</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Flash съобщения -->
<div class="container">
    <?php if ($flash_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash_success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($flash_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Съдържание -->
    <?= $content ?>
</div>

<footer class="mt-5 py-4 bg-dark text-center text-muted">
    <small>Курсова задача – Сървърно уеб програмиране</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
