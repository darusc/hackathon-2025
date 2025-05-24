<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use Psr\Log\LoggerInterface;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private LoggerInterface $logger,
    ) {}

    public function register(string $username, string $password): ?User
    {
        // Check if a user with the current username already exists
        $user = $this->users->findByUsername($username);
        if ($user != null) {
            $this->logger->alert("User '{$username}' already exists");
            return null;
        }

        if(!$this->validateCredentials($username, $password)) {
            return null;
        }

        // Hash the password before storing it in the database
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $user = new User(null, $username, $hashed_password, new \DateTimeImmutable());
        $this->users->save($user);

        $this->logger->alert("User '{$username}' has been created");

        return $user;
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
    private function validateCredentials(string $username, string $password): bool {
        // Check length
        if(strlen($username) < 4 || strlen($password) < 8){
            $this->logger->alert('Username or password is too short');
            return false;
        }

        // Check if password contains at least 1 number
        if(!preg_match("/\d/", $password)){
            $this->logger->alert('Password must contain at least one number');
            return false;
        }

        return true;
    }
}
