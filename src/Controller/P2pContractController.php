<?php

namespace App\Controller;

use App\Entity\P2pContract;
use App\Repository\AssetRepository;
use App\Repository\P2pContractRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class P2pContractController extends AbstractController
{
    #[Route('/p2p-contracts', name: 'p2p_contract_index', methods: ['GET'])]
    public function index(P2pContractRepository $contractRepository, AssetRepository $assetRepository, Request $request): Response
    {
        if (!$request->getSession()->get('role')) {
            return $this->redirectToRoute('app_login');
        }

        $contracts = $contractRepository->findAll();
        $assets = $assetRepository->findAll();
        $totalContracts = count($contracts);

        return $this->render('p2p_contract/index.html.twig', [
            'contracts' => $contracts,
            'assets' => $assets,
            'activeCount' => $totalContracts,
        ]);
    }

    #[Route('/p2p-contracts/create', name: 'p2p_contract_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, AssetRepository $assetRepository, ValidatorInterface $validator): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('p2p_contract_index');
        }

        $assetId = (int)$request->request->get('asset_id');
        $asset = $assetRepository->find($assetId);

        if (!$asset) {
            $this->addFlash('danger', 'Selected Asset not found.');
            return $this->redirectToRoute('p2p_contract_index');
        }

        $contract = new P2pContract();
        $contract->setCreatorId((int)$request->request->get('creatorId'));
        $contract->setAsset($asset);
        $contract->setQuantity((int)$request->request->get('quantity'));
        $contract->setPricePerUnit((float)$request->request->get('pricePerUnit'));
        $contract->setContractType(strtoupper(trim((string)$request->request->get('contractType'))));
        $contract->setStatus(strtoupper(trim((string)$request->request->get('status', 'OPEN'))));

        $errors = $validator->validate($contract);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }

            return $this->redirectToRoute('p2p_contract_index');
        }

        $entityManager->persist($contract);
        $entityManager->flush();
        $this->addFlash('success', 'P2P Contract created successfully!');

        return $this->redirectToRoute('p2p_contract_index');
    }

    #[Route('/p2p-contracts/update/{id}', name: 'p2p_contract_update', methods: ['POST'])]
    public function update(P2pContract $contract, Request $request, EntityManagerInterface $entityManager, AssetRepository $assetRepository, ValidatorInterface $validator): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('p2p_contract_index');
        }

        $assetId = (int)$request->request->get('asset_id');
        $asset = $assetRepository->find($assetId);
        if ($asset) {
            $contract->setAsset($asset);
        }

        $contract->setCreatorId((int)$request->request->get('creatorId'));
        $contract->setQuantity((int)$request->request->get('quantity'));
        $contract->setPricePerUnit((float)$request->request->get('pricePerUnit'));
        $contract->setContractType(strtoupper(trim((string)$request->request->get('contractType'))));
        $contract->setStatus(strtoupper(trim((string)$request->request->get('status'))));

        $errors = $validator->validate($contract);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }

            return $this->redirectToRoute('p2p_contract_index');
        }

        $entityManager->flush();
        $this->addFlash('success', 'P2P Contract updated successfully!');

        return $this->redirectToRoute('p2p_contract_index');
    }

    #[Route('/p2p-contracts/delete/{id}', name: 'p2p_contract_delete', methods: ['POST'])]
    public function delete(P2pContract $contract, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('p2p_contract_index');
        }

        $entityManager->remove($contract);
        $entityManager->flush();
        $this->addFlash('success', 'P2P Contract deleted successfully.');

        return $this->redirectToRoute('p2p_contract_index');
    }
}
