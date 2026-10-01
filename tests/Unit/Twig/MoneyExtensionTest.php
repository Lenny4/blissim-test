<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\MoneyExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyExtensionTest extends TestCase
{
    #[DataProvider('amounts')]
    public function testFormatCents(int $cents, string $expected): void
    {
        self::assertSame($expected, (new MoneyExtension())->formatCents($cents));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function amounts(): array
    {
        return [
            'zéro' => [0, '0,00 €'],
            'centimes seuls' => [5, '0,05 €'],
            'prix courant' => [2490, '24,90 €'],
            'milliers' => [1099500, "10\u{202F}995,00 €"],
            'négatif (remboursement)' => [-1550, '-15,50 €'],
        ];
    }
}
