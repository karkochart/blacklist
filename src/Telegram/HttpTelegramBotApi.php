<?php

declare(strict_types=1);

namespace App\Telegram;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to https://api.telegram.org/bot<token>/ via the "telegram.client" scoped client.
 */
final class HttpTelegramBotApi implements TelegramBotApi
{
    public function __construct(
        // parameter name matches the scoped client id "telegram.client" (camelCased) → autowired
        private readonly HttpClientInterface $telegramClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendMessage(int $chatId, string $text): void
    {
        try {
            $response = $this->telegramClient->request('POST', 'sendMessage', [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ],
            ]);

            // Force the request to complete now so failures are logged here, not lazily later.
            if (200 !== $response->getStatusCode()) {
                $this->logger->error('Telegram sendMessage returned {status}', [
                    'status' => $response->getStatusCode(),
                    'body' => $response->getContent(false),
                ]);
            }
        } catch (ExceptionInterface $e) {
            // Never let a Telegram outage break the webhook response.
            $this->logger->error('Telegram sendMessage failed: {message}', ['message' => $e->getMessage()]);
        }
    }
}
