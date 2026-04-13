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

class OrderController extends AbstractController
{
    #[Route('/orders', name: 'order_index', methods: ['GET'])]
    public function index(OrderRepository $orderRepository, AssetRepository $assetRepository, Request $request): Response
    {
        if (!$request->getSession()->get('role')) {
            return $this->redirectToRoute('app_login');
        }

        $orders = $orderRepository->findAll();
        $assets = $assetRepository->findAll(); // For the create modal dropdown
        $totalOrders = count($orders);

        return $this->render('order/index.html.twig', [
            'orders' => $orders,
            'assets' => $assets,
            'activeCount' => $totalOrders,
        ]);
    }

    #[Route('/orders/create', name: 'order_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, AssetRepository $assetRepository, ValidatorInterface $validator): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('order_index');
        }

        $assetId = (int)$request->request->get('asset_id');
        $asset = $assetRepository->find($assetId);

        if (!$asset) {
            $this->addFlash('danger', 'Selected Asset not found.');
            return $this->redirectToRoute('order_index');
        }

        $order = new Order();
        $order->setAsset($asset);
        $order->setUserId((int)$request->request->get('userId'));
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

        $entityManager->persist($order);
        $entityManager->flush();
        $this->addFlash('success', 'Order created successfully!');

        return $this->redirectToRoute('order_index');
    }

    #[Route('/orders/update/{id}', name: 'order_update', methods: ['POST'])]
    public function update(Order $order, Request $request, EntityManagerInterface $entityManager, AssetRepository $assetRepository, ValidatorInterface $validator): Response
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

        $order->setUserId((int)$request->request->get('userId'));
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
        $this->addFlash('success', 'Order updated successfully!');

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
