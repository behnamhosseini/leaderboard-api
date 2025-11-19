<?php

namespace GameLadder\Tests\Integration;

use GameLadder\Config\Config;
use GameLadder\Factory\ServiceFactory;
use GameLadder\Service\LeaderboardServiceInterface;
use PHPUnit\Framework\TestCase;
use Predis\Client;

class ConcurrencyTest extends TestCase
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

    public function testMultiplePlayersUpdatingSimultaneously(): void
    {
        $numPlayers = 50;
        $updatesPerPlayer = 10;

        // Initialize players
        for ($i = 1; $i <= $numPlayers; $i++) {
            $this->service->updatePlayerScore("player{$i}", rand(100, 1000));
        }

        // Simulate concurrent updates
        for ($round = 0; $round < $updatesPerPlayer; $round++) {
            for ($i = 1; $i <= $numPlayers; $i++) {
                $playerId = "player{$i}";
                $currentPlayer = $this->service->getPlayerRank($playerId);
                $newScore = ($currentPlayer ? $currentPlayer->getScore() : 0) + rand(1, 100);
                $this->service->updatePlayerScore($playerId, $newScore);
            }
        }

        // Verify all players still exist
        $this->assertEquals($numPlayers, $this->service->getTotalPlayers());

        // Verify rankings are correct (descending order)
        $topPlayers = $this->service->getTopPlayers($numPlayers);
        $this->assertCount($numPlayers, $topPlayers);

        for ($i = 0; $i < count($topPlayers) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $topPlayers[$i + 1]->getScore(),
                $topPlayers[$i]->getScore(),
                "Rankings must be in descending order"
            );
            $this->assertEquals($i + 1, $topPlayers[$i]->getRank());
        }
    }

    public function testRankingsAlwaysReflectLatestScores(): void
    {
        // Create initial state
        $this->service->updatePlayerScore('player1', 100);
        $this->service->updatePlayerScore('player2', 200);
        $this->service->updatePlayerScore('player3', 150);

        // Verify initial rankings
        $player1 = $this->service->getPlayerRank('player1');
        $this->assertEquals(3, $player1->getRank());

        // Update player1 to highest score
        $this->service->updatePlayerScore('player1', 300);

        // Immediately verify rankings reflect latest scores
        $player1 = $this->service->getPlayerRank('player1');
        $this->assertEquals(1, $player1->getRank());
        $this->assertEquals(300, $player1->getScore());

        $player2 = $this->service->getPlayerRank('player2');
        $this->assertEquals(2, $player2->getRank());

        $player3 = $this->service->getPlayerRank('player3');
        $this->assertEquals(3, $player3->getRank());

        // Verify top players list is also updated
        $topPlayers = $this->service->getTopPlayers(3);
        $this->assertEquals('player1', $topPlayers[0]->getPlayerId());
        $this->assertEquals(300, $topPlayers[0]->getScore());
    }

    public function testRapidScoreUpdates(): void
    {
        $playerId = 'rapid_player';
        
        // Perform rapid updates
        for ($score = 100; $score <= 1000; $score += 50) {
            $this->service->updatePlayerScore($playerId, $score);
            
            // Verify each update is immediately reflected
            $player = $this->service->getPlayerRank($playerId);
            $this->assertEquals($score, $player->getScore());
        }

        // Final verification
        $player = $this->service->getPlayerRank($playerId);
        $this->assertEquals(1000, $player->getScore());
        $this->assertEquals(1, $player->getRank());
    }
}

