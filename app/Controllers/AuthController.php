<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Domain\Service\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Random\RandomException;
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

    /**
     * @throws RandomException
     */
    public function showRegister(Request $request, Response $response): Response
    {
        $this->logger->info('Register page requested');

        // Get previous filled credentials and possible error
        $errors = $_SESSION['registerErrors'] ?? [];
        $username = $_SESSION['username'] ?? null;
        $password = $_SESSION['password'] ?? null;
        $passwordConfirm = $_SESSION['passwordConfirm'] ?? null;
        unset($_SESSION['registerErrors']);
        unset($_SESSION['username']);
        unset($_SESSION['password']);

        // Generate a random CSRF token which is hidden in the form
        // After the form is submitted the controller checks the
        // token to be identical to the one generated
        $_SESSION['csrfToken'] = bin2hex(random_bytes(32));

        return $this->render($response, 'auth/register.twig', [
            'username' => $username,
            'password' => $password,
            'passwordConfirm' => $passwordConfirm,
            'errors' => $errors,
            'csrfToken' => $_SESSION['csrfToken']
        ]);
    }

    public function register(Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();
        $username = $params['username'];
        $password = $params['password'];
        $passwordConfirm = $params['passwordConfirm'];
        $csrfToken = $params['csrftoken'];

        // Terminate immediately if the CSRF token doesn't match the generated one
        if(!isset($_SESSION['csrfToken']) || $csrfToken !== $_SESSION['csrfToken']){
            $this->logger->alert("[REGISTER] CSRF Failed");
            return $response->withHeader('Location', '/register')->withStatus(302);
        }

        $result = $this->authService->register($username, $password, $passwordConfirm);
        if(is_bool($result) && $result){
            return $response->withHeader('Location', '/login')->withStatus(302);
        } else {
            // Pass the register error and the credentials to the register view through $_SESSION
            $_SESSION['username'] = $username;
            $_SESSION['password'] = $password;
            $_SESSION['passwordConfirm'] = $passwordConfirm;

            // Set the error messages based on the result returned from authService
            $_SESSION['registerErrors'] = ['username' => $result[0], 'password' => $result[1], 'passwordConfirm' => $result[2]];

            return $response->withHeader('Location', '/register')->withStatus(302);
        }
    }

    /**
     * @throws RandomException
     */
    public function showLogin(Request $request, Response $response): Response
    {
        $error = $_SESSION['loginError'] ?? null;
        unset($_SESSION['loginErrors']);

        // Generate a random CSRF token which is hidden in the form
        // After the form is submitted the controller checks the
        // token to be identical to the one generated
        $_SESSION['csrfToken'] = bin2hex(random_bytes(32));

        return $this->render($response, 'auth/login.twig', ['error' => $error, 'csrfToken' => $_SESSION['csrfToken']]);
    }

    public function login(Request $request, Response $response): Response
    {
        $params = $request->getParsedBody();
        $username = $params['username'];
        $password = $params['password'];
        $csrfToken = $params['csrftoken'];

        // Terminate immediately if the CSRF token doesn't match the generated one
        if(!isset($_SESSION['csrfToken']) || $csrfToken !== $_SESSION['csrfToken']){
            $this->logger->alert("[LOGIN] CSRF Failed");
            return $response->withHeader('Location', '/login')->withStatus(302);
        }

        $result = $this->authService->attempt($username, $password);
        if(is_bool($result) && $result){
            // Regenerate the session id and remove the old one to prevent session fixation attacks
            session_regenerate_id(true);
            return $response->withHeader('Location', '/')->withStatus(302);
        } else {
            // Build the error messages based on the error returned from authService
            $_SESSION['loginError'] = $result;

            return $response->withHeader('Location', '/login')->withStatus(302);
        }

    }

    public function logout(Request $request, Response $response): Response
    {
        session_unset();
        session_destroy();
        return $response->withHeader('Location', '/login')->withStatus(302);
    }
}
