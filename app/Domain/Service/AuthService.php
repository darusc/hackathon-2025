<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use Psr\Log\LoggerInterface;

class AuthService
{
    public const SUCCESSS = 0;
    public const INVALID_PASSWORD = 1;
    public const INVALID_USERNAME = 2;
    public const USERNAME_ALREADY_EXISTS = 3;
    public const USERNAME_TOO_SHORT = 4;
    public const PASSWORD_TOO_SHORT = 5;
    public const PASSWORD_NO_NUMBER = 6;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private LoggerInterface $logger,
    ) {}

    public function register(string $username, string $password): int
    {
        // Check if a user with the current username already exists
        $user = $this->users->findByUsername($username);
        if ($user != null) {
            $this->logger->alert("User '{$username}' already exists");
            return self::USERNAME_ALREADY_EXISTS;
        }

        $err = $this->validateCredentials($username, $password);
        if($err != self::SUCCESSS) {
            $this->logger->alert("Credentials are invalid. ($err)");
            return $err;
        }

        // Hash the password before storing it in the database
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $user = new User(null, $username, $hashed_password, new \DateTimeImmutable());
        $this->users->save($user);

        $this->logger->alert("User '{$username}' has been created");

        return self::SUCCESSS;
    }

    public function attempt(string $username, string $password): bool
    {
        // TODO: implement this for authenticating the user
        // TODO: make sur ethe user exists and the password matches
        // TODO: don't forget to store in session user data needed afterwards

        return true;
    }

    /**
     * Validate the given username and passwords.
     * Username criteria: length >= 4 characters
     * Password criteria: length >= 8 characters + at least 1 number
     */
    private function validateCredentials(string $username, string $password): int {
        if(strlen($username) < 4){
            return self::USERNAME_TOO_SHORT;
        }

        if(strlen($password) < 8){
            return self::PASSWORD_TOO_SHORT;
        }

        // Check if password contains at least 1 number
        if(!preg_match("/\d/", $password)){
            return self::PASSWORD_NO_NUMBER;
        }

        return self::SUCCESSS;
    }
}
