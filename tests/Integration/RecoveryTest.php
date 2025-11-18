<?php

namespace Tests\Integration;

use GameLadder\Config\Config;
use GameLadder\Factory\ServiceFactory;
use GameLadder\Service\LeaderboardServiceInterface;
use GameLadder\Service\RedisRecoveryService;
use PHPUnit\Framework\TestCase;
use Predis\Client;

class RecoveryTest extends TestCase
{
    private LeaderboardServiceInterface $service;
    private RedisRecoveryService $recoveryService;
    private Client $redis;

    protected function setUp(): void
    {
        Config::load();
        $this->service = ServiceFactory::createLeaderboardService();
        $this->recoveryService = ServiceFactory::createRecoveryService();
        $this->redis = new Client([
            'host' => Config::get('REDIS_HOST', '127.0.0.1'),
            'port' => Config::getInt('REDIS_PORT', 6379),
        ]);

        $this->redis->flushdb();
    }

    protected function tearDown(): void
    {
        $this->redis->flushdb();
    }

    public function testRecoveryAfterRedisRestart(): void
    {
        $this->service->updatePlayerScore('player1', 1000);
        $this->service->updatePlayerScore('player2', 2000);
        $this->service->updatePlayerScore('player3', 1500);

        $this->assertEquals(3, $this->service->getTotalPlayers());

        $this->redis->flushdb();

        $this->assertEquals(0, $this->service->getTotalPlayers());

        $recoveredCount = $this->recoveryService->rebuildLeaderboardFromDatabase();
        $this->assertEquals(3, $recoveredCount);

        $this->assertEquals(3, $this->service->getTotalPlayers());

        $topPlayers = $this->service->getTopPlayers(3);
        $this->assertCount(3, $topPlayers);
        $this->assertEquals('player2', $topPlayers[0]->getPlayerId());
        $this->assertEquals(2000, $topPlayers[0]->getScore());
        $this->assertEquals(1, $topPlayers[0]->getRank());

        $this->assertEquals('player3', $topPlayers[1]->getPlayerId());
        $this->assertEquals(1500, $topPlayers[1]->getScore());
        $this->assertEquals(2, $topPlayers[1]->getRank());

        $this->assertEquals('player1', $topPlayers[2]->getPlayerId());
        $this->assertEquals(1000, $topPlayers[2]->getScore());
        $this->assertEquals(3, $topPlayers[2]->getRank());
    }

    public function testNeedsRecovery(): void
    {
        $this->assertFalse($this->recoveryService->needsRecovery());

        $this->service->updatePlayerScore('player1', 1000);
        $this->service->updatePlayerScore('player2', 2000);

        $this->redis->flushdb();

        $this->assertTrue($this->recoveryService->needsRecovery());

        $this->recoveryService->rebuildLeaderboardFromDatabase();

        $this->assertFalse($this->recoveryService->needsRecovery());
    }

    public function testRecoveryWithEmptyDatabase(): void
    {
        $recoveredCount = $this->recoveryService->rebuildLeaderboardFromDatabase();
        $this->assertEquals(0, $recoveredCount);
        $this->assertFalse($this->recoveryService->needsRecovery());
    }
}

