#!/usr/bin/env bash
# =============================================================================
# Инсталационен скрипт за "Сървърно уеб програмиране"
# Ubuntu 22.04 / Ubuntu 24.04
# =============================================================================
# Инсталира:
#   • Git
#   • PHP 8.x + Composer
#   • Java 25 JDK (Eclipse Temurin) + Maven
#   • PostgreSQL 16 + pgAdmin 4
#   • Visual Studio Code + разширения за PHP, Java, Spring Boot, SQL
#   • IntelliJ IDEA Community Edition
# =============================================================================
# Стартиране:
#   chmod +x install-dev-environment.sh
#   sudo ./install-dev-environment.sh
# =============================================================================

set -euo pipefail

# ── Помощни функции ─────────────────────────────────────────────────────────

write_header() {
    echo ""
    echo -e "\e[36m============================================================\e[0m"
    echo -e "\e[36m  $1\e[0m"
    echo -e "\e[36m============================================================\e[0m"
}

write_step() {
    echo -e "\e[33m  >> $1\e[0m"
}

write_ok() {
    echo -e "\e[32m  [OK] $1\e[0m"
}

write_skip() {
    echo -e "\e[90m  [--] $1 (вече е инсталирано)\e[0m"
}

write_warn() {
    echo -e "\e[35m  [!]  $1\e[0m"
}

command_exists() {
    command -v "$1" &>/dev/null
}

# ── 0. Проверка на root права ────────────────────────────────────────────────

write_header "Проверка на права"

if [[ $EUID -ne 0 ]]; then
    echo -e "\e[31m  ГРЕШКА: Скриптът трябва да се стартира с sudo!\e[0m"
    echo -e "\e[31m  sudo ./install-dev-environment.sh\e[0m"
    echo ""
    exit 1
fi

# Запазваме реалния потребител (за команди, които не трябва да се пускат като root)
REAL_USER="${SUDO_USER:-$USER}"
REAL_HOME=$(eval echo "~$REAL_USER")
write_ok "Скриптът работи с root права (реален потребител: $REAL_USER)."

# ── 1. Обновяване на пакетния индекс ────────────────────────────────────────

write_header "Обновяване на apt индекс"
write_step "apt-get update ..."
apt-get update -qq
write_ok "apt индексът е обновен."

# ── 2. Базови инструменти ────────────────────────────────────────────────────

write_header "Базови инструменти"
write_step "Инсталиране на curl, wget, gnupg, ca-certificates, unzip, lsb-release ..."
apt-get install -y -qq curl wget gnupg ca-certificates unzip lsb-release apt-transport-https software-properties-common
write_ok "Базовите инструменти са инсталирани."

# ── 3. Git ────────────────────────────────────────────────────────────────────

write_header "Git"
if command_exists git; then
    write_skip "Git ($(git --version))"
else
    write_step "Инсталиране на Git ..."
    apt-get install -y -qq git
    write_ok "Git $(git --version) е инсталиран."
fi

# ── 4. PHP 8.x ────────────────────────────────────────────────────────────────

write_header "PHP 8.x"

