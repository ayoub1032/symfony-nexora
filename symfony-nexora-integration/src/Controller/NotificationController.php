<?php

namespace App\Controller;

use App\Repository\NotificationRepository;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NotificationController extends AbstractController
{
    /**
     * Utilisé pour le rendu de la cloche dans le base.html.twig via {{ render(controller(...)) }}
     */
    public function renderHeader(NotificationRepository $notificationRepository, WalletRepository $walletRepository, Request $request): Response
    {
        $role = $request->getSession()->get('role');
        $walletId = $request->getSession()->get('logged_in_wallet_id');

        if (!$role || !$walletId) {
            return new Response('');
        }

        $wallet = $walletRepository->find($walletId);
        if (!$wallet) {
            return new Response('');
        }

        $unreadNotifications = $notificationRepository->findUnreadByWallet($wallet);
        $latestNotifications = $notificationRepository->findLatestByWallet($wallet, 5);

        return $this->render('notification/_header_bell.html.twig', [
            'unreadCount' => count($unreadNotifications),
            'notifications' => $latestNotifications,
        ]);
    }

    #[Route('/notifications/mark-read/{id?}', name: 'notification_mark_read', methods: ['POST'])]
    public function markRead(?int $id, NotificationRepository $notificationRepository, EntityManagerInterface $entityManager, Request $request): Response
    {
        $walletId = $request->getSession()->get('logged_in_wallet_id');
        
        if ($id) {
            $notification = $notificationRepository->find($id);
            if ($notification && $notification->getWallet()->getId() == $walletId) {
                $notification->setRead(true);
            }
        } else {
            // Mark all as read for this wallet
            $unread = $notificationRepository->findBy(['wallet' => $walletId, 'isRead' => false]);
            foreach ($unread as $n) {
                $n->setRead(true);
            }
        }

        $entityManager->flush();

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('wallet_index');
    }
}
