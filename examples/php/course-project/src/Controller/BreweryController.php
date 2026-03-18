<?php
declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;

/**
 * BreweryController – демонстрира заявка към external REST API.
 *
 * Използва Open Brewery DB: https://www.openbrewerydb.org/
 * Endpoint: GET https://api.openbrewerydb.org/v1/breweries?by_city=...
 */
class BreweryController
{
    private const API_BASE = 'https://api.openbrewerydb.org/v1/breweries';

    public function index(array $params = []): void
    {
        $city      = Request::query('city', 'sofia');
        $perPage   = 12;
        $breweries = [];
        $error     = null;

        try {
            $breweries = $this->fetchBreweries($city, $perPage);
        } catch (\RuntimeException $e) {
            $error = $e->getMessage();
        }

        require dirname(__DIR__, 2) . '/views/brewery/index.php';
    }

    /**
     * Извиква Open Brewery DB API с cURL.
     *
     * @return array<int, array<string,mixed>>
     */
    private function fetchBreweries(string $city, int $perPage): array
    {
        $url = self::API_BASE . '?' . http_build_query([
            'by_city' => $city,
            'per_page' => $perPage,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            CURLOPT_USERAGENT      => 'SWP-CourseProject/1.0',
            // SSRF protection: само HTTPS, без вътрешни IP адреси
            CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException("Грешка при свързване с API: $curlErr");
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("API върна статус $httpCode.");
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Невалиден отговор от API.');
        }

        return $data;
    }
}