# Добавяме ondrej/php PPA за най-новия PHP 8.x
if ! grep -q "ondrej/php" /etc/apt/sources.list.d/*.list 2>/dev/null &&
   ! grep -q "ondrej/php" /etc/apt/sources.list 2>/dev/null; then
    write_step "Добавяне на ondrej/php PPA ..."
    add-apt-repository -y ppa:ondrej/php
    apt-get update -qq
    write_ok "PPA ondrej/php е добавен."
fi

# Определяме най-новата налична PHP 8.x версия
PHP_VER=$(apt-cache search --names-only '^php8\.' 2>/dev/null \
    | grep -oP 'php8\.\d+(?=\s)' \
    | sort -t. -k2 -rn \
    | head -1)
PHP_VER=${PHP_VER:-php8.3}

if command_exists php; then
    write_skip "PHP ($(php -r 'echo PHP_VERSION;' 2>/dev/null))"
else
    write_step "Инсталиране на $PHP_VER и разширения ..."
    apt-get install -y -qq \
        "$PHP_VER" \
        "$PHP_VER-cli" \
        "$PHP_VER-fpm" \
        "$PHP_VER-curl" \
        "$PHP_VER-mbstring" \
        "$PHP_VER-xml" \
        "$PHP_VER-zip" \
        "$PHP_VER-pgsql" \
        "$PHP_VER-pdo-pgsql" \
        "$PHP_VER-sqlite3" \
        "$PHP_VER-mysql" \
        "$PHP_VER-sodium" \
        "$PHP_VER-intl" \
        "$PHP_VER-bcmath"
    write_ok "PHP $(php -r 'echo PHP_VERSION;' 2>/dev/null) е инсталиран."
fi

# Конфигуриране на php.ini – активиране на подходящи настройки за разработка
PHP_INI=$(php --ini 2>/dev/null | grep -oP '(?<=Loaded Configuration File:\s{1,20})/.+' | head -1)
if [[ -f "$PHP_INI" ]]; then
    write_step "Конфигуриране на $(basename "$PHP_INI") ..."
    sed -i 's/^;\?date\.timezone\s*=.*/date.timezone = Europe\/Sofia/'     "$PHP_INI"
    sed -i 's/^display_errors\s*=\s*Off/display_errors = On/'              "$PHP_INI"
    sed -i 's/^display_startup_errors\s*=\s*Off/display_startup_errors = On/' "$PHP_INI"
    sed -i 's/^error_reporting\s*=.*/error_reporting = E_ALL/'             "$PHP_INI"
    write_ok "php.ini е конфигуриран (timezone, display_errors, error_reporting)."
else
    write_warn "php.ini не е намерен – конфигурирайте ръчно."
fi

# ── 5. Xdebug (PHP дебъгер за VS Code) ──────────────────────────────────────

write_header "Xdebug (PHP дебъгер за VS Code)"

if php -m 2>/dev/null | grep -qi xdebug; then
    write_skip "Xdebug (вече е зареден)"
else
    XDEBUG_PKG="${PHP_VER}-xdebug"
    if apt-cache show "$XDEBUG_PKG" &>/dev/null; then
        write_step "Инсталиране на $XDEBUG_PKG ..."
        apt-get install -y -qq "$XDEBUG_PKG"
    else
        write_step "Инсталиране на Xdebug чрез PECL ..."
        apt-get install -y -qq "${PHP_VER}-dev" make
        pecl install xdebug 2>/dev/null || true
    fi

    # Добавяме конфигурация в отделен .ini файл
    XDEBUG_INI_DIR=$(php --ini 2>/dev/null | grep -oP '(?<=Scan for additional .ini files in:\s{1,20})/.+' | head -1)
    if [[ -n "$XDEBUG_INI_DIR" && -d "$XDEBUG_INI_DIR" ]]; then
        XDEBUG_CONF="$XDEBUG_INI_DIR/99-xdebug.ini"
        if [[ ! -f "$XDEBUG_CONF" ]]; then
            cat > "$XDEBUG_CONF" <<'XDINI'
[xdebug]
zend_extension=xdebug
xdebug.mode=debug
xdebug.start_with_request=yes
xdebug.client_host=127.0.0.1
xdebug.client_port=9003
xdebug.log_level=0
XDINI
            write_ok "Xdebug конфигуриран в $XDEBUG_CONF (mode=debug, порт 9003)."
        else
            write_skip "Xdebug конфигурация ($XDEBUG_CONF)"
        fi
    fi
fi

# ── 6. Composer ───────────────────────────────────────────────────────────────

write_header "Composer (PHP пакетен мениджър)"

if command_exists composer; then
    write_skip "Composer ($(su - "$REAL_USER" -c 'composer --version 2>/dev/null' | head -1))"
else
    write_step "Изтегляне и инсталиране на Composer ..."
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"

    if [[ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]]; then
        rm -f composer-setup.php
        write_warn "Невалиден контролен код на Composer инсталатора! Прекратяване на инсталацията на Composer."
    else
        php composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
        rm -f composer-setup.php
        write_ok "Composer $(composer --version 2>/dev/null | head -1) е инсталиран."
    fi
fi

# ── 7. Java 25 JDK (Eclipse Temurin) ────────────────────────────────────────

