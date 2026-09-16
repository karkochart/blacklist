<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\AbstractDriverEvent;
use App\Entity\BlacklistEntry;
use App\Entity\Driver;
use App\Repository\DriverRepository;
use App\Service\DriverEventFeed;
use App\Service\SubscriptionService;
use App\Service\TelegramAuth;
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

    private const string BTN_SEARCH = '🔍 Пошук водія';
    private const string BTN_HELP = 'ℹ️ Довідка';

    private const string HELP_TEXT = "Надішліть прізвище, ім'я або номер посвідчення водія — перевірю чорний список і покажу історію.\n\n"
        . 'Або натисніть «' . self::BTN_SEARCH . '» знизу.';

    private const string ADMIN_CONTACT = '@BakS_vet_lana';

    private const string SUBSCRIPTION_REQUIRED_TEXT = "Пошук доступний тільки за підпискою (щоденною, щомісячною або щорічною).\n\n"
        . 'Зверніться до ' . self::ADMIN_CONTACT . ', щоб оформити доступ.';

    #[Route('/api/telegram/webhook', name: 'api_telegram_webhook', methods: ['POST'])]
    public function __invoke(
        Request $request,
        DriverRepository $drivers,
        DriverEventFeed $feed,
        TelegramBotApi $telegram,
        TelegramAuth $auth,
        SubscriptionService $subscriptions,
    ): Response {
        $update = json_decode($request->getContent(), true, flags: JSON_THROW_ON_ERROR);

        if (isset($update['callback_query']) && is_array($update['callback_query'])) {
            $this->handleCallbackQuery($update['callback_query'], $drivers, $feed, $telegram, $auth, $subscriptions);

            return new Response('', Response::HTTP_OK);
        }

        $message = $update['message'] ?? null;
        if (!is_array($message) || !isset($message['text'], $message['chat']['id'], $message['from']) || !is_array($message['from'])) {
            return new Response('', Response::HTTP_OK);   // ack и игнор — не наш тип апдейта
        }

        // Every contact registers the sender — this is the "authorization" the bot
        // does: identify by Telegram id, unconditionally. It says nothing about
        // whether they're allowed to search; that's Subscription's call below.
        $telegramUser = $auth->identify($message['from']);

        $chatId = (int) $message['chat']['id'];
        $text = trim((string) $message['text']);

        if (str_starts_with($text, '/start') || str_starts_with($text, '/help') || $text === self::BTN_HELP) {
            $telegram->sendMessage($chatId, self::HELP_TEXT, $this->mainKeyboard());

            return new Response('', Response::HTTP_OK);
        }

        if ($text === self::BTN_SEARCH) {
            $telegram->sendMessage(
                $chatId,
                "Введіть прізвище, ім'я або номер посвідчення водія:",
                $this->forceReply(),
            );

            return new Response('', Response::HTTP_OK);
        }

        if (!$subscriptions->isActive($telegramUser)) {
            $telegram->sendMessage($chatId, self::SUBSCRIPTION_REQUIRED_TEXT);

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
    private function handleCallbackQuery(
        array $callbackQuery,
        DriverRepository $drivers,
        DriverEventFeed $feed,
        TelegramBotApi $telegram,
        TelegramAuth $auth,
        SubscriptionService $subscriptions,
    ): void {
        $callbackId = $callbackQuery['id'] ?? null;
        $data = $callbackQuery['data'] ?? null;
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $from = $callbackQuery['from'] ?? null;

        if (!is_string($callbackId)) {
            return; // nothing we can even acknowledge
        }

        // callback_data format: "hist:<driverId>:<offset>" — chosen to stay well under
        // Telegram's 64-byte limit for callback_data.
        $parts = is_string($data) ? explode(':', $data, 3) : [];
        if (count($parts) === 3 && $parts[0] === 'hist' && is_numeric($parts[1]) && is_numeric($parts[2]) && $chatId !== null && is_array($from)) {
            // re-checked here too: a subscription can lapse between the initial
            // card and a "load more" tap made hours or days later.
            $telegramUser = $auth->identify($from);
            if ($subscriptions->isActive($telegramUser)) {
                $driver = $drivers->find((int) $parts[1]);
                if ($driver !== null) {
                    $this->sendEventsPage($driver, (int) $parts[2], (int) $chatId, $feed, $telegram);
                }
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

    /**
     * Persistent buttons under the input field — stay visible until replaced.
     * Pressing one just sends its label as a normal text message; we special-case
     * that label above instead of treating it as a search query.
     *
     * @return array{keyboard: list<list<string>>, resize_keyboard: true}
     */
    private function mainKeyboard(): array
    {
        return [
            'keyboard' => [[self::BTN_SEARCH], [self::BTN_HELP]],
            'resize_keyboard' => true,
        ];
    }

    /**
     * Puts the user straight into "reply" mode on the next message — no need to
     * remember a command, they just type and hit send.
     *
     * @return array{force_reply: true, input_field_placeholder: string}
     */
    private function forceReply(): array
    {
        return [
            'force_reply' => true,
            'input_field_placeholder' => "Прізвище, ім'я або № прав",
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
            ? sprintf('🚫 %d активн%s запис%s у чорному списку', $activeCount, $activeCount === 1 ? 'ий' : 'их', $activeCount === 1 ? '' : 'и')
            : '✅ не в чорному списку';

        return implode("\n", $lines);
    }

    /**
     * @param list<AbstractDriverEvent> $events
     */
    private function formatEventsBlock(array $events): string
    {
        if ($events === []) {
            return 'Історія відсутня.';
        }

        $blocks = [];
        foreach ($events as $event) {
            $kind = $event instanceof BlacklistEntry ? '🚫 Чорний список' : 'ℹ️ Історія';
            $lines = [sprintf('%s — %s', $kind, $event->getCreatedAt()->format('Y-m-d'))];

            $reporter = $event->getReporterName();
            if ($reporter !== null) {
                $lines[] = 'Додав: ' . htmlspecialchars($reporter);
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
            return sprintf("Нічого не знайдено за запитом «%s».", htmlspecialchars($query));
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
                ? sprintf('🚫 %d активних записів у ЧС', $activeCount)
                : '✅ не в чорному списку';

            $blocks[] = '<b>' . htmlspecialchars($driver->getFullName()) . '</b> — ' . $status;
        }

        return "Знайдено декілька водіїв, уточніть запит (напр. додайте номер прав):\n\n" . implode("\n", $blocks);
    }
}
