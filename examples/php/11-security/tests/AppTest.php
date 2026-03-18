<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Тест за Тема 11 – Сигурност на уеб приложенията
 *
 * Стартиране:
 *   composer install
 *   composer test
 */
class AppTest extends TestCase
{
    private static mixed $serverProcess = null;
    private static string $base = 'http://127.0.0.1:18011';

    public static function setUpBeforeClass(): void
    {
        $docRoot = __DIR__ . '/..';
        $cmd     = [PHP_BINARY, '-S', '127.0.0.1:18011', '-t', $docRoot];
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

    private function findHeader(array $headers, string $name): ?string
    {
        foreach ($headers as $h) {
            if (stripos($h, $name . ':') === 0) {
                return trim(substr($h, strlen($name) + 1));
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

    public function testSecurityHeadersArePresent(): void
    {
        $r = $this->get('/');
        $this->assertNotNull(
            $this->findHeader($r['headers'], 'X-Frame-Options'),
            'Хедърът X-Frame-Options трябва да присъства.'
        );
        $this->assertNotNull(
            $this->findHeader($r['headers'], 'X-Content-Type-Options'),
            'Хедърът X-Content-Type-Options трябва да присъства.'
        );
        $this->assertNotNull(
            $this->findHeader($r['headers'], 'Content-Security-Policy'),
            'Хедърът Content-Security-Policy трябва да присъства.'
        );
    }

    public function testXFrameOptionsDeny(): void
    {
        $r = $this->get('/');
        $value = $this->findHeader($r['headers'], 'X-Frame-Options');
        $this->assertSame('DENY', strtoupper(trim((string) $value)));
    }

    public function testXssInputIsEscapedInOutput(): void
    {
        // Проверяваме, че GET параметърът не се отразява суров в HTML
        $r   = $this->get('/?name=<script>alert(1)</script>');
        $this->assertSame(200, $r['status']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $r['body']);
    }

    public function testCsrfTokenIsPresentInForm(): void
    {
        $r = $this->get('/');
        // Форматът на hidden field в HTML
        $this->assertStringContainsString('csrf_token', $r['body']);
    }
}
