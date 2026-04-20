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
use App\Repository\UserRepository;

class PortfolioController extends AbstractController
{
    #[Route('/portfolios', name: 'portfolio_index', methods: ['GET'])]
    public function index(
        PortfolioRepository $portfolioRepository, 
        UserRepository $userRepository, 
        EntityManagerInterface $entityManager, 
        Request $request,
        \App\Service\SentimentAiService $aiService
    ): Response
    {
        if (!$request->getSession()->get('role')) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->getSession()->get('role') === 'USER') {
            $userId = (int)$request->getSession()->get('user_id');
            $user = $userRepository->find($userId);
            $portfolios = $user ? $portfolioRepository->findBy(['user' => $user]) : []; 
        } else {
            $portfolios = $portfolioRepository->findAll();
        }

        // Fix: recalculate total value for each portfolio to ensure it's accurate
        foreach ($portfolios as $portfolio) {
            $portfolio->recalculateTotalValue();
        }
        $entityManager->flush();

        $totalValue = array_reduce($portfolios, fn($carry, $p) => $carry + $p->getTotalValue(), 0);

        // AI Advisor Logic (for the primary portfolio)
        $aiAdvice = null;
        if (!empty($portfolios)) {
            $mainPortfolio = $portfolios[0];
            $assetsData = [];
            foreach ($mainPortfolio->getPortfolioAssets() as $pa) {
                $assetsData[] = [
                    'symbol' => $pa->getAsset()->getSymbol(),
                    'quantity' => $pa->getQuantity(),
                    'price' => $pa->getAsset()->getValue()
                ];
            }
            $aiAdvice = $aiService->getPortfolioAdvice($assetsData, $mainPortfolio->getTotalValue());
        }

        return $this->render('portfolio/index.html.twig', [
            'portfolios' => $portfolios,
            'activeCount' => count($portfolios),
            'totalValue' => $totalValue,
            'aiAdvice' => $aiAdvice
        ]);
    }

    #[Route('/portfolios/create', name: 'portfolio_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, ValidatorInterface $validator): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('portfolio_index');
        }

        $userId = (int)$request->request->get('userId');
        $user = $userRepository->find($userId);

        if (!$user) {
            $this->addFlash('danger', 'User not found.');
            return $this->redirectToRoute('portfolio_index');
        }

        $portfolio = new Portfolio();
        $portfolio->setUser($user);
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
    public function update(Portfolio $portfolio, Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository, ValidatorInterface $validator): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('portfolio_index');
        }

        $userId = (int)$request->request->get('userId');
        $user = $userRepository->find($userId);
        if ($user) {
            $portfolio->setUser($user);
        }

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

    #[Route('/portfolio/report/{id}', name: 'portfolio_report_pdf', methods: ['GET'])]
    public function report(Portfolio $portfolio, \App\Service\PortfolioReportService $reportService, Request $request): Response
    {
        // Check session ownership
        $userId = (int)$request->getSession()->get('user_id');
        if ($request->getSession()->get('role') !== 'ADMIN' && $portfolio->getUser()->getId() !== $userId) {
            throw $this->createAccessDeniedException('You do not have access to this report.');
        }

        $pdfBinary = $reportService->generatePortfolioPdf($portfolio);

        return new Response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Nexora_Portfolio_Report_' . $portfolio->getId() . '.pdf"',
        ]);
    }
}
