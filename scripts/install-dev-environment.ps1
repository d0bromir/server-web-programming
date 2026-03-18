# =============================================================================
# Инсталационен скрипт за "Сървърно уеб програмиране"
# Windows 10 / Windows 11
# =============================================================================
# Инсталира:
#   • Git
#   • PHP 8.x + Composer
#   • Java 25 JDK (Eclipse Temurin) + Maven
#   • PostgreSQL 16 + pgAdmin 4
#   • Visual Studio Code + разширения за PHP, Java, Spring Boot, SQL
#   • IntelliJ IDEA Community Edition
# =============================================================================
# Стартиране (като Администратор):
#   Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
#   .\install-dev-environment.ps1
# =============================================================================

#Requires -Version 5.1

$ErrorActionPreference = "Stop"

# ── Помощни функции ─────────────────────────────────────────────────────────

function Write-Header {
    param([string]$Text)
    Write-Host ""
    Write-Host ("=" * 60) -ForegroundColor Cyan
    Write-Host "  $Text" -ForegroundColor Cyan
    Write-Host ("=" * 60) -ForegroundColor Cyan
}

function Write-Step {
    param([string]$Text)
    Write-Host "  >> $Text" -ForegroundColor Yellow
}

function Write-OK {
    param([string]$Text)
    Write-Host "  [OK] $Text" -ForegroundColor Green
}

function Write-Skip {
    param([string]$Text)
    Write-Host "  [--] $Text (вече е инсталирано)" -ForegroundColor Gray
}

function Write-Warn {
    param([string]$Text)
    Write-Host "  [!]  $Text" -ForegroundColor Magenta
}

function Test-Command {
    param([string]$Name)
    return [bool](Get-Command $Name -ErrorAction SilentlyContinue)
}

function Install-WingetPackage {
    param(
        [string]$Id,
        [string]$Name,
        [string[]]$ExtraArgs = @()
    )
    Write-Step "Инсталиране на $Name ..."
    $result = winget install --id $Id --silent --accept-package-agreements --accept-source-agreements @ExtraArgs 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-OK "$Name е инсталиран успешно."
    } elseif ($LASTEXITCODE -eq -1978335189) {
        # 0x8A150011 = вече инсталирано
        Write-Skip $Name
    } else {
        Write-Warn "${Name}: winget върна код ${LASTEXITCODE} - проверете ръчно."
    }
}

function Refresh-Path {
    $env:Path = [System.Environment]::GetEnvironmentVariable("Path", "Machine") + ";" +
                [System.Environment]::GetEnvironmentVariable("Path", "User")
}

# ── 0. Проверка на администраторски права ───────────────────────────────────

Write-Header "Проверка на права"
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
    [Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host ""
    Write-Host "  ГРЕШКА: Скриптът трябва да се стартира като Администратор!" -ForegroundColor Red
    Write-Host "  Щракнете с десен бутон на мишката върху PowerShell -> 'Run as Administrator'" -ForegroundColor Red
    Write-Host ""
    exit 1
}
Write-OK "Скриптът работи с администраторски права."

# Запазваме кодирането веднаж в началото за възстановяване при грешка или еарли exit
$script:savedOutputEncoding = [Console]::OutputEncoding
$script:savedInputEncoding  = [Console]::InputEncoding
trap {
    [Console]::OutputEncoding = $script:savedOutputEncoding
    [Console]::InputEncoding  = $script:savedInputEncoding
    break
}

# ── 1. Проверка на winget ────────────────────────────────────────────────────

Write-Header "Проверка на winget"
if (-not (Test-Command "winget")) {
    Write-Warn "winget не е намерен. Инсталирайте 'App Installer' от Microsoft Store и стартирайте скрипта отново."
    exit 1
}
$wingetVer = (winget --version 2>&1)
Write-OK "winget версия: $wingetVer"

# ── 2. Git ───────────────────────────────────────────────────────────────────

Write-Header "Git"
if (Test-Command "git") {
    Write-Skip "Git"
} else {
    Install-WingetPackage -Id "Git.Git" -Name "Git"
    Refresh-Path
}

# ── 3. PHP 8.x ───────────────────────────────────────────────────────────────

