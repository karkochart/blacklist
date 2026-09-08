<?php

namespace App\Entity;

use App\Repository\BlacklistEntryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlacklistEntryRepository::class)]
class BlacklistEntry extends AbstractDriverEvent
{
    #[ORM\Column]
    private bool $isActive = true;

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }
}