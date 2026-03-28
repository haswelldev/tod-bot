<?php

use TodBot\Repository\SqliteTodRepository;
use TodBot\Service\CommandHandler;
use PHPUnit\Framework\TestCase;
use React\Promise\PromiseInterface;

class CommandHandlerSqliteTest extends TestCase
{
    private string $dbFile;

    private function makeDiscordMock()
    {
        return $this->getMockBuilder(Discord\Discord::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function makeMessage($content, CommandHandlerSqliteTest_FakeChannel $channel)
    {
        return new CommandHandlerSqliteTest_FakeMessage($content, $channel);
    }

    protected function setUp(): void
    {
        $this->dbFile = tempnam(sys_get_temp_dir(), 'tods_sqlite_');
        if (file_exists($this->dbFile)) {
            unlink($this->dbFile);
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->dbFile)) {
            @unlink($this->dbFile);
        }
    }

    public function testTodWindowAndListFlowUsingSqlite()
    {
        $discord = $this->makeDiscordMock();
        $repo = new SqliteTodRepository($this->dbFile);
        $handler = new CommandHandler($discord, $repo);
        $channel = new CommandHandlerSqliteTest_FakeChannel();

        // .tod with explicit epoch + UTC
        // Use a recent past ToD so that the window is not closed yet (10h ago => opens in 2h)
        $epoch = time() - 10 * 3600;
        $msg1 = $this->makeMessage('.tod antharas ' . $epoch . ' UTC', $channel);
        $handler($msg1);

        $row = $repo->get('antharas', $msg1->channel_id);
        $this->assertIsArray($row);
        $this->assertSame($epoch, $row['tod']);
        $this->assertGreaterThan(0, $msg1->deletedCount);
        $this->assertInstanceOf(Discord\Builders\MessageBuilder::class, $channel->lastPayload);

        // .window should read from sqlite and send embed
        $channel->reset();
        $msg2 = $this->makeMessage('.window antharas', $channel);
        $handler($msg2);
        $this->assertSame(1, $channel->sendCount);
        $this->assertInstanceOf(Discord\Builders\MessageBuilder::class, $channel->lastPayload);
        $this->assertGreaterThan(0, $msg2->deletedCount);

        // .list shows only this boss for this channel (string response)
        $channel->reset();
        $msg3 = $this->makeMessage('.list', $channel);
        $handler($msg3);
        $this->assertSame(1, $channel->sendCount);
        $this->assertIsString($channel->lastPayload);
        $this->assertStringContainsString('Antharas', $channel->lastPayload);
        $this->assertGreaterThan(0, $msg3->deletedCount);
    }
}

class CommandHandlerSqliteTest_FakeChannel
{
    public int $sendCount = 0;
    public $lastPayload = null;
    private string $id = 'sqlite-channel';

    public function sendMessage($payload): PromiseInterface
    {
        $this->sendCount++;
        $this->lastPayload = $payload;
        return React\Promise\resolve(true);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function reset(): void
    {
        $this->sendCount = 0;
        $this->lastPayload = null;
    }
}

class CommandHandlerSqliteTest_FakeMessage
{
    public string $content;
    public CommandHandlerSqliteTest_FakeChannel $channel;
    public string $channel_id;
    public int $deletedCount = 0;

    public function __construct($content, $channel)
    {
        $this->content = $content;
        $this->channel = $channel;
        $this->channel_id = $channel->id();
    }

    public function delete(): PromiseInterface
    {
        $this->deletedCount++;
        return React\Promise\resolve(true);
    }
}
