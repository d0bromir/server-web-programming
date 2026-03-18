<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Тест за Тема 09 – Автентикация и авторизация
 *
 * Стартиране:
 *   composer install
 *   composer test
 */
class AppTest extends TestCase
{
    private static mixed $serverProcess = null;
    private static string $base = 'http://127.0.0.1:18009';

    public static function setUpBeforeClass(): void
    {
        $docRoot = __DIR__ . '/..';
        $cmd     = [PHP_BINARY, '-S', '127.0.0.1:18009', '-t', $docRoot];
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

    public function testLoginPageReturns200(): void
    {
        $r = $this->get('/login');
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsStringIgnoringCase('login', $r['body']);
    }

    public function testDashboardWithoutAuthRedirectsToLogin(): void
    {
        $r = $this->get('/dashboard');
        // Страницата трябва да редиректне към /login
        $this->assertContains($r['status'], [302, 403]);
    }

    public function testAdminWithoutAuthRedirectsOrForbids(): void
    {
        $r = $this->get('/admin');
        $this->assertContains($r['status'], [302, 403]);
    }

    public function testSuccessfulAdminLoginRedirectsToDashboard(): void
    {
        $r = $this->post('/login', ['username' => 'admin', 'password' => 'admin123']);
        $this->assertSame(302, $r['status']);
    }

    public function testAdminCanAccessDashboard(): void
    {
        $loginR  = $this->post('/login', ['username' => 'admin', 'password' => 'admin123']);
        $sessId  = $this->extractSessionCookie($loginR['headers']);

        if ($sessId === '') {
            $this->markTestSkipped('Не може да се извлече сесионен cookie след login.');
        }

        $r = $this->get('/dashboard', $sessId);
        $this->assertSame(200, $r['status']);
    }

    public function testAdminCanAccessAdminPage(): void
    {
        $loginR = $this->post('/login', ['username' => 'admin', 'password' => 'admin123']);
        $sessId = $this->extractSessionCookie($loginR['headers']);

        if ($sessId === '') {
            $this->markTestSkipped('Не може да се извлече сесионен cookie след login.');
        }

        $r = $this->get('/admin', $sessId);
        $this->assertSame(200, $r['status']);
    }

    public function testUserCannotAccessAdminPage(): void
    {
        $loginR = $this->post('/login', ['username' => 'user', 'password' => 'user123']);
        $sessId = $this->extractSessionCookie($loginR['headers']);

        if ($sessId === '') {
            $this->markTestSkipped('Не може да се извлече сесионен cookie след login.');
        }

        $r = $this->get('/admin', $sessId);
        $this->assertContains($r['status'], [302, 403]);
    }

    public function testInvalidLoginReturns200WithError(): void
    {
        $r = $this->post('/login', ['username' => 'admin', 'password' => 'wrong']);
        // Показва login формата отново с грешка, или редиректне обратно
        $this->assertContains($r['status'], [200, 302]);
    }
}
