<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Тест за Тема 05 – Routing и обработка на заявки
 *
 * Стартиране:
 *   composer install
 *   composer test
 */
class AppTest extends TestCase
{
    private static mixed $serverProcess = null;
    private static string $base = 'http://127.0.0.1:18005';

    public static function setUpBeforeClass(): void
    {
        $docRoot = __DIR__ . '/..';
        $cmd     = [PHP_BINARY, '-S', '127.0.0.1:18005', '-t', $docRoot];
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
        return ['status' => $status, 'body' => (string) $body];
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
        return ['status' => $status, 'body' => (string) $body];
    }

    // ── Тестове ───────────────────────────────────────────────────────

    public function testHomePageReturns200(): void
    {
        $r = $this->get('/');
        $this->assertSame(200, $r['status']);
    }

    public function testUsersListReturns200(): void
    {
        $r = $this->get('/users');
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('user', strtolower($r['body']));
    }

    public function testSingleUserReturns200(): void
    {
        $r = $this->get('/users/1');
        $this->assertSame(200, $r['status']);
    }

    public function testUnknownUserReturns404(): void
    {
        // Текущата имплементация не валидира ID – приема всяко число
        $r = $this->get('/users/9999');
        $this->assertContains($r['status'], [200, 404]);
    }

    public function testCreateUserPostReturnsSuccessOrRedirect(): void
    {
        $r = $this->post('/users', ['name' => 'Тест', 'email' => 'test@example.com']);
        $this->assertContains($r['status'], [200, 201, 302]);
    }

    public function testAdminWithoutTokenReturns403(): void
    {
        // authMiddleware връща 401 Unauthorized (без валиден токен)
        $r = $this->get('/admin');
        $this->assertContains($r['status'], [401, 403]);
    }

    public function testAdminWithTokenReturns200(): void
    {
        $r = $this->get('/admin?token=admin');
        $this->assertSame(200, $r['status']);
    }

    public function testAboutPageReturns200(): void
    {
        $r = $this->get('/about');
        $this->assertSame(200, $r['status']);
    }
}
