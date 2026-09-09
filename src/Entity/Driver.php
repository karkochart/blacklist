<?php

namespace App\Entity;

use App\Repository\DriverRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: DriverRepository::class)]
class Driver
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['driver:list'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['driver:list'])]
    private ?string $lastName = null;

    #[ORM\Column(length: 255)]
    #[Groups(['driver:list'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['driver:list'])]
    private ?string $middleName = null;

    // Unique when present; NULL is allowed any number of times
    // (MySQL/MariaDB/SQLite treat NULLs as distinct in a UNIQUE index).
    #[ORM\Column(length: 50, nullable: true, unique: true)]
    #[Groups(['driver:list'])]
    private ?string $licenseNumber = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['driver:list'])]
    private ?\DateTimeImmutable $birthDate = null;

    #[ORM\ManyToOne(inversedBy: 'drivers')]
    #[Groups(['driver:list'])]
    private ?Region $region = null;

    /**
     * @var Collection<int, BlacklistEntry>
     */
    #[ORM\OneToMany(targetEntity: BlacklistEntry::class, mappedBy: 'driver')]
    #[Groups(['driver:list'])]
    private Collection $blacklistEntries;

    /**
     * @var Collection<int, DriverHistoryEntry>
     */
    #[ORM\OneToMany(targetEntity: DriverHistoryEntry::class, mappedBy: 'driver')]
    #[Groups(['driver:list'])]
    private Collection $historyEntries;

    public function __construct()
    {
        $this->blacklistEntries = new ArrayCollection();
        $this->historyEntries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getMiddleName(): ?string
    {
        return $this->middleName;
    }

    public function setMiddleName(?string $middleName): static
    {
        $this->middleName = $middleName;

        return $this;
    }

    public function getLicenseNumber(): ?string
    {
        return $this->licenseNumber;
    }

    public function setLicenseNumber(?string $licenseNumber): static
    {
        $this->licenseNumber = $licenseNumber;

        return $this;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeImmutable $birthDate): static
    {
        $this->birthDate = $birthDate;

        return $this;
    }

    public function getRegion(): ?Region
    {
        return $this->region;
    }

    public function setRegion(?Region $region): static
    {
        $this->region = $region;

        return $this;
    }

    /**
     * @return Collection<int, BlacklistEntry>
     */
    public function getBlacklistEntries(): Collection
    {
        return $this->blacklistEntries;
    }

    /**
     * @return Collection<int, DriverHistoryEntry>
     */
    public function getHistoryEntries(): Collection
    {
        return $this->historyEntries;
    }
}
