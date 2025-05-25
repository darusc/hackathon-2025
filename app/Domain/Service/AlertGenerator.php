<?php

declare(strict_types=1);

namespace App\Domain\Service;

class AlertGenerator
{
    private array $categoryBudgets;

    public function __construct(
        private readonly MonthlySummaryService $summaryService,
    )
    {

        $categories = explode(",", $_ENV['CATEGORIES']);
        $budgets = explode(",", $_ENV['BUDGETS']);

        $count = count($categories);
        for ($i = 0; $i < $count; $i++) {
            $this->categoryBudgets[$categories[$i]] = $budgets[$i];
        }
    }

    public function generate(int $userId, int $year, int $month): array
    {
        $alerts = [];

        $totalPerCategory = $this->summaryService->computePerCategoryTotals($userId, $year, $month);
        foreach ($totalPerCategory as $category => $data) {
            $value = $data['value'];
            $budget = (float)$this->categoryBudgets[$category];
            if ($value > $budget) {
                $alerts[$category] = $value - $budget;
            }
        }

        return $alerts;
    }
}