function Install-PhpManually {
    $phpDir = "C:\tools\php"
    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12

    # Откриване на най-новата PHP 8.x версия от windows.php.net
    Write-Step "Откриване на най-новата PHP 8.x версия ..."
    $phpVersion = $null
    try {
        $relPage = Invoke-WebRequest -Uri "https://windows.php.net/download/" -UseBasicParsing
        # Търсим зип файл за VS17 x64 Non-Thread-Safe (NTS) за встроен сървър
        $zipLink = $relPage.Links.href |
            Where-Object { $_ -match 'php-8\.[\d.]+-nts-Win32-vs17-x64\.zip$' } |
            Select-Object -First 1
        if ($zipLink) {
            if ($zipLink -notmatch '^https?://') { $zipLink = "https://windows.php.net$zipLink" }
            $phpVersion = ([regex]::Match($zipLink, 'php-([\d.]+)-')).Groups[1].Value
        }
    } catch { }

    if (-not $zipLink) {
        Write-Warn "Не може да се определи URL на PHP. Изтеглете ръчно от: https://windows.php.net/download/"
        return
    }

    $zipPath  = "$env:TEMP\php-$phpVersion.zip"
    $phpDest  = "$phpDir\php-$phpVersion"

    Write-Step "Изтегляне на PHP $phpVersion (NTS x64) ..."
    try {
        Invoke-WebRequest -Uri $zipLink -OutFile $zipPath -UseBasicParsing
    } catch {
        Write-Warn "Изтеглянето не успя: $_"
        return
    }

    Write-Step "Разархивиране в $phpDest ..."
    if (-not (Test-Path $phpDir)) { New-Item -ItemType Directory -Path $phpDir | Out-Null }
    if (-not (Test-Path $phpDest)) { New-Item -ItemType Directory -Path $phpDest | Out-Null }
    Expand-Archive -Path $zipPath -DestinationPath $phpDest -Force
    Remove-Item $zipPath -ErrorAction SilentlyContinue

    # Добавяне в PATH
    $currentPath = [System.Environment]::GetEnvironmentVariable("Path", "Machine")
    if ($currentPath -notlike "*$phpDest*") {
        [System.Environment]::SetEnvironmentVariable("Path", "$currentPath;$phpDest", "Machine")
        $env:Path += ";$phpDest"
    }
    Write-OK "PHP $phpVersion е инсталиран в $phpDest"
}

Write-Header "PHP 8.x"

if (Test-Command "php") {
    $phpVer = (cmd /c 'php -r "echo PHP_VERSION;" 2>&1' 2>$null)
    Write-Skip "PHP ($phpVer)"
} else {
    # Опит 1: winget
    Write-Step "Опит за инсталиране на PHP чрез winget ..."
    winget install --id PHP.PHP --silent --accept-package-agreements --accept-source-agreements 2>&1 | Out-Null
    Refresh-Path
    if (Test-Command "php") {
        Write-OK "PHP е инсталиран чрез winget."
    } else {
        # Опит 2: директно изтегляне от windows.php.net
        Write-Warn "winget не успя. Ще се опита директно изтегляне ..."
        Install-PhpManually
        Refresh-Path
    }
}

