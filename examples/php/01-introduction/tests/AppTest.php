<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Тест за Тема 01 – Въведение в сървърното уеб програмиране
 *
 * Стартиране:
 *   composer install
 *   composer test
 */
class AppTest extends TestCase
{
    private static mixed $serverProcess = null;
    private static string $base = 'http://127.0.0.1:18001';

    // ── Жизнен цикъл ──────────────────────────────────────────────────

    public static function setUpBeforeClass(): void
    {
        $docRoot = __DIR__ . '/..';
        $cmd     = [PHP_BINARY, '-S', '127.0.0.1:18001', '-t', $docRoot];
        $desc    = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
        self::$serverProcess = proc_open($cmd, $desc, $pipes);
        usleep(400_000); // изчакваме сървъра да стартира
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverProcess) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
    }

    // ── Помощни методи ────────────────────────────────────────────────

    private function get(string $path): array
    {
        $ctx = stream_context_create(['http' => [
            'method'          => 'GET',
            'ignore_errors'   => true,
            'follow_location' => 0,
        ]]);
        $body = file_get_contents(self::$base . $path, false, $ctx);
        // $http_response_header е магическа PHP променлива
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

    // ── Тестове ───────────────────────────────────────────────────────

    public function testHomePageReturns200(): void
    {
        $r = $this->get('/');
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('<html', $r['body']);
    }

    public function testGetWithNameParameterShowsGreeting(): void
    {
        $r = $this->get('/?name=' . rawurlencode('Студент'));
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('Студент', $r['body']);
    }

    public function testPostFormReturns200OrRedirect(): void
    {
        $r = $this->post('/', ['greeting' => 'Добър ден']);
        $this->assertContains($r['status'], [200, 302]);
    }
}
