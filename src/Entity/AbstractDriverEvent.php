<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

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

    #[ORM\Column(length: 255)]
    protected ?string $reportedBy = null;

    #[ORM\Column(type: 'text')]
    protected ?string $text = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
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

    public function getReportedBy(): ?string
    {
        return $this->reportedBy;
    }

    public function setReportedBy(string $reportedBy): static
    {
        $this->reportedBy = $reportedBy;
        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
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