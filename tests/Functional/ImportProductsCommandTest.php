<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Repository\ProductRepository;
use App\Tests\Support\TestDatabase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * L'API est simulée par FakeStoreMockResponseFactory (produits 1 et 2).
 */
final class ImportProductsCommandTest extends KernelTestCase
{
    public function testImportUpdatesTheCatalog(): void
    {
        TestDatabase::reset();
        $kernel = self::bootKernel();

        $commandTester = new CommandTester((new Application($kernel))->find('app:import-products'));
        $commandTester->execute([]);

        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('2 produits importés', $commandTester->getDisplay());

        self::assertSame('Produit de test 1', self::getContainer()->get(ProductRepository::class)->find(1)?->getTitle());
    }
}
