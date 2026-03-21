# =============================================================================
# Стартира Maven тестовете за всички Java примери
# =============================================================================
# Употреба (от произволна директория):
#   .\scripts\run-java-tests.ps1
#
# По избор – само конкретен пример:
#   .\scripts\run-java-tests.ps1 -Example 06-database
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
$examplesDir = Join-Path $repoRoot "examples\java"

if (-not (Test-Path $examplesDir)) {
    Write-Warn "Директорията $examplesDir не е намерена."
    exit 1
}

# ── Проверка на зависимости ──────────────────────────────────────────────────

if (-not (Get-Command "java" -ErrorAction SilentlyContinue)) { Write-Warn "java не е в PATH."; exit 1 }
if (-not (Get-Command "mvn"  -ErrorAction SilentlyContinue)) { Write-Warn "mvn не е в PATH.";  exit 1 }

# ── Намираме кои директории да тестваме ─────────────────────────────────────

if ($Example -ne "") {
    $dirs = @(Get-Item (Join-Path $examplesDir $Example) -ErrorAction SilentlyContinue)
    if (-not $dirs) { Write-Warn "Не намерих '$Example' в $examplesDir"; exit 1 }
} else {
    $dirs = Get-ChildItem $examplesDir -Directory | Where-Object {
        Test-Path (Join-Path $_.FullName "pom.xml")
    } | Sort-Object Name
}

# ── Главен цикъл ─────────────────────────────────────────────────────────────

Write-Header "Java тестове – examples/java/"

$passed  = 0
$failed  = 0
$skipped = 0

foreach ($dir in $dirs) {
    $pomFile = Join-Path $dir.FullName "pom.xml"

    if (-not (Test-Path $pomFile)) {
        Write-Skip "$($dir.Name)  (няма pom.xml)"
        $skipped++
        continue
    }

    Write-Step "$($dir.Name)"
    Push-Location $dir.FullName

    # mvn test: компилира и изпълнява тестовете; -B = batch (без цветове/интерактивност)
    $output = mvn test -B 2>&1
    $exitCode = $LASTEXITCODE

    # Показваме само важните редове (BUILD SUCCESS/FAILURE + тестови резултати)
    $output | Where-Object {
        $_ -match "Tests run:|BUILD |ERROR |FAILURE|^\[INFO\] ---"
    } | ForEach-Object { Write-Host "    $_" }

    if ($exitCode -eq 0) {
        Write-OK "$($dir.Name)"
        $passed++
    } else {
        Write-Fail "$($dir.Name)"
        # При неуспех – показваме пълния output за диагностика
        Write-Host ""
        Write-Host "  --- Пълен лог ---" -ForegroundColor DarkGray
        $output | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }
        $failed++
    }

    Pop-Location
}

# ── Обобщение ────────────────────────────────────────────────────────────────

Write-Header "Резюме"
Write-Host "  Преминали : $passed" -ForegroundColor Green
if ($failed  -gt 0) { Write-Host "  Неуспешни: $failed"  -ForegroundColor Red  }
if ($skipped -gt 0) { Write-Host "  Пропуснати: $skipped" -ForegroundColor Gray }
Write-Host ""

if ($failed -gt 0) { exit 1 } else { exit 0 }
