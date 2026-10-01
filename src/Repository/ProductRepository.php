<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Database;
use App\Exception\DatabaseException;
use App\Model\Product;

/**
 * Table product : copie locale des catalogues externes (FakeStore) et produits propres à l'application.
 */
final class ProductRepository
{
    public function __construct(
        private readonly Database $database,
    ) {
    }

    /**
     * @param int $id identifiant de l'application
     *
     * @throws DatabaseException
     */
    public function find(int $id): ?Product
    {
        $row = $this->database->execute(
            'SELECT id, source, supplier_product_id, name, description, category, image_url, price_cents
             FROM product WHERE id = :id',
            ['id' => $id],
        )->fetch();

        return false === $row ? null : $this->hydrate($row);
    }

    /**
     * @throws DatabaseException
     */
    public function findBySupplier(string $source, string $supplierProductId): ?Product
    {
        $row = $this->database->execute(
            'SELECT id, source, supplier_product_id, name, description, category, image_url, price_cents
             FROM product WHERE source = :source AND supplier_product_id = :supplier_product_id',
            ['source' => $source, 'supplier_product_id' => $supplierProductId],
        )->fetch();

        return false === $row ? null : $this->hydrate($row);
    }

    /**
     * Enregistre un produit externe (voir upsert()) et le renvoie avec son identifiant de l'application.
     *
     * @throws DatabaseException
     */
    public function save(Product $product): Product
    {
        $this->upsert($product);

        return $product;
    }

    /**
     * Enregistre les produits externes (voir saveAll()) et les renvoie avec leur identifiant
     * de l'application, dans le même ordre.
     * Les pages ont besoin de cet identifiant : c'est lui qui figure dans l'URL de la fiche.
     *
     * @param list<Product> $products
     *
     * @return list<Product>
     *
     * @throws DatabaseException
     */
    public function synchronize(array $products): array
    {
        $this->saveAll($products);

        return $products;
    }

    /**
     * @param iterable<Product> $products
     *
     * @throws DatabaseException
     */
    public function saveAll(iterable $products): int
    {
        $pdo = $this->database->pdo();
        $count = 0;

        // Tout ou rien : un import interrompu ne laisse pas un catalogue à moitié à jour
        $pdo->beginTransaction();

        try {
            foreach ($products as $product) {
                $this->upsert($product);
                ++$count;
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }

        return $count;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Product
    {
        return new Product(
            id: (int) $row['id'],
            source: $row['source'],
            supplierProductId: $row['supplier_product_id'],
            title: $row['name'],
            priceCents: (int) $row['price_cents'],
            description: (string) $row['description'],
            category: $row['category'],
            image: $row['image_url'],
        );
    }

    /**
     * Insère le produit, ou le met à jour s'il existe déjà : upsert sur la clé unique
     * source + supplier_product_id (l'id n'est pas fourni, la clé primaire ne peut donc
     * pas entrer en conflit). Une seule requête atomique : pas de "SELECT puis INSERT"
     * sujet aux accès concurrents.
     * RETURNING renvoie l'identifiant de l'application, inséré ou existant, qui est renseigné sur le produit.
     *
     * @throws DatabaseException
     */
    private function upsert(Product $product): void
    {
        if (null === $product->getSource() || null === $product->getSupplierProductId()) {
            throw new \LogicException('Seul un produit provenant d\'une source externe peut être synchronisé.');
        }

        $id = $this->database->execute(
            'INSERT INTO product (source, supplier_product_id, name, description, category, image_url, price_cents)
             VALUES (:source, :supplier_product_id, :name, :description, :category, :image_url, :price_cents)
             ON CONFLICT (source, supplier_product_id) DO UPDATE SET
                name = EXCLUDED.name,
                description = EXCLUDED.description,
                category = EXCLUDED.category,
                image_url = EXCLUDED.image_url,
                price_cents = EXCLUDED.price_cents
             RETURNING id',
            [
                'source' => $product->getSource(),
                'supplier_product_id' => $product->getSupplierProductId(),
                'name' => $product->getTitle(),
                'description' => $product->getDescription(),
                'category' => $product->getCategory(),
                'image_url' => $product->getImage(),
                'price_cents' => $product->getPriceCents(),
            ],
        )->fetchColumn();

        $product->setId((int) $id);
    }
}
