<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Тест за Тема 02 – Клиент–сървър архитектура и HTTP протокол
 *
 * Стартиране:
 *   composer install
 *   composer test
 */
class AppTest extends TestCase
{
    private static mixed $serverProcess = null;
    private static string $base = 'http://127.0.0.1:18002';

    public static function setUpBeforeClass(): void
    {
        $docRoot = __DIR__ . '/..';
        $cmd     = [PHP_BINARY, '-S', '127.0.0.1:18002', '-t', $docRoot];
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

    private function post(string $path, array $data, string $contentType = 'application/x-www-form-urlencoded'): array
    {
        $content = ($contentType === 'application/json') ? json_encode($data) : http_build_query($data);
        $ctx = stream_context_create(['http' => [
            'method'          => 'POST',
            'header'          => "Content-Type: {$contentType}",
            'content'         => $content,
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

    public function testStatusCode200(): void
    {
        $r = $this->get('/status?code=200');
        $this->assertSame(200, $r['status']);
    }

    public function testStatusCode404(): void
    {
        $r = $this->get('/status?code=404');
        $this->assertSame(404, $r['status']);
    }

    public function testStatusCode500(): void
    {
        $r = $this->get('/status?code=500');
        $this->assertSame(500, $r['status']);
    }

    public function testEchoRequestPostJsonReturns200(): void
    {
        $r = $this->post('/echo-request', ['key' => 'value', 'num' => 42], 'application/json');
        $this->assertSame(200, $r['status']);
    }

    public function testRedirectReturns301(): void
    {
        $r = $this->get('/redirect');
        $this->assertSame(301, $r['status']);
    }
}
