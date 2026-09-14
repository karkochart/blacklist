<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\AbstractDriverEvent;
use App\Entity\BlacklistEntry;
use App\Entity\Driver;
use App\Repository\DriverRepository;
use App\Service\DriverEventFeed;
use App\Telegram\TelegramBotApi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles both update shapes Telegram sends here:
 *  - "message": a text search query (name / license number)
 *  - "callback_query": a tap on the "Load more" inline-keyboard button
 */
class TelegramWebhookController extends AbstractController
{
    private const int PAGE_SIZE = 5;

    #[Route('/api/telegram/webhook', name: 'api_telegram_webhook', methods: ['POST'])]
    public function __invoke(
        Request $request,
        DriverRepository $drivers,
        DriverEventFeed $feed,
        TelegramBotApi $telegram,
    ): Response {
        $update = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);

        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query'], $drivers, $feed, $telegram);

            return new Response('', Response::HTTP_OK);
        }

        $message = $update['message'] ?? null;
        if (!is_array($message) || !isset($message['text'], $message['chat']['id'])) {
            return new Response('', Response::HTTP_OK);   // ack и игнор — не наш тип апдейта
        }

        $chatId = (int) $message['chat']['id'];
        $text = trim((string) $message['text']);

        if (str_starts_with($text, '/start') || str_starts_with($text, '/help')) {
            $telegram->sendMessage($chatId, 'Send a driver full name or license number to check the blacklist.');

            return new Response('', Response::HTTP_OK);
        }

        $query = ltrim($text, '/');
        $found = $drivers->search($query, 10);

        if (count($found) === 1) {
            $this->sendDriverCard($found[0], $chatId, $feed, $telegram);
        } else {
            $telegram->sendMessage($chatId, $this->formatListReply($query, $found));
        }

        return new Response('', Response::HTTP_OK);
    }

    /**
     * @param array<string, mixed> $callbackQuery
     */
    private function handleCallbackQuery(array $callbackQuery, DriverRepository $drivers, DriverEventFeed $feed, TelegramBotApi $telegram): void
    {
        $callbackId = $callbackQuery['id'] ?? null;
        $data = $callbackQuery['data'] ?? null;
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;

        if (!is_string($callbackId)) {
            return; // nothing we can even acknowledge
        }

        // callback_data format: "hist:<driverId>:<offset>" — chosen to stay well under
        // Telegram's 64-byte limit for callback_data.
        $parts = is_string($data) ? explode(':', $data, 3) : [];
        if (count($parts) === 3 && $parts[0] === 'hist' && is_numeric($parts[1]) && is_numeric($parts[2]) && $chatId !== null) {
            $driver = $drivers->find((int) $parts[1]);
            if ($driver !== null) {
                $this->sendEventsPage($driver, (int) $parts[2], (int) $chatId, $feed, $telegram);
            }
        }

        // Must always be called, or the tapped button spins forever on the user's side.
        $telegram->answerCallbackQuery($callbackId);
    }

    private function sendDriverCard(Driver $driver, int $chatId, DriverEventFeed $feed, TelegramBotApi $telegram): void
    {
        $page = $feed->page($driver, 0, self::PAGE_SIZE);

        $text = $this->formatDriverHeader($driver) . "\n\n" . $this->formatEventsBlock($page['items']);
        $keyboard = $page['hasMore'] ? $this->loadMoreKeyboard($driver->getId(), self::PAGE_SIZE) : null;

        $telegram->sendMessage($chatId, $text, $keyboard);
    }

    private function sendEventsPage(Driver $driver, int $offset, int $chatId, DriverEventFeed $feed, TelegramBotApi $telegram): void
    {
        $page = $feed->page($driver, $offset, self::PAGE_SIZE);
        if ($page['items'] === []) {
            return;
        }

        $nextOffset = $offset + self::PAGE_SIZE;
        $keyboard = $page['hasMore'] ? $this->loadMoreKeyboard($driver->getId(), $nextOffset) : null;

        $telegram->sendMessage($chatId, $this->formatEventsBlock($page['items']), $keyboard);
    }

    /**
     * @return array{inline_keyboard: list<list<array{text: string, callback_data: string}>>}
     */
    private function loadMoreKeyboard(int $driverId, int $nextOffset): array
    {
        return [
            'inline_keyboard' => [[
                ['text' => 'Завантажити ще', 'callback_data' => sprintf('hist:%d:%d', $driverId, $nextOffset)],
            ]],
        ];
    }

    private function formatDriverHeader(Driver $driver): string
    {
        $activeCount = 0;
        foreach ($driver->getBlacklistEntries() as $entry) {
            if ($entry->isActive()) {
                $activeCount++;
            }
        }

        $lines = ['<b>' . htmlspecialchars($driver->getFullName()) . '</b>'];
        if ($driver->getRegion() !== null) {
            $lines[] = htmlspecialchars($driver->getRegion()->getName());
        }
        if ($driver->getLicenseNumber() !== null) {
            $lines[] = 'License: ' . htmlspecialchars($driver->getLicenseNumber());
        }
        $lines[] = $activeCount > 0
            ? sprintf('🚫 %d active blacklist entr%s', $activeCount, $activeCount === 1 ? 'y' : 'ies')
            : '✅ not on the blacklist';

        return implode("\n", $lines);
    }

    /**
     * @param list<AbstractDriverEvent> $events
     */
    private function formatEventsBlock(array $events): string
    {
        if ($events === []) {
            return 'No history yet.';
        }

        $blocks = [];
        foreach ($events as $event) {
            $kind = $event instanceof BlacklistEntry ? '🚫 Blacklist' : 'ℹ️ History';
            $lines = [sprintf('%s — %s', $kind, $event->getCreatedAt()->format('Y-m-d'))];

            $reporter = $event->getReporterName();
            if ($reporter !== null) {
                $lines[] = 'By: ' . htmlspecialchars($reporter);
            }

            $text = $event->getText();
            if ($text !== null) {
                $lines[] = htmlspecialchars($text);
            }

            $blocks[] = implode("\n", $lines);
        }

        return implode("\n\n", $blocks);
    }

    /**
     * @param list<Driver> $drivers
     */
    private function formatListReply(string $query, array $drivers): string
    {
        if ($drivers === []) {
            return sprintf('Nothing found for <b>%s</b>.', htmlspecialchars($query));
        }

        $blocks = [];
        foreach ($drivers as $driver) {
            $activeCount = 0;
            foreach ($driver->getBlacklistEntries() as $entry) {
                if ($entry->isActive()) {
                    $activeCount++;
                }
            }

            $status = $activeCount > 0
                ? sprintf('🚫 %d active blacklist entr%s', $activeCount, $activeCount === 1 ? 'y' : 'ies')
                : '✅ not on the blacklist';

            $blocks[] = '<b>' . htmlspecialchars($driver->getFullName()) . '</b> — ' . $status;
        }

        return "Multiple matches, narrow your search (e.g. add license number):\n\n" . implode("\n", $blocks);
    }
}
