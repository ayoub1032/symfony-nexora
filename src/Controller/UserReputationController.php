<?php

namespace App\Controller;

use App\Entity\UserReputation;
use App\Repository\UserReputationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserReputationController extends AbstractController
{
    #[Route('/user-reputations', name: 'user_reputation_index', methods: ['GET'])]
    public function index(UserReputationRepository $reputationRepository, Request $request): Response
    {
        if (!$request->getSession()->get('role')) {
            return $this->redirectToRoute('app_gateway');
        }

        $reputations = $reputationRepository->findAll();
        $totalReputations = count($reputations);

        return $this->render('user_reputation/index.html.twig', [
            'reputations' => $reputations,
            'activeCount' => $totalReputations,
        ]);
    }

    #[Route('/user-reputations/create', name: 'user_reputation_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('user_reputation_index');
        }

        $reputation = new UserReputation();
        $reputation->setUserId((int)$request->request->get('userId'));
        $reputation->setCompletedContracts((int)$request->request->get('completedContracts', 0));
        $reputation->setCanceledContracts((int)$request->request->get('canceledContracts', 0));
        $reputation->setTotalScore((int)$request->request->get('totalScore', 0));
        $reputation->setRatingCount((int)$request->request->get('ratingCount', 0));

        $entityManager->persist($reputation);
        $entityManager->flush();
        $this->addFlash('success', 'User Reputation created successfully!');

        return $this->redirectToRoute('user_reputation_index');
    }

    #[Route('/user-reputations/update/{id}', name: 'user_reputation_update', methods: ['POST'])]
    public function update(UserReputation $reputation, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('user_reputation_index');
        }

        $reputation->setUserId((int)$request->request->get('userId'));
        $reputation->setCompletedContracts((int)$request->request->get('completedContracts'));
        $reputation->setCanceledContracts((int)$request->request->get('canceledContracts'));
        $reputation->setTotalScore((int)$request->request->get('totalScore'));
        $reputation->setRatingCount((int)$request->request->get('ratingCount'));

        $entityManager->flush();
        $this->addFlash('success', 'User Reputation updated successfully!');

        return $this->redirectToRoute('user_reputation_index');
    }

    #[Route('/user-reputations/delete/{id}', name: 'user_reputation_delete', methods: ['POST'])]
    public function delete(UserReputation $reputation, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('user_reputation_index');
        }

        $entityManager->remove($reputation);
        $entityManager->flush();
        $this->addFlash('success', 'User Reputation deleted successfully.');

        return $this->redirectToRoute('user_reputation_index');
    }
}
