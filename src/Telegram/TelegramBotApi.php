<?php

declare(strict_types=1);

namespace App\Telegram;

/**
 * The slice of the Telegram Bot API this app needs. An interface so the
 * webhook controller can be tested without hitting the network.
 */
interface TelegramBotApi
{
    /**
     * @param string $text HTML-formatted message body (parse_mode=HTML)
     */
    public function sendMessage(int $chatId, string $text): void;
}
