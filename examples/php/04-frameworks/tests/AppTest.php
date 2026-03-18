<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Тест за Тема 04 – Сървърни Framework-и: концепции и сравнение
 *
 * Стартиране:
 *   composer install
 *   composer test
 */
class AppTest extends TestCase
{
    private static mixed $serverProcess = null;
    private static string $base = 'http://127.0.0.1:18004';

    public static function setUpBeforeClass(): void
    {
        $docRoot = __DIR__ . '/..';
        $cmd     = [PHP_BINARY, '-S', '127.0.0.1:18004', '-t', $docRoot];
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

    // ── Тестове ───────────────────────────────────────────────────────

    public function testHomePageReturns200(): void
    {
        $r = $this->get('/');
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('<html', $r['body']);
    }

    public function testProtectedRouteWithoutTokenReturns401(): void
    {
        $r = $this->get('/protected');
        $this->assertSame(401, $r['status']);
    }

    public function testProtectedRouteWithValidTokenReturns200(): void
    {
        $r = $this->get('/protected?token=secret123');
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('<html', $r['body']);
    }
}
