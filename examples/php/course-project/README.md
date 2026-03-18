# Курсова задача – PHP: Любими заведения

Пълно уеб приложение, реализиращо всички изисквания от
[02_Курсова_задача_по_сървърно_уеб_програмиране.md](../../../docs/02_Курсова_задача_по_сървърно_уеб_програмиране.md).

## Тема: Любими заведения (Тема 7)

Потребителите могат да разглеждат, добавят и управляват любими заведения.
Приложението интегрира и Open Brewery DB като пример за external REST API.

---

## Технологии

| Компонент      | Версия          |
|----------------|-----------------|
| PHP            | 8.3             |
| PostgreSQL     | 16+             |
| PHPUnit        | 11 (dev)        |
| Уеб сървър     | Apache + mod_rewrite |

---

## Структура

```
course-project/
├── public/
│   ├── index.php          # Front controller
│   └── .htaccess          # URL rewriting
├── src/
│   ├── Core/
│   │   ├── Database.php   # PDO PostgreSQL singleton
│   │   ├── Router.php     # Маршрутизатор
│   │   ├── Auth.php       # Автентикация (requireAuth, requireRole)
│   │   └── Request.php    # Заявки, CSRF токени, redirect
│   ├── Model/
│   │   ├── User.php       # Потребителски модел
│   │   └── Venue.php      # Модел за заведение
│   └── Controller/
│       ├── HomeController.php    # Публичен списък
│       ├── AuthController.php    # Вход/регистрация/изход
│       ├── VenueController.php   # CRUD за заведения
│       ├── ApiController.php     # REST API с токен
│       └── BreweryController.php # External API (Open Brewery DB)
├── views/
│   ├── layout.php
│   ├── home.php
│   ├── login.php
│   ├── register.php
│   ├── venues/
│   │   ├── index.php
│   │   └── form.php
│   └── brewery/
│       └── index.php
├── config/
│   └── database.php
├── install.php            # Създава таблиците + admin потребител
└── composer.json
```

---

## Инсталация

### 1. Клониране / копиране

```bash
cd /var/www/html
cp -r examples/php/course-project venues-app
cd venues-app
```

### 2. Composer зависимости

```bash
composer install
```

### 3. База данни (PostgreSQL)

Уверете се, че PostgreSQL работи, след това:

```bash
# Windows
psql -U postgres -c "CREATE DATABASE venues_db;"

# macOS / Linux
sudo -u postgres createdb venues_db
```

### 4. Конфигурация

Редактирайте `config/database.php` с вашите данни за достъп.

### 5. Инсталационен скрипт

```bash
php install.php
```

Скриптът създава таблиците `users` и `venues`, и добавя default admin:
- **Email**: `admin@example.com`
- **Парола**: `Admin1234!`

### 6. Apache конфигурация

```apache
DocumentRoot /var/www/html/venues-app/public

<Directory /var/www/html/venues-app/public>
    AllowOverride All
    Require all granted
</Directory>
```

Или с PHP built-in сървър (само за разработка):

```bash
php -S localhost:8000 -t public/
```

---

## Покрити изисквания от курсовата задача

| Изискване                          | Файл(ове)                                               |
|------------------------------------|---------------------------------------------------------|
| PHP 8+ / Spring Boot               | Цялото приложение                                       |
| CRUD за основна тема               | `src/Controller/VenueController.php`, `Venue.php`      |
| Автентикация + сесии               | `src/Controller/AuthController.php`, `Auth.php`        |
| Хеширани пароли                    | `User.php::create()`, `password_hash()`                |
| Защита CSRF                        | `Request.php::csrfToken()`, всички форми               |
| Превенция XSS                      | `htmlspecialchars()` навсякъде                         |
| Prepared statements                | Целият `src/Model/` слой                               |
| Пагинация + търсене + сортиране    | `HomeController.php`, `Venue::paginate()`              |
| Cookie за любими                   | `HomeController.php` (favorite cookie)                 |
| REST API с токен                   | `src/Controller/ApiController.php`                     |
| External REST API                  | `src/Controller/BreweryController.php` (Open Brewery)  |
| Security headers                   | `public/index.php` bootstrap                           |

---

## REST API Документация

### Автентикация

Добавете `Authorization: Bearer <token>` хедър. Токенът е видим в профила.

### Крайни точки

| Метод  | URL                     | Описание                         |
|--------|-------------------------|----------------------------------|
| GET    | `/api/venues`           | Списък (с пагинация)             |
| GET    | `/api/venues/{id}`      | Единично заведение               |
| POST   | `/api/venues`           | Създаване (изисква токен)        |
| PUT    | `/api/venues/{id}`      | Пълна замяна (изисква токен)     |
| DELETE | `/api/venues/{id}`      | Изтриване (изисква токен)        |

```bash
# Примерни curl заявки
curl http://localhost:8000/api/venues

curl -X POST http://localhost:8000/api/venues \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"Cafe Central","city":"Sofia","category":"cafe"}'
```
