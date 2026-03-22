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

    public function testCsrfTestEndpointReturnsJson(): void
    {
        // Взимаме csrf_token от начална страница
        $init  = $this->get('/');
        $sessId = $this->extractSessionCookie($init['headers']);

        preg_match('/name="csrf_token"\s+value="([^"]+)"/', $init['body'], $m);
        $token = $m[1] ?? '';

        if ($token === '' || $sessId === '') {
            $this->markTestSkipped('Не можа да се извлече csrf_token или сесия.');
        }

        $r = $this->post('/?action=csrf-test', ['csrf_token' => $token], $sessId);
        $this->assertSame(200, $r['status']);
        $json = json_decode($r['body'], true);
        $this->assertArrayHasKey('ok', $json, 'CSRF test endpoint трябва да върне JSON с ключ "ok".');
        $this->assertTrue($json['ok'], 'Валиден CSRF токен трябва да върне ok=true.');
    }

    public function testCsrfTestWithWrongTokenReturnsError(): void
    {
        $init   = $this->get('/');
        $sessId = $this->extractSessionCookie($init['headers']);

        $r    = $this->post('/?action=csrf-test', ['csrf_token' => 'wrong-token'], $sessId);
        $json = json_decode($r['body'], true);
        $this->assertFalse($json['ok'] ?? true, 'Невалиден токен трябва да върне ok=false.');
    }

    public function testNewNonceEndpointReturnsNonce(): void
    {
        $r    = $this->get('/?action=new-nonce');
        $this->assertSame(200, $r['status']);
        $json = json_decode($r['body'], true);
        $this->assertArrayHasKey('nonce', $json, 'new-nonce endpoint трябва да върне JSON с ключ "nonce".');
    }

    public function testSqlDemoEndpointReturnsJson(): void
    {
        $r    = $this->get('/?action=sql-demo&input=Alice');
        $this->assertContains($r['status'], [200, 501]);
        $json = json_decode($r['body'], true);
        $this->assertIsArray($json, 'sql-demo endpoint трябва да върне JSON.');
    }

    public function testCspToggleDisablesHeader(): void
    {
        // Нова сесия с CSP по подразбиране включен
        $init   = $this->get('/');
        $sessId = $this->extractSessionCookie($init['headers']);

        if ($sessId === '') {
            $this->markTestSkipped('Не може да се извлече сесионен cookie.');
        }

        $cspBefore = $this->findHeader($init['headers'], 'Content-Security-Policy');
        $this->assertNotNull($cspBefore, 'CSP по подразбиране трябва да е включен.');

        // Изключване на CSP
        $toggle = $this->get('/?action=toggle-csp', $sessId);
        // toggle-csp редиректва към /
        $this->assertSame(302, $toggle['status']);

        // Нов GET без редирект
        $afterToggle = $this->get('/', $sessId);
        $cspAfter = $this->findHeader($afterToggle['headers'], 'Content-Security-Policy');
        $this->assertNull($cspAfter, 'След изключване CSP хедърът не трябва да присъства.');

        // Включване обратно
        $this->get('/?action=toggle-csp', $sessId);
    }
}
