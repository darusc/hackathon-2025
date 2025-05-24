<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use Psr\Log\LoggerInterface;

class AlertGenerator
{
    private array $categoryBudgets;

    public function __construct(
        private readonly MonthlySummaryService $monthlySummaryService,
    ) {

        $categories = explode(",", $_ENV['CATEGORIES']);
        $budgets = explode(",", $_ENV['BUDGETS']);

        $count = count($categories);
        for($i = 0; $i < $count; $i++) {
            $this->categoryBudgets[$categories[$i]] = $budgets[$i];
        }
    }

    public function generate(int $userId, int $year, int $month): array
    {
        $alerts = [];

        $totalPerCategory = $this->monthlySummaryService->computePerCategoryTotals($userId, $year, $month);
        foreach ($totalPerCategory as $category => $data) {
            $value = $data['value'] / 100;
            $budget = (float)$this->categoryBudgets[$category];
            if($value > $budget) {
                $alerts[$category] = $value - $budget;
            }
        }

        return $alerts;
    }
}
