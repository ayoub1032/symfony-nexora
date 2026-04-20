<?php

namespace App\Command;

use App\Entity\Asset;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-assets',
    description: 'Seed the database with initial cryptocurrency assets',
)]
class SeedAssetsCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $assets = [
            ['name' => 'Ethereum', 'symbol' => 'ETH', 'value' => 2500.0, 'type' => 'Crypto'],
            ['name' => 'Solana', 'symbol' => 'SOL', 'value' => 100.0, 'type' => 'Crypto'],
            ['name' => 'Binance Coin', 'symbol' => 'BNB', 'value' => 450.0, 'type' => 'Crypto'],
            ['name' => 'Cardano', 'symbol' => 'ADA', 'value' => 0.45, 'type' => 'Crypto'],
            ['name' => 'Dogecoin', 'symbol' => 'DOGE', 'value' => 0.15, 'type' => 'Crypto'],
            ['name' => 'Polkadot', 'symbol' => 'DOT', 'value' => 7.0, 'type' => 'Crypto'],
            ['name' => 'Ripple', 'symbol' => 'XRP', 'value' => 0.55, 'type' => 'Crypto'],
            ['name' => 'Avalanche', 'symbol' => 'AVAX', 'value' => 35.0, 'type' => 'Crypto'],
            ['name' => 'Chainlink', 'symbol' => 'LINK', 'value' => 18.0, 'type' => 'Crypto'],
        ];

        $assetRepo = $this->entityManager->getRepository(Asset::class);

        foreach ($assets as $a) {
            $existing = $assetRepo->findOneBy(['symbol' => $a['symbol']]);
            if (!$existing) {
                $asset = new Asset();
                $asset->setName($a['name']);
                $asset->setSymbol($a['symbol']);
                $asset->setValue($a['value']);
                $asset->setType($a['type']);
                $this->entityManager->persist($asset);
                $io->note(sprintf('Adding %s (%s)', $a['name'], $a['symbol']));
            } else {
                $io->text(sprintf('%s (%s) already exists, skipping.', $a['name'], $a['symbol']));
            }
        }

        $this->entityManager->flush();
        $io->success('Assets seeded successfully!');

        return Command::SUCCESS;
    }
}
