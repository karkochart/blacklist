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
     * @param array{inline_keyboard: list<list<array{text: string, callback_data: string}>>}|null $replyMarkup
     *        Telegram's InlineKeyboardMarkup shape, or null for no keyboard.
     */
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): void;

    /**
     * Must be called after handling a callback_query update, or the tapped
     * button keeps showing a loading spinner on the user's side.
     */
    public function answerCallbackQuery(string $callbackQueryId): void;
}
