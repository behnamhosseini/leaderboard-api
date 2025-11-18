<?php

namespace Tests\Integration;

use GameLadder\Config\Config;
use GameLadder\Factory\ServiceFactory;
use GameLadder\Service\LeaderboardServiceInterface;
use PHPUnit\Framework\TestCase;
use Predis\Client;

class ApiTest extends TestCase
{
    private LeaderboardServiceInterface $service;
    private Client $redis;

    protected function setUp(): void
    {
        Config::load();
        $this->service = ServiceFactory::createLeaderboardService();
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

    public function testUpdateAndGetPlayerScore(): void
    {
        $playerId = 'test_player_1';
        $score = 150;

        $this->service->updatePlayerScore($playerId, $score);
        $player = $this->service->getPlayerRank($playerId);

        $this->assertNotNull($player);
        $this->assertEquals($playerId, $player->getPlayerId());
        $this->assertEquals($score, $player->getScore());
        $this->assertEquals(1, $player->getRank());
    }

    public function testGetTopPlayers(): void
    {
        $this->service->updatePlayerScore('player1', 300);
        $this->service->updatePlayerScore('player2', 200);
        $this->service->updatePlayerScore('player3', 100);
        $this->service->updatePlayerScore('player4', 250);

        $topPlayers = $this->service->getTopPlayers(3);

        $this->assertCount(3, $topPlayers);
        $this->assertEquals('player1', $topPlayers[0]->getPlayerId());
        $this->assertEquals(300, $topPlayers[0]->getScore());
        $this->assertEquals(1, $topPlayers[0]->getRank());

        $this->assertEquals('player4', $topPlayers[1]->getPlayerId());
        $this->assertEquals(250, $topPlayers[1]->getScore());
        $this->assertEquals(2, $topPlayers[1]->getRank());

        $this->assertEquals('player2', $topPlayers[2]->getPlayerId());
        $this->assertEquals(200, $topPlayers[2]->getScore());
        $this->assertEquals(3, $topPlayers[2]->getRank());
    }

    public function testRealTimeRankingUpdate(): void
    {
        // Initial scores
        $this->service->updatePlayerScore('player1', 100);
        $this->service->updatePlayerScore('player2', 200);
        $this->service->updatePlayerScore('player3', 150);

        // player1 should be rank 3
        $player1 = $this->service->getPlayerRank('player1');
        $this->assertEquals(3, $player1->getRank());

        // Update player1's score to be highest
        $this->service->updatePlayerScore('player1', 300);

        // Now player1 should be rank 1
        $player1 = $this->service->getPlayerRank('player1');
        $this->assertEquals(1, $player1->getRank());

        // player2 should now be rank 2
        $player2 = $this->service->getPlayerRank('player2');
        $this->assertEquals(2, $player2->getRank());
    }

    public function testGetTotalPlayers(): void
    {
        $this->assertEquals(0, $this->service->getTotalPlayers());

        $this->service->updatePlayerScore('player1', 100);
        $this->service->updatePlayerScore('player2', 200);
        $this->service->updatePlayerScore('player3', 150);

        $this->assertEquals(3, $this->service->getTotalPlayers());
    }

    public function testConcurrentUpdates(): void
    {
        // Simulate concurrent updates
        $players = [];
        for ($i = 1; $i <= 10; $i++) {
            $playerId = "player{$i}";
            $score = rand(100, 1000);
            $this->service->updatePlayerScore($playerId, $score);
            $players[$playerId] = $score;
        }

        $this->assertEquals(10, $this->service->getTotalPlayers());

        // Verify all players are ranked correctly
        $topPlayers = $this->service->getTopPlayers(10);
        $this->assertCount(10, $topPlayers);

        // Verify scores are in descending order
        for ($i = 0; $i < count($topPlayers) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $topPlayers[$i + 1]->getScore(),
                $topPlayers[$i]->getScore()
            );
        }
    }
}