write_header "Java 25 JDK (Eclipse Temurin)"

# Добавяме Adoptium APT хранилище
if [[ ! -f /etc/apt/sources.list.d/adoptium.list ]]; then
    write_step "Добавяне на Eclipse Adoptium APT хранилище ..."
    CODENAME=$(lsb_release -cs)
    wget -qO - https://packages.adoptium.net/artifactory/api/gpg/key/public \
        | gpg --dearmor \
        | tee /etc/apt/trusted.gpg.d/adoptium.gpg > /dev/null
    echo "deb https://packages.adoptium.net/artifactory/deb $CODENAME main" \
        > /etc/apt/sources.list.d/adoptium.list
    apt-get update -qq
    write_ok "Adoptium хранилището е добавено."
fi

JAVA_INSTALLED_VER=""
if command_exists java; then
    JAVA_INSTALLED_VER=$(java -version 2>&1 | head -1)
fi

if echo "$JAVA_INSTALLED_VER" | grep -q '"25'; then
    write_skip "Java ($JAVA_INSTALLED_VER)"
else
    if [[ -n "$JAVA_INSTALLED_VER" ]]; then
        write_step "Открита е различна версия ($JAVA_INSTALLED_VER) – инсталиране на Java 25 ..."
    else
        write_step "Инсталиране на temurin-25-jdk ..."
    fi
    apt-get install -y -qq temurin-25-jdk
    write_ok "Java $(java -version 2>&1 | head -1) е инсталиран."
fi

# Задаване на JAVA_HOME
if [[ -z "${JAVA_HOME:-}" ]]; then
    DETECTED_JAVA_HOME=$(update-alternatives --query java 2>/dev/null \
        | grep -oP '(?<=Value: )/.+' \
        | sed 's|/bin/java||' \
        | head -1)
    if [[ -n "$DETECTED_JAVA_HOME" ]]; then
        write_step "Задаване на JAVA_HOME = $DETECTED_JAVA_HOME ..."
        echo "JAVA_HOME=$DETECTED_JAVA_HOME" >> /etc/environment
        export JAVA_HOME="$DETECTED_JAVA_HOME"
        write_ok "JAVA_HOME = $JAVA_HOME"
    else
        write_warn "Не може да се зададе JAVA_HOME автоматично. Задайте го ръчно в /etc/environment."
    fi
else
    write_skip "JAVA_HOME ($JAVA_HOME)"
fi

# ── 8. Apache Maven ───────────────────────────────────────────────────────────

write_header "Apache Maven (build инструмент за Java)"

if command_exists mvn; then
    write_skip "Maven ($(mvn --version 2>/dev/null | head -1))"
else
    # Динамично определяме най-новата версия от Apache CDN
    write_step "Определяне на най-новата версия на Maven ..."
    MAVEN_VERSION=$(curl -fsSL https://dlcdn.apache.org/maven/maven-3/ 2>/dev/null \
        | grep -oP '(?<=href=")[\d]+\.[\d]+\.[\d]+(?=/)' \
        | sort -V | tail -1)
    MAVEN_VERSION=${MAVEN_VERSION:-3.9.9}

    MAVEN_URL="https://dlcdn.apache.org/maven/maven-3/${MAVEN_VERSION}/binaries/apache-maven-${MAVEN_VERSION}-bin.tar.gz"
    MAVEN_DEST="/opt/maven"

    write_step "Изтегляне на Apache Maven ${MAVEN_VERSION} ..."
    if wget -q "$MAVEN_URL" -O /tmp/apache-maven.tar.gz; then
        mkdir -p "$MAVEN_DEST"
        tar -xzf /tmp/apache-maven.tar.gz -C "$MAVEN_DEST" --strip-components=1
        rm -f /tmp/apache-maven.tar.gz

        # Добавяме maven в PATH чрез /etc/profile.d/
        cat > /etc/profile.d/maven.sh <<MVNSH
