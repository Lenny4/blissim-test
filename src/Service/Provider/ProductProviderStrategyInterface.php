<?php

declare(strict_types=1);

namespace App\Service\Provider;

use App\Exception\ProductApiException;
use App\Exception\ProductNotFoundException;
use App\Model\Product;

/**
 * Stratégie d'accès au catalogue produits : une implémentation par source (FakeStore aujourd'hui).
 * Les pages reçoivent CachedProductProvider (#[AsAlias]), qui enveloppe la stratégie active
 * (FakeStore, désignée par son #[Autowire]).
 */
interface ProductProviderStrategyInterface
{
    /**
     * Identifie la stratégie : le nom de la source ('fakestore'), complété par un décorateur
     * (ex. "fakestore.cache"). La valeur enregistrée en base est celle du produit ($product->getSource()).
     */
    public function source(): string;

    /**
     * @return list<Product>
     *
     * @throws ProductApiException
     */
    public function findAll(): array;

    /**
     * @param string $supplierProductId identifiant du produit chez la source
     *
     * @throws ProductNotFoundException
     * @throws ProductApiException
     */
    public function find(string $supplierProductId): Product;
}
