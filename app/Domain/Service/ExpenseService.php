<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Expense;
use App\Domain\Entity\User;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;

class ExpenseService
{
    public const SUCCESS = 0;
    public const ERROR_DATE = 1;
    public const ERROR_CATEGORY = 2;
    public const ERROR_AMOUNT = 3;
    public const ERROR_DESCRIPTION = 4;


    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
        private LoggerInterface $logger
    ) {}

    public function findById(int $id): Expense|null {
        return $this->expenses->find($id);
    }

    public function list(int $userId, int $year, int $month, int $pageNumber, int $pageSize): array
    {
        $data = $this->expenses->findBy(
            ['user_id' => $userId, 'year' => $year, 'month' => $month],
            ($pageNumber - 1) * $pageSize,
            $pageSize
        );

        // Sort descending by date
        usort($data, function (Expense $e1, Expense $e2) {
            return $e2->date <=> $e1->date;
        });

        return $data;
    }

    public function listExpenditureYears(int $userId): array {
        return $this->expenses->listExpenditureYears($userId);
    }

    public function count(array $criteria): int {
        return $this->expenses->countBy($criteria);
    }

    public function create(
        int $userId,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): int {
        $result = $this->validateData($date, $category, $amount, $description);
        if($result != self::SUCCESS) {
            $this->logger->info("Expense create failed. ($result)");
            return $result;
        }

        $expense = new Expense(null, $userId, $date, $category, (int)($amount * 100), $description);
        $this->expenses->save($expense);

        $this->logger->info("Expense created.");

        return self::SUCCESS;
    }

    public function update(
        int $id,
        int $userId,
        float $amount,
        string $description,
        DateTimeImmutable $date,
        string $category,
    ): int {
        $result = $this->validateData($date, $category, $amount, $description);
        if($result != self::SUCCESS) {
            $this->logger->info("[EXPENSE UPDATE] Expense $id update failed. ($result)");
            return $result;
        }

        $expense = new Expense($id, $userId, $date, $category, (int)($amount * 100), $description);
        $this->expenses->update($expense);

        $this->logger->info("[EXPENSE UPDATE] Expense $id updated");

        return self::SUCCESS;
    }

    public function delete(int $id): void {
        $this->expenses->delete($id);
    }

    /**
     * @throws \Exception
     */
    public function importFromCsv(int $userId, UploadedFileInterface $csvFile): int
    {
        // Move the loaded .csv file into a temporary file
        $tmpPath = sys_get_temp_dir() . '/' . uniqid('csv_', true) . '.csv';
        $csvFile->moveTo($tmpPath);

        $rows = 0;
        $expenses = [];
        $visited = [];

        // Open the temporary file and read its content
        if(($handle = fopen($tmpPath, "r")) !== FALSE) {
            $categories = explode(",", $_ENV['CATEGORIES']);
            while(($data = fgetcsv($handle, 1000)) !== FALSE) {
                if(!in_array($data[3], $categories)) {
                    $this->logger->info("[EXPENSE IMPORT] Skip row. Unkown category $data[3].");
                    continue;
                }

                // Generate a unique key for each row by trimming all white spaces and
                $key = implode('|', array_map('trim', $data));
                if(isset($visited[$key])) {
                    // Skip current row if it is a duplicate
                    $this->logger->info("[EXPENSE IMPORT] Skip row. Duplicate $key.");
                    continue;
                }
                $visited[$key] = true;
                $rows++;
                var_dump($data[2]);
                $expense = new Expense(null, $_SESSION['user_id'], new \DateTimeImmutable($data[0]), $data[3], (int)$data[1] * 100, $data[2]);
                $expenses[] = $expense;
            }
            fclose($handle);
        }

        // Delete the temporary file
        unlink($tmpPath);

        $this->expenses->saveImported($expenses);
        return $rows; // number of imported rows
    }

    private function validateData(DateTimeImmutable $date, string $category, float $amount, string $description): int {
        if($date > new DateTimeImmutable()) {
            return self::ERROR_DATE;
        }

        if($category === "Select a category") {
            return self::ERROR_CATEGORY;
        }

        if($amount <= 0) {
            return self::ERROR_AMOUNT;
        }

        if(strlen($description) == 0) {
            return self::ERROR_DESCRIPTION;
        }

        return self::SUCCESS;
    }
}
