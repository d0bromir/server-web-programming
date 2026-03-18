<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Тест за Тема 07 – CRUD операции и слоево разделение
 *
 * Стартиране:
 *   composer install
 *   composer test
 */
class AppTest extends TestCase
{
    private static mixed $serverProcess = null;
    private static string $base = 'http://127.0.0.1:18007';

    public static function setUpBeforeClass(): void
    {
        $docRoot = __DIR__ . '/..';
        $cmd     = [PHP_BINARY, '-S', '127.0.0.1:18007', '-t', $docRoot];
        $desc    = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
        self::$serverProcess = proc_open($cmd, $desc, $pipes);
        usleep(400_000);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverProcess) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
    }

    private function get(string $path): array
    {
        $ctx = stream_context_create(['http' => [
            'method'          => 'GET',
            'ignore_errors'   => true,
            'follow_location' => 0,
        ]]);
        $body   = file_get_contents(self::$base . $path, false, $ctx);
        $status = (int) substr($http_response_header[0], 9, 3);
        return ['status' => $status, 'body' => (string) $body, 'headers' => $http_response_header];
    }

    private function post(string $path, array $data): array
    {
        $ctx = stream_context_create(['http' => [
            'method'          => 'POST',
            'header'          => 'Content-Type: application/x-www-form-urlencoded',
            'content'         => http_build_query($data),
            'ignore_errors'   => true,
            'follow_location' => 0,
        ]]);
        $body   = file_get_contents(self::$base . $path, false, $ctx);
        $status = (int) substr($http_response_header[0], 9, 3);
        return ['status' => $status, 'body' => (string) $body, 'headers' => $http_response_header];
    }

    private function findLocationHeader(array $headers): ?string
    {
        foreach ($headers as $h) {
            if (stripos($h, 'Location:') === 0) {
                return trim(substr($h, 9));
            }
        }
        return null;
    }

    // ── Тестове ───────────────────────────────────────────────────────

    public function testHomePageReturns200(): void
    {
        $r = $this->get('/');
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('<html', $r['body']);
    }

    public function testCreateTaskRedirectsToHome(): void
    {
        $r = $this->post('/tasks', [
            'title'    => 'Тест задача',
            'status'   => 'pending',
            'priority' => 'normal',
        ]);
        // След POST → редирект към /
        $this->assertSame(302, $r['status']);
        $this->assertSame('/', $this->findLocationHeader($r['headers']));
    }

    public function testCreateTaskWithEmptyTitleShowsError(): void
    {
        $r = $this->post('/tasks', ['title' => '', 'status' => 'pending', 'priority' => 'normal']);
        // Редиректва обратно с flash грешка
        $this->assertSame(302, $r['status']);
    }

    public function testUpdateTaskRedirectsToHome(): void
    {
        // Първо създаваме задача
        $this->post('/tasks', ['title' => 'За update', 'status' => 'pending', 'priority' => 'normal']);

        // GET / → намираме ID на последната задача
        $list = $this->get('/');
        preg_match('@/tasks/(\d+)@', $list['body'], $m);
        $id = $m[1] ?? 1;

        $r = $this->post("/tasks/{$id}", [
            '_method'  => 'PUT',
            'title'    => 'Обновена',
            'status'   => 'done',
            'priority' => 'high',
        ]);
        $this->assertSame(302, $r['status']);
    }

    public function testDeleteTaskRedirectsToHome(): void
    {
        // Първо създаваме задача
        $this->post('/tasks', ['title' => 'За изтриване', 'status' => 'pending', 'priority' => 'normal']);

        $list = $this->get('/');
        preg_match('@/tasks/(\d+)@', $list['body'], $m);
        $id = $m[1] ?? 1;

        $r = $this->post("/tasks/{$id}", ['_method' => 'DELETE']);
        $this->assertSame(302, $r['status']);
    }
}
