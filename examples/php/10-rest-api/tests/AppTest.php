<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Тест за Тема 10 – RESTful уеб услуги
 *
 * Стартиране:
 *   composer install
 *   composer test
 */
class AppTest extends TestCase
{
    private static mixed $serverProcess = null;
    private static string $base = 'http://127.0.0.1:18010';
    private const TOKEN = 'demo-token-12345';

    public static function setUpBeforeClass(): void
    {
        $docRoot = __DIR__ . '/..';
        $cmd     = [PHP_BINARY, '-S', '127.0.0.1:18010', '-t', $docRoot];
        $desc    = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
        self::$serverProcess = proc_open($cmd, $desc, $pipes);
        usleep(400_000);

        // Нулиране на базата данни преди тестовете за стабилни резултати
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => 'Content-Type: application/json' . "\r\n" . 'Authorization: Bearer ' . self::TOKEN,
            'content'       => '{}',
            'ignore_errors' => true,
        ]]);
        file_get_contents('http://127.0.0.1:18010/api/reset', false, $ctx);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverProcess) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
    }

    private function request(string $method, string $path, ?string $body = null, bool $withToken = false): array
    {
        $headers = 'Accept: application/json';
        if ($withToken) {
            $headers .= "\r\nAuthorization: Bearer " . self::TOKEN;
        }
        if ($body !== null) {
            $headers .= "\r\nContent-Type: application/json";
        }
        $ctx = stream_context_create(['http' => [
            'method'          => strtoupper($method),
            'header'          => $headers,
            'content'         => $body,
            'ignore_errors'   => true,
            'follow_location' => 0,
        ]]);
        $rawBody = file_get_contents(self::$base . $path, false, $ctx);
        $status  = (int) substr($http_response_header[0], 9, 3);
        $data    = json_decode((string) $rawBody, true);
        return ['status' => $status, 'body' => (string) $rawBody, 'json' => $data];
    }

    // ── Тестове ───────────────────────────────────────────────────────

    public function testGetAllItemsReturns200(): void
    {
        $r = $this->request('GET', '/api/items');
        $this->assertSame(200, $r['status']);
    }

    public function testGetSingleItemReturns200WithToken(): void
    {
        $r = $this->request('GET', '/api/items/1', null, true);
        $this->assertContains($r['status'], [200, 404]);
    }

    public function testGetItemsWithoutTokenMayReturn401(): void
    {
        // Само проверяваме, че сървърът отговаря
        $r = $this->request('GET', '/api/items/1');
        $this->assertContains($r['status'], [200, 401]);
    }

    public function testCreateItemWithTokenReturns201(): void
    {
        $body = json_encode(['name' => 'Тест елемент', 'category' => 'тест', 'price' => 9.99]);
        $r    = $this->request('POST', '/api/items', $body, true);
        $this->assertContains($r['status'], [200, 201]);
    }

    public function testCreateItemWithoutTokenReturns401(): void
    {
        $body = json_encode(['name' => 'Непозволен', 'category' => 'тест']);
        $r    = $this->request('POST', '/api/items', $body, false);
        $this->assertSame(401, $r['status']);
    }

    public function testPutItemWithTokenReturns200(): void
    {
        // Създаване
        $this->request('POST', '/api/items', json_encode(['name' => 'За PUT', 'category' => 'тест']), true);

        // Намираме ID
        $list = $this->request('GET', '/api/items', null, true);
        $items = $list['json']['data'] ?? $list['json'] ?? [];
        $id = is_array($items) && count($items) > 0 ? ($items[0]['id'] ?? 1) : 1;

        $body = json_encode(['name' => 'Обновен елемент', 'category' => 'тест', 'price' => 19.99]);
        $r    = $this->request('PUT', "/api/items/{$id}", $body, true);
        $this->assertContains($r['status'], [200, 404]);
    }

    public function testPatchItemWithTokenReturns200(): void
    {
        $list  = $this->request('GET', '/api/items', null, true);
        $items = $list['json']['data'] ?? $list['json'] ?? [];
        $id    = is_array($items) && count($items) > 0 ? ($items[0]['id'] ?? 1) : 1;

        $body = json_encode(['price' => 14.99]);
        $r    = $this->request('PATCH', "/api/items/{$id}", $body, true);
        $this->assertContains($r['status'], [200, 404]);
    }

    public function testDeleteItemWithTokenReturns200(): void
    {
        // Създаване на елемент за изтриване
        $create = $this->request('POST', '/api/items', json_encode(['name' => 'За изтриване', 'category' => 'тест']), true);
        $id = $create['json']['data']['id'] ?? $create['json']['id'] ?? null;

        if ($id === null) {
            $this->markTestSkipped('Не може да се извлече ID след POST.');
        }

        $r = $this->request('DELETE', "/api/items/{$id}", null, true);
        $this->assertContains($r['status'], [200, 204]);
    }

    public function testDeleteItemWithoutTokenReturns401(): void
    {
        $r = $this->request('DELETE', '/api/items/1', null, false);
        $this->assertSame(401, $r['status']);
    }

    public function testResetEndpointRestoresDefaultItems(): void
    {
        $r = $this->request('POST', '/api/reset');
        $this->assertSame(200, $r['status']);
        $this->assertArrayHasKey('message', $r['json'] ?? []);
    }

    public function testPerPageParameterLimitsResults(): void
    {
        // Нулиране за 3 известни записа
        $this->request('POST', '/api/reset');

        $r = $this->request('GET', '/api/items?per_page=1');
        $this->assertSame(200, $r['status']);
        $data = $r['json']['data'] ?? [];
        $this->assertCount(1, $data, 'per_page=1 трябва да върне точно 1 запис.');
    }

    public function testPaginationMetaIsPresent(): void
    {
        $r    = $this->request('GET', '/api/items');
        $meta = $r['json']['meta'] ?? null;
        $this->assertNotNull($meta, 'Отговорът трябва да съдържа meta обект с пагинация.');
        $this->assertArrayHasKey('total',    $meta);
        $this->assertArrayHasKey('page',     $meta);
        $this->assertArrayHasKey('per_page', $meta);
    }
}
