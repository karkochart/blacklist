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

    public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ];
        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        $this->call('sendMessage', $payload);
    }

    public function answerCallbackQuery(string $callbackQueryId): void
    {
        $this->call('answerCallbackQuery', ['callback_query_id' => $callbackQueryId]);
    }

    /**
     * @param array<string, mixed> $json
     */
    private function call(string $method, array $json): void
    {
        try {
            $response = $this->telegramClient->request('POST', $method, ['json' => $json]);

            // Force the request to complete now so failures are logged here, not lazily later.
            if (200 !== $response->getStatusCode()) {
                $this->logger->error('Telegram {method} returned {status}', [
                    'method' => $method,
                    'status' => $response->getStatusCode(),
                    'body' => $response->getContent(false),
                ]);
            }
        } catch (ExceptionInterface $e) {
            // Never let a Telegram outage break the webhook response.
            $this->logger->error('Telegram {method} failed: {message}', ['method' => $method, 'message' => $e->getMessage()]);
        }
    }
}
