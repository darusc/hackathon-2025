<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AlertGenerator;
use App\Domain\Service\ExpenseService;
use App\Domain\Service\MonthlySummaryService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class DashboardController extends BaseController
{
    private const MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    private AlertGenerator $alertGenerator;

    public function __construct(
        Twig $view,
        private readonly ExpenseService        $expenseService,
        private readonly MonthlySummaryService $summaryService,
    ) {
        parent::__construct($view);
        $this->alertGenerator = new AlertGenerator($summaryService);
    }

    public function index(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'];

        $year = (int)($request->getQueryParams()['year'] ?? date("Y"));
        $month = (int)($request->getQueryParams()['month'] ?? date("m"));

        $years = $this->expenseService->listExpenditureYears($userId);

        $alerts = $this->alertGenerator->generate($userId, $year, $month);
        $totalExpenditure = $this->summaryService->computeTotalExpenditure($userId, $year, $month);
        $totalsForCategories = $this->summaryService->computePerCategoryTotals($userId, $year, $month);
        $averageForCategories = $this->summaryService->computePerCategoryAverages($userId, $year, $month);

        return $this->render($response, 'dashboard.twig', [
            'months' => self::MONTHS,
            'years' => $years,
            'selectedMonth' => $month,
            'selectedYear' => $year,
            'totalExpenditure' => $totalExpenditure,
            'alerts'                => $alerts,
            'totalsForCategories'   => $totalsForCategories,
            'averagesForCategories' => $averageForCategories
        ]);
    }
}
