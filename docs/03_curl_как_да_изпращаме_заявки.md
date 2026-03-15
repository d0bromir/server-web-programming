# Използване на `curl` за HTTP заявки (Windows 11 и Linux)

Това ръководство показва как да използвате **curl** за изпращане на HTTP
заявки към уеб сайт или RESTful API.

Поддържани методи: - GET - POST - PUT - DELETE - OPTIONS

Работи еднакво добре на **Windows 11**, **Linux** и **macOS**.

------------------------------------------------------------------------

# 1. Проверка дали curl е инсталиран

## Windows 11

Windows 11 има curl по подразбиране.

Проверка:

``` bash
curl --version
```

Ако командата не работи: https://curl.se/windows/

## Linux

Ubuntu / Debian

``` bash
sudo apt install curl
```

Fedora

``` bash
sudo dnf install curl
```

Arch

``` bash
sudo pacman -S curl
```

Проверка:

``` bash
curl --version
```

------------------------------------------------------------------------

# 2. Основен синтаксис

``` bash
curl [options] URL
```

Пример:

``` bash
curl https://example.com
```

------------------------------------------------------------------------

# 3. GET заявка

Изтегляне на ресурс.

``` bash
curl https://api.example.com/users
```

С headers:

``` bash
curl -H "Accept: application/json" https://api.example.com/users
```

GET с параметри:

``` bash
curl "https://api.example.com/users?id=10"
```

------------------------------------------------------------------------

# 4. POST заявка

Изпращане на данни към сървър.

JSON пример:

``` bash
curl -X POST https://api.example.com/users \
-H "Content-Type: application/json" \
-d "{\"name\":\"Ivan\",\"age\":30}"
```

Форма:

``` bash
curl -X POST https://example.com/login \
-d "username=user&password=pass"
```

------------------------------------------------------------------------

# 5. PUT заявка

Актуализиране на ресурс.

``` bash
curl -X PUT https://api.example.com/users/10 \
-H "Content-Type: application/json" \
-d "{\"name\":\"Ivan Updated\"}"
```

------------------------------------------------------------------------

# 6. DELETE заявка

Изтриване на ресурс.

``` bash
curl -X DELETE https://api.example.com/users/10
```

------------------------------------------------------------------------

# 7. OPTIONS заявка

Проверка кои HTTP методи поддържа сървърът.

``` bash
curl -X OPTIONS https://api.example.com/users -i
```

Ще върне headers като:

    Allow: GET, POST, PUT, DELETE, OPTIONS

------------------------------------------------------------------------

# 8. Добавяне на HTTP headers

``` bash
curl -H "Authorization: Bearer TOKEN" \
-H "Accept: application/json" \
https://api.example.com/data
```

------------------------------------------------------------------------

# 9. Показване на HTTP headers в отговора

``` bash
curl -i https://example.com
```

Само headers:

``` bash
curl -I https://example.com
```

------------------------------------------------------------------------

# 10. Записване на отговор във файл

``` bash
curl https://example.com -o page.html
```

------------------------------------------------------------------------

# 11. Работа с REST API

GET:

``` bash
curl https://api.example.com/products
```

POST:

``` bash
curl -X POST https://api.example.com/products \
-H "Content-Type: application/json" \
-d "{\"name\":\"Laptop\",\"price\":2000}"
```

PUT:

``` bash
curl -X PUT https://api.example.com/products/5 \
-H "Content-Type: application/json" \
-d "{\"price\":1800}"
```

DELETE:

``` bash
curl -X DELETE https://api.example.com/products/5
```

------------------------------------------------------------------------

# 12. Полезни опции

  Опция   Описание
  ------- --------------------
  -X      HTTP метод
  -H      HTTP header
  -d      изпращане на данни
  -i      показва headers
  -I      само headers
  -o      запис във файл
  -v      verbose режим

------------------------------------------------------------------------

# 13. Debug режим

``` bash
curl -v https://example.com
```

Показва: - DNS lookup - TLS handshake - HTTP headers - request/response

------------------------------------------------------------------------

# Полезни ресурси

Официална документация:

https://curl.se/docs/

------------------------------------------------------------------------

# Кратко обобщение

  Метод     curl пример
  --------- ---------------------------------------------------
  GET       `curl https://api.site.com/data`
  POST      `curl -X POST -d '{}' https://api.site.com/data`
  PUT       `curl -X PUT -d '{}' https://api.site.com/data/1`
  DELETE    `curl -X DELETE https://api.site.com/data/1`
  OPTIONS   `curl -X OPTIONS https://api.site.com/data`
