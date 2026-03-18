# Курсова задача – Любими заведения (Java Spring Boot)

Пълно Spring Boot 3.4.1 / Java 21 уеб приложение за управление на любими заведения.

## Функционалности

| # | Описание |
|---|----------|
| 1 | Публичен списък с пагинация, търсене и филтриране по категория |
| 2 | Регистрация и вход (Spring Security 6 + BCrypt) |
| 3 | CRUD за заведения – само собственикът или ADMIN може да редактира/изтрива |
| 4 | Маркиране на любими заведения чрез cookie (без login) |
| 5 | REST API `/api/venues` с Bearer-token автентикация |
| 6 | Интеграция с Open Brewery DB (external HTTP client) |
| 7 | CSRF защита, security headers, XSS-безопасни Thymeleaf шаблони |

## Стартиране

### 1. Създайте PostgreSQL база

```bash
createdb venues_db
```

Или с psql:
```sql
CREATE DATABASE venues_db;
```

### 2. Конфигурирайте `application.properties`

```properties
spring.datasource.url=jdbc:postgresql://localhost:5432/venues_db
spring.datasource.username=postgres
spring.datasource.password=postgres
```

### 3. Стартирайте

```bash
mvn spring-boot:run
```

Таблиците се създават автоматично при първо стартиране (`ddl-auto=update`).

При стартиране се показва в конзолата:
```
=== Admin акаунт: admin@venues.bg / Admin1234! ===
=== API токен: <uuid> ===
```

### 4. Отворете браузъра

```
http://localhost:8080
```

## Структура на проекта

```
src/main/java/com/swp/venues/
├── Application.java
├── config/
│   ├── SecurityConfig.java        # Spring Security Filter Chain, seed admin
│   └── ApiTokenFilter.java        # Bearer token → SecurityContext
├── model/
│   ├── User.java                  # @Entity – потребител
│   └── Venue.java                 # @Entity – заведение
├── repository/
│   ├── UserRepository.java        # JpaRepository<User>
│   └── VenueRepository.java       # JpaRepository<Venue> + JPQL заявки
├── service/
│   ├── UserService.java           # UserDetailsService + регистрация
│   └── VenueService.java          # CRUD + пагинация
└── controller/
    ├── HomeController.java        # GET / + любими cookie
    ├── AuthController.java        # /login, /register
    ├── VenueController.java       # CRUD /venues/**
    ├── ApiController.java         # REST /api/venues
    └── BreweryController.java     # GET /brewery → Open Brewery DB
```

## REST API

Базов URL: `http://localhost:8080/api/venues`

| Метод | Краище | Описание | Автентикация |
|-------|--------|----------|--------------|
| GET   | `/api/venues`      | Всички заведения  | Не |
| GET   | `/api/venues/{id}` | Едно заведение    | Не |
| POST  | `/api/venues`      | Създаване         | Bearer токен |
| DELETE| `/api/venues/{id}` | Изтриване         | Bearer токен |

### Примерни заявки с curl

```bash
# Всички заведения
curl http://localhost:8080/api/venues

# Създаване (смете tokena от конзолата)
curl -X POST http://localhost:8080/api/venues \
     -H "Authorization: Bearer <api-token>" \
     -H "Content-Type: application/json" \
     -d '{"name":"Ателие 43","city":"София","category":"cafe","description":"Уютно кафе","rating":4.5,"isPublic":true}'

# Изтриване
curl -X DELETE http://localhost:8080/api/venues/1 \
     -H "Authorization: Bearer <api-token>"
```
