<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\MappedSuperclass]
abstract class AbstractDriverEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Driver::class)]
    #[ORM\JoinColumn(nullable: false)]
    protected ?Driver $driver = null;

    // Two ways of saying "who reported this":
    //  - $reporter : an app user, set for entries created through the admin / API
    //  - $reportedBy : free text from the original spreadsheet import (dirty nicknames),
    //                  kept for historical rows where there is no matching account
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    protected ?User $reporter = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['driver:list'])]
    protected ?string $reportedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]

    #[Groups(['driver:list'])]
    protected ?string $text = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['driver:list'])]
    protected ?\DateTimeImmutable $occurredAt = null;

    #[ORM\Column]
    protected \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDriver(): ?Driver
    {
        return $this->driver;
    }

    public function setDriver(?Driver $driver): static
    {
        $this->driver = $driver;
        return $this;
    }

    public function getReporter(): ?User
    {
        return $this->reporter;
    }

    public function setReporter(?User $reporter): static
    {
        $this->reporter = $reporter;
        return $this;
    }

    public function getReportedBy(): ?string
    {
        return $this->reportedBy;
    }

    public function setReportedBy(?string $reportedBy): static
    {
        $this->reportedBy = $reportedBy;
        return $this;
    }

    /** Best available human name for whoever reported this — the account, else the imported text. */
    #[Groups(['driver:list'])]
    public function getReporterName(): ?string
    {
        return $this->reporter?->getName() ?? $this->reportedBy;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(?string $text): static
    {
        $this->text = $text;
        return $this;
    }

    public function getOccurredAt(): ?\DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(?\DateTimeImmutable $occurredAt): static
    {
        $this->occurredAt = $occurredAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}