<?php

namespace App\Controller;

use App\Entity\Order;
use App\Repository\AssetRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Repository\UserRepository;
use App\Entity\ActivityLog;
use App\Entity\Transaction;
use App\Entity\PortfolioAsset;

class OrderController extends AbstractController
{
    #[Route('/orders', name: 'order_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository, AssetRepository $assetRepository, UserRepository $userRepository, Request $request): Response
    {
        if (!$request->getSession()->get('role')) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->getSession()->get('role') === 'ADMIN') {
            $orders = $orderRepository->findAll();
        } else {
            $user = $userRepository->find((int)$request->getSession()->get('user_id'));
            $orders = $orderRepository->findBy(['user' => $user]);
        }

        $assets = $assetRepository->findAll(); // For the create modal dropdown
        $totalOrders = count($orders);

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
            'assets' => $assets,
            'activeCount' => $totalOrders,
        ]);
    }

    #[Route('/orders/create', name: 'order_create', methods: ['POST'])]
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
            return $this->redirectToRoute('order_index');
        }

        if ($role === 'ADMIN') {
            $userId = (int)$request->request->get('userId');
        } else {
            $userId = (int)$request->getSession()->get('user_id');
        }
        
        $user = $userRepository->find($userId);

        if (!$user) {
            $this->addFlash('danger', 'Selected User not found.');
            return $this->redirectToRoute('order_index');
        }

        $quantity = (int)$request->request->get('quantity');
        // Use asset's current market price — not user-submitted input
        $price = (float)$asset->getValue();
        $type = strtoupper(trim((string)$request->request->get('type')));

        $order = new Order();
        $order->setAsset($asset);
        $order->setUser($user);
        $order->setQuantity($quantity);
        $order->setPrice($price);
        $order->setType($type);

        $errors = $validator->validate($order);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }
            return $this->redirectToRoute('order_index');
        }

        // Execute Trading Logic
        $wallet = $user->getWallet();
        $portfolio = $user->getPortfolio();

        if (!$wallet || !$portfolio) {
            $this->addFlash('danger', 'User must have a linked wallet and portfolio to trade.');
            return $this->redirectToRoute('order_index');
        }

        $totalCost = $quantity * $price;

        if ($type === 'BUY') {
            if ((float)$wallet->getBalance() < $totalCost) {
                $this->addFlash('danger', 'Insufficient wallet balance for this BUY order.');
                return $this->redirectToRoute('order_index');
            }
            // Deduct funds
            $wallet->setBalance((string)((float)$wallet->getBalance() - $totalCost));

            // Add Asset
            $portfolioAsset = null;
            foreach ($portfolio->getPortfolioAssets() as $pa) {
                if ($pa->getAsset()->getId() === $asset->getId()) {
                    $portfolioAsset = $pa;
                    break;
                }
            }
            if (!$portfolioAsset) {
                $portfolioAsset = new PortfolioAsset();
                $portfolioAsset->setPortfolio($portfolio);
                $portfolioAsset->setAsset($asset);
                $portfolioAsset->setQuantity(0);
                $portfolioAsset->setAvgPrice(0);
                $entityManager->persist($portfolioAsset);
            }
            $currentVal = $portfolioAsset->getQuantity() * $portfolioAsset->getAvgPrice();
            $newVal = $currentVal + $totalCost;
            $newQ = $portfolioAsset->getQuantity() + $quantity;
            $portfolioAsset->setQuantity($newQ);
            $portfolioAsset->setAvgPrice($newVal / $newQ);

        } elseif ($type === 'SELL') {
            $portfolioAsset = null;
            foreach ($portfolio->getPortfolioAssets() as $pa) {
                if ($pa->getAsset()->getId() === $asset->getId()) {
                    $portfolioAsset = $pa;
                    break;
                }
            }
            if (!$portfolioAsset || $portfolioAsset->getQuantity() < $quantity) {
                $this->addFlash('danger', 'Insufficient asset quantity in portfolio to execute SELL order.');
                return $this->redirectToRoute('order_index');
            }

            // Deduct Asset
            $portfolioAsset->setQuantity($portfolioAsset->getQuantity() - $quantity);
            if ($portfolioAsset->getQuantity() === 0) {
                $entityManager->remove($portfolioAsset);
            }

            // Add funds
            $wallet->setBalance((string)((float)$wallet->getBalance() + $totalCost));
        }

        // Recalculate portfolio total value
        $portfolio->recalculateTotalValue();

        // Log Activity
        $log = new ActivityLog();
        $log->setWallet($wallet);
        $log->setMessage(sprintf('%s %d %s at %.2f TND', $type, $quantity, $asset->getSymbol(), $price));
        $log->setType('info');
        $entityManager->persist($log);

        // Transaction
        $transaction = new Transaction();
        $transaction->setWallet($wallet);
        $transaction->setAmount((string)$totalCost);
        $transaction->setType($type === 'BUY' ? 'Out' : 'In');
        // Find category if possible
        $catName = 'Trading';
        $category = $entityManager->getRepository(\App\Entity\Category::class)->findOneBy(['name' => $catName]);
        if ($category) {
            $transaction->setCategory($category);
        }
        $entityManager->persist($transaction);

        $entityManager->persist($order);
        $entityManager->flush();
        $this->addFlash('success', 'Order created and executed successfully!');

        return $this->redirectToRoute('order_index');
    }

    #[Route('/orders/update/{id}', name: 'order_update', methods: ['POST'])]
    public function update(Order $order, Request $request, EntityManagerInterface $entityManager, AssetRepository $assetRepository, UserRepository $userRepository, ValidatorInterface $validator): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('order_index');
        }

        $assetId = (int)$request->request->get('asset_id');
        $asset = $assetRepository->find($assetId);
        if ($asset) {
            $order->setAsset($asset);
        }

        $userId = (int)$request->request->get('userId');
        $user = $userRepository->find($userId);
        if ($user) {
            $order->setUser($user);
        }

        $order->setQuantity((int)$request->request->get('quantity'));
        $order->setPrice((float)$request->request->get('price'));
        $order->setType(strtoupper(trim((string)$request->request->get('type'))));

        $errors = $validator->validate($order);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }

            return $this->redirectToRoute('order_index');
        }

        $entityManager->flush();
        $this->addFlash('success', 'Order updated successfully (Note: updating an order does not automatically reverse earlier wallet/portfolio deductions automatically yet).');

        return $this->redirectToRoute('order_index');
    }

    #[Route('/orders/delete/{id}', name: 'order_delete', methods: ['POST'])]
    public function delete(Order $order, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('order_index');
        }

        $entityManager->remove($order);
        $entityManager->flush();
        $this->addFlash('success', 'Order deleted successfully.');

        return $this->redirectToRoute('order_index');
    }
}
