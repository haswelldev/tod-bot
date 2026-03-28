<?php

use TodBot\Repository\ChannelConfigRepositoryInterface;
use TodBot\Service\InitHandler;
use PHPUnit\Framework\TestCase;
use React\Promise\Promise;

// ---------------------------------------------------------------------------
// Fakes
// ---------------------------------------------------------------------------

class InitFakeGuild
{
    public string $name = 'test-guild';
}

class InitFakeChannel
{
    public int $sendCount = 0;
    public ?string $lastPayload = null;
    public string $name = 'test-channel';
    public ?InitFakeGuild $guild = null;
    private string $id;

    public function __construct(string $id = 'init-channel')
    {
        $this->id    = $id;
        $this->guild = new InitFakeGuild();
    }

    public function id(): string
    {
        return $this->id;
    }

    public function sendMessage($payload): Promise
    {
        $this->sendCount++;
        $this->lastPayload = is_string($payload) ? $payload : (string) $payload;
        return new Promise(function ($resolve) { $resolve(null); });
    }
}

class InitFakeMessage
{
    public string $content;
    public InitFakeChannel $channel;
    public string $channel_id;
    public ?string $guild_id = 'guild-1';
    public int $deletedCount = 0;

    public function __construct(string $content, InitFakeChannel $channel)
    {
        $this->content    = $content;
        $this->channel    = $channel;
        $this->channel_id = $channel->id();
    }

    public function delete(): Promise
    {
        $this->deletedCount++;
        return new Promise(function ($resolve) { $resolve(null); });
    }
}

class InMemoryChannelConfigRepo implements ChannelConfigRepositoryInterface
{
    private array $data = [];

    public function get(string $channelId): ?array
    {
        return $this->data[$channelId] ?? null;
    }

    public function set(string $channelId, array $data): void
    {
        $this->data[$channelId] = $data;
    }

    public function delete(string $channelId): void
    {
        unset($this->data[$channelId]);
    }

