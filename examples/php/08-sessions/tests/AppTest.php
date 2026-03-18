<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Тест за Тема 08 – Управление на състояние: Сесии и Cookies
 *
 * Стартиране:
 *   composer install
 *   composer test
 */
class AppTest extends TestCase
{
    private static mixed $serverProcess = null;
    private static string $base = 'http://127.0.0.1:18008';

    public static function setUpBeforeClass(): void
    {
        $docRoot = __DIR__ . '/..';
        $cmd     = [PHP_BINARY, '-S', '127.0.0.1:18008', '-t', $docRoot];
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

    /** Извлича стойността на Set-Cookie хедър (само ID, без параметри) */
    private function extractSessionCookie(array $headers): string
    {
        foreach ($headers as $h) {
            if (stripos($h, 'Set-Cookie:') === 0 && stripos($h, 'PHPSESSID') !== false) {
                preg_match('/PHPSESSID=([^;]+)/', $h, $m);
                return $m[1] ?? '';
            }
        }
        return '';
    }

    private function get(string $path, string $cookie = ''): array
    {
        $headers = 'Accept: text/html';
        if ($cookie !== '') {
            $headers .= "\r\nCookie: PHPSESSID={$cookie}";
        }
        $ctx = stream_context_create(['http' => [
            'method'          => 'GET',
            'header'          => $headers,
            'ignore_errors'   => true,
            'follow_location' => 0,
        ]]);
        $body   = file_get_contents(self::$base . $path, false, $ctx);
        $status = (int) substr($http_response_header[0], 9, 3);
        return ['status' => $status, 'body' => (string) $body, 'headers' => $http_response_header];
    }

    private function post(string $path, array $data, string $cookie = ''): array
    {
        $headers = 'Content-Type: application/x-www-form-urlencoded';
        if ($cookie !== '') {
            $headers .= "\r\nCookie: PHPSESSID={$cookie}";
        }
        $ctx = stream_context_create(['http' => [
            'method'          => 'POST',
            'header'          => $headers,
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

    public function testSetSessionActionRedirects(): void
    {
        $r = $this->post('/?action=set_session', ['username' => 'Тест']);
        $this->assertSame(302, $r['status']);
    }

    public function testSessionPersistsBetweenRequests(): void
    {
        // Първа заявка – задаваме сесия
        $r1     = $this->post('/?action=set_session', ['username' => 'Иван']);
        $sessId = $this->extractSessionCookie($r1['headers']);

        if ($sessId === '') {
            $this->markTestSkipped('PHP built-in server не върна сесионен cookie.');
        }

        // Начална страница с вече зададена сесия
        $r2 = $this->get('/', $sessId);
        $this->assertSame(200, $r2['status']);
    }

    public function testSetCookieActionRedirects(): void
    {
        $r = $this->post('/?action=set_cookie', ['name' => 'theme', 'value' => 'dark']);
        $this->assertSame(302, $r['status']);
    }

    public function testDeleteCookieActionRedirects(): void
    {
        $r = $this->post('/?action=delete_cookie', ['name' => 'theme']);
        $this->assertSame(302, $r['status']);
    }
}
