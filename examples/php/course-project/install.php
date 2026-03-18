<?php
declare(strict_types=1);

/**
 * install.php – Инсталационен скрипт
 *
 * Изпълнете еднократно:  php install.php
 *
 * Създава:
 *   - таблица users
 *   - таблица venues
 *   - default admin акаунт  (admin@example.com / Admin1234!)
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Database;

$config = require __DIR__ . '/config/database.php';

try {
    $dsn = sprintf(
        'pgsql:host=%s;port=%d;dbname=%s',
        $config['host'],
        $config['port'],
        $config['dbname']
    );

    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✔ Свързване с базата данни успешно.\n";

    // ── Таблица: users ────────────────────────────────────────────────
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id         SERIAL PRIMARY KEY,
            name       VARCHAR(100)  NOT NULL,
            email      VARCHAR(200)  NOT NULL UNIQUE,
            password   VARCHAR(255)  NOT NULL,
            role       VARCHAR(20)   NOT NULL DEFAULT 'user',
            api_token  VARCHAR(64)   UNIQUE,
            created_at TIMESTAMPTZ   NOT NULL DEFAULT NOW()
        );
    SQL);
    echo "✔ Таблица 'users' създадена.\n";

    // ── Таблица: venues ───────────────────────────────────────────────
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS venues (
            id          SERIAL PRIMARY KEY,
            user_id     INT          NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name        VARCHAR(200) NOT NULL,
            city        VARCHAR(100) NOT NULL,
            address     VARCHAR(300),
            category    VARCHAR(50)  NOT NULL DEFAULT 'other',
            description TEXT,
            rating      SMALLINT     CHECK (rating BETWEEN 1 AND 5),
            website     VARCHAR(300),
            is_public   BOOLEAN      NOT NULL DEFAULT TRUE,
            created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW(),
            updated_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
        );
    SQL);
    echo "✔ Таблица 'venues' създадена.\n";

    // ── Index за по-бързо търсене ─────────────────────────────────────
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_venues_city ON venues(city);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_venues_category ON venues(category);');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_venues_user ON venues(user_id);');

    // ── Default admin потребител ──────────────────────────────────────
    $adminEmail = 'admin@example.com';
    $existing   = $pdo->prepare('SELECT id FROM users WHERE email = :email');
    $existing->execute([':email' => $adminEmail]);

    if ($existing->fetch()) {
        echo "ℹ  Admin потребителят вече съществува.\n";
    } else {
        $token    = bin2hex(random_bytes(32));
        $hash     = password_hash('Admin1234!', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt     = $pdo->prepare(
            'INSERT INTO users (name, email, password, role, api_token) VALUES (:n, :e, :p, :r, :t)'
        );
        $stmt->execute([
            ':n' => 'Administrator',
            ':e' => $adminEmail,
            ':p' => $hash,
            ':r' => 'admin',
            ':t' => $token,
        ]);
        echo "✔ Admin акаунт създаден:\n";
        echo "     Email:  admin@example.com\n";
        echo "     Парола: Admin1234!\n";
        echo "     Token:  $token\n";
    }

    // ── Demo заведения ────────────────────────────────────────────────
    $count = (int) $pdo->query('SELECT COUNT(*) FROM venues')->fetchColumn();
    if ($count === 0) {
        $adminId = (int) $pdo->query("SELECT id FROM users WHERE email='admin@example.com'")->fetchColumn();
        $demo = [
            ['Ресторант Централ', 'София',    'бул. Витоша 14',  'restaurant', 'Класически ресторант', 5],
            ['Кафе Европа',       'Пловдив',  'ул. Главна 3',    'cafe',       'Уютно кафе',           4],
            ['Бар Лагуна',        'Варна',    'ул. Морска 22',   'bar',        'Плажен бар',            4],
            ['Пицария Наполи',    'Бургас',   'ул. Александровска 10', 'restaurant', 'Автентична пица', 5],
        ];
        $ins = $pdo->prepare(
            'INSERT INTO venues (user_id, name, city, address, category, description, rating)
             VALUES (:uid, :name, :city, :addr, :cat, :desc, :rat)'
        );
        foreach ($demo as [$name, $city, $addr, $cat, $desc, $rat]) {
            $ins->execute([
                ':uid'  => $adminId,
                ':name' => $name,
                ':city' => $city,
                ':addr' => $addr,
                ':cat'  => $cat,
                ':desc' => $desc,
                ':rat'  => $rat,
            ]);
        }
        echo "✔ Демо заведения добавени.\n";
    }

    echo "\n✅ Инсталацията завърши успешно!\n";
    echo "   Стартирайте:  php -S localhost:8000 -t public/\n";

} catch (PDOException $e) {
    echo "\n❌ Грешка: " . $e->getMessage() . "\n";
    echo "   Проверете config/database.php\n";
    exit(1);
}
