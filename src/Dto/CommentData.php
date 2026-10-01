<?php

declare(strict_types=1);

namespace App\Dto;

use App\Model\Comment;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Données saisies dans le formulaire de commentaire (création et modification).
 */
final class CommentData
{
    #[Assert\NotBlank(message: 'Merci d\'indiquer votre nom.')]
    #[Assert\Length(max: 100)]
    private ?string $author = null;

    #[Assert\NotBlank(message: 'Le commentaire ne peut pas être vide.')]
    #[Assert\Length(min: 3, max: 2000)]
    private ?string $content = null;

    public static function fromComment(Comment $comment): self
    {
        $commentData = new self();
        $commentData->author = $comment->getAuthor();
        $commentData->content = $comment->getContent();

        return $commentData;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }
}
