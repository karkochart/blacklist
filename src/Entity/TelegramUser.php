<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TelegramUserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Anyone who has ever messaged the bot — created automatically on first contact,
 * identified by their Telegram user id. Having a row here is not authorization by
 * itself; whether they can actually search is decided by Subscription.
 */
#[ORM\Entity(repositoryClass: TelegramUserRepository::class)]
class TelegramUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Doctrine's "bigint" type is a string on 32-bit PHP, but this app only ever
    // runs on 64-bit PHP, where it round-trips as int — kept as int for that reason.
    #[ORM\Column(type: 'bigint', unique: true)]
    private int $telegramId;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Subscription>
     */
    #[ORM\OneToMany(targetEntity: Subscription::class, mappedBy: 'telegramUser')]
    private Collection $subscriptions;

    public function __construct(int $telegramId)
    {
        $this->telegramId = $telegramId;
        $this->createdAt = new \DateTimeImmutable();
        $this->subscriptions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTelegramId(): int
    {
        return $this->telegramId;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Subscription>
     */
    public function getSubscriptions(): Collection
    {
        return $this->subscriptions;
    }

    public function getDisplayName(): string
    {
        if ($this->username !== null) {
            return '@' . $this->username;
        }

        return $this->firstName ?? (string) $this->telegramId;
    }
}
