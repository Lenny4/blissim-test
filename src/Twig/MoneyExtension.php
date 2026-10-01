<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFilter;

final class MoneyExtension
{
    /**
     * Formate un montant en centimes pour l'affichage : 1099500 => "10 995,00 €".
     * Calcul en entiers uniquement, sans passer par un flottant.
     */
    #[AsTwigFilter('format_cents')]
    public function formatCents(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $cents = abs($cents);

        return sprintf(
            '%s%s,%02d €',
            $sign,
            number_format(intdiv($cents, 100), 0, ',', "\u{202F}"),
            $cents % 100,
        );
    }
}