    public function save(): void {}
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class InitHandlerTest extends TestCase
{
    private function makeDiscordMock()
    {
        return $this->getMockBuilder(Discord\Discord::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function makeHandler(InMemoryChannelConfigRepo $repo): InitHandler
    {
        return new InitHandler($this->makeDiscordMock(), $repo);
    }

    // --- hasPending ---

    public function testNoPendingInitially(): void
    {
        $handler = $this->makeHandler(new InMemoryChannelConfigRepo());
        $this->assertFalse($handler->hasPending('any-channel'));
    }

    // --- handleInit on unregistered channel ---

    public function testInitOnUnregisteredChannelSendsLanguageMenu(): void
    {
        $repo    = new InMemoryChannelConfigRepo();
        $handler = $this->makeHandler($repo);
        $channel = new InitFakeChannel();
        $msg     = new InitFakeMessage('.init', $channel);

        $handler->handleInit($msg);

        $this->assertSame(1, $channel->sendCount);
        $this->assertStringContainsString('English', $channel->lastPayload);
        $this->assertStringContainsString('Русский', $channel->lastPayload);
        $this->assertTrue($handler->hasPending($channel->id()));
    }

    // --- handleInit on already-registered channel ---

    public function testInitOnRegisteredChannelShowsAlreadyConfigured(): void
    {
        $repo = new InMemoryChannelConfigRepo();
        $repo->set('init-channel', ['guild_id' => 'g1', 'locale' => 'en']);

        $handler = $this->makeHandler($repo);
        $channel = new InitFakeChannel();
        $msg     = new InitFakeMessage('.init', $channel);

        $handler->handleInit($msg);

        $this->assertSame(1, $channel->sendCount);
        $this->assertStringContainsString('already configured', $channel->lastPayload);
        $this->assertStringContainsString('English', $channel->lastPayload);
        $this->assertFalse($handler->hasPending($channel->id()));
    }

    // --- language selection step ---

    public function testSelectLanguageByNumber(): void
    {
        $repo    = new InMemoryChannelConfigRepo();
        $handler = $this->makeHandler($repo);
        $channel = new InitFakeChannel();

        $handler->handleInit(new InitFakeMessage('.init', $channel));
        $channel->sendCount = 0; // reset

        $handler->handleResponse(new InitFakeMessage('1', $channel)); // English

        $this->assertSame(1, $channel->sendCount);
        $this->assertStringContainsString('English', $channel->lastPayload);
        $this->assertStringContainsString('yes', $channel->lastPayload);
        $this->assertTrue($handler->hasPending($channel->id()));
    }

    public function testSelectLanguageByCode(): void
    {
        $repo    = new InMemoryChannelConfigRepo();
        $handler = $this->makeHandler($repo);
        $channel = new InitFakeChannel();

        $handler->handleInit(new InitFakeMessage('.init', $channel));
        $channel->sendCount = 0;

        $handler->handleResponse(new InitFakeMessage('uk', $channel));

        $this->assertSame(1, $channel->sendCount);
        $this->assertStringContainsString('Українська', $channel->lastPayload);
    }

    public function testSelectLanguageByCodeCaseInsensitive(): void
    {
        $repo    = new InMemoryChannelConfigRepo();
        $handler = $this->makeHandler($repo);
        $channel = new InitFakeChannel();

        $handler->handleInit(new InitFakeMessage('.init', $channel));
        $channel->sendCount = 0;

        $handler->handleResponse(new InitFakeMessage('RU', $channel));

        $this->assertStringContainsString('Русский', $channel->lastPayload);
    }

    public function testInvalidLanguageSelectionShowsError(): void
    {
        $repo    = new InMemoryChannelConfigRepo();
        $handler = $this->makeHandler($repo);
        $channel = new InitFakeChannel();

        $handler->handleInit(new InitFakeMessage('.init', $channel));
        $channel->sendCount = 0;

        $handler->handleResponse(new InitFakeMessage('xyz', $channel));

        $this->assertSame(1, $channel->sendCount);
        $this->assertStringContainsString('Unknown language', $channel->lastPayload);
        // Still pending at language step
        $this->assertTrue($handler->hasPending($channel->id()));
    }

    // --- confirm step ---

    public function testConfirmYesRegistersChannel(): void
    {
        $repo    = new InMemoryChannelConfigRepo();
        $handler = $this->makeHandler($repo);
        $channel = new InitFakeChannel();

        $handler->handleInit(new InitFakeMessage('.init', $channel));
        $handler->handleResponse(new InitFakeMessage('2', $channel)); // Russian
        $handler->handleResponse(new InitFakeMessage('yes', $channel)); // confirm
        $channel->sendCount = 0;

        // Step 3: reminders prompt — reply no
        $handler->handleResponse(new InitFakeMessage('no', $channel));

        $this->assertSame(1, $channel->sendCount);
        $this->assertStringContainsString('registered', $channel->lastPayload);
        $this->assertFalse($handler->hasPending($channel->id()));

        $config = $repo->get($channel->id());
        $this->assertNotNull($config);
        $this->assertSame('ru', $config['locale']);
        $this->assertFalse($config['reminders_enabled']);
    }

    public function testConfirmNoCancels(): void
    {
        $repo    = new InMemoryChannelConfigRepo();
        $handler = $this->makeHandler($repo);
        $channel = new InitFakeChannel();

        $handler->handleInit(new InitFakeMessage('.init', $channel));
        $handler->handleResponse(new InitFakeMessage('1', $channel));
        $channel->sendCount = 0;

        $handler->handleResponse(new InitFakeMessage('no', $channel));

        $this->assertSame(1, $channel->sendCount);
        $this->assertStringContainsString('cancelled', strtolower($channel->lastPayload));
        $this->assertFalse($handler->hasPending($channel->id()));
        $this->assertNull($repo->get($channel->id()));
    }

    public function testConfirmInvalidInputAsksAgain(): void
    {
        $repo    = new InMemoryChannelConfigRepo();
        $handler = $this->makeHandler($repo);
        $channel = new InitFakeChannel();

        $handler->handleInit(new InitFakeMessage('.init', $channel));
        $handler->handleResponse(new InitFakeMessage('1', $channel));
        $channel->sendCount = 0;

        $handler->handleResponse(new InitFakeMessage('maybe', $channel));

        $this->assertSame(1, $channel->sendCount);
        $this->assertStringContainsString('yes', $channel->lastPayload);
        $this->assertTrue($handler->hasPending($channel->id())); // still pending
        $this->assertNull($repo->get($channel->id()));           // not registered yet
    }

    // --- multi-channel isolation ---

    public function testTwoChannelsHaveIndependentState(): void
    {
        $repo     = new InMemoryChannelConfigRepo();
        $handler  = $this->makeHandler($repo);
        $channel1 = new InitFakeChannel('ch-1');
        $channel2 = new InitFakeChannel('ch-2');

        $handler->handleInit(new InitFakeMessage('.init', $channel1));
        $this->assertTrue($handler->hasPending('ch-1'));
        $this->assertFalse($handler->hasPending('ch-2'));

        $handler->handleInit(new InitFakeMessage('.init', $channel2));
        $this->assertTrue($handler->hasPending('ch-1'));
        $this->assertTrue($handler->hasPending('ch-2'));

        // Confirm ch-1 (3 steps now: language, confirm, reminders)
        $handler->handleResponse(new InitFakeMessage('1', $channel1));
        $handler->handleResponse(new InitFakeMessage('yes', $channel1));
        // Still pending at reminders step
        $this->assertTrue($handler->hasPending('ch-1'));
        $handler->handleResponse(new InitFakeMessage('no', $channel1));
        $this->assertFalse($handler->hasPending('ch-1'));
        $this->assertTrue($handler->hasPending('ch-2'));

        $this->assertNotNull($repo->get('ch-1'));
        $this->assertNull($repo->get('ch-2'));
    }
}
