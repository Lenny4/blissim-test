<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Produit du catalogue.
 *
 * Trois identifiants, comme dans la table product :
 * - id : identifiant de l'application, null tant que le produit n'est pas enregistré en base ;
 * - source : origine du produit ('fakestore'), null pour un produit créé dans l'application ;
 * - supplierProductId : identifiant du produit chez la source (null si pas de source).
 *
 * Le prix est exprimé en centimes (entier), comme en base : 24,90 € => 2490.
 *
 * Le modèle ne connaît aucun format externe : chaque source (FakeStoreProviderStrategy)
 * et le repository construisent eux-mêmes le produit à partir de leurs données.
 */
final class Product
{
    public function __construct(
        private ?int $id,
        private ?string $source,
        private ?string $supplierProductId,
        private string $title,
        private int $priceCents,
        private string $description,
        private string $category,
        private string $image,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getSupplierProductId(): ?string
    {
        return $this->supplierProductId;
    }

    public function setSupplierProductId(?string $supplierProductId): static
    {
        $this->supplierProductId = $supplierProductId;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getPriceCents(): int
    {
        return $this->priceCents;
    }

    public function setPriceCents(int $priceCents): static
    {
        $this->priceCents = $priceCents;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getImage(): string
    {
        return $this->image;
    }

    public function setImage(string $image): static
    {
        $this->image = $image;

        return $this;
    }
}
