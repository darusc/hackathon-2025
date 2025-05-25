<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use DateTimeImmutable;

final class Expense
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly DateTimeImmutable $date,
        public readonly string $category,
        public readonly float $amount,
        public readonly string $description,
    ) {}
}
