<?php

declare(strict_types=1);

namespace App\Tests\Controller\Api;

use App\Entity\BlacklistEntry;
use App\Entity\Driver;
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

    /** @var object{sent: list<array{0:int,1:string}>} */
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
            /** @var list<array{0:int,1:string}> */
            public array $sent = [];

            public function sendMessage(int $chatId, string $text): void
            {
                $this->sent[] = [$chatId, $text];
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

    public function testUpdateWithoutMessageIsIgnored(): void
    {
        $this->postUpdate(['update_id' => 5, 'edited_message' => ['message_id' => 1]]);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->telegramSpy->sent);
    }
}
