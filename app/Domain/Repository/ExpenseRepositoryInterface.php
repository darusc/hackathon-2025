<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Expense;

interface ExpenseRepositoryInterface
{
    public function save(Expense $expense): void;

    public function saveImported(array $expenses): void;

    public function update(Expense $expense): void;

    public function delete(int $id): void;

    public function find(int $id): ?Expense;

    public function findBy(array $criteria, int $from, int $limit): array;

    public function countBy(array $criteria): int;

    public function listExpenditureYears(int $userId): array;

    public function sumAmountsByCategory(int $userId, int $year, int $month, string $category): float;

    public function averageAmountsByCategory(int $userId, int $year, int $month, string $category): float;

    public function sumAmounts(int $userId, int $year, int $month): float;
}