# Конфигуриране на php.ini – активиране на нужните разширения
Write-Step "Конфигуриране на php.ini ..."
$phpExeCmd = Get-Command "php" -ErrorAction SilentlyContinue
$phpExe = if ($phpExeCmd) { $phpExeCmd.Source } else { $null }
if ($phpExe) {
    $phpDir    = Split-Path $phpExe
    $phpIniDst = Join-Path $phpDir "php.ini"
    $phpIniSrc = Join-Path $phpDir "php.ini-development"

    if (-not (Test-Path $phpIniDst)) {
        Copy-Item $phpIniSrc $phpIniDst -ErrorAction SilentlyContinue
        Write-OK "Създаден php.ini от php.ini-development"
    }

    # Разширения, нужни за курса
    $extensions = @(
        "extension=curl",
        "extension=fileinfo",
        "extension=mbstring",
        "extension=openssl",
        "extension=pdo_pgsql",
        "extension=pdo_sqlite",
        "extension=pdo_mysql",
        "extension=pgsql",
        "extension=sqlite3",
        "extension=sodium"
    )

    $ini = Get-Content $phpIniDst -Raw

    # Задаване на extension_dir (задължително е коментирано в php.ini-development)
    $extDir = Join-Path $phpDir "ext"
    $ini = $ini -replace ';\s*extension_dir\s*=\s*"ext"', "extension_dir = `"$extDir`""
    $ini = $ini -replace ';\s*extension_dir\s*=\s*\./', "extension_dir = `"$extDir`""
    # Ако нито от двете не се случи, добавяме extension_dir начало
    if ($ini -notmatch 'extension_dir\s*=\s*"') {
        $ini = "extension_dir = `"$extDir`"`r`n" + $ini
    }

    foreach ($ext in $extensions) {
        $commented = ";" + $ext
        if ($ini -match [regex]::Escape($commented)) {
            $ini = $ini -replace [regex]::Escape($commented), $ext
        } elseif ($ini -notmatch [regex]::Escape($ext)) {
            $ini += "`r`n$ext"
        }
    }

    # Активиране на display_errors за разработка
    $ini = $ini -replace "display_errors = Off", "display_errors = On"
    $ini = $ini -replace ";date.timezone =", "date.timezone = Europe/Sofia"

    Set-Content $phpIniDst $ini -Encoding UTF8
    Write-OK "php.ini е конфигуриран (разширения, timezone, display_errors)."
} else {
    Write-Warn "php.exe не е намерен в PATH. Конфигурацията може да се наложи ръчно."
}

# ── 4. Composer ──────────────────────────────────────────────────────────────

function Install-ComposerManually {
    # Изтеглейме composer.phar и го обвием в composer.bat
    $phpCmd = Get-Command "php" -ErrorAction SilentlyContinue
    if (-not $phpCmd) {
        Write-Warn "PHP не е намерен в PATH - не може да се инсталира Composer."
        return
    }
    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12

    $composerDir  = "C:\tools\composer"
    $pharPath     = "$composerDir\composer.phar"
    $batPath      = "$composerDir\composer.bat"

    Write-Step "Изтегляне на composer.phar ..."
    try {
        if (-not (Test-Path $composerDir)) { New-Item -ItemType Directory -Path $composerDir | Out-Null }
        Invoke-WebRequest -Uri "https://getcomposer.org/composer-stable.phar" -OutFile $pharPath -UseBasicParsing
    } catch {
        Write-Warn "Изтеглянето не успя: $_"
        return
    }

    # Създаваме composer.bat който да позволява "composer" от cmd/PowerShell
    $batContent = "@echo off`r`n""$($phpCmd.Source)"" ""%~dp0composer.phar"" %*`r`n"
    Set-Content -Path $batPath -Value $batContent -Encoding ASCII

    # Добавяне в PATH
    $currentPath = [System.Environment]::GetEnvironmentVariable("Path", "Machine")
    if ($currentPath -notlike "*$composerDir*") {
        [System.Environment]::SetEnvironmentVariable("Path", "$currentPath;$composerDir", "Machine")
        $env:Path += ";$composerDir"
    }
    Write-OK "Composer е инсталиран в $composerDir"
}

Write-Header "Composer (PHP пакетен мениджър)"
if (Test-Command "composer") {
    # PHP startup warnings отиват на stderr; използваме cmd /c за да избегнем NativeCommandError
    $compVer = (cmd /c "composer --version 2>&1" 2>$null | Select-Object -First 1)
    Write-Skip "Composer ($compVer)"
} else {
    # Опит 1: winget
    Write-Step "Опит за инсталиране на Composer чрез winget ..."
    winget install --id Composer.Composer --silent --accept-package-agreements --accept-source-agreements 2>&1 | Out-Null
    Refresh-Path
    if (Test-Command "composer") {
        Write-OK "Composer е инсталиран чрез winget."
    } else {
        # Опит 2: директно изтегляне от getcomposer.org
        Write-Warn "winget не успя. Ще се опита директно изтегляне ..."
        Install-ComposerManually
        Refresh-Path
    }
}

# ── 5. Java 25 JDK (Eclipse Temurin) ───────────────────────────────────────

Write-Header "Java 25 JDK (Eclipse Temurin)"
if (Test-Command "java") {
    # java -version prints to stderr; capture with cmd /c to get clean stdout string
    $javaVer = (cmd /c "java -version 2>&1" 2>$null | Select-Object -First 1)
    if ($javaVer -match '\b25\b') {
        Write-Skip "Java ($javaVer)"
    } else {
        Write-Step "Открита е различна версия ($javaVer) – инсталиране на Java 25 ..."
        Install-WingetPackage -Id "EclipseAdoptium.Temurin.25.JDK" -Name "Java 25 JDK (Temurin)"
        Refresh-Path
    }
} else {
    Install-WingetPackage -Id "EclipseAdoptium.Temurin.25.JDK" -Name "Java 25 JDK (Temurin)"
    Refresh-Path
}

# Проверка на JAVA_HOME
if (-not $env:JAVA_HOME) {
    Write-Step "Задаване на JAVA_HOME ..."

    # Първо: питаме директно JVM-а за реалния път (работи с всеки vendor-и и shim-ове)
    $javaCmd = Get-Command "java" -ErrorAction SilentlyContinue
    if ($javaCmd) {
        # java -XshowSettings:properties извежда "java.home = <path>" на stderr
        $javaProps = cmd /c "`"$($javaCmd.Source)`" -XshowSettings:properties -version 2>&1"
        $javaHomeLine = $javaProps | Where-Object { $_ -match '\bjava\.home\s*=' } | Select-Object -First 1
        if ($javaHomeLine -match 'java\.home\s*=\s*(.+)') {
            $javaHome = $Matches[1].Trim()
            # Ако пътят сам посочва на \jre или \conf, отидем едно ниво нагоре за JDK root-а
            if ($javaHome -match '\\jre$' -or -not (Test-Path "$javaHome\bin\javac.exe")) {
                $parent = Split-Path $javaHome -Parent
                if (Test-Path "$parent\bin\javac.exe") { $javaHome = $parent }
            }
            [System.Environment]::SetEnvironmentVariable("JAVA_HOME", $javaHome, "Machine")
            $env:JAVA_HOME = $javaHome
            Write-OK "JAVA_HOME = $javaHome"
        } else {
            Write-Warn "Не може да се парсне java.home. Задайте JAVA_HOME ръчно."
        }
    } else {
        # Запасни патеки: известни директории за JDK
        $jdkCandidates = @(
            "C:\Program Files\Eclipse Adoptium",
            "C:\Program Files\Microsoft",
            "C:\Program Files\Java",
            "C:\Program Files\OpenJDK"
        ) | Where-Object { Test-Path $_ } | ForEach-Object {
            Get-ChildItem $_ -Filter "jdk*" -ErrorAction SilentlyContinue
        } | Sort-Object Name -Descending | Select-Object -First 1

        if ($jdkCandidates) {
            [System.Environment]::SetEnvironmentVariable("JAVA_HOME", $jdkCandidates.FullName, "Machine")
            $env:JAVA_HOME = $jdkCandidates.FullName
            Write-OK "JAVA_HOME = $($jdkCandidates.FullName)"
        } else {
            Write-Warn "JAVA_HOME не може да се зададе автоматично. Задайте го ръчно след инсталацията."
        }
    }
} else {
    Write-Skip "JAVA_HOME ($env:JAVA_HOME)"
}

