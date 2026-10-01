<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\DatabaseException;
use App\Exception\ProductApiException;
use App\Repository\ProductRepository;
use App\Service\Provider\ProductProviderStrategyInterface;

/**
 * Importe dans la table product le catalogue d'une source, quelle qu'elle soit :
 * c'est l'appelant qui choisit le provider (la stratégie).
 */
final class ProductImporter
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {
    }

    /**
     * @param ProductProviderStrategyInterface $productProviderStrategy source à importer ; sans cache, pour avoir des données à jour
     *
     * @return int nombre de produits importés
     *
     * @throws ProductApiException
     * @throws DatabaseException
     */
    public function import(ProductProviderStrategyInterface $productProviderStrategy): int
    {
        // On récupère tout avant d'écrire : si l'API échoue, la base n'est pas touchée
        $products = $productProviderStrategy->findAll();

        return $this->productRepository->saveAll($products);
    }
}