export MAVEN_HOME=/opt/maven
export PATH=\$MAVEN_HOME/bin:\$PATH
MVNSH
        export PATH="/opt/maven/bin:$PATH"
        write_ok "Apache Maven ${MAVEN_VERSION} е инсталиран в $MAVEN_DEST."
    else
        write_warn "Изтеглянето не успя. Изтеглете ръчно от: https://maven.apache.org/download.cgi"
    fi
fi

# ── 9. PostgreSQL 16 ─────────────────────────────────────────────────────────

write_header "PostgreSQL 16"

if command_exists psql && psql --version 2>/dev/null | grep -q ' 16'; then
    write_skip "PostgreSQL ($(psql --version))"
else
    # Добавяме официалното PostgreSQL APT хранилище
    if [[ ! -f /etc/apt/sources.list.d/pgdg.list ]]; then
        write_step "Добавяне на PostgreSQL APT хранилище ..."
        DISTRO_CODENAME=$(lsb_release -cs)
        curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
            | gpg --dearmor \
            | tee /etc/apt/trusted.gpg.d/postgresql.gpg > /dev/null
        echo "deb https://apt.postgresql.org/pub/repos/apt ${DISTRO_CODENAME}-pgdg main" \
            > /etc/apt/sources.list.d/pgdg.list
        apt-get update -qq
        write_ok "PostgreSQL APT хранилището е добавено."
    fi

    write_step "Инсталиране на postgresql-16 ..."

    echo ""
    echo -e "\e[33m  +------------------------------------------------------+\e[0m"
    echo -e "\e[33m  |  PostgreSQL ще се инсталира с парола 'postgres'       |\e[0m"
    echo -e "\e[33m  |  за superuser-а 'postgres'.                           |\e[0m"
    echo -e "\e[33m  |  ВНИМАНИЕ: само за разработка, не за production!      |\e[0m"
    echo -e "\e[33m  +------------------------------------------------------+\e[0m"
    echo ""

    apt-get install -y -qq postgresql-16

    # Стартираме услугата ако не работи
    systemctl enable postgresql 2>/dev/null || true
    systemctl start  postgresql 2>/dev/null || true

    # Задаваме парола на postgres superuser-а
    su - postgres -c "psql -c \"ALTER USER postgres WITH PASSWORD 'postgres';\"" 2>/dev/null || true
    write_ok "PostgreSQL 16 е инсталиран и работи."
fi

# ── 10. pgAdmin 4 ─────────────────────────────────────────────────────────────

write_header "pgAdmin 4 (GUI за PostgreSQL)"

if command_exists pgadmin4 || [[ -d /usr/pgadmin4 ]]; then
    write_skip "pgAdmin 4"
else
    write_step "Добавяне на pgAdmin 4 APT хранилище ..."
    DISTRO_CODENAME=$(lsb_release -cs)

    curl -fsSL https://www.pgadmin.org/static/packages_pgadmin_org.pub \
        | gpg --dearmor \
        | tee /etc/apt/trusted.gpg.d/pgadmin.gpg > /dev/null

    echo "deb https://ftp.postgresql.org/pub/pgadmin/pgadmin4/apt/${DISTRO_CODENAME} pgadmin4 main" \
        > /etc/apt/sources.list.d/pgadmin4.list

    apt-get update -qq

    write_step "Инсталиране на pgAdmin 4 (desktop режим) ..."
    if apt-get install -y -qq pgadmin4-desktop 2>/dev/null; then
        write_ok "pgAdmin 4 е инсталиран."
    else
        write_warn "pgAdmin 4 не може да се инсталира от APT. Проверете: https://www.pgadmin.org/download/pgadmin-4-apt/"
    fi
fi

# ── 11. Visual Studio Code ───────────────────────────────────────────────────

write_header "Visual Studio Code"

if command_exists code; then
    write_skip "VS Code ($(code --version 2>/dev/null | head -1))"
else
    write_step "Добавяне на Microsoft APT хранилище за VS Code ..."
    curl -fsSL https://packages.microsoft.com/keys/microsoft.asc \
        | gpg --dearmor \
        | tee /etc/apt/trusted.gpg.d/microsoft.gpg > /dev/null
    echo "deb [arch=amd64] https://packages.microsoft.com/repos/code stable main" \
        > /etc/apt/sources.list.d/vscode.list
    apt-get update -qq
    apt-get install -y -qq code
    write_ok "VS Code $(code --version 2>/dev/null | head -1) е инсталиран."