# ── 6. Apache Maven ──────────────────────────────────────────────────────────

function Install-MavenManually {
    $mavenDir = "C:\tools\maven"
    [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12

    # Динамично откриване на най-новата версия от Apache CDN
    Write-Step "Откриване на най-новата версия на Maven ..."
    $mavenVersion = $null
    try {
        $distPage = Invoke-WebRequest -Uri "https://dlcdn.apache.org/maven/maven-3/" -UseBasicParsing
        $mavenVersion = ($distPage.Links.href |
            Where-Object { $_ -match '^\d+\.\d+\.\d+/$' } |
            ForEach-Object { [version]($_.TrimEnd('/')) } |
            Sort-Object -Descending |
            Select-Object -First 1).ToString()
    } catch { }

    if (-not $mavenVersion) {
        # Запасен номер ако CDN е недостъпен
        $mavenVersion = "3.9.6"
        Write-Warn "Не може да се определи най-новата версия; използва запасна $mavenVersion"
    }

    $zipUrl  = "https://dlcdn.apache.org/maven/maven-3/$mavenVersion/binaries/apache-maven-$mavenVersion-bin.zip"
    $zipPath = "$env:TEMP\apache-maven-$mavenVersion-bin.zip"
    $mavenBin = "$mavenDir\apache-maven-$mavenVersion\bin"

    Write-Step "Изтегляне на Apache Maven $mavenVersion ..."
    try {
        Invoke-WebRequest -Uri $zipUrl -OutFile $zipPath -UseBasicParsing
    } catch {
        Write-Warn "Изтеглянето не успя: $_"
        Write-Warn "Изтеглете ръчно от: https://maven.apache.org/download.cgi"
        return
    }

    Write-Step "Разархивиране в $mavenDir ..."
    if (-not (Test-Path $mavenDir)) { New-Item -ItemType Directory -Path $mavenDir | Out-Null }
    Expand-Archive -Path $zipPath -DestinationPath $mavenDir -Force
    Remove-Item $zipPath -ErrorAction SilentlyContinue

    # Добавяне на bin в системния PATH
    $currentPath = [System.Environment]::GetEnvironmentVariable("Path", "Machine")
    if ($currentPath -notlike "*$mavenBin*") {
        [System.Environment]::SetEnvironmentVariable("Path", "$currentPath;$mavenBin", "Machine")
        $env:Path += ";$mavenBin"
    }
    Write-OK "Apache Maven $mavenVersion е инсталиран в $mavenDir"
}

Write-Header "Apache Maven (build инструмент за Java)"
if (Test-Command "mvn") {
    # mvn пише на stderr при грешка в JAVA_HOME; използваме cmd /c за безопасно захващане
    $mvnVer = (cmd /c "mvn --version 2>&1" 2>$null | Select-Object -First 1)
    Write-Skip "Maven ($mvnVer)"
} else {
    # Опит 1: winget
    Write-Step "Опит за инсталиране на Maven чрез winget ..."
    winget install --id Apache.Maven --silent --accept-package-agreements --accept-source-agreements 2>&1 | Out-Null
    Refresh-Path
    if (Test-Command "mvn") {
        Write-OK "Apache Maven е инсталиран чрез winget."
    } else {
        # Опит 2: директно изтегляне от Apache
        Write-Warn "winget не успя. Ще се опита директно изтегляне от Apache CDN ..."
        Install-MavenManually
        Refresh-Path
    }
}

# ── 7. PostgreSQL ─────────────────────────────────────────────────────────────

Write-Header "PostgreSQL 16"
$pgService = Get-Service -Name "postgresql*" -ErrorAction SilentlyContinue
if ($pgService) {
    Write-Skip "PostgreSQL ($($pgService.Name))"
} else {
    Write-Step "Инсталиране на PostgreSQL 16 ..."
    Write-Host ""
    Write-Host "  +------------------------------------------------------+" -ForegroundColor Yellow
    Write-Host "  |  По време на инсталацията въведете:                  |" -ForegroundColor Yellow
    Write-Host "  |    Парола за 'postgres': postgres                    |" -ForegroundColor Yellow
    Write-Host "  |    Port                : 5432 (по подразбиране)      |" -ForegroundColor Yellow
    Write-Host "  |  ВНИМАНИЕ: само за разработка, не за production     |" -ForegroundColor Yellow
    Write-Host "  +------------------------------------------------------+" -ForegroundColor Yellow
    Write-Host ""
    # EDB инсталаторът поддържа --mode unattended за автоматична инсталация
    Install-WingetPackage -Id "PostgreSQL.PostgreSQL.16" -Name "PostgreSQL 16" -ExtraArgs @(
        "--override",
        "--mode unattended --superpassword postgres --serverport 5432 --servicename postgresql-x64-16"
    )
    # Ако автоматичната инсталация не сработи, winget ще стартира интерактивния инсталатор.

    Refresh-Path
    $env:PGPASSWORD = "postgres"
    $pgBin = @(
        "C:\Program Files\PostgreSQL\16\bin",
        "C:\Program Files\PostgreSQL\15\bin"
    ) | Where-Object { Test-Path $_ } | Select-Object -First 1

    if ($pgBin) {
        $currentPath = [System.Environment]::GetEnvironmentVariable("Path", "Machine")
        if ($currentPath -notlike "*$pgBin*") {
            [System.Environment]::SetEnvironmentVariable("Path", "$currentPath;$pgBin", "Machine")
            $env:Path += ";$pgBin"
        }
        Write-OK "PostgreSQL bin добавен в PATH: $pgBin"
    }
}

# ── 8. pgAdmin 4 ──────────────────────────────────────────────────────────────

Write-Header "pgAdmin 4 (GUI за PostgreSQL)"
# Проверяваме registry (HKLM + HKCU, 64-bit + 32-bit) и файловата система.
# pgAdmin 4 се инсталира per-user в HKCU когато се инсталира без администраторски права.
$pgAdminFound = $false
$uninstallPaths = @(
    "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall",
    "HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall",
    "HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\Uninstall",
    "HKCU:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall"
)
foreach ($regPath in $uninstallPaths) {
    if ($pgAdminFound) { break }
    Get-ChildItem $regPath -ErrorAction SilentlyContinue | ForEach-Object {
        try {
            $displayName = $_.GetValue("DisplayName")
            if ($displayName -like "pgAdmin*") { $pgAdminFound = $true }
        } catch { }
    }
}
# Запасна проверка по файловата система – pgAdmin4.exe в известни директории
if (-not $pgAdminFound) {
    $pgAdminExePaths = @(
        "C:\Program Files\pgAdmin 4\runtime\pgAdmin4.exe",
        "C:\Program Files (x86)\pgAdmin 4\runtime\pgAdmin4.exe",
        "$env:LOCALAPPDATA\Programs\pgAdmin 4\runtime\pgAdmin4.exe",
        "$env:ProgramFiles\pgAdmin 4\v8\runtime\pgAdmin4.exe",
        "$env:ProgramFiles\pgAdmin 4\v7\runtime\pgAdmin4.exe"
    )
    if ($pgAdminExePaths | Where-Object { Test-Path $_ }) { $pgAdminFound = $true }
}
if ($pgAdminFound) {
    Write-Skip "pgAdmin 4"
} else {
    # Опит 1: winget
    Write-Step "Опит за инсталиране на pgAdmin 4 чрез winget ..."
    winget install --id PostgreSQL.pgAdmin --silent --accept-package-agreements --accept-source-agreements 2>&1 | Out-Null
    Refresh-Path

    # Проверяваме наново в регистъра (HKLM + HKCU) и файловата система
    $pgAdminFound = $false
    foreach ($regPath in $uninstallPaths) {
        if ($pgAdminFound) { break }
        Get-ChildItem $regPath -ErrorAction SilentlyContinue | ForEach-Object {
            try { if ($_.GetValue("DisplayName") -like "pgAdmin*") { $pgAdminFound = $true } } catch { }
        }
    }
    if (-not $pgAdminFound) {
        if ($pgAdminExePaths | Where-Object { Test-Path $_ }) { $pgAdminFound = $true }
    }

    if ($pgAdminFound) {
        Write-OK "pgAdmin 4 е инсталиран чрез winget."
    } else {
        # Опит 2: PostgreSQL FTP CDN (надежден източник, не изисква JS рендериране)
        Write-Warn "winget не успя. Ще се опита директно изтегляне от PostgreSQL FTP ..."
        [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12

        $pgAdminVersion = $null
        $pgAdminUrl     = $null
        try {
            # Листингът на FTP е обикновен HTML индекс с директории vX.Y/
            $ftpIndex = Invoke-WebRequest -Uri "https://ftp.postgresql.org/pub/pgadmin/pgadmin4/" -UseBasicParsing
            $pgAdminVersion = ($ftpIndex.Links.href |
                Where-Object { $_ -match '^v[\d.]+/$' } |
                ForEach-Object { [version]($_.Trim('/').TrimStart('v')) } |
                Sort-Object -Descending |
                Select-Object -First 1).ToString()
        } catch { }

        if ($pgAdminVersion) {
            $pgAdminUrl = "https://ftp.postgresql.org/pub/pgadmin/pgadmin4/v$pgAdminVersion/windows/pgadmin4-$pgAdminVersion-x64.exe"
        }

        if ($pgAdminUrl) {
            $installerPath = "$env:TEMP\pgadmin4-$pgAdminVersion-x64.exe"
            Write-Step "Изтегляне на pgAdmin 4 v$pgAdminVersion ..."
            try {
                Invoke-WebRequest -Uri $pgAdminUrl -OutFile $installerPath -UseBasicParsing
                Write-Step "Инсталиране (тихо) ..."
                Start-Process -FilePath $installerPath -ArgumentList "/VERYSILENT /NORESTART /SUPPRESSMSGBOXES" -Wait
                Remove-Item $installerPath -ErrorAction SilentlyContinue
                Write-OK "pgAdmin 4 v$pgAdminVersion е инсталиран."
            } catch {
                Write-Warn "Изтеглянето не успя: $_"
                Write-Warn "Изтеглете ръчно от: https://www.pgadmin.org/download/pgadmin-4-windows/"
            }
        } else {
            Write-Warn "Не може да се открие URL за pgAdmin. Изтеглете ръчно от: https://www.pgadmin.org/download/pgadmin-4-windows/"
        }
    }
}

# ── 9. Visual Studio Code ─────────────────────────────────────────────────────

Write-Header "Visual Studio Code"
if (Test-Command "code") {
    Write-Skip "VS Code"
} else {
    Install-WingetPackage -Id "Microsoft.VisualStudioCode" -Name "Visual Studio Code" -ExtraArgs @(
        "--override", "/VERYSILENT /NORESTART /MERGETASKS=!runcode,addcontextmenufiles,addcontextmenufolders,associatewithfiles,addtopath"
    )
    Refresh-Path
}

# VS Code разширения
Write-Step "Инсталиране на VS Code разширения ..."
$vscodeExtensions = @(
    # PHP
    @{ Id = "bmewburn.vscode-intelephense-client"; Name = "PHP Intelephense (autocomplete, refactoring)" }
    @{ Id = "xdebug.php-debug";                   Name = "PHP Debug (Xdebug)" }
    @{ Id = "neilbrayfield.php-docblocker";        Name = "PHP DocBlocker" }
    # Java & Spring Boot
    @{ Id = "vscjava.vscode-java-pack";            Name = "Extension Pack for Java" }
    @{ Id = "vmware.vscode-spring-boot";           Name = "Spring Boot Tools" }
    @{ Id = "vscjava.vscode-spring-initializr";    Name = "Spring Initializr Java Support" }
    @{ Id = "vscjava.vscode-spring-boot-dashboard";Name = "Spring Boot Dashboard" }
    # Бази данни
    @{ Id = "mtxr.sqltools";                       Name = "SQLTools (универсален SQL клиент)" }
    @{ Id = "mtxr.sqltools-driver-pg";             Name = "SQLTools PostgreSQL Driver" }
    # REST & HTTP
    @{ Id = "humao.rest-client";                   Name = "REST Client (тестване на API)" }
    # Общи инструменти
    @{ Id = "eamodio.gitlens";                     Name = "GitLens" }
    @{ Id = "esbenp.prettier-vscode";              Name = "Prettier (форматиране)" }
    @{ Id = "ms-vscode.live-server";               Name = "Live Server" }
    @{ Id = "PKief.material-icon-theme";           Name = "Material Icon Theme" }
)

$codeCmd = Get-Command "code" -ErrorAction SilentlyContinue
if ($codeCmd) {
    foreach ($ext in $vscodeExtensions) {
        Write-Step "Разширение: $($ext.Name) ..."
        code --install-extension $ext.Id --force 2>&1 | Out-Null
        Write-OK $ext.Name
    }
} else {
    Write-Warn "VS Code не е в PATH. Разширенията ще трябва да се инсталират ръчно след рестартиране."
}

# ── 10. IntelliJ IDEA Community Edition ──────────────────────────────────────

Write-Header "IntelliJ IDEA Community Edition"
$ideaInstalled = Test-Path "C:\Program Files\JetBrains" -ErrorAction SilentlyContinue
if ($ideaInstalled) {
    Write-Skip "IntelliJ IDEA Community"
} else {
    Install-WingetPackage -Id "JetBrains.IntelliJIDEA.Community" -Name "IntelliJ IDEA Community Edition"
}

# ── 11. Проверка на инсталациите ─────────────────────────────────────────────

Write-Header "Проверка на инсталираните компоненти"
Refresh-Path

$checks = @(
    @{ Cmd = "git";      Label = "Git" }
    @{ Cmd = "php";      Label = "PHP" }
    @{ Cmd = "composer"; Label = "Composer" }
    @{ Cmd = "java";     Label = "Java JDK" }
    @{ Cmd = "mvn";      Label = "Maven" }
    @{ Cmd = "psql";     Label = "PostgreSQL (psql)" }
)

$allOK = $true
foreach ($c in $checks) {
    if (Test-Command $c.Cmd) {
        # Използваме cmd /c за всички външни процеси които пишат stderr
        # (или могат да поменят Console.OutputCP) - java, mvn, psql, composer
        $ver = switch ($c.Cmd) {
            'java'     { cmd /c 'java -version 2>&1'     | Select-Object -First 1 }
            'mvn'      { cmd /c 'mvn --version 2>&1'     | Select-Object -First 1 }
            'psql'     { cmd /c 'psql --version 2>&1'    | Select-Object -First 1 }
            'composer' { cmd /c 'composer --version 2>&1'| Select-Object -First 1 }
            default    { & $c.Cmd --version 2>$null      | Select-Object -First 1 }
        }
        Write-OK "$($c.Label): $ver"
    } else {
        Write-Warn "$($c.Label): НЕ е намерен в PATH - може да е нужен рестарт или ръчна проверка."
        $allOK = $false
    }
}

# ── 12. Създаване на база данни за курса ─────────────────────────────────────

Write-Header "Настройване на PostgreSQL база данни за курса"
$psqlCmd = Get-Command "psql" -ErrorAction SilentlyContinue
if ($psqlCmd) {
    try {
        Write-Step "Създаване на потребител 'webdev' и база данни 'webdev_db' ..."
        $env:PGPASSWORD = "postgres"

        $pgConn = "-U postgres -h localhost -p 5432"

    # 1. Потребител - пишем SQL в temp файл; cmd /c изолира encoding-а
    $sqlUser = "$env:TEMP\webdev_user.sql"
    [System.IO.File]::WriteAllText($sqlUser,
        "DO `$`$ BEGIN IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'webdev') THEN CREATE USER webdev WITH PASSWORD 'webdev123'; END IF; END `$`$;",
        [System.Text.Encoding]::ASCII)

    Write-Step "Проверка/създаване на потребител 'webdev' ..."
    $out = cmd /c "psql $pgConn -f `"$sqlUser`" 2>&1"
    $out | ForEach-Object { Write-Host "  $_" -ForegroundColor Gray }
    Remove-Item $sqlUser -ErrorAction SilentlyContinue

    # 2. База данни - CREATE DATABASE не може в DO block
    Write-Step "Проверка/създаване на база данни 'webdev_db' ..."
    $dbExists = cmd /c "psql $pgConn -tAc `"SELECT 1 FROM pg_database WHERE datname='webdev_db';`" 2>NUL"
    # $dbExists е $null или '' когато базата НЕ съществува (празен result set)
    $dbExistsStr = if ($dbExists) { ($dbExists | Select-Object -Last 1).ToString().Trim() } else { '' }
    if ($dbExistsStr -ne '1') {
        $out2 = cmd /c "psql $pgConn -c `"CREATE DATABASE webdev_db OWNER webdev;`" 2>&1"
        $out2 | ForEach-Object { Write-Host "  $_" -ForegroundColor Gray }
    } else {
        Write-Host "  Базата данни 'webdev_db' вече съществува." -ForegroundColor Gray
    }

        Write-OK "База данни 'webdev_db' и потребител 'webdev' (парола: webdev123) са готови."
    } catch {
        Write-Warn "Грешка при настройка на базата данни: $_"
    } finally {
        # Възстановяваме винаги при всички случаи: успех, грешка, skip
        [Console]::OutputEncoding = $script:savedOutputEncoding
        [Console]::InputEncoding  = $script:savedInputEncoding
        $env:PGPASSWORD = $null
    }
} else {
    Write-Warn "psql не е намерен. Базата данни ще трябва да се създаде ръчно след рестарт."
}

# ── 13. Финален отчет ─────────────────────────────────────────────────────────

Write-Header "Инсталацията е завършена"

Write-Host ""
Write-Host "  ОБОБЩЕНИЕ НА ДОСТЪП ДО БАЗАТА ДАННИ:" -ForegroundColor Cyan
Write-Host "  ------------------------------------------" -ForegroundColor Cyan
Write-Host "  Host    : localhost"
Write-Host "  Port    : 5432"
Write-Host "  Database: webdev_db"
Write-Host "  User    : webdev"
Write-Host "  Password: webdev123"
Write-Host "  (admin) : postgres / postgres  [САМО ЗА РАЗРАБОТКА]"
Write-Host ""
Write-Host "  СЛЕДВАЩИ СТЪПКИ:" -ForegroundColor Cyan
Write-Host "  ------------------------------------------" -ForegroundColor Cyan
Write-Host "  1. РЕСТАРТИРАЙТЕ компютъра за зареждане на PATH промените."
Write-Host "  2. Отворете VS Code и IntelliJ IDEA – ще ги конфигурирате при"
Write-Host "     първото стартиране."
Write-Host "  3. За PHP проекти: стартирайте вградения сървър с:"
Write-Host "       php -S localhost:8000"
Write-Host "  4. За Spring Boot: използвайте IntelliJ IDEA или VS Code."
Write-Host "  5. Управление на PostgreSQL:"
Write-Host "       pgAdmin 4  (GUI) – от Start менюто"
Write-Host "       psql -U webdev -d webdev_db  (команден ред)"
Write-Host ""

if (-not $allOK) {
    Write-Warn "Някои компоненти не са намерени в PATH. Рестартирайте и проверете отново."
}

Write-Host "  Документация: docs/  |  Примери: examples/" -ForegroundColor Cyan
Write-Host ""
