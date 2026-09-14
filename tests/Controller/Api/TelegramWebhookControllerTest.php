<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\BlacklistEntry;
use App\Entity\Driver;
use App\Entity\DriverHistoryEntry;
use App\Entity\Region;
use App\Telegram\TelegramBotApi;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * POST /api/telegram/webhook
 *
 *   - guarded by the X-Telegram-Bot-Api-Secret-Token header (firewall "telegram")
 *   - a text message is treated as a driver search; the bot replies via TelegramBotApi
 *   - updates without a message are acknowledged (200) and ignored
 *
 * The real Telegram API is never called: TelegramBotApi is swapped for a recording spy.
 */
final class TelegramWebhookControllerTest extends WebTestCase
{
    private const string SECRET = 'test-webhook-secret';

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    /** @var object{sent: list<array{0:int,1:string,2:array|null}>, answered: list<string>} */
    private object $telegramSpy;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        self::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['blacklist_entry', 'driver_history_entry', 'driver', 'region', 'user'] as $table) {
            $connection->executeStatement("TRUNCATE TABLE {$table}");
        }
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        $region = (new Region())->setCode('UA-51')->setName('Odesa Oblast');
        $ivanov = (new Driver())->setLastName('Иванов')->setFirstName('Иван')->setRegion($region);
        $entry = (new BlacklistEntry())->setDriver($ivanov)->setText('owes 5000')->setReportedBy('Денис');

        $this->em->persist($region);
        $this->em->persist($ivanov);
        $this->em->persist($entry);
        $this->em->flush();
        $this->em->clear();

        $this->telegramSpy = new class implements TelegramBotApi {
            /** @var list<array{0:int,1:string,2:array|null}> */
            public array $sent = [];

            /** @var list<string> */
            public array $answered = [];

            public function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): void
            {
                $this->sent[] = [$chatId, $text, $replyMarkup];
            }

            public function answerCallbackQuery(string $callbackQueryId): void
            {
                $this->answered[] = $callbackQueryId;
            }
        };
        static::getContainer()->set(TelegramBotApi::class, $this->telegramSpy);
    }

    /**
     * @param array<string, mixed> $update
     */
    private function postUpdate(array $update, ?string $secret = self::SECRET): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if (null !== $secret) {
            $server['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] = $secret;
        }

        $this->client->request('POST', '/api/telegram/webhook', server: $server, content: json_encode($update, \JSON_THROW_ON_ERROR));
    }

    private function textMessage(string $text, int $chatId = 42): array
    {
        return [
            'update_id' => 1,
            'message' => [
                'message_id' => 10,
                'chat' => ['id' => $chatId, 'type' => 'private'],
                'from' => ['id' => $chatId, 'is_bot' => false, 'first_name' => 'Karen'],
                'date' => 1_700_000_000,
                'text' => $text,
            ],
        ];
    }

    private function callbackQuery(string $data, int $chatId = 42, string $callbackId = 'cb-1'): array
    {
        return [
            'update_id' => 2,
            'callback_query' => [
                'id' => $callbackId,
                'from' => ['id' => $chatId, 'is_bot' => false, 'first_name' => 'Karen'],
                'message' => ['message_id' => 11, 'chat' => ['id' => $chatId, 'type' => 'private']],
                'data' => $data,
            ],
        ];
    }

    public function testMissingSecretIsRejected(): void
    {
        $this->postUpdate($this->textMessage('Иванов'), secret: null);

        self::assertResponseStatusCodeSame(401);
        self::assertSame([], $this->telegramSpy->sent);
    }

    public function testWrongSecretIsRejected(): void
    {
        $this->postUpdate($this->textMessage('Иванов'), secret: 'nope');

        self::assertResponseStatusCodeSame(401);
    }

    public function testTextMessageTriggersSearchReply(): void
    {
        $this->postUpdate($this->textMessage('Иванов', chatId: 42));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->telegramSpy->sent);
        self::assertSame(42, $this->telegramSpy->sent[0][0]);
        self::assertStringContainsString('Иванов', $this->telegramSpy->sent[0][1]);
    }
