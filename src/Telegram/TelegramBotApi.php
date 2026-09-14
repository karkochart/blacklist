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
     * @param array<string, mixed>|null $replyMarkup Any of Telegram's reply_markup shapes
     *        (InlineKeyboardMarkup, ReplyKeyboardMarkup, ForceReply), or null for none.
     */
    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): void;

    /**
     * Must be called after handling a callback_query update, or the tapped
     * button keeps showing a loading spinner on the user's side.
     */
    public function answerCallbackQuery(string $callbackQueryId): void;
}
