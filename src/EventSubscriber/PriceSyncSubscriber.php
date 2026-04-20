<?php

namespace App\EventSubscriber;

use App\Service\AssetPriceService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class PriceSyncSubscriber implements EventSubscriberInterface
{
    private AssetPriceService $assetPriceService;
    private CacheInterface $cache;

    public function __construct(AssetPriceService $assetPriceService, CacheInterface $cache)
    {
        $this->assetPriceService = $assetPriceService;
        $this->cache = $cache;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Target routes that require fresh prices
        $targetRoutes = ['portfolio_index', 'order_index', 'p2p_contract_index', 'app_home'];

        if (in_array($route, $targetRoutes)) {
            // Check cooldown (2 minutes = 120 seconds)
            $this->cache->get('last_price_sync_trigger', function (ItemInterface $item) {
                $item->expiresAfter(120);
                
                // Trigger the sync
                $this->assetPriceService->syncAssetPrices();
                $this->assetPriceService->recalculatePortfolios();
                
                return true; 
            });
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }
}
