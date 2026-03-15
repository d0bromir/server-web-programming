# Инсталационен скрипт – Сървърно уеб програмиране

## Какво се инсталира

| Компонент | Версия | Предназначение |
|---|---|---|
| **Git** | последна | Контрол на версиите |
| **PHP** | 8.x | Сървърно програмиране – PHP |
| **Composer** | последна | Пакетен мениджър за PHP |
| **Java JDK** | 21 LTS (Temurin) | Сървърно програмиране – Java |
| **Apache Maven** | последна | Build система за Java |
| **PostgreSQL** | 16 | Релационна база данни |
| **pgAdmin 4** | последна | GUI клиент за PostgreSQL |
| **VS Code** | последна | Редактор с PHP + Java + Spring Boot разширения |
| **IntelliJ IDEA** | Community | IDE за Java и Spring Boot |

## Изисквания

- Windows 10 (версия 1809+) или Windows 11
- Интернет връзка
- `winget` (App Installer) – стандартен компонент на Windows 11; за Windows 10 инсталирайте „App Installer" от Microsoft Store

## Стъпки за инсталация

### 1. Отворете PowerShell като Администратор

```
Щракнете с десен бутон на Start → "Windows PowerShell (Admin)"
```

или натиснете `Win + X` → `Windows Terminal (Admin)`.

### 2. Позволете изпълнение на скрипта

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
```

### 3. Навигирайте до папката на скрипта

```powershell
cd "C:\...\server-web-programming\scripts"
```

### 4. Стартирайте инсталацията

```powershell
.\install-dev-environment.ps1
```

> **PostgreSQL парола:** по време на инсталацията въведете `postgres` като парола за superuser (само за разработка).

### 5. Рестартирайте компютъра

След завършване задължително рестартирайте, за да се заредят промените в `PATH`.

---

## Данни за свързване с базата данни (за разработка)

```
Host     : localhost
Port     : 5432
Database : webdev_db
User     : webdev
Password : webdev123

PostgreSQL admin:
User     : postgres
Password : postgres
```

---

## Проверка след инсталация

Отворете нов PowerShell прозорец и изпълнете:

```powershell
php --version          # PHP 8.x.x
composer --version     # Composer 2.x
java -version          # openjdk 21
mvn --version          # Apache Maven 3.x
psql --version         # psql (PostgreSQL) 16.x
git --version          # git version 2.x
```

---

## Полезни команди

```powershell
# Стартиране на PHP вграден сървър (в папката на проекта)
php -S localhost:8000

# Свързване с PostgreSQL
psql -U webdev -d webdev_db -h localhost

# Създаване на нов Spring Boot проект с Maven
mvn archetype:generate -DgroupId=com.example -DartifactId=my-app `
    -DarchetypeArtifactId=maven-archetype-quickstart
```

---

## Ако нещо не се инсталира автоматично

Ръчни download линкове:

| Компонент | Линк |
|---|---|
| PHP 8.x (Windows) | https://windows.php.net/download/ |
| Composer | https://getcomposer.org/Composer-Setup.exe |
| Java 21 Temurin | https://adoptium.net/temurin/releases/?version=21 |
| Maven | https://maven.apache.org/download.cgi |
| PostgreSQL 16 | https://www.enterprisedb.com/downloads/postgres-postgresql-downloads |
| VS Code | https://code.visualstudio.com/ |
| IntelliJ IDEA | https://www.jetbrains.com/idea/download/ |
