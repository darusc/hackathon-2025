<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;

class MonthlySummaryService
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
    ) {}

    public function computeTotalExpenditure(int $userId, int $year, int $month): float {
        return $this->expenses->sumAmounts($userId, $year, $month);
    }

    public function computePerCategoryTotals(int $userId, int $year, int $month): array {
        $categories = explode(",", $_ENV['CATEGORIES']);
        $totals = [];

        $total = $this->computeTotalExpenditure($userId, $year, $month);

        foreach($categories as $category) {
            $categoryTotal = $this->expenses->sumAmountsByCategory($userId, $year, $month, $category);
            $totals[$category] = [
                'value' => $categoryTotal,
                'percentage' => $total != 0 ? $categoryTotal / $total * 100 : 0
            ];
        }

        return $totals;
    }

    public function computePerCategoryAverages(int $userId, int $year, int $month): array {
        $categories = explode(",", $_ENV['CATEGORIES']);
        $averages = [];

        $total = $this->computeTotalExpenditure($userId, $year, $month);

        foreach($categories as $category) {
            $categoryAverage = $this->expenses->averageAmountsByCategory($userId, $year, $month, $category);
            $averages[$category] = [
                'value' => $categoryAverage,
                'percentage' => $total != 0 ? $categoryAverage / $total * 100 : 0
            ];
        }

        return $averages;
    }
}