fi

# VS Code разширения (инсталираме като реалния потребител, не като root)
write_step "Инсталиране на VS Code разширения ..."

VSCODE_EXTENSIONS=(
    "bmewburn.vscode-intelephense-client"    # PHP Intelephense
    "xdebug.php-debug"                       # PHP Debug (Xdebug)
    "neilbrayfield.php-docblocker"           # PHP DocBlocker
    "vscjava.vscode-java-pack"               # Extension Pack for Java
    "vmware.vscode-spring-boot"              # Spring Boot Tools
    "vscjava.vscode-spring-initializr"       # Spring Initializr
    "vscjava.vscode-spring-boot-dashboard"   # Spring Boot Dashboard
    "mtxr.sqltools"                          # SQLTools
    "mtxr.sqltools-driver-pg"               # SQLTools PostgreSQL Driver
    "humao.rest-client"                      # REST Client
    "eamodio.gitlens"                        # GitLens
    "esbenp.prettier-vscode"                 # Prettier
    "ms-vscode.live-server"                  # Live Server
    "PKief.material-icon-theme"              # Material Icon Theme
)

if command_exists code; then
    for EXT in "${VSCODE_EXTENSIONS[@]}"; do
        EXT_NAME="${EXT##*.}"
        write_step "Разширение: $EXT ..."
        # Инсталираме като реалния потребител
        su - "$REAL_USER" -c "code --install-extension '$EXT' --force" &>/dev/null && \
            write_ok "$EXT" || write_warn "Не може да се инсталира: $EXT"
    done
else
    write_warn "VS Code не е в PATH. Разширенията ще трябва да се инсталират ръчно."
fi

# ── 12. IntelliJ IDEA Community Edition ─────────────────────────────────────

write_header "IntelliJ IDEA Community Edition"

if snap list intellij-idea-community &>/dev/null 2>&1; then
    write_skip "IntelliJ IDEA Community (snap)"
else
    if command_exists snap; then
        write_step "Инсталиране на IntelliJ IDEA Community чрез snap ..."
        snap install intellij-idea-community --classic 2>/dev/null && \
            write_ok "IntelliJ IDEA Community е инсталиран." || \
            write_warn "snap install не успя. Инсталирайте ръчно от: https://www.jetbrains.com/idea/download/"
    else
        write_warn "snap не е наличен. Инсталирайте IntelliJ IDEA ръчно от: https://www.jetbrains.com/idea/download/"
    fi
fi

# ── 13. Проверка на инсталациите ─────────────────────────────────────────────

write_header "Проверка на инсталираните компоненти"

ALL_OK=true

check_tool() {
    local CMD="$1"
    local LABEL="$2"
    local VER_CMD="$3"
    if command_exists "$CMD"; then
        local VER
        VER=$(eval "$VER_CMD" 2>/dev/null | head -1)
        write_ok "$LABEL: $VER"
    else
        write_warn "$LABEL: НЕ е намерен в PATH - може да е нужно ново влизане в системата."
        ALL_OK=false
    fi
}

check_tool "git"      "Git"              "git --version"
check_tool "php"      "PHP"              "php -r 'echo \"PHP \" . PHP_VERSION;'"
check_tool "composer" "Composer"         "composer --version"
check_tool "java"     "Java JDK"         "java -version 2>&1"
check_tool "mvn"      "Maven"            "mvn --version"
check_tool "psql"     "PostgreSQL (psql)" "psql --version"

# ── 14. Създаване на база данни за курса ─────────────────────────────────────

write_header "Настройване на PostgreSQL база данни за курса"

