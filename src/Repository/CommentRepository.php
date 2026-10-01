<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Database;
use App\Model\Comment;

/**
 * Persistance des commentaires via PDO (requêtes préparées, cf. Database::execute()).
 * product_id est une clé étrangère : le produit doit exister dans la table product.
 */
final class CommentRepository
{
    public function __construct(
        private readonly Database $database,
    ) {
    }

    /**
     * @return list<Comment>
     */
    public function findByProduct(int $productId): array
    {
        $statement = $this->database->execute(
            'SELECT id, product_id, author, content, created_at, updated_at
             FROM comment
             WHERE product_id = :product_id
             ORDER BY created_at DESC, id DESC',
            ['product_id' => $productId],
        );

        return array_map($this->hydrate(...), $statement->fetchAll());
    }

    public function find(int $id): ?Comment
    {
        $row = $this->database->execute(
            'SELECT id, product_id, author, content, created_at, updated_at FROM comment WHERE id = :id',
            ['id' => $id],
        )->fetch();

        return false === $row ? null : $this->hydrate($row);
    }

    public function create(int $productId, string $author, string $content): Comment
    {
        // created_at et updated_at sont remplis par la base : RETURNING les renvoie avec l'id
        $row = $this->database->execute(
            'INSERT INTO comment (product_id, author, content) VALUES (:product_id, :author, :content)
             RETURNING id, product_id, author, content, created_at, updated_at',
            [
                'product_id' => $productId,
                'author' => $author,
                'content' => $content,
            ],
        )->fetch();

        return $this->hydrate($row);
    }

    /**
     * updated_at est mis à jour par la base (trigger set_updated_at), seulement si une valeur change.
     */
    public function update(int $id, string $author, string $content): void
    {
        $this->database->execute(
            'UPDATE comment SET author = :author, content = :content WHERE id = :id',
            [
                'id' => $id,
                'author' => $author,
                'content' => $content,
            ],
        );
    }

    public function delete(int $id): void
    {
        $this->database->execute('DELETE FROM comment WHERE id = :id', ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Comment
    {
        return new Comment(
            id: (int) $row['id'],
            productId: (int) $row['product_id'],
            author: $row['author'],
            content: $row['content'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at']),
        );
    }
}
