<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;
use App\Service\AiService;
use App\Repository\TransactionRepository;
use App\Repository\WalletRepository;
use App\Repository\WalletGoalRepository;
use App\Repository\UserRepository;
use App\Service\CurrencyService;
use App\Service\PdfService;
use Symfony\Component\HttpFoundation\JsonResponse;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        ChartBuilderInterface $chartBuilder, 
        Request $request, 
        TransactionRepository $transactionRepository,
        WalletRepository $walletRepository,
        WalletGoalRepository $goalRepository,
        CurrencyService $currencyService
    ): Response
    {
        if (!$request->getSession()->get('role')) {
            return $this->redirectToRoute('app_login');
        }

        $walletId = $request->getSession()->get('logged_in_wallet_id');
        $wallet = $walletRepository->find($walletId);
        $rates = $currencyService->getLatestRates();
        
        // --- Statistiques Haut de Page ---
        $totalBalance = $wallet ? (float)$wallet->getBalance() : 0;
        
        // Invested Capital = Somme des montants cibles des objectifs en cours
        $goals = $goalRepository->findBy(['wallet' => $wallet, 'status' => 'in_progress']);
        $investedCapital = array_reduce($goals, fn($carry, $goal) => $carry + (float)$goal->getTargetAmount(), 0);

        // --- Chart 1: Répartition par Catégorie (Vraies Données) ---
        $stats = $transactionRepository->getStatsByCategory($walletId);
        $labels = array_map(fn($s) => $s['name'], $stats);
        $dataPie = array_map(fn($s) => (float)$s['total'], $stats);
        $colors = array_map(fn($s) => $s['color'], $stats);

        if (empty($labels)) {
            $labels = ['No Data']; $dataPie = [100]; $colors = ['#334155'];
        }

        $chart1 = $chartBuilder->createChart(Chart::TYPE_PIE);
        $chart1->setData([
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Distribution des Actifs',
                    'backgroundColor' => $colors,
                    'data' => $dataPie,
                    'borderWidth' => 0,
                ],
            ],
        ]);
        $chart1->setOptions([
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => ['color' => '#ffffff', 'padding' => 20]
                ]
            ]
        ]);

        // --- Chart 2: Performance (Vraies Données - 7 derniers jours par défaut) ---
        $performance = $transactionRepository->getPerformanceHistory($walletId, '7D');
        
        $chart2 = $chartBuilder->createChart(Chart::TYPE_LINE);
        $chart2->setData([
            'labels' => array_keys($performance),
            'datasets' => [
                [
                    'label' => 'Valeur du Portfolio (TND)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.1)',
                    'borderColor' => '#a855f7',
                    'data' => array_values($performance),
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
        ]);
        $chart2->setOptions([
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => [
                    'grid' => ['color' => 'rgba(255, 255, 255, 0.05)'],
                    'ticks' => ['color' => '#94a3b8']
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#94a3b8']
                ]
            ],
            'plugins' => [
                'legend' => ['display' => false]
            ]
        ]);

        // --- Data for Executive Analysis Highlights ---
        $topCategory = !empty($stats) ? $stats[0] : null;
        $totalTransactionsCount = $transactionRepository->count(['wallet' => $wallet]);
        
        // --- Analysis Summary Object ---
        $analysis = [
            'top_category' => $topCategory ? $topCategory['name'] : 'N/A',
            'health_score' => $totalBalance > 1000 ? 85 : 45, // Demo logic
            'insight_text' => $topCategory ? "Votre activité est dominée par {$topCategory['name']}. Pensez à diversifier pour réduire les risques." : "Commencez à trader pour obtenir une analyse Nexora complète."
        ];

        return $this->render('dashboard/index.html.twig', [
            'chart1' => $chart1,
            'chart2' => $chart2,
            'totalBalance' => $totalBalance,
            'investedCapital' => $investedCapital,
            'wallet' => $wallet,
            'rates' => $rates,
            'analysis' => $analysis,
            'stats' => $stats
        ]);
    }

    #[Route('/dashboard/export', name: 'app_dashboard_export')]
    public function exportPdf(
        Request $request, 
        WalletRepository $walletRepository, 
        TransactionRepository $transactionRepository,
        PdfService $pdfService
    ): Response
    {
        $walletId = $request->getSession()->get('logged_in_wallet_id');
        $wallet = $walletRepository->find($walletId);
        
        if (!$wallet) {
            return $this->redirectToRoute('app_dashboard');
        }

        $transactions = $transactionRepository->findBy(['wallet' => $wallet], ['createdAt' => 'DESC'], 20);

        $html = $this->renderView('dashboard/report.html.twig', [
            'wallet' => $wallet,
            'transactions' => $transactions,
            'date' => new \DateTime(),
        ]);

        $pdf = $pdfService->generatePdf($html);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="nexora_bilan_'.date('Y-m-d').'.pdf"',
        ]);
    }

    #[Route('/dashboard/performance', name: 'app_dashboard_performance', methods: ['GET'])]
    public function performanceData(TransactionRepository $transactionRepository, Request $request): JsonResponse
    {
        $walletId = $request->getSession()->get('logged_in_wallet_id');
        $period = $request->query->get('period', '7D');

        $data = $transactionRepository->getPerformanceHistory($walletId, $period);

        return new JsonResponse([
            'labels' => array_keys($data),
            'data' => array_values($data)
        ]);
    }

    #[Route('/dashboard/analyze', name: 'app_dashboard_analyze', methods: ['POST'])]
    public function analyze(AiService $aiService, Request $request, TransactionRepository $transactionRepository, UserRepository $userRepository): JsonResponse
    {
        if (!$request->getSession()->get('role')) {
            return new JsonResponse(['error' => 'Unauthorized'], 403);
        }

        $walletId = $request->getSession()->get('logged_in_wallet_id');
        $userId = $request->getSession()->get('user_id');
        $user = $userRepository->find($userId);

        $stats = $transactionRepository->getStatsByCategory($walletId);
        $performance = $transactionRepository->getPerformanceHistory($walletId, '7D');

        $data = [
            'category_distribution' => $stats,
            'recent_performance' => $performance
        ];

        $advice = $aiService->getFinancialAdvice($data, $user ? $user->getRiskProfile() : 'Balanced');

        return new JsonResponse(['advice' => $advice]);
    }

    #[Route('/dashboard/update-profile', name: 'app_dashboard_profile_update', methods: ['POST'])]
    public function updateProfile(Request $request, UserRepository $userRepository, \Doctrine\ORM\EntityManagerInterface $em): JsonResponse
    {
        $userId = $request->getSession()->get('user_id');
        $profile = $request->request->get('profile');

        if (!$userId || !in_array($profile, ['Prudent', 'Balanced', 'Dynamic'])) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $user = $userRepository->find($userId);
        if ($user) {
            $user->setRiskProfile($profile);
            $em->flush();
            return new JsonResponse(['success' => true, 'profile' => $profile]);
        }

        return new JsonResponse(['error' => 'User not found'], 404);
    }
}
