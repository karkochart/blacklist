<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\TelegramUser;
use App\Repository\TelegramUserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * "Authorization" here just means: everyone who ever contacts the bot gets a
 * TelegramUser row, identified by Telegram's own user id. It's identification,
 * not a gate — Subscription decides what an identified user is allowed to do.
 */
final class TelegramAuth
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TelegramUserRepository $telegramUsers,
    ) {
    }

    /**
     * @param array<string, mixed> $from Telegram's "from" object: {id, username?, first_name?, ...}
     */
    public function identify(array $from): TelegramUser
    {
        $telegramId = (int) ($from['id'] ?? 0);
        $username = isset($from['username']) ? (string) $from['username'] : null;
        $firstName = isset($from['first_name']) ? (string) $from['first_name'] : null;

        $user = $this->telegramUsers->findOneByTelegramId($telegramId);
        if ($user === null) {
            $user = new TelegramUser($telegramId);
            $this->em->persist($user);
        }

        // Telegram usernames/names can change at any time — keep them fresh on every contact.
        $user->setUsername($username);
        $user->setFirstName($firstName);
        $this->em->flush();

        return $user;
    }
}
