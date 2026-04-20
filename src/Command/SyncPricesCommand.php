<?php

namespace App\Command;

use App\Service\AssetPriceService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-prices',
    description: 'Fetch real-time asset prices from market APIs and update portfolios',
)]
class SyncPricesCommand extends Command
{
    private AssetPriceService $assetPriceService;

    public function __construct(AssetPriceService $assetPriceService)
    {
        parent::__construct();
        $this->assetPriceService = $assetPriceService;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Syncing Asset Prices');
        $io->text('Fetching latest data from CoinGecko...');

        $updatedCount = $this->assetPriceService->syncAssetPrices();

        if ($updatedCount > 0) {
            $io->success(sprintf('Successfully synced %d asset prices.', $updatedCount));
            
            $io->text('Recalculating all user portfolios...');
            $this->assetPriceService->recalculatePortfolios();
            $io->success('Portfolios recalculated successfully.');
        } else {
            $io->warning('No assets were updated. Check if your assets have symbols mapped in AssetPriceService or if API is reachable.');
        }

        return Command::SUCCESS;
    }
}
