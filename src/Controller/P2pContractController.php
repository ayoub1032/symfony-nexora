<?php

namespace App\Controller;

use App\Entity\P2pContract;
use App\Entity\ActivityLog;
use App\Entity\Transaction;
use App\Entity\UserReputation;
use App\Entity\PortfolioAsset;
use App\Repository\AssetRepository;
use App\Repository\P2pContractRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class P2pContractController extends AbstractController
{
    #[Route('/p2p-contracts', name: 'p2p_contract_index', methods: ['GET'])]
    public function index(
        P2pContractRepository $contractRepository, 
        AssetRepository $assetRepository, 
        UserRepository $userRepository, 
        Request $request,
        \App\Service\ReputationService $reputationService
    ): Response
    {
        if (!$request->getSession()->get('role')) {
            return $this->redirectToRoute('app_login');
        }

        $walletBalance = 0;
        $portfolioAssets = [];

        if ($request->getSession()->get('role') === 'ADMIN') {
            $contracts = $contractRepository->findAll();
        } else {
            $userId = (int)$request->getSession()->get('user_id');
            $user = $userRepository->find($userId);

            // Get wallet balance and portfolio assets for form validation
            if ($user) {
                $walletBalance = $user->getWallet() ? (float)$user->getWallet()->getBalance() : 0;
                $portfolio = $user->getPortfolio();
                if ($portfolio) {
                    foreach ($portfolio->getPortfolioAssets() as $pa) {
                        $portfolioAssets[$pa->getAsset()->getId()] = [
                            'name'     => $pa->getAsset()->getName(),
                            'symbol'   => $pa->getAsset()->getSymbol(),
                            'quantity' => $pa->getQuantity(),
                        ];
                    }
                }
            }

            $qb = $contractRepository->createQueryBuilder('c');
            $contracts = $qb->where('c.creator = :userId')
               ->orWhere('c.status = :status')
               ->setParameter('userId', $userId)
               ->setParameter('status', 'OPEN')
               ->getQuery()
               ->getResult();
        }
        $assets = $assetRepository->findAll();
        $totalContracts = count($contracts);

        // Fetch reputation for all creators
        $reputations = [];
        foreach ($contracts as $c) {
            if ($c->getCreator()) {
                $reputations[$c->getCreator()->getId()] = $reputationService->getReputationStats($c->getCreator());
            }
        }

        return $this->render('p2p_contract/index.html.twig', [
            'contracts'      => $contracts,
            'assets'         => $assets,
            'activeCount'    => $totalContracts,
            'walletBalance'  => $walletBalance,
            'portfolioAssets' => $portfolioAssets,
            'reputations'    => $reputations,
        ]);
    }

    #[Route('/p2p-contracts/create', name: 'p2p_contract_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, AssetRepository $assetRepository, UserRepository $userRepository, ValidatorInterface $validator): Response
    {
        $role = $request->getSession()->get('role');
        if (!$role) {
            return $this->redirectToRoute('app_login');
        }

        $assetId = (int)$request->request->get('asset_id');
        $asset = $assetRepository->find($assetId);

        if (!$asset) {
            $this->addFlash('danger', 'Selected Asset not found.');
            return $this->redirectToRoute('p2p_contract_index');
        }

        if ($role === 'ADMIN') {
            $creatorId = (int)$request->request->get('creatorId');
        } else {
            $creatorId = (int)$request->getSession()->get('user_id');
        }

        $creator = $userRepository->find($creatorId);

        if (!$creator) {
            $this->addFlash('danger', 'Selected Creator not found.');
            return $this->redirectToRoute('p2p_contract_index');
        }

        $contractType = strtoupper(trim((string)$request->request->get('contractType')));
        $quantity = (int)$request->request->get('quantity');
        $pricePerUnit = (float)$request->request->get('pricePerUnit');
        $totalCost = $quantity * $pricePerUnit;

        // Escrow phase logic
        $wallet = $creator->getWallet();
        $portfolio = $creator->getPortfolio();

        if (!$wallet || !$portfolio) {
            $this->addFlash('danger', 'Creator must have a wallet and a portfolio to create a P2P contract.');
            return $this->redirectToRoute('p2p_contract_index');
        }

        if ($contractType === 'BUY') {
            if ((float)$wallet->getBalance() < $totalCost) {
                $this->addFlash('danger', 'Insufficient funds to hold in escrow for this BUY contract.');
                return $this->redirectToRoute('p2p_contract_index');
            }
            // Lock funds in escrow (deducting from wallet balance temporarily)
            $wallet->setBalance((string)((float)$wallet->getBalance() - $totalCost));
            
            $log = new ActivityLog();
            $log->setWallet($wallet);
            $log->setMessage(sprintf('Escrow: %s funds locked for P2P BUY contract of %d %s', $totalCost, $quantity, $asset->getSymbol()));
            $log->setType('warning');
            $entityManager->persist($log);
            
        } elseif ($contractType === 'SELL') {
            $portfolioAsset = null;
            foreach ($portfolio->getPortfolioAssets() as $pa) {
                if ($pa->getAsset()->getId() === $asset->getId()) {
                    $portfolioAsset = $pa;
                    break;
                }
            }
            if (!$portfolioAsset || $portfolioAsset->getQuantity() < $quantity) {
                $this->addFlash('danger', 'Insufficient asset quantity in portfolio to hold in escrow for this SELL contract.');
                return $this->redirectToRoute('p2p_contract_index');
            }
            // Lock assets in escrow
            $portfolioAsset->setQuantity($portfolioAsset->getQuantity() - $quantity);
            if ($portfolioAsset->getQuantity() === 0) {
                $entityManager->remove($portfolioAsset);
            }
            
            // Recalculate portfolio value
            $portfolio->recalculateTotalValue();
            
            $log = new ActivityLog();
            $log->setWallet($wallet);
            $log->setMessage(sprintf('Escrow: %d %s locked for P2P SELL contract', $quantity, $asset->getSymbol()));
            $log->setType('warning');
            $entityManager->persist($log);
        }

        $contract = new P2pContract();
        $contract->setCreator($creator);
        $contract->setAsset($asset);
        $contract->setQuantity($quantity);
        $contract->setPricePerUnit($pricePerUnit);
        $contract->setContractType($contractType);
        $contract->setStatus('OPEN');

        $errors = $validator->validate($contract);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }

            return $this->redirectToRoute('p2p_contract_index');
        }

        $entityManager->persist($contract);
        $entityManager->flush();
        $this->addFlash('success', 'P2P Contract created and escrow locked successfully!');

        return $this->redirectToRoute('p2p_contract_index');
    }

    #[Route('/p2p-contracts/update/{id}', name: 'p2p_contract_update', methods: ['POST'])]
    public function update(P2pContract $contract, Request $request, EntityManagerInterface $entityManager, AssetRepository $assetRepository, UserRepository $userRepository, ValidatorInterface $validator): Response
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

        $creatorId = (int)$request->request->get('creatorId');
        $creator = $userRepository->find($creatorId);
        if ($creator) {
            $contract->setCreator($creator);
        }

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
        $this->addFlash('warning', 'P2P Contract updated successfully! (Note: altering quantities/types will not automatically adjust already escrowed funds.)');

        return $this->redirectToRoute('p2p_contract_index');
    }

    #[Route('/p2p-contracts/accept/{id}', name: 'p2p_contract_accept', methods: ['POST'])]
    public function accept(P2pContract $contract, Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        if ($contract->getStatus() !== 'OPEN') {
            $this->addFlash('danger', 'This contract is no longer open.');
            return $this->redirectToRoute('p2p_contract_index');
        }

        $role = $request->getSession()->get('role');
        if (!$role) {
            return $this->redirectToRoute('app_login');
        }

        if ($role === 'ADMIN') {
            $acceptorId = (int)$request->request->get('acceptorId');
        } else {
            $acceptorId = (int)$request->getSession()->get('user_id');
        }

        $acceptor = $userRepository->find($acceptorId);

        if (!$acceptor) {
            $this->addFlash('danger', 'Acceptor account not found.');
            return $this->redirectToRoute('p2p_contract_index');
        }

        if ($acceptor === $contract->getCreator()) {
            $this->addFlash('danger', 'You cannot accept your own contract.');
            return $this->redirectToRoute('p2p_contract_index');
        }

        $wallet = $acceptor->getWallet();
        $portfolio = $acceptor->getPortfolio();
        $creator = $contract->getCreator();
        $creatorWallet = $creator->getWallet();
        $creatorPortfolio = $creator->getPortfolio();

        if (!$wallet || !$portfolio || !$creatorWallet || !$creatorPortfolio) {
            $this->addFlash('danger', 'Both parties must have valid wallets and portfolios.');
            return $this->redirectToRoute('p2p_contract_index');
        }

        $totalCost = $contract->getQuantity() * $contract->getPricePerUnit();
        $quantity = $contract->getQuantity();
        $asset = $contract->getAsset();
        $type = $contract->getContractType();

        if ($type === 'BUY') {
            // Creator wants to buy, Acceptor must sell.
            $acceptorAsset = null;
            foreach ($portfolio->getPortfolioAssets() as $pa) {
                if ($pa->getAsset()->getId() === $asset->getId()) {
                    $acceptorAsset = $pa;
                    break;
                }
            }
            if (!$acceptorAsset || $acceptorAsset->getQuantity() < $quantity) {
                $this->addFlash('danger', 'Acceptor has insufficient asset quantity to fulfill this BUY contract.');
                return $this->redirectToRoute('p2p_contract_index');
            }

            // Deduct asset from Acceptor
            $acceptorAsset->setQuantity($acceptorAsset->getQuantity() - $quantity);
            if ($acceptorAsset->getQuantity() === 0) {
                $entityManager->remove($acceptorAsset);
            }
            
            // Give Escrowed funds to Acceptor
            $wallet->setBalance((string)((float)$wallet->getBalance() + $totalCost));

            // Give Asset to Creator
            $creatorAsset = null;
            foreach ($creatorPortfolio->getPortfolioAssets() as $pa) {
                if ($pa->getAsset()->getId() === $asset->getId()) {
                    $creatorAsset = $pa;
                    break;
                }
            }
            if (!$creatorAsset) {
                $creatorAsset = new PortfolioAsset();
                $creatorAsset->setPortfolio($creatorPortfolio);
                $creatorAsset->setAsset($asset);
                $creatorAsset->setQuantity(0);
                $creatorAsset->setAvgPrice(0);
                $entityManager->persist($creatorAsset);
            }
            
            $currentVal = $creatorAsset->getQuantity() * $creatorAsset->getAvgPrice();
            $newVal = $currentVal + $totalCost;
            $newQ = $creatorAsset->getQuantity() + $quantity;
            $creatorAsset->setQuantity($newQ);
            $creatorAsset->setAvgPrice($newVal / $newQ);

        } else { // SELL
            // Creator wants to sell, Acceptor must buy.
            if ((float)$wallet->getBalance() < $totalCost) {
                $this->addFlash('danger', 'Acceptor has insufficient balance to fulfill this SELL contract.');
                return $this->redirectToRoute('p2p_contract_index');
            }

            // Deduct funds from Acceptor
            $wallet->setBalance((string)((float)$wallet->getBalance() - $totalCost));

            // Give Asset to Acceptor
            $acceptorAsset = null;
            foreach ($portfolio->getPortfolioAssets() as $pa) {
                if ($pa->getAsset()->getId() === $asset->getId()) {
                    $acceptorAsset = $pa;
                    break;
                }
            }
            if (!$acceptorAsset) {
                $acceptorAsset = new PortfolioAsset();
                $acceptorAsset->setPortfolio($portfolio);
                $acceptorAsset->setAsset($asset);
                $acceptorAsset->setQuantity(0);
                $acceptorAsset->setAvgPrice(0);
                $entityManager->persist($acceptorAsset);
            }
            $currentVal = $acceptorAsset->getQuantity() * $acceptorAsset->getAvgPrice();
            $newVal = $currentVal + $totalCost;
            $newQ = $acceptorAsset->getQuantity() + $quantity;
            $acceptorAsset->setQuantity($newQ);
            $acceptorAsset->setAvgPrice($newVal / $newQ);

            // Give Escrowed funds to Creator
            $creatorWallet->setBalance((string)((float)$creatorWallet->getBalance() + $totalCost));
        }

        // Finalize contract
        $contract->setAcceptor($acceptor);
        $contract->setStatus('COMPLETED');
        $contract->setAcceptedAt(new \DateTime());
        $contract->setCompletedAt(new \DateTime());

        // Recalculate portfolio values for both parties
        $portfolio->recalculateTotalValue();
        $creatorPortfolio->recalculateTotalValue();

        // Update Reputation
        $this->incrementReputation($creator, $entityManager);
        $this->incrementReputation($acceptor, $entityManager);

        $entityManager->flush();
        $this->addFlash('success', 'P2P Contract accepted and settled completely!');

        return $this->redirectToRoute('p2p_contract_index');
    }

    #[Route('/p2p-contracts/cancel/{id}', name: 'p2p_contract_cancel', methods: ['POST'])]
    public function cancel(P2pContract $contract, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($contract->getStatus() !== 'OPEN') {
            $this->addFlash('danger', 'Only OPEN contracts can be cancelled.');
            return $this->redirectToRoute('p2p_contract_index');
        }

        $role = $request->getSession()->get('role');
        if (!$role) {
            return $this->redirectToRoute('app_login');
        }

        $creator = $contract->getCreator();
        if ($role !== 'ADMIN' && $creator->getId() !== (int)$request->getSession()->get('user_id')) {
            $this->addFlash('danger', 'You can only cancel your own contracts.');
            return $this->redirectToRoute('p2p_contract_index');
        }
        $wallet = $creator->getWallet();
        $portfolio = $creator->getPortfolio();
        $totalCost = $contract->getQuantity() * $contract->getPricePerUnit();

        // Refund Escrow
        if ($contract->getContractType() === 'BUY') {
            $wallet->setBalance((string)((float)$wallet->getBalance() + $totalCost));
        } else {
            $creatorAsset = null;
            foreach ($portfolio->getPortfolioAssets() as $pa) {
                if ($pa->getAsset()->getId() === $contract->getAsset()->getId()) {
                    $creatorAsset = $pa;
                    break;
                }
            }
            if (!$creatorAsset) {
                $creatorAsset = new PortfolioAsset();
                $creatorAsset->setPortfolio($portfolio);
                $creatorAsset->setAsset($contract->getAsset());
                $creatorAsset->setQuantity(0);
                $creatorAsset->setAvgPrice(0);
                $entityManager->persist($creatorAsset);
            }
            $creatorAsset->setQuantity($creatorAsset->getQuantity() + $contract->getQuantity());
            
            // Recalculate portfolio value
            $portfolio->recalculateTotalValue();
        }

        $contract->setStatus('CANCELLED');

        // Reputation
        $rep = $creator->getReputation();
        if ($rep) {
            $rep->setCanceledContracts($rep->getCanceledContracts() + 1);
        }

        $entityManager->flush();
        $this->addFlash('info', 'P2P Contract cancelled and escrow refunded to creator.');

        return $this->redirectToRoute('p2p_contract_index');
    }

    private function incrementReputation(\App\Entity\User $user, EntityManagerInterface $em): void
    {
        $rep = $user->getReputation();
        if (!$rep) {
            $rep = new UserReputation();
            $rep->setUser($user);
            $em->persist($rep);
        }
        $rep->setCompletedContracts($rep->getCompletedContracts() + 1);
        // Default rating addition could be simulated here, or tracked explicitly later.
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