//
    public function testUnknownDriverGetsAReply(): void
    {
        $this->postUpdate($this->textMessage('Ковальчук'));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->telegramSpy->sent, 'the bot still answers when nothing is found');
    }

    public function testStartShowsHelpWithMainKeyboard(): void
    {
        $this->postUpdate($this->textMessage('/start'));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->telegramSpy->sent);
        [, $text, $replyMarkup] = $this->telegramSpy->sent[0];

        self::assertStringContainsString('Пошук водія', $text);
        self::assertSame([['🔍 Пошук водія'], ['ℹ️ Довідка']], $replyMarkup['keyboard']);
    }

    public function testHelpButtonReSendsHelp(): void
    {
        $this->postUpdate($this->textMessage('ℹ️ Довідка'));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->telegramSpy->sent);
        self::assertStringContainsString('Пошук водія', $this->telegramSpy->sent[0][1]);
    }

    public function testSearchButtonAsksToTypeAQueryInsteadOfSearching(): void
    {
        $this->postUpdate($this->textMessage('🔍 Пошук водія'));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->telegramSpy->sent);
        [, $text, $replyMarkup] = $this->telegramSpy->sent[0];

        self::assertTrue($replyMarkup['force_reply']);
        // the button label itself must never be treated as a driver name to search for
        self::assertStringNotContainsString('Нічого не знайдено', $text);
    }

    public function testUpdateWithoutMessageIsIgnored(): void
    {
        $this->postUpdate(['update_id' => 5, 'edited_message' => ['message_id' => 1]]);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->telegramSpy->sent);
    }

    public function testSingleMatchShowsBlacklistStatusAndRecentHistory(): void
    {
        $driver = $this->em->getRepository(Driver::class)->findOneBy(['lastName' => 'Иванов']);
        $this->em->persist((new DriverHistoryEntry())->setDriver($driver)->setText('warned once')->setReportedBy('Оля'));
        $this->em->flush();

        $this->postUpdate($this->textMessage('Иванов'));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->telegramSpy->sent);
        [, $text, $replyMarkup] = $this->telegramSpy->sent[0];

        self::assertStringContainsString('Иванов', $text);
        self::assertStringContainsString('🚫 1 активний запис у чорному списку', $text);
        self::assertStringContainsString('owes 5000', $text);       // the blacklist entry from setUp
        self::assertStringContainsString('warned once', $text);      // the history entry added above
        self::assertNull($replyMarkup, 'only 2 events exist — no "load more" button expected');
    }

    public function testMoreThanFiveEventsShowsLoadMoreButton(): void
    {
        $driver = $this->em->getRepository(Driver::class)->findOneBy(['lastName' => 'Иванов']);
        for ($i = 0; $i < 5; $i++) {
            $this->em->persist((new DriverHistoryEntry())->setDriver($driver)->setText("note {$i}"));
        }
        $this->em->flush();
        // total events now: 1 (setUp's blacklist entry) + 5 = 6

        $this->postUpdate($this->textMessage('Иванов'));

        [, , $replyMarkup] = $this->telegramSpy->sent[0];

        self::assertNotNull($replyMarkup, '6 events exist — a "load more" button is expected');
        self::assertSame(
            sprintf('hist:%d:5', $driver->getId()),
            $replyMarkup['inline_keyboard'][0][0]['callback_data'],
        );
    }

    public function testLoadMoreCallbackSendsNextPageAndAnswersTheQuery(): void
    {
        $driver = $this->em->getRepository(Driver::class)->findOneBy(['lastName' => 'Иванов']);
        for ($i = 0; $i < 5; $i++) {
            $this->em->persist((new DriverHistoryEntry())->setDriver($driver)->setText("note {$i}"));
        }
        $this->em->flush();
        $driverId = $driver->getId();

        $this->postUpdate($this->callbackQuery(sprintf('hist:%d:5', $driverId), callbackId: 'cb-42'));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->telegramSpy->sent, 'exactly one more page (1 leftover event) should be sent');
        self::assertSame(['cb-42'], $this->telegramSpy->answered);
        self::assertNull($this->telegramSpy->sent[0][2], 'no more pages left — no button on the last one');
    }

    public function testMultipleMatchesAreListedWithoutHistory(): void
    {
        $region = $this->em->getRepository(Region::class)->findOneBy(['code' => 'UA-51']);
        $this->em->persist((new Driver())->setLastName('Ивановський')->setFirstName('Петро')->setRegion($region));
        $this->em->flush();

        $this->postUpdate($this->textMessage('Иванов'));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->telegramSpy->sent);
        [, $text, $replyMarkup] = $this->telegramSpy->sent[0];

        self::assertStringContainsString('Иванов Иван', $text);
        self::assertStringContainsString('Ивановський Петро', $text);
        self::assertNull($replyMarkup);
    }
}
