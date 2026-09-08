<?php

declare(strict_types=1);

namespace App\Import;

final readonly class ParsedName
{
    public function __construct(
        public string $lastName,
        public string $firstName,
        public ?string $middleName = null,
        public ?\DateTimeImmutable $birthDate = null,
        public ?int $birthYear = null,
    ) {
    }
}