if command_exists psql; then
    write_step "Проверка/създаване на потребител 'webdev' ..."
    su - postgres -c "psql -c \"DO \\\$\\\$ BEGIN IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'webdev') THEN CREATE USER webdev WITH PASSWORD 'webdev123'; END IF; END \\\$\\\$;\"" 2>/dev/null && \
        write_ok "Потребителят 'webdev' е готов." || \
        write_warn "Не може да се създаде потребителят 'webdev'. Проверете дали PostgreSQL работи."

    write_step "Проверка/създаване на база данни 'webdev_db' ..."
    DB_EXISTS=$(su - postgres -c "psql -tAc \"SELECT 1 FROM pg_database WHERE datname='webdev_db';\"" 2>/dev/null | tr -d '[:space:]')
    if [[ "$DB_EXISTS" != "1" ]]; then
        su - postgres -c "psql -c \"CREATE DATABASE webdev_db OWNER webdev;\"" 2>/dev/null && \
            write_ok "База данни 'webdev_db' е създадена." || \
            write_warn "Не може да се създаде базата данни 'webdev_db'."
    else
        write_skip "Base 'webdev_db' (вече съществува)"
    fi
    write_ok "База данни 'webdev_db' и потребител 'webdev' (парола: webdev123) са готови."
else
    write_warn "psql не е намерен. Базата данни ще трябва да се създаде ръчно."
fi

# ── 15. composer install за всички PHP примери ───────────────────────────────

write_header "composer install за PHP примери"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
EXAMPLES_DIR="$(dirname "$SCRIPT_DIR")/examples/php"

if command_exists composer && [[ -d "$EXAMPLES_DIR" ]]; then
    while IFS= read -r -d '' COMPOSER_FILE; do
        DIR="$(dirname "$COMPOSER_FILE")"
        write_step "composer install в $DIR ..."
        # Изпълняваме като реалния потребител
        if su - "$REAL_USER" -c "cd '$DIR' && composer install --no-interaction --quiet" 2>/dev/null; then
            write_ok "$(basename "$DIR"): зависимостите са инсталирани."
        else
            write_warn "$(basename "$DIR"): composer install върна грешка."
        fi
    done < <(find "$EXAMPLES_DIR" -maxdepth 2 -name "composer.json" -print0)
elif ! command_exists composer; then
    write_warn "composer не е намерен в PATH – пропускам. Изпълнете 'composer install' ръчно в examples/php/<номер>/"
else
    write_warn "Директорията $EXAMPLES_DIR не е намерена – пропускам."
fi

# ── 16. Финален отчет ─────────────────────────────────────────────────────────

write_header "Инсталацията е завършена"

echo ""
echo -e "\e[36m  ОБОБЩЕНИЕ НА ДОСТЪП ДО БАЗАТА ДАННИ:\e[0m"
echo -e "\e[36m  ------------------------------------------\e[0m"
echo "  Host    : localhost"
echo "  Port    : 5432"
echo "  Database: webdev_db"
echo "  User    : webdev"
echo "  Password: webdev123"
echo "  (admin) : postgres / postgres  [САМО ЗА РАЗРАБОТКА]"
echo ""
echo -e "\e[36m  СЛЕДВАЩИ СТЪПКИ:\e[0m"
echo -e "\e[36m  ------------------------------------------\e[0m"
echo "  1. ИЗЛЕЗТЕ и влезте отново (или стартирайте 'source /etc/environment')"
echo "     за зареждане на PATH и JAVA_HOME промените."
echo "  2. Отворете VS Code и IntelliJ IDEA – ще ги конфигурирате при"
echo "     първото стартиране."
echo "  3. За PHP проекти: стартирайте вградения сървър с:"
echo "       php -S localhost:8000"
echo "  4. PHP Дебъгване в VS Code:"
echo "       - Отворете файл index.php"
echo "       - Сложете breakpoint (клик вляво от номера на ред)"
echo "       - Run & Debug -> изберете 'PHP: XX-example' -> F5"
echo "       (launch.json е в .vscode/ папката на проекта)"
echo "  5. За Spring Boot: използвайте IntelliJ IDEA или VS Code."
echo "  6. Управление на PostgreSQL:"
echo "       pgAdmin 4  (GUI) – от Application Menu"
echo "       psql -U webdev -d webdev_db  (команден ред)"
echo ""

if [[ "$ALL_OK" != true ]]; then
    write_warn "Някои компоненти не са намерени в PATH. Излезте и влезте отново в системата."
fi

echo -e "\e[36m  Документация: docs/  |  Примери: examples/\e[0m"
echo ""
