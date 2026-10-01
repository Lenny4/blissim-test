<?php

declare(strict_types=1);

namespace App\Service\Provider;

use App\Model\Product;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Décorateur : met en cache les réponses de l'API pour éviter un appel réseau à chaque page.
 * Les exceptions ne sont pas mises en cache : un échec sera retenté à la requête suivante.
 *
 * Il peut envelopper n'importe quelle stratégie : les clés de cache sont préfixées par la
 * source, deux sources ne partagent donc jamais une entrée (ex. le produit "1" de chacune).
 * La stratégie enveloppée est désignée par l'attribut #[Autowire] du constructeur.
 *
 * #[AsAlias] : c'est cette classe qui est injectée partout où l'interface est demandée (pages).
 */
#[AsAlias(ProductProviderStrategyInterface::class)]
final class CachedProductProvider implements ProductProviderStrategyInterface
{
    private const TTL = 600;

    public function __construct(
        #[Autowire(service: FakeStoreProviderStrategy::class)]
        private readonly ProductProviderStrategyInterface $productProviderStrategy,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * Ex. "fakestore.cache" : la source enveloppée, en précisant que les réponses viennent du cache.
     */
    public function source(): string
    {
        return sprintf('%s.cache', $this->productProviderStrategy->source());
    }

    public function findAll(): array
    {
        return $this->cache->get($this->key('products'), function (ItemInterface $item): array {
            $item->expiresAfter(self::TTL);

            return $this->productProviderStrategy->findAll();
        });
    }

    public function find(string $supplierProductId): Product
    {
        return $this->cache->get($this->key('product.'.$supplierProductId), function (ItemInterface $item) use ($supplierProductId): Product {
            $item->expiresAfter(self::TTL);

            return $this->productProviderStrategy->find($supplierProductId);
        });
    }

    /**
     * Préfixe la clé par la source : "product.1" devient "fakestore.product.1".
     * rawurlencode : une clé de cache ne doit pas contenir de caractères réservés ({}()/\@:),
     * qui pourraient venir de la source ou de l'id (le point, lui, est conservé).
     */
    private function key(string $key): string
    {
        return rawurlencode($this->productProviderStrategy->source()).'.'.rawurlencode($key);
    }
}
