<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

class AuthController extends BaseController
{
    public function __construct(
        Twig $view,
        private AuthService $authService,
        private LoggerInterface $logger,
    ) {
        parent::__construct($view);
    }

    public function showRegister(Request $request, Response $response): Response
    {
        $this->logger->info('Register page requested');

        // Get previous filled credentials and possible error
        $errors = $_SESSION['registerErrors'] ?? [];
        $username = $_SESSION['username'] ?? null;
        $password = $_SESSION['password'] ?? null;
        unset($_SESSION['registerErrors']);
        unset($_SESSION['username']);
        unset($_SESSION['password']);

        return $this->render($response, 'auth/register.twig', [
            'username' => $username,
            'password' => $password,
            'errors' => $errors,
        ]);
    }

    public function register(Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();
        $username = $params['username'];
        $password = $params['password'];

        $result = $this->authService->register($username, $password);

        if($result == AuthService::SUCCESSS){
            return $response->withHeader('Location', '/login')->withStatus(302);
        } else {
            // Pass the register error and the credentials to the register view through $_SESSION
            $_SESSION['username'] = $username;
            $_SESSION['password'] = $password;

            // Build the error messages based on the error returned from authService
            $_SESSION['registerErrors'] = [
                'username' => $result == AuthService::USERNAME_ALREADY_EXISTS ? "Username already exists" :
                             ($result == AuthService::USERNAME_TOO_SHORT ? "Username too short" : null),
                'password' => $result == AuthService::PASSWORD_TOO_SHORT ? "Password too short" :
                             ($result == AuthService::PASSWORD_NO_NUMBER ? "Password must contain at least 1 number" : null)
            ];

            return $response->withHeader('Location', '/register')->withStatus(302);
        }
    }

    public function showLogin(Request $request, Response $response): Response
    {
        return $this->render($response, 'auth/login.twig');
    }

    public function login(Request $request, Response $response): Response
    {
        // TODO: call corresponding service to perform user login, handle login failures

        return $response->withHeader('Location', '/')->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        // TODO: handle logout by clearing session data and destroying session

        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
