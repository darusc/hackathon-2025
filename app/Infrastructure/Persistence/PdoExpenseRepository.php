<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Expense;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Exception;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;

class PdoExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(
        private readonly PDO             $pdo,
        private readonly LoggerInterface $logger
    )
    {
    }

    /**
     * @throws Exception
     */
    public function find(int $id): ?Expense
    {
        $query = 'SELECT * FROM expenses WHERE id = :id';
        $statement = $this->pdo->prepare($query);
        $statement->execute(['id' => $id]);
        $data = $statement->fetch();
        if (false === $data) {
            return null;
        }

        return $this->createExpenseFromData($data);
    }

    public function save(Expense $expense): void
    {
        $query = 'INSERT INTO expenses (user_id, date, category, amount, description) VALUES (:user_id, :date, :category, :amount, :description)';
        $statement = $this->pdo->prepare($query);
        $statement->execute([
            'user_id' => $expense->userId,
            'date' => $expense->date->format('c'),
            'category' => $expense->category,
            'amount' => $expense->amount,
            'description' => $expense->description,
        ]);
    }

    public function saveImported(array $expenses): void
    {
        try {
            $this->pdo->beginTransaction();
            $query = 'INSERT INTO expenses (user_id, date, category, amount, description) VALUES (:user_id, :date, :category, :amount, :description)';
            $statement = $this->pdo->prepare($query);

            foreach ($expenses as $expense) {
                $statement->execute([
                    'user_id' => $expense->userId,
                    'date' => $expense->date->format('c'),
                    'category' => $expense->category,
                    'amount' => $expense->amount,
                    'description' => $expense->description,
                ]);
            }

            $this->pdo->commit();
            $this->logger->error('[EXPENSES IMPORT] Transaction successful.');
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            $this->logger->error('[EXPENSES IMPORT] Transaction failed. ' . $e->getMessage());
        }
    }

    public function update(Expense $expense): void
    {
        $query = 'UPDATE expenses SET date = :date, category = :category, amount = :amount, description = :description WHERE id = :id';
        $statement = $this->pdo->prepare($query);
        $statement->execute([
            'id' => $expense->id,
            'date' => $expense->date->format('c'),
            'category' => $expense->category,
            'amount' => $expense->amount,
            'description' => $expense->description,
        ]);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM expenses WHERE id=?');
        $statement->execute([$id]);
    }

    /**
     * @throws Exception
     */
    public function findBy(array $criteria, int $from, int $limit): array
    {
        $query = "SELECT * FROM expenses 
                  WHERE user_id = :user_id AND strftime('%m', date) = :month AND strftime('%Y', date) = :year
                  ORDER BY date DESC
                  LIMIT :limit
                  OFFSET :offset";

        $statement = $this->pdo->prepare($query);
        $statement->execute([
            'user_id' => $criteria['user_id'],
            'month' => str_pad((string)$criteria['month'], 2, '0', STR_PAD_LEFT), // pad the month because strftime result is "05"
            'year' => $criteria['year'],
            'limit' => $limit,
            'offset' => $from
        ]);

        $data = $statement->fetchAll();

        return array_map(function ($entry) {
            return $this->createExpenseFromData($entry);
        }, $data);
    }


    public function countBy(array $criteria): int
    {
        $query = 'SELECT COUNT(*) FROM expenses WHERE user_id = :user_id';
        $params = ['user_id' => $criteria['user_id']];

        if (array_key_exists('year', $criteria)) {
            $query .= ' AND strftime("%Y", date) = :year';
            $params['year'] = $criteria['year'];
        }
        if (array_key_exists('month', $criteria)) {
            $query .= ' AND strftime("%m", date) = :month';
            $params['month'] = str_pad((string)$criteria['month'], 2, '0', STR_PAD_LEFT);
        }

        $statement = $this->pdo->prepare($query);
        $statement->execute($params);

        $data = $statement->fetchColumn();

        return (int)$data;
    }

    public function listExpenditureYears(int $userId): array
    {
        $query = "SELECT DISTINCT strftime('%Y', date) as year FROM expenses WHERE user_id = :user_id";

        $statement = $this->pdo->prepare($query);
        $statement->execute(['user_id' => $userId]);

        $data = $statement->fetchAll();

        return array_map(function ($entry) {
            return (int)$entry['year'];
        }, $data);
    }

    public function sumAmountsByCategory(int $userId, int $year, int $month, string $category): float
    {
        $query = "SELECT SUM(amount) FROM expenses WHERE user_id = :user_id AND category = :category AND strftime('%Y', date) = :year AND strftime('%m', date) = :month";
        $statement = $this->pdo->prepare($query);
        $statement->execute([
            'user_id' => $userId,
            'year' => (string)$year,
            'month' => str_pad((string)$month, 2, '0', STR_PAD_LEFT),
            'category' => $category,
        ]);

        return $statement->fetchColumn() ?: 0;
    }

    public function averageAmountsByCategory(int $userId, int $year, int $month, string $category): float
    {
        $query = "SELECT AVG(amount) FROM expenses WHERE user_id = :user_id AND category = :category AND strftime('%Y', date) = :year AND strftime('%m', date) = :month";
        $statement = $this->pdo->prepare($query);
        $statement->execute([
            'user_id' => $userId,
            'year' => (string)$year,
            'month' => str_pad((string)$month, 2, '0', STR_PAD_LEFT),
            'category' => $category,
        ]);

        return $statement->fetchColumn() ?: 0;
    }

    public function sumAmounts(int $userId, int $year, int $month): float
    {
        $query = "SELECT SUM(amount) FROM expenses WHERE user_id = :user_id AND strftime('%Y', date) = :year AND strftime('%m', date) = :month";
        $statement = $this->pdo->prepare($query);
        $statement->execute([
            'user_id' => $userId,
            'year' => (string)$year,
            'month' => str_pad((string)$month, 2, '0', STR_PAD_LEFT),
        ]);

        return $statement->fetchColumn() ?: 0;
    }

    /**
     * @throws Exception
     */
    private function createExpenseFromData(mixed $data): Expense
    {
        return new Expense(
            $data['id'],
            $data['user_id'],
            new DateTimeImmutable($data['date']),
            $data['category'],
            $data['amount'],
            $data['description'],
        );
    }
}
