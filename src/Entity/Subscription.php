<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SubscriptionType;
use App\Repository\SubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * One granted period of access. Normal top-ups create a new row (see
 * SubscriptionService::grant()) so the grant history stays intact. expiresAt is
 * still directly correctable from the admin panel for the odd manual fix
 * (wrong date typed in, a complaint, a refund) — that's an explicit exception
 * to "immutable", not the everyday path.
 */
#[ORM\Entity(repositoryClass: SubscriptionRepository::class)]
class Subscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TelegramUser::class, inversedBy: 'subscriptions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TelegramUser $telegramUser = null;

    #[ORM\Column(type: 'string', enumType: SubscriptionType::class)]
    private ?SubscriptionType $type = null;

    #[ORM\Column]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    // Which admin granted it, manually, from the admin panel. Null once this is
    // ever driven by an automated payment instead.
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $grantedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        TelegramUser $telegramUser,
        SubscriptionType $type,
        \DateTimeImmutable $startsAt,
        \DateTimeImmutable $expiresAt,
        ?User $grantedBy = null,
    ) {
        $this->telegramUser = $telegramUser;
        $this->type = $type;
        $this->startsAt = $startsAt;
        $this->expiresAt = $expiresAt;
        $this->grantedBy = $grantedBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTelegramUser(): ?TelegramUser
    {
        return $this->telegramUser;
    }

    public function getType(): ?SubscriptionType
    {
        return $this->type;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getGrantedBy(): ?User
    {
        return $this->grantedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
