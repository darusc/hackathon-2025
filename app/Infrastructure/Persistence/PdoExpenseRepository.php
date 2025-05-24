<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Exception;
use PDO;
use Psr\Log\LoggerInterface;
use Slim\Logger;

class PdoExpenseRepository implements ExpenseRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private LoggerInterface $logger,

    ) {}

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
        $query = 'INSERT INTO expenses (user_id, date, category, amount_cents, description) VALUES (:user_id, :date, :category, :amount_cents, :description)';
        $statement = $this->pdo->prepare($query);
        $statement->execute([
            'user_id' => $expense->userId,
            'date' => $expense->date->format('c'),
            'category' => $expense->category,
            'amount_cents' => $expense->amountCents,
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
        $query = 'SELECT * FROM expenses 
                  WHERE user_id = :user_id AND strftime("%m", date) = :month AND strftime("%Y", date) = :year
                  LIMIT :limit
                  OFFSET :offset';

        $statement = $this->pdo->prepare($query);
        $statement->execute([
            'user_id' => $criteria['user_id'],
            'month' => str_pad((string)$criteria['month'], 2, '0', STR_PAD_LEFT), // pad the month because strftime result is "05"
            'year' => $criteria['year'],
            'limit' => $limit,
            'offset' => $from
        ]);

        $data = $statement->fetchAll();

        return array_map(function($entry) {
            return $this->createExpenseFromData($entry);
        }, $data);
    }


    public function countBy(array $criteria): int
    {
        $query = 'SELECT COUNT(*) FROM expenses WHERE user_id = :user_id';
        $params = ['user_id' => $criteria['user_id']];

        if(array_key_exists('year', $criteria)) {
            $query .= ' AND strftime("%Y", date) = :year';
            $params['year'] = $criteria['year'];
        }
        if(array_key_exists('month', $criteria)) {
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
        $query = 'SELECT DISTINCT strftime("%Y", date) as year FROM expenses WHERE user_id = :user_id';

        $statement = $this->pdo->prepare($query);
        $statement->execute(['user_id' => $userId]);

        $data = $statement->fetchAll();

        return array_map(function($entry) {
            return (int)$entry['year'];
        }, $data);
    }

    public function sumAmountsByCategory(array $criteria): array
    {
        // TODO: Implement sumAmountsByCategory() method.
        return [];
    }

    public function averageAmountsByCategory(array $criteria): array
    {
        // TODO: Implement averageAmountsByCategory() method.
        return [];
    }

    public function sumAmounts(array $criteria): float
    {
        // TODO: Implement sumAmounts() method.
        return 0;
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
            $data['amount_cents'],
            $data['description'],
        );
    }
}
