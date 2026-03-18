<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Тест за Тема 06 – Работа с бази данни и ORM подход
 *
 * Стартиране:
 *   composer install
 *   composer test
 */
class AppTest extends TestCase
{
    private static mixed $serverProcess = null;
    private static string $base = 'http://127.0.0.1:18006';

    public static function setUpBeforeClass(): void
    {
        $docRoot = __DIR__ . '/..';
        $cmd     = [PHP_BINARY, '-S', '127.0.0.1:18006', '-t', $docRoot];
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

    // ── Тестове ───────────────────────────────────────────────────────

    public function testHomePageReturns200(): void
    {
        $r = $this->get('/');
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('<html', $r['body']);
    }

    public function testPaginationReturns200(): void
    {
        $r = $this->get('/?page=1');
        $this->assertSame(200, $r['status']);
    }

    public function testSearchReturns200(): void
    {
        $r = $this->get('/?search=книга');
        $this->assertSame(200, $r['status']);
    }

    public function testTransactionDemoReturns200(): void
    {
        $r = $this->get('/?tx=1');
        $this->assertSame(200, $r['status']);
    }
}
