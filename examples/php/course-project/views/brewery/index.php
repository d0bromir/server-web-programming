<?php
declare(strict_types=1);

use App\Core\Request;

$title = 'Пивоварни (External API)';

function e(mixed $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE); }

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">Пивоварни – Open Brewery DB</h1>
        <small class="text-muted">Данни от <strong>openbrewerydb.org</strong> (external REST API)</small>
    </div>
</div>

<!-- Форма за търсене по град -->
<form method="GET" action="/brewery" class="row g-2 mb-4">
    <div class="col-md-4">
        <input type="text" name="city" class="form-control"
               placeholder="Град (напр. sofia, london, berlin)"
               value="<?= e($city) ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Търси</button>
    </div>
</form>

<?php if (!empty($error)): ?>
    <div class="alert alert-warning">
        <strong>API грешка:</strong> <?= e($error) ?>
    </div>
<?php elseif (empty($breweries)): ?>
    <div class="alert alert-info">
        Няма намерени пивоварни за <?= e($city) ?>.
    </div>
<?php else: ?>
    <p class="text-muted mb-3">Намерени: <?= count($breweries) ?> пивоварни за „<?= e($city) ?>"</p>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <?php foreach ($breweries as $b): ?>
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><?= e($b['name'] ?? '') ?></h5>

                    <div class="mb-2">
                        <span class="badge bg-warning text-dark">
                            <?= e($b['brewery_type'] ?? 'unknown') ?>
                        </span>
                    </div>

                    <?php
                    $location = array_filter([
                        $b['address_1']   ?? '',
                        $b['city']        ?? '',
                        $b['state']       ?? '',
                        $b['country']     ?? '',
                    ]);
                    ?>
                    <?php if ($location): ?>
                        <p class="card-text small text-muted mb-2">
                            📍 <?= e(implode(', ', $location)) ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($b['phone'])): ?>
                        <p class="card-text small mb-1">
                            📞 <?= e($b['phone']) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($b['website_url'])): ?>
                <div class="card-footer bg-transparent">
                    <a href="<?= e($b['website_url']) ?>"
                       target="_blank" rel="noopener noreferrer"
                       class="btn btn-sm btn-outline-primary">
                        🌐 Уебсайт
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- API Info блок -->
<div class="mt-5 p-3 bg-light rounded border">
    <h5>Как работи external API интеграцията?</h5>
    <pre class="mb-0"><code>// src/Controller/BreweryController.php
$url = 'https://api.openbrewerydb.org/v1/breweries?by_city=' . urlencode($city);
$ch  = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$json = curl_exec($ch);
curl_close($ch);
$data = json_decode($json, true);</code></pre>
</div>

<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layout.php';
