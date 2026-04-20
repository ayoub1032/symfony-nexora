<?php

namespace App\Controller;

use App\Entity\Asset;
use App\Repository\AssetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Service\AssetPriceService;

class AssetController extends AbstractController
{
    #[Route('/market-assets', name: 'asset_index', methods: ['GET'])]
    public function index(AssetRepository $assetRepository, Request $request): Response
    {
        if (!$request->getSession()->get('role')) {
            return $this->redirectToRoute('app_login');
        }

        $assets = $assetRepository->findAll();
        $totalAssets = count($assets);

        return $this->render('asset/index.html.twig', [
            'assets' => $assets,
            'activeCount' => $totalAssets,
        ]);
    }

    #[Route('/market-assets/create', name: 'asset_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('asset_index');
        }

        $asset = new Asset();
        $asset->setName((string)$request->request->get('name'));
        $asset->setSymbol((string)$request->request->get('symbol'));
        $asset->setValue((float)$request->request->get('value', 0));
        $asset->setType((string)$request->request->get('type'));

        $errors = $validator->validate($asset);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }

            return $this->redirectToRoute('asset_index');
        }

        $entityManager->persist($asset);
        $entityManager->flush();
        $this->addFlash('success', 'Asset created successfully!');

        return $this->redirectToRoute('asset_index');
    }

    #[Route('/market-assets/update/{id}', name: 'asset_update', methods: ['POST'])]
    public function update(Asset $asset, Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('asset_index');
        }

        $asset->setName((string)$request->request->get('name'));
        $asset->setSymbol((string)$request->request->get('symbol'));
        $asset->setValue((float)$request->request->get('value', 0));
        $asset->setType((string)$request->request->get('type'));

        $errors = $validator->validate($asset);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }

            return $this->redirectToRoute('asset_index');
        }

        $entityManager->flush();
        $this->addFlash('success', 'Asset updated successfully!');

        return $this->redirectToRoute('asset_index');
    }

    #[Route('/market-assets/delete/{id}', name: 'asset_delete', methods: ['POST'])]
    public function delete(Asset $asset, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('asset_index');
        }

        $entityManager->remove($asset);
        $entityManager->flush();
        $this->addFlash('success', 'Asset deleted successfully.');

        return $this->redirectToRoute('asset_index');
    }
    #[Route('/market-assets/sync', name: 'asset_sync', methods: ['POST'])]
    public function sync(AssetPriceService $priceService, Request $request): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('asset_index');
        }

        $updated = $priceService->syncAssetPrices();
        $priceService->recalculatePortfolios();

        $this->addFlash('success', sprintf('Successfully synced %d asset prices and recalculated portfolios!', $updated));
        
        return $this->redirectToRoute('asset_index');
    }
}
