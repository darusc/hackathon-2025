<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

class AuthService
{
    public const SUCCESS = 0;
    public const INVALID_USERNAME_PASSWORD = "Invalid username or password";
    public const USERNAME_ALREADY_EXISTS = "Username already exists";
    public const USERNAME_TOO_SHORT = "Username must be at least 4 characters long";
    public const PASSWORD_TOO_SHORT = "Password must be at least 8 characters long";
    public const PASSWORD_NO_NUMBER = "Password must contain at least 1 number";
    public const PASSWORD_NO_MATCH = "Passwords don't match";

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly LoggerInterface         $logger,
    )
    {
    }

    /**
     * Register a new user with the given username and password.
     * Returns an array representing the errors for each
     * field (username, password, passwordConfirmation)
     * Or true if there register was successful
     */
    public function register(string $username, string $password, string $passwordConfirm): array|bool
    {
        $errors = [self::SUCCESS, self::SUCCESS, self::SUCCESS];

        // Check if a user with the current username already exists
        $user = $this->users->findByUsername($username);
        if ($user != null) {
            $this->logger->alert("[REGISTER] User '$username' already exists");
            $errors[0] = self::USERNAME_ALREADY_EXISTS;
        }


        // Validate the given username and password for registering.
        // Username criteria: length >= 4 characters
        // Password criteria: length >= 8 characters + at least 1 number
        if (strlen($username) < 4) {
            $errors[0] = self::USERNAME_TOO_SHORT;
        }
        if (strlen($password) < 8) {
            $errors[1] = self::PASSWORD_TOO_SHORT;
        }
        // Check if password contains at least 1 number
        if (!preg_match("/\d/", $password)) {
            $errors[1] = self::PASSWORD_NO_NUMBER;
        }
        if (strcmp($password, $passwordConfirm) != 0) {
            $errors[2] = self::PASSWORD_NO_MATCH;
        }

        if ($errors[0] != null || $errors[1] != null || $errors[2] != null) {
            $this->logger->alert("[REGISTER] Credentials are invalid. ($errors[0], $errors[1], $errors[2]);");
            return $errors;
        }

        // Hash the password before storing it in the database
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $user = new User(null, $username, $hashed_password, new DateTimeImmutable());
        $this->users->save($user);

        $this->logger->alert("[REGISTER] User '$username' has been created");

        return true;
    }

    /**
     * Attempt to log in with the given username and password.
     * If login is successful returns true otherwise returns a string
     * representing the encountered error.
     */
    public function attempt(string $username, string $password): string|bool
    {
        // Check if the username exists
        $user = $this->users->findByUsername($username);
        if ($user == null) {
            $this->logger->alert("[LOGIN] User '$username' does not exist");
            return self::INVALID_USERNAME_PASSWORD;
        }

        if (password_verify($password, $user->passwordHash)) {
            $this->logger->alert("[LOGIN] User '$username' has been authenticated");

            // Start new session and store user data
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user->id;

            return true;
        }

        return self::INVALID_USERNAME_PASSWORD;
    }
}
