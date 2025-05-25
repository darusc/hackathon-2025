<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;

final class User
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $username,
        public readonly string $passwordHash,
        public readonly DateTimeImmutable $createdAt,
    ) {}
}
