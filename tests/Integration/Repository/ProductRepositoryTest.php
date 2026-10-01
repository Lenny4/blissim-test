<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Database\Database;
use App\Exception\DatabaseException;
use App\Model\Product;
use App\Repository\ProductRepository;
use App\Tests\Support\TestDatabase;
use PHPUnit\Framework\TestCase;

final class ProductRepositoryTest extends TestCase
{
    private Database $database;
    private ProductRepository $productRepository;

    protected function setUp(): void
    {
        $this->database = TestDatabase::reset();
        $this->productRepository = new ProductRepository($this->database);
    }

    public function testSaveInsertsANewProductAndReturnsItWithItsId(): void
    {
        $saved = $this->productRepository->save(self::product('100', 'Nouveau produit', 1999));

        self::assertNotNull($saved->getId());
        self::assertSame('100', $saved->getSupplierProductId());
        self::assertSame('Nouveau produit', $saved->getTitle());

        $row = $this->row($saved->getId());
        self::assertSame('fakestore', $row['source']);
        self::assertSame('100', $row['supplier_product_id']);
        self::assertSame('Nouveau produit', $row['name']);
        self::assertSame(1999, $row['price_cents']);
    }

    public function testSaveWithIdenticalDataStillReturnsTheId(): void
    {
        $id = $this->productRepository->save(self::product('100', 'A', 100))->getId();

        // Aucune colonne ne change : le produit doit quand même être renvoyé avec son id
        self::assertSame($id, $this->productRepository->save(self::product('100', 'A', 100))->getId());
    }

    public function testProductWithoutSourceCannotBeSynchronised(): void
    {
        $this->expectException(\LogicException::class);

        $this->productRepository->save(new Product(null, null, null, 'Produit local', 100, '', '', 'a.png'));
    }

    public function testUpsertKeepsCreatedAtAndRefreshesUpdatedAt(): void
    {
        TestDatabase::moveDatesBackOneHour($this->database, 'product', 1);
        $before = $this->row(1);

        $this->productRepository->save(self::product('1', 'Nouveau nom', 11995));

        $after = $this->row(1);
        self::assertSame($before['created_at'], $after['created_at']);
        self::assertGreaterThan($before['updated_at'], $after['updated_at']);
    }

    public function testUpsertWithIdenticalDataDoesNotTouchUpdatedAt(): void
    {
        TestDatabase::moveDatesBackOneHour($this->database, 'product', 1);
        $before = $this->row(1);

        // Mêmes valeurs que la ligne existante : rien ne change, updated_at non plus
        $this->productRepository->save(new Product(
            null,
            'fakestore',
            '1',
            $before['name'],
            $before['price_cents'],
            $before['description'],
            $before['category'],
            $before['image_url'],
        ));

        self::assertSame($before['updated_at'], $this->row(1)['updated_at']);
    }

    public function testFindReturnsNullForAnUnknownId(): void
    {
        self::assertNull($this->productRepository->find(9999));
    }

    public function testSaveAllReturnsTheNumberOfProducts(): void
    {
        $count = $this->productRepository->saveAll([
            self::product('100', 'A', 100),
            self::product('101', 'B', 200),
        ]);

        self::assertSame(2, $count);
    }

    public function testSaveAllIsAllOrNothing(): void
    {
        $before = $this->productCount();

        try {
            $this->productRepository->saveAll([
                self::product('100', 'Valide', 100),
                // Nom trop long pour VARCHAR(255) : erreur PostgreSQL au 2e produit
                self::product('101', str_repeat('x', 300), 200),
            ]);
            self::fail('Une DatabaseException était attendue.');
        } catch (DatabaseException) {
        }

        // Le premier produit a été annulé avec le reste
        self::assertSame($before, $this->productCount());
    }

    private static function product(string $supplierProductId, string $title, int $priceCents): Product
    {
        return new Product(null, 'fakestore', $supplierProductId, $title, $priceCents, '', '', 'https://example.com/'.$supplierProductId.'.png');
    }

    /**
     * @return array<string, mixed>|false
     */
    private function row(int $id): array|false
    {
        return $this->database->execute('SELECT * FROM product WHERE id = :id', ['id' => $id])->fetch();
    }

    private function productCount(): int
    {
        return (int) $this->database->execute('SELECT COUNT(*) FROM product')->fetchColumn();
    }
}
