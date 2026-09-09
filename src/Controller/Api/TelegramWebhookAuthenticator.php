<?php

namespace App\Controller\Api;

use App\Repository\DriverRepository;
use App\Telegram\TelegramBotApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TelegramWebhookAuthenticator extends AbstractController
{

    #[Route('/api/telegram/webhook', name: 'api_telegram_webhook', methods: ['POST'])]
    public function __invoke(Request $request, DriverRepository $drivers, TelegramBotApi $telegram): Response
    {
        $update = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);

        // Telegram шлёт разные типы апдейтов — нас интересует только текстовое сообщение
        $message = $update['message'] ?? null;
        if (!is_array($message) || !isset($message['text'], $message['chat']['id'])) {
            return new Response('', Response::HTTP_OK);   // ack и игнор
        }

        $chatId = (int) $message['chat']['id'];
        $text = trim((string) $message['text']);

        if (str_starts_with($text, '/start') || str_starts_with($text, '/help')) {
            $telegram->sendMessage($chatId, 'Send a driver last name to check the blacklist.');
            return new Response('', Response::HTTP_OK);
        }

        $query = ltrim($text, '/');
        $found = $drivers->search($query, 10);

        $telegram->sendMessage($chatId, $this->formatReply($query, $found));

        return new Response('', Response::HTTP_OK);
    }

    /**
     * @param list<\App\Entity\Driver> $drivers
     */
    private function formatReply(string $query, array $drivers): string
    {
        if ($drivers === []) {
            return sprintf('Nothing found for <b>%s</b>.', htmlspecialchars($query));
        }

        $blocks = [];
        foreach ($drivers as $driver) {
            $active = 0;
            $firstReason = null;
            foreach ($driver->getBlacklistEntries() as $entry) {
                if ($entry->isActive()) {
                    $active++;
                    $firstReason ??= $entry->getText();
                }
            }

            $lines = ['<b>' . htmlspecialchars($driver->getFullName()) . '</b>'];
            if ($driver->getRegion() !== null) {
                $lines[] = htmlspecialchars($driver->getRegion()->getName());
            }
            $lines[] = $active > 0
                ? sprintf('🚫 %d active blacklist entr%s', $active, $active === 1 ? 'y' : 'ies')
                : '✅ not on the blacklist';
            if ($firstReason !== null) {
                $lines[] = '• ' . htmlspecialchars($firstReason);
            }

            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }
}