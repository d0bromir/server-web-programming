# =============================================================================
# Стартира PHPUnit тестовете за всички PHP примери
# =============================================================================
# Употреба (от произволна директория):
#   .\scripts\run-php-tests.ps1
#
# По избор – само конкретен пример:
#   .\scripts\run-php-tests.ps1 -Example 07-crud
# =============================================================================

param(
    [string]$Example = ""   # Ако е зададен, тества само тази поддиректория
)

$ErrorActionPreference = "Continue"

# ── Цветни помощни функции ───────────────────────────────────────────────────

function Write-Header { param([string]$t)
    Write-Host ""
    Write-Host ("=" * 60) -ForegroundColor Cyan
    Write-Host "  $t" -ForegroundColor Cyan
    Write-Host ("=" * 60) -ForegroundColor Cyan
}

function Write-Step  { param([string]$t) Write-Host "  >> $t" -ForegroundColor Yellow }
function Write-OK    { param([string]$t) Write-Host "  [PASS] $t" -ForegroundColor Green }
function Write-Fail  { param([string]$t) Write-Host "  [FAIL] $t" -ForegroundColor Red }
function Write-Skip  { param([string]$t) Write-Host "  [SKIP] $t" -ForegroundColor Gray }
function Write-Warn  { param([string]$t) Write-Host "  [!]   $t" -ForegroundColor Magenta }

# ── Намираме корена на хранилището ──────────────────────────────────────────

$scriptDir   = Split-Path $MyInvocation.MyCommand.Path -Parent
$repoRoot    = Split-Path $scriptDir -Parent
$examplesDir = Join-Path $repoRoot "examples\php"

if (-not (Test-Path $examplesDir)) {
    Write-Warn "Директорията $examplesDir не е намерена."
    exit 1
}

# ── Проверка на зависимости ──────────────────────────────────────────────────

if (-not (Get-Command "php"      -ErrorAction SilentlyContinue)) { Write-Warn "php не е в PATH.";      exit 1 }
if (-not (Get-Command "composer" -ErrorAction SilentlyContinue)) { Write-Warn "composer не е в PATH."; exit 1 }

# ── Намираме кои директории да тестваме ─────────────────────────────────────

if ($Example -ne "") {
    $dirs = @(Get-Item (Join-Path $examplesDir $Example) -ErrorAction SilentlyContinue)
    if (-not $dirs) { Write-Warn "Не намерих '$Example' в $examplesDir"; exit 1 }
} else {
    $dirs = Get-ChildItem $examplesDir -Directory | Sort-Object Name
}

# ── Главен цикъл ─────────────────────────────────────────────────────────────

Write-Header "PHP тестове – examples/php/"

$passed  = 0
$failed  = 0
$skipped = 0

foreach ($dir in $dirs) {
    $composerJson = Join-Path $dir.FullName "composer.json"
    $testsDir     = Join-Path $dir.FullName "tests"

    if (-not (Test-Path $composerJson) -or -not (Test-Path $testsDir)) {
        Write-Skip "$($dir.Name)  (няма composer.json/tests/)"
        $skipped++
        continue
    }

    Write-Step "$($dir.Name)"
    Push-Location $dir.FullName

    # Уверяваме се, че vendor/ е налице
    if (-not (Test-Path "vendor")) {
        Write-Step "  composer install ..."
        composer install --no-interaction --quiet 2>&1 | Out-Null
    }

    # Изпълняваме тестовете
    $output = composer test 2>&1
    $exitCode = $LASTEXITCODE

    # Показваме резултата
    $output | ForEach-Object { Write-Host "    $_" }

    if ($exitCode -eq 0) {
        Write-OK "$($dir.Name)"
        $passed++
    } else {
        Write-Fail "$($dir.Name)"
        $failed++
    }

    Pop-Location
}

# ── Обобщение ────────────────────────────────────────────────────────────────

Write-Header "Резюме"
Write-Host "  Преминали : $passed" -ForegroundColor Green
if ($failed  -gt 0) { Write-Host "  Неуспешни: $failed"  -ForegroundColor Red     }
if ($skipped -gt 0) { Write-Host "  Пропуснати: $skipped" -ForegroundColor Gray    }
Write-Host ""

if ($failed -gt 0) { exit 1 } else { exit 0 }
