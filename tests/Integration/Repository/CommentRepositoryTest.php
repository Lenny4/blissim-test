<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Database\Database;
use App\Exception\DatabaseException;
use App\Repository\CommentRepository;
use App\Tests\Support\TestDatabase;
use PHPUnit\Framework\TestCase;

final class CommentRepositoryTest extends TestCase
{
    private Database $database;
    private CommentRepository $commentRepository;

    protected function setUp(): void
    {
        // Les produits 1 à 6 existent dans le jeu de données de blissim.sql
        $this->database = TestDatabase::reset();
        $this->commentRepository = new CommentRepository($this->database);
    }

    public function testCreateAndFind(): void
    {
        $created = $this->commentRepository->create(1, 'Camille', 'Très bon produit');

        $found = $this->commentRepository->find($created->getId());

        self::assertNotNull($found);
        self::assertSame(1, $found->getProductId());
        self::assertSame('Camille', $found->getAuthor());
        self::assertSame('Très bon produit', $found->getContent());
    }

    public function testDatesAreSetByTheDatabase(): void
    {
        $comment = $this->commentRepository->create(1, 'Camille', 'Nouveau');

        self::assertEqualsWithDelta(time(), $comment->getCreatedAt()->getTimestamp(), 5);
        self::assertEquals($comment->getCreatedAt(), $comment->getUpdatedAt());
        self::assertFalse($comment->isEdited());
    }

    public function testFindByProductOnlyReturnsCommentsOfThatProduct(): void
    {
        $this->commentRepository->create(1, 'Camille', 'Premier');
        $this->commentRepository->create(2, 'Lucas', 'Autre produit');
        $this->commentRepository->create(1, 'Léa', 'Second');

        $comments = $this->commentRepository->findByProduct(1);

        self::assertCount(2, $comments);
    }

    public function testUpdate(): void
    {
        $comment = $this->commentRepository->create(1, 'Camille', 'Avant');
        TestDatabase::moveDatesBackOneHour($this->database, 'comment', $comment->getId());
        $createdAt = $this->commentRepository->find($comment->getId())?->getCreatedAt();

        $this->commentRepository->update($comment->getId(), 'Camille M.', 'Après');

        $updated = $this->commentRepository->find($comment->getId());
        self::assertSame('Camille M.', $updated?->getAuthor());
        self::assertSame('Après', $updated?->getContent());
        // created_at ne bouge pas, updated_at avance
        self::assertEquals($createdAt, $updated?->getCreatedAt());
        self::assertTrue($updated?->isEdited());
    }

    public function testDelete(): void
    {
        $comment = $this->commentRepository->create(1, 'Camille', 'À supprimer');

        $this->commentRepository->delete($comment->getId());

        self::assertNull($this->commentRepository->find($comment->getId()));
    }

    public function testSqlInjectionAttemptIsStoredAsPlainText(): void
    {
        $payload = "'; DROP TABLE comment;";
        $comment = $this->commentRepository->create(1, $payload, $payload);

        self::assertSame($payload, $this->commentRepository->find($comment->getId())?->getContent());
    }
}
