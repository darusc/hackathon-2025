<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\ExpenseService;
use DateTimeImmutable;
use Exception;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class ExpenseController extends BaseController
{
    private const PAGE_SIZE = 20;
    private const MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    public function __construct(
        Twig                             $view,
        private readonly ExpenseService  $expenseService,
        private readonly LoggerInterface $logger
    )
    {
        parent::__construct($view);
    }

    public function index(Request $request, Response $response): Response
    {
        // Parse request parameters
        $userId = $_SESSION['user_id'];
        $page = (int)($request->getQueryParams()['page'] ?? 1);
        $pageSize = (int)($request->getQueryParams()['pageSize'] ?? self::PAGE_SIZE);
        $year = (int)($request->getQueryParams()['year'] ?? date("Y"));
        $month = (int)($request->getQueryParams()['month'] ?? date("m"));

        $years = $this->expenseService->listExpenditureYears($userId);
        $expenses = $this->expenseService->list($userId, $year, $month, $page, $pageSize);

        $count = $this->expenseService->count(['user_id' => $userId, 'year' => $year, 'month' => $month]);

        // If a flash message needs to be shown the values are
        // set in $_SESSION, otherwise make them null to avoid
        // rendering the corresponding HTML components
        $importedRows = $_SESSION['importedRows'] ?? null;
        unset($_SESSION['importedRows']);
        $expenseDestroyed = $_SESSION['expenseDestroyed'] ?? null;
        unset($_SESSION['expenseDestroyed']);

        // Build the page urls for the 1 2 3 ... N page links
        $pageurls = [];
        $totalPages = (int)ceil($count / $pageSize);
        for ($i = 1; $i <= $totalPages; $i++) {
            $pageurls[] = $this->buildPageUrl($i);
        }

        return $this->render($response, 'expenses/index.twig', [
            'expenses' => $expenses,
            'page' => $page,
            'pageSize' => $pageSize,
            'years' => $years,
            'months' => self::MONTHS,
            'total' => $count,
            'selectedYear' => $year,
            'selectedMonth' => $month,
            'nextPageUrl' => $this->buildPageUrl($page + 1),
            'previousPageUrl' => $this->buildPageUrl($page - 1),
            'importedRows' => $importedRows,
            'expenseDestroyed' => $expenseDestroyed,
            'currentPage' => $page,
            'totalPages' => (int)ceil($count / $pageSize),
            'pageurls' => $pageurls,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $categories = explode(",", $_ENV['CATEGORIES']);
        return $this->render($response, 'expenses/create.twig', ['categories' => $categories]);
    }

    /**
     * @throws Exception
     */
    public function store(Request $request, Response $response): Response
    {
        $userId = $_SESSION['user_id'];

        $body = $request->getParsedBody();
        $category = $body['category'] ?? null;
        $amount = $body['amount'] ?? null;
        $description = $body['description'] ?? null;
        $date = new DateTimeImmutable($body['date']) ?: new DateTimeImmutable();

        $result = $this->expenseService->create($userId, (float)$amount, $description, $date, $category);
        if (is_bool($result) && $result) {
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        } else {
            // Prefill the create page with previous data
            return $this->render($response, 'expenses/create.twig', [
                'categories' => explode(",", $_ENV['CATEGORIES']),
                'selectedAmount' => $amount,
                'selectedDate' => $date->format('c'),
                'selectedDescription' => $description,
                'selectedCategory' => $category,
                'errors' => $result,
            ]);
        }
    }

    public function edit(Request $request, Response $response, array $routeParams): Response
    {
        $categories = explode(",", $_ENV['CATEGORIES']);

        $expenseId = $routeParams['id'];
        $expense = $this->expenseService->findById((int)$expenseId);

        if ($expense == null) {
            $this->logger->info("[EXPENSE EDIT] Expense '$expenseId' not found");
            return $response->withStatus(404);
        }

        // Check if the logged-in user is the owner of the edited expense
        if ($expense->userId != $_SESSION['user_id']) {
            $this->logger->info("[EXPENSE EDIT] User '{$_SESSION['user_id']}' does not have permission to edit this expense");
            return $response->withStatus(403);
        }

        $previousEditErrors = $_SESSION['expenseEditErrors'] ?? null;
        unset($_SESSION['expenseEditErrors']);

        return $this->render($response, 'expenses/edit.twig', ['expense' => $expense, 'categories' => $categories, 'errors' => $previousEditErrors]);
    }

    /**
     * @throws Exception
     */
    public function update(Request $request, Response $response, array $routeParams): Response
    {
        $userId = $_SESSION['user_id'];
        $expenseId = $routeParams['id'];
        $expense = $this->expenseService->findById((int)$expenseId);

        // Check if the expense exists
        if ($expense == null) {
            $this->logger->info("[EXPENSE EDIT] Expense '$expenseId' not found");
            return $response->withStatus(404);
        }

        // Check if the current user owns the expense to be deleted
        if ($expense->userId != $userId) {
            $this->logger->info("[EXPENSE EDIT] User '$userId' does not have permission to edit this expense");
            return $response->withStatus(403);
        }

        // Get edited data from the form
        $body = $request->getParsedBody();
        $category = $body['category'] ?? null;
        $amount = $body['amount'] ?? null;
        $description = $body['description'] ?? null;
        $date = new DateTimeImmutable($body['date']) ?: new DateTimeImmutable();

        $result = $this->expenseService->update((int)$expenseId, $userId, (float)$amount, $description, $date, $category);
        if (is_bool($result) && $result) {
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        } else {
            $_SESSION['expenseEditErrors'] = $result;
            return $response->withHeader('Location', "/expenses/$expenseId/edit")->withStatus(302);
        }
    }

    public function destroy(Request $request, Response $response, array $routeParams): Response
    {
        $expenseId = $routeParams['id'];
        $userId = $_SESSION['user_id'];

        $expense = $this->expenseService->findById((int)$expenseId);

        // Check if the expense exists
        if ($expense == null) {
            $this->logger->info("[EXPENSE DELETE] Expense '$expenseId' not found");
            return $response->withStatus(404);
        }

        // Check if the current user owns the expense to be deleted
        if ($expense->userId != $userId) {
            $this->logger->info("[EXPENSE DELETE] User '$userId' does not have permission to delete this expense");
            return $response->withStatus(403);
        }

        $this->expenseService->delete((int)$expenseId);
        $_SESSION['expenseDestroyed'] = $expense->description;
        $this->logger->info("[EXPENSE DELETE] Expense '$expenseId' deleted");

        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }

    /**
     * @throws Exception
     */
    public function import(Request $request, Response $response): Response
    {
        $files = $request->getUploadedFiles();

        if (empty($files['csv'])) {
            $this->logger->info("[EXPENSE LOAD CSV] No file uploaded");
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        }

        $csvfile = $files['csv'];
        if ($csvfile->getError() != UPLOAD_ERR_OK) {
            $this->logger->info("[EXPENSE LOAD CSV] File upload error");
            return $response->withHeader('Location', '/expenses')->withStatus(302);
        }

        $rows = $this->expenseService->importFromCsv($_SESSION['user_id'], $csvfile);
        $_SESSION['importedRows'] = $rows;
        $this->logger->info("[EXPENSES IMPORT] Imported $rows rows");

        return $response->withHeader('Location', '/expenses')->withStatus(302);
    }

    /**
     * Build the url for the given page.
     * Takes into account existing query params.
     */
    private function buildPageUrl(int $page): string
    {
        $params = $_GET;
        $baseurl = strtok($_SERVER['REQUEST_URI'], "?");
        $params['page'] = $page;
        
        return $baseurl . '?' . http_build_query($params);
    }
}
