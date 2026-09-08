<?php

namespace App\Entity;

use App\Repository\BlacklistEntryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlacklistEntryRepository::class)]
class BlacklistEntry extends AbstractDriverEvent
{
    #[ORM\Column]
    private bool $isActive = true;

    /**
     * Origin of an imported row ("<file>#row-<n>"); NULL for entries created via API/bot.
     * Unique, so a spreadsheet import can be re-run without creating duplicates.
     */
    #[ORM\Column(length: 120, nullable: true, unique: true)]
    private ?string $sourceReference = null;

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getSourceReference(): ?string
    {
        return $this->sourceReference;
    }

    public function setSourceReference(?string $sourceReference): static
    {
        $this->sourceReference = $sourceReference;
        return $this;
    }
}