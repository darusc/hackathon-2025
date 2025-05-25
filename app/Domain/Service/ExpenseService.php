<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\Expense;
use App\Domain\Repository\ExpenseRepositoryInterface;
use DateTimeImmutable;
use Exception;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;

class ExpenseService
{
    public const ERROR_DATE = "Date must be less than or equal to today";
    public const ERROR_CATEGORY = "Category must be selected";
    public const ERROR_AMOUNT = "Amount must be greater than 0";
    public const ERROR_DESCRIPTION = "Description must not be empty";


    public function __construct(
        private readonly ExpenseRepositoryInterface $expenses,
        private readonly LoggerInterface            $logger
    )
    {
    }

    public function findById(int $id): Expense|null
    {
        return $this->expenses->find($id);
    }

    public function list(int $userId, int $year, int $month, int $pageNumber, int $pageSize): array
    {
        return $this->expenses->findBy(
            ['user_id' => $userId, 'year' => $year, 'month' => $month],
            ($pageNumber - 1) * $pageSize,
            $pageSize
        );
    }

    public function listExpenditureYears(int $userId): array
    {
        return $this->expenses->listExpenditureYears($userId);
    }

    public function count(array $criteria): int
    {
        return $this->expenses->countBy($criteria);
    }

    /**
     * @return array|bool Array containing the errors [date, category, amount, description]
     * or true if create succeeded
     */
    public function create(
        int               $userId,
        float             $amount,
        string            $description,
        DateTimeImmutable $date,
        string            $category,
    ): array|bool
    {
        $errors = [null, null, null, null];

        $result = $this->validateData($date, $category, $amount, $description, $errors);
        if (!$result) {
            $this->logger->info("Expense create failed.");
            return $errors;
        }

        $expense = new Expense(null, $userId, $date, $category, $amount, $description);
        $this->expenses->save($expense);

        $this->logger->info("Expense created.");

        return true;
    }

    /**
     * @return array|bool Array containing the errors [date, category, amount, description]
     * or true if create succeeded
     */
    public function update(
        int               $id,
        int               $userId,
        float             $amount,
        string            $description,
        DateTimeImmutable $date,
        string            $category,
    ): array|bool
    {
        $errors = [null, null, null, null];

        $result = $this->validateData($date, $category, $amount, $description, $errors);
        if (!$result) {
            $this->logger->info("[EXPENSE UPDATE] Expense $id update failed.");
            return $errors;
        }

        $expense = new Expense($id, $userId, $date, $category, $amount, $description);
        $this->expenses->update($expense);

        $this->logger->info("[EXPENSE UPDATE] Expense $id updated");

        return true;
    }

    public function delete(int $id): void
    {
        $this->expenses->delete($id);
    }

    /**
     * @throws Exception
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
        if (($handle = fopen($tmpPath, "r")) !== FALSE) {
            $categories = explode(",", $_ENV['CATEGORIES']);
            while (($data = fgetcsv($handle, 1000)) !== FALSE) {
                if (!in_array($data[3], $categories)) {
                    $this->logger->info("[EXPENSE IMPORT] Skip row. Unknown category $data[3].");
                    continue;
                }

                // Generate a unique key for each row by trimming all white spaces and
                $key = implode('|', array_map('trim', $data));
                if (isset($visited[$key])) {
                    // Skip current row if it is a duplicate
                    $this->logger->info("[EXPENSE IMPORT] Skip row. Duplicate $key.");
                    continue;
                }
                $visited[$key] = true;
                $rows++;
                $expense = new Expense(null, $userId, new DateTimeImmutable($data[0]), $data[3], (float)$data[1], $data[2]);
                $expenses[] = $expense;
            }
            fclose($handle);
        }

        // Delete the temporary file
        unlink($tmpPath);

        $this->expenses->saveImported($expenses);

        return $rows; // number of imported rows
    }

    /**
     * Validates the expense data with the following criteria:
     * Date ≤ today
     * Category selected
     * Amount > 0
     * Description not empty
     *
     * Returns true if all criteria is met, false otherwise.
     * Sets the $errors param accordingly => [date, category, amount, description].
     */
    private function validateData(DateTimeImmutable $date, string $category, float $amount, string $description, array &$errors): bool
    {
        if ($date > new DateTimeImmutable()) {
            $errors[0] = self::ERROR_DATE;
        }

        if ($category === "Select a category") {
            $errors[1] = self::ERROR_CATEGORY;
        }

        if ($amount <= 0) {
            $errors[2] = self::ERROR_AMOUNT;
        }

        if (strlen($description) == 0) {
            $errors[3] = self::ERROR_DESCRIPTION;
        }

        foreach ($errors as $error) {
            if ($error != null) {
                return false;
            }
        }
        
        return true;
    }
}
