<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Simule l'API FakeStore pendant les tests fonctionnels (aucun appel réseau réel).
 * Branché via framework.http_client.mock_response_factory (config/packages/http_client.yaml).
 */
final class FakeStoreMockResponseFactory
{
    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        $path = (string) parse_url($url, \PHP_URL_PATH);

        if ('/products' === $path) {
            return $this->json([self::product(1), self::product(2)]);
        }

        if (preg_match('#^/products/(\d+)$#', $path, $matches)) {
            $id = (int) $matches[1];

            return match ($id) {
                1, 2 => $this->json(self::product($id)),
                // Comportement réel de l'API : 200 avec un corps vide
                default => new MockResponse(''),
            };
        }

        return new MockResponse('', ['http_code' => 404]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function product(int $id): array
    {
        return [
            'id' => $id,
            'title' => 'Produit de test '.$id,
            'price' => 10.5 * $id,
            'description' => 'Description du produit '.$id,
            'category' => 'test',
            'image' => 'https://example.com/'.$id.'.png',
        ];
    }

    private function json(mixed $data): MockResponse
    {
        return new MockResponse(json_encode($data, \JSON_THROW_ON_ERROR), [
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }
}
