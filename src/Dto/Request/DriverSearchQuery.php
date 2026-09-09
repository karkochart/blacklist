<?php

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

class DriverSearchQuery
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    public string $query = '';

    #[Assert\Range(min: 1, max: 100)]
    public ?int $limit = 50;
}