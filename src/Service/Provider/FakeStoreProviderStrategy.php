<?php

declare(strict_types=1);

namespace App\Service\Provider;

use App\Exception\ProductApiException;
use App\Exception\ProductNotFoundException;
use App\Model\Product;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Accès aux produits via https://fakestoreapi.com.
 *
 * Toutes les erreurs techniques (réseau, statut HTTP, JSON invalide, structure
 * inattendue) sont converties en ProductApiException pour que le reste de
 * l'application n'ait pas à connaître le client HTTP.
 */
final class FakeStoreProviderStrategy implements ProductProviderStrategyInterface
{
    /** Valeur de product.source pour les produits de cette source */
    public const SOURCE = 'fakestore';

    public function __construct(
        private readonly HttpClientInterface $fakestoreClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function source(): string
    {
        return self::SOURCE;
    }

    public function findAll(): array
    {
        try {
            $data = $this->get('/products');
        } catch (ProductNotFoundException $e) {
            // La liste existe toujours : son absence est une panne de l'API, pas un 404
            throw new ProductApiException('La liste des produits renvoyée par l\'API est vide.', 0, $e);
        }

        if (!array_is_list($data)) {
            throw new ProductApiException('La liste des produits renvoyée par l\'API est invalide.');
        }

        return array_map($this->hydrate(...), $data);
    }

    public function find(string $supplierProductId): Product
    {
        return $this->hydrate($this->get('/products/'.rawurlencode($supplierProductId)));
    }

    /**
     * @return array<mixed>
     *
     * @throws ProductNotFoundException la ressource n'existe pas (404, ou 200 avec un corps vide)
     * @throws ProductApiException      l'API est injoignable ou sa réponse est inexploitable
     */
    private function get(string $path): array
    {
        try {
            $response = $this->fakestoreClient->request('GET', $path);

            if (404 === $response->getStatusCode()) {
                throw ProductNotFoundException::forPath($path);
            }

            // getContent() lève une exception pour les statuts 3xx/4xx/5xx
            $content = $response->getContent();
        } catch (HttpClientException $e) {
            $this->logger->error('Appel à l\'API FakeStore en échec', ['path' => $path, 'exception' => $e]);

            throw new ProductApiException('Le catalogue produits est momentanément indisponible.', 0, $e);
        }

        if ('' === trim($content)) {
            throw ProductNotFoundException::forPath($path);
        }

        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->error('Réponse JSON invalide de l\'API FakeStore', ['path' => $path, 'exception' => $e]);

            throw new ProductApiException('Le catalogue produits a renvoyé une réponse invalide.', 0, $e);
        }

        if (!\is_array($data)) {
            throw new ProductApiException('Le catalogue produits a renvoyé une réponse invalide.');
        }

        return $data;
    }

    /**
     * Convertit un produit au format FakeStore en Product. Seule cette classe connaît ce format :
     * title, price en euros décimaux, image.
     *
     * @throws ProductApiException si la structure ne correspond pas à un produit
     */
    private function hydrate(mixed $item): Product
    {
        if (!\is_array($item)) {
            throw $this->invalidProduct('un produit doit être un objet JSON');
        }

        foreach (['id', 'title', 'price', 'image'] as $field) {
            if (!isset($item[$field])) {
                throw $this->invalidProduct(sprintf('champ "%s" manquant', $field));
            }
        }

        if (!\is_int($item['id']) && !\is_string($item['id'])) {
            throw $this->invalidProduct('le champ "id" doit être un entier ou une chaîne');
        }

        if (!is_numeric($item['price'])) {
            throw $this->invalidProduct('le champ "price" doit être numérique');
        }

        if ($item['price'] < 0) {
            throw $this->invalidProduct('le prix ne peut pas être négatif');
        }

        return new Product(
            id: null,
            source: self::SOURCE,
            supplierProductId: (string) $item['id'],
            title: trim((string) $item['title']),
            priceCents: self::toCents($item['price']),
            description: (string) ($item['description'] ?? ''),
            category: (string) ($item['category'] ?? ''),
            image: (string) $item['image'],
        );
    }

    /**
     * L'API renvoie des euros en nombre décimal (ex. 109.95). La multiplication en
     * flottant n'est pas exacte (0.29 * 100 = 28.999…), d'où l'arrondi avant la conversion.
     */
    private static function toCents(int|float|string $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    private function invalidProduct(string $reason): ProductApiException
    {
        $this->logger->error('Produit FakeStore mal formé', ['reason' => $reason]);

        return new ProductApiException(sprintf('Le catalogue produits a renvoyé un produit invalide : %s.', $reason));
    }
}
