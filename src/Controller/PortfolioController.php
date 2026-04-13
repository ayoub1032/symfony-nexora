<?php

namespace App\Controller;

use App\Entity\Portfolio;
use App\Repository\PortfolioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PortfolioController extends AbstractController
{
    #[Route('/portfolios', name: 'portfolio_index', methods: ['GET'])]
    public function index(PortfolioRepository $portfolioRepository, Request $request): Response
    {
        if (!$request->getSession()->get('role')) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->getSession()->get('role') === 'USER') {
            // For a user, theoretically they would only see their own portfolio
            // Since User auth relies on wallet ID right now, we'll just show all for simplicity or mock it
            $portfolios = $portfolioRepository->findAll(); 
        } else {
            $portfolios = $portfolioRepository->findAll();
        }

        $totalValue = array_reduce($portfolios, fn($carry, $p) => $carry + $p->getTotalValue(), 0);

        return $this->render('portfolio/index.html.twig', [
            'portfolios' => $portfolios,
            'activeCount' => count($portfolios),
            'totalValue' => $totalValue
        ]);
    }

    #[Route('/portfolios/create', name: 'portfolio_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('portfolio_index');
        }

        $portfolio = new Portfolio();
        $portfolio->setUserId((int)$request->request->get('userId'));
        $portfolio->setTotalValue(0.0); // newly created portfolio has 0 value

        $errors = $validator->validate($portfolio);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }

            return $this->redirectToRoute('portfolio_index');
        }

        $entityManager->persist($portfolio);
        $entityManager->flush();
        $this->addFlash('success', 'Portfolio created successfully!');

        return $this->redirectToRoute('portfolio_index');
    }

    #[Route('/portfolios/update/{id}', name: 'portfolio_update', methods: ['POST'])]
    public function update(Portfolio $portfolio, Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('portfolio_index');
        }

        $portfolio->setUserId((int)$request->request->get('userId'));
        if ($request->request->has('totalValue')) {
            $portfolio->setTotalValue((float)$request->request->get('totalValue'));
        }

        $errors = $validator->validate($portfolio);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }

            return $this->redirectToRoute('portfolio_index');
        }

        $entityManager->flush();
        $this->addFlash('success', 'Portfolio updated successfully!');

        return $this->redirectToRoute('portfolio_index');
    }

    #[Route('/portfolios/delete/{id}', name: 'portfolio_delete', methods: ['POST'])]
    public function delete(Portfolio $portfolio, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('portfolio_index');
        }

        if ($portfolio->getTotalValue() > 0) {
            $this->addFlash('danger', 'Cannot delete a portfolio that still has value.');
            return $this->redirectToRoute('portfolio_index');
        }

        $entityManager->remove($portfolio);
        $entityManager->flush();
        $this->addFlash('success', 'Portfolio deleted successfully.');

        return $this->redirectToRoute('portfolio_index');
    }
}
