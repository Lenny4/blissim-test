<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\DatabaseException;
use App\Exception\ProductApiException;
use App\Service\ProductImporter;
use App\Service\Provider\FakeStoreProviderStrategy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-products',
    description: 'Importe (ou met à jour) le catalogue FakeStore dans la table product',
)]
final class ImportProductsCommand extends Command
{
    public function __construct(
        private readonly ProductImporter $productImporter,
        // Classe concrète, pas l'interface : l'import interroge l'API directement, sans le cache des pages
        private readonly FakeStoreProviderStrategy $fakeStoreProviderStrategy,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $count = $this->productImporter->import($this->fakeStoreProviderStrategy);
        } catch (ProductApiException|DatabaseException $e) {
            $io->error(sprintf('Import impossible : %s', $e->getMessage()));

            return Command::FAILURE;
        }

        $io->success(sprintf('%d produits importés.', $count));

        return Command::SUCCESS;
    }
}
