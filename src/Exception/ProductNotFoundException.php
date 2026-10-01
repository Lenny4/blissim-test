<?php

declare(strict_types=1);

namespace App\Exception;

final class ProductNotFoundException extends \RuntimeException
{
    public static function forPath(string $path): self
    {
        return new self(sprintf('Aucun produit trouvé à l\'adresse %s.', $path));
    }
}
