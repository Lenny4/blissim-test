<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\TestDatabase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'API est simulée par FakeStoreMockResponseFactory ; la base blissim_test est recréée avant chaque test.
 */
final class ProductControllerTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        TestDatabase::reset();
        $this->client = static::createClient();
    }

    public function testCatalogListsProducts(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('.card'));
        self::assertSelectorTextContains('.card-title', 'Produit de test 1');
    }

    public function testProductPageShowsDetailsAndCommentForm(): void
    {
        $this->client->request('GET', '/products/2');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Produit de test 2');
        self::assertSelectorTextContains('.card-body', '21,00 €');
        self::assertSelectorTextContains('#comments', 'Aucun commentaire');
        self::assertSelectorExists('form[name="comment"]');
    }

    public function testUnknownProductReturns404(): void
    {
        $this->client->request('GET', '/products/9999');

        self::assertResponseStatusCodeSame(404);
    }

    public function testInvalidCommentIsRejected(): void
    {
        $crawler = $this->client->request('GET', '/products/1');
        $this->client->submit($crawler->selectButton('Publier')->form([
            'comment[author]' => '',
            'comment[content]' => 'ok',
        ]));

        self::assertResponseStatusCodeSame(422);
    }
}
